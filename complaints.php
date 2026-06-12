<?php
// student/complaints.php
require_once __DIR__ . '/../config/db.php';
require_student();

$sid   = (int)$_SESSION['student_id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category']    ?? '';
    $subject  = clean($_POST['subject']  ?? '');
    $desc     = clean($_POST['description'] ?? '');
    $priority = $_POST['priority']    ?? 'medium';
    $allowed_cats = ['maintenance','plumbing','electricity','internet','cleanliness','security','other'];

    if (!in_array($category, $allowed_cats) || empty($subject) || empty($desc)) {
        $error = 'Please fill in all required fields.';
    } else {
        $alloc = db_row($conn,"SELECT room_id FROM allocations WHERE student_id=? AND status='active' LIMIT 1",'i',$sid);
        $room_id = $alloc['room_id'] ?? null;
        $ins = db_insert($conn,
            "INSERT INTO complaints (student_id,room_id,category,subject,description,priority) VALUES (?,?,?,?,?,?)",
            'iissss', $sid, $room_id, $category, $subject, $desc, $priority
        );
        if ($ins) { flash('success','Complaint submitted! We will respond soon.'); redirect('complaints.php'); }
        else       { $error = 'Could not submit complaint. Please try again.'; }
    }
}

$complaints = db_query($conn,
    "SELECT c.*, r.room_number FROM complaints c LEFT JOIN rooms r ON r.id=c.room_id
     WHERE c.student_id=? ORDER BY c.created_at DESC", 'i', $sid);

$student    = db_row($conn,"SELECT * FROM students WHERE id=?",'i',$sid);
$page_title = 'My Complaints';
require_once __DIR__ . '/../includes/header.php';
?>
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="dashboard.php">← Dashboard</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/student_sidebar.php'; ?>
  <main class="dash-main">
    <div class="page-title">Complaints &amp; Requests</div>
    <div class="page-sub">Submit maintenance issues or other hostel complaints</div>
    <?php if($error): ?><div class="err-box" style="margin-bottom:1rem;">❌ <?= $error ?></div><?php endif; ?>

    <div class="g21">
      <!-- LIST -->
      <div>
        <div class="table-wrapper">
          <div class="table-header"><div class="table-title">My Complaints (<?= count($complaints) ?>)</div></div>
          <?php if(empty($complaints)): ?>
            <div style="padding:2.5rem 1rem;text-align:center;opacity:.5;">
              <div style="font-size:2rem;margin-bottom:.5rem;">📣</div>
              <div style="font-size:.875rem;color:var(--ts);">No complaints yet.</div>
            </div>
          <?php else: ?>
          <table class="data-table">
            <thead><tr><th>ID</th><th>Category</th><th>Subject</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($complaints as $c): $cat_icons=['maintenance'=>'🔧','plumbing'=>'💧','electricity'=>'💡','internet'=>'📶','cleanliness'=>'🧹','security'=>'🔒','other'=>'📌']; ?>
              <tr>
                <td><code>#C<?= str_pad($c['id'],3,'0',STR_PAD_LEFT) ?></code></td>
                <td><?= ($cat_icons[$c['category']]??'📌').' '.ucfirst($c['category']) ?></td>
                <td><?= clean(substr($c['subject'],0,30)) ?><?= strlen($c['subject'])>30?'…':'' ?></td>
                <td><span class="badge badge-<?= $c['priority']==='urgent'||$c['priority']==='high'?'rejected':($c['priority']==='low'?'available':'pending') ?>"><?= ucfirst($c['priority']) ?></span></td>
                <td><span class="badge badge-<?= str_replace('_','',$c['status']) ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
                <td style="color:var(--ts);font-size:.8rem;"><?= date('M d, Y',strtotime($c['created_at'])) ?></td>
              </tr>
              <?php if($c['admin_response']): ?>
              <tr><td colspan="6" style="background:rgba(104,211,145,.04);padding:.6rem 1rem;font-size:.8rem;">
                <strong style="color:var(--success);">Admin Response:</strong> <?= clean($c['admin_response']) ?>
              </td></tr>
              <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- FORM -->
      <div>
        <div class="glass" style="padding:1.2rem;">
          <div class="table-title" style="margin-bottom:1rem;">New Complaint</div>
          <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
            <div class="form-group">
              <label class="form-label">Category *</label>
              <select class="form-input" name="category" required>
                <option value="">Select category</option>
                <option value="maintenance">🔧 Maintenance / Repair</option>
                <option value="plumbing">💧 Water / Plumbing</option>
                <option value="electricity">💡 Electricity</option>
                <option value="internet">📶 Internet / WiFi</option>
                <option value="cleanliness">🧹 Cleanliness</option>
                <option value="security">🔒 Security</option>
                <option value="other">📌 Other</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Subject *</label>
              <input class="form-input" name="subject" placeholder="Brief one-line description" required>
            </div>
            <div class="form-group">
              <label class="form-label">Description *</label>
              <textarea class="form-input" name="description" rows="4" placeholder="Describe the issue in detail…" required></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Priority</label>
              <select class="form-input" name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;">📤 Submit Complaint</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
</body></html>
