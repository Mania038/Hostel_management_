<?php
// admin/allocations.php
require_once __DIR__ . '/../config/db.php';
require_admin();
$admin_id = (int)$_SESSION['admin_id'];

// VACATE ALLOCATION
if (isset($_GET['action']) && $_GET['action']==='vacate' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $al = db_row($conn,"SELECT al.room_id, al.student_id FROM allocations al WHERE al.id=? AND al.status='active'",'i',$id);
    if ($al) {
        db_exec($conn,"UPDATE allocations SET status='vacated',end_date=CURDATE() WHERE id=?",'i',$id);
        db_exec($conn,"UPDATE rooms SET occupied=GREATEST(occupied-1,0) WHERE id=?",'i',$al['room_id']);
        // mark any payment as overdue if unpaid
        db_exec($conn,"UPDATE payments SET status='overdue' WHERE allocation_id=? AND status='pending'",'i',$id);
        flash('success','Student vacated successfully.');
    }
    redirect(APP_URL.'/admin/allocations.php');
}

// ALLOCATE — POST
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action']??'')==='allocate') {
    $app_id    = (int)$_POST['app_id'];
    $room_id   = (int)$_POST['room_id'];
    $semester  = clean($_POST['semester'] ?? '');
    $start     = $_POST['start_date'] ?? date('Y-m-d');
    $notes     = clean($_POST['notes'] ?? '');

    // Validate
    $app  = db_row($conn,"SELECT * FROM applications WHERE id=? AND status='approved'",'i',$app_id);
    $room = db_row($conn,"SELECT * FROM rooms WHERE id=? AND status='available'",'i',$room_id);
    $already = db_row($conn,"SELECT id FROM allocations WHERE student_id=? AND status='active'",'i',$app['student_id']??0);

    if (!$app)    { flash('error','Application not found or not approved.'); }
    elseif(!$room){ flash('error','Room not found or unavailable.'); }
    elseif($room['occupied'] >= $room['capacity']) { flash('error','Selected room is already full.'); }
    elseif($already) { flash('error','Student already has an active allocation.'); }
    else {
        // Create allocation
        $alloc_id = db_insert($conn,
            "INSERT INTO allocations (application_id,student_id,room_id,semester,start_date,allocated_by,notes)
             VALUES (?,?,?,?,?,?,?)",
            'iiissss', $app_id, $app['student_id'], $room_id, $semester, $start, $admin_id, $notes
        );
        // Update room occupied count
        db_exec($conn,"UPDATE rooms SET occupied=occupied+1 WHERE id=?",'i',$room_id);
        // Update application status
        db_exec($conn,"UPDATE applications SET status='allocated',reviewed_by=?,reviewed_at=NOW() WHERE id=?",'ii',$admin_id,$app_id);
        // Create payment record
        $fee = $room['fee_per_sem'];
        $due = date('Y-m-d', strtotime($start . ' +30 days'));
        db_insert($conn,
            "INSERT INTO payments (allocation_id,student_id,semester,amount,status,due_date,created_by)
             VALUES (?,?,?,?,'pending',?,?)",
            'iisssi', $alloc_id, $app['student_id'], $semester, $fee, $due, $admin_id
        );
        flash('success','Room allocated and payment record created successfully!');
    }
    redirect(APP_URL.'/admin/allocations.php');
}

// Pre-fill from applications page
$pre_app_id = (int)($_GET['app_id'] ?? 0);
$pre_app = null;
if ($pre_app_id) {
    $pre_app = db_row($conn,
        "SELECT a.*, s.full_name, s.student_id AS sid, s.gender FROM applications a
         JOIN students s ON s.id=a.student_id WHERE a.id=? AND a.status='approved'",'i',$pre_app_id);
}

// Approved applications with no allocation
$approved_apps = db_query($conn,
    "SELECT a.*, s.full_name, s.student_id AS sid, s.gender FROM applications a
     JOIN students s ON s.id=a.student_id
     WHERE a.status='approved'
       AND NOT EXISTS (SELECT 1 FROM allocations al WHERE al.student_id=a.student_id AND al.status='active')
     ORDER BY a.created_at ASC");

// Available rooms
$avail_rooms = db_query($conn,
    "SELECT *, (capacity-occupied) AS free FROM rooms WHERE status='available' AND occupied<capacity ORDER BY block,floor,room_number");

// Current active allocations
$allocs = db_query($conn,
    "SELECT al.*, s.full_name, s.student_id AS sid, r.room_number, r.block, r.room_type, r.fee_per_sem, adm.full_name AS by_name
     FROM allocations al
     JOIN students s ON s.id=al.student_id
     JOIN rooms r ON r.id=al.room_id
     LEFT JOIN admins adm ON adm.id=al.allocated_by
     WHERE al.status='active'
     ORDER BY al.created_at DESC");

$page_title = 'Allocations';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.8);backdrop-filter:blur(8px);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:500px;animation:mIn .3s ease;max-height:90vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.7rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.4rem;}
</style>
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest Admin</a>
  <div class="nav-links">
    <span style="font-size:.82rem;color:var(--ts);">👤 <?= clean($_SESSION['admin_name']) ?></span>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="dash-main">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:.3rem;">
      <div class="page-title" style="margin:0;">Room Allocations</div>
      <?php if(!empty($approved_apps)): ?>
        <button class="btn btn-primary" onclick="document.getElementById('modal-alloc').classList.add('open')">+ New Allocation</button>
      <?php endif; ?>
    </div>
    <div class="page-sub">Assign approved applications to available rooms</div>

    <!-- PENDING ALLOCATION BANNER -->
    <?php if(!empty($approved_apps)): ?>
    <div class="warn-box" style="margin-bottom:1.2rem;">
      ⏳ <strong><?= count($approved_apps) ?> approved application<?= count($approved_apps)>1?'s':'' ?></strong> waiting for room allocation.
      <a href="#" onclick="document.getElementById('modal-alloc').classList.add('open');return false;" style="color:var(--warning);font-weight:600;margin-left:.5rem;">Allocate now →</a>
    </div>
    <?php else: ?>
    <div class="ok-box" style="margin-bottom:1.2rem;">✅ All approved applications have been allocated.</div>
    <?php endif; ?>

    <!-- CURRENT ALLOCATIONS TABLE -->
    <div class="table-wrapper">
      <div class="table-header"><div class="table-title">Active Allocations (<?= count($allocs) ?>)</div></div>
      <?php if(empty($allocs)): ?>
        <div style="padding:2.5rem;text-align:center;opacity:.55;"><div style="font-size:2rem;">🗺️</div><div style="margin-top:.5rem;font-size:.875rem;color:var(--ts);">No active allocations yet.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Student</th><th>ID</th><th>Room</th><th>Block</th><th>Type</th><th>Semester</th><th>Fee/Sem</th><th>Start Date</th><th>Allocated By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($allocs as $al): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div class="avatar" style="width:28px;height:28px;font-size:.65rem;"><?= strtoupper(substr($al['full_name'],0,1).substr(explode(' ',$al['full_name'])[1]??'',0,1)) ?></div>
                <span style="font-size:.875rem;font-weight:600;"><?= clean($al['full_name']) ?></span>
              </div>
            </td>
            <td><code><?= clean($al['sid']) ?></code></td>
            <td><strong><?= clean($al['room_number']) ?></strong></td>
            <td>Block <?= $al['block'] ?></td>
            <td><span class="tag tag-blue"><?= ucfirst($al['room_type']) ?></span></td>
            <td style="font-size:.82rem;"><?= clean($al['semester']) ?></td>
            <td style="font-family:var(--fm);color:var(--green);">৳<?= number_format($al['fee_per_sem'],0) ?></td>
            <td style="color:var(--ts);font-size:.82rem;"><?= date('M d, Y',strtotime($al['start_date'])) ?></td>
            <td style="font-size:.82rem;color:var(--ts);"><?= clean($al['by_name']??'Admin') ?></td>
            <td>
              <a class="btn btn-danger btn-sm"
                 href="allocations.php?action=vacate&id=<?= $al['id'] ?>"
                 onclick="return confirm('Vacate room for <?= clean($al['full_name']) ?>? This will free up the seat.')">
                🚪 Vacate
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- ALLOCATE ROOM MODAL -->
<div class="modal-bg <?= $pre_app?'open':'' ?>" id="modal-alloc">
  <div class="modal-box">
    <div class="modal-title">🗺️ Allocate Room to Student</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="allocate">
      <div class="form-group">
        <label class="form-label">Select Approved Application *</label>
        <select class="form-input" name="app_id" required>
          <option value="">— Select Student —</option>
          <?php foreach($approved_apps as $aa): ?>
            <option value="<?= $aa['id'] ?>" <?= $pre_app&&$pre_app['id']===$aa['id']?'selected':'' ?>>
              <?= clean($aa['full_name']) ?> (<?= clean($aa['sid']) ?>) — Pref: Block <?= $aa['preferred_block'] ?> / <?= ucfirst($aa['preferred_type']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Select Room *</label>
        <select class="form-input" name="room_id" required>
          <option value="">— Select Room —</option>
          <?php foreach($avail_rooms as $rm): ?>
            <option value="<?= $rm['id'] ?>">
              <?= $rm['room_number'] ?> — Block <?= $rm['block'] ?> / <?= ucfirst($rm['room_type']) ?> — <?= $rm['free'] ?> seat<?= $rm['free']>1?'s':'' ?> free — ৳<?= number_format($rm['fee_per_sem'],0) ?>/sem
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Semester *</label>
        <select class="form-input" name="semester" required>
          <option value="Spring-<?= date('Y') ?>">Spring <?= date('Y') ?></option>
          <option value="Fall-<?= date('Y') ?>">Fall <?= date('Y') ?></option>
          <option value="Spring-<?= date('Y')+1 ?>">Spring <?= date('Y')+1 ?></option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Start Date *</label>
        <input class="form-input" type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Notes (optional)</label>
        <textarea class="form-input" name="notes" rows="2" placeholder="Any special notes…"></textarea>
      </div>
      <div class="info-box" style="font-size:.8rem;">A payment record for the semester fee will be automatically created upon allocation.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-alloc').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">✓ Allocate Room</button>
      </div>
    </form>
  </div>
</div>
<script>document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));</script>
</body></html>
