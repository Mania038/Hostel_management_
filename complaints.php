<?php
// admin/complaints.php
require_once __DIR__ . '/../config/db.php';
require_admin();
$admin_id = (int)$_SESSION['admin_id'];

// ── RESOLVE ──────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'resolve' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $res = clean($_GET['response'] ?? 'Complaint has been resolved by admin.');
    db_exec($conn,
        "UPDATE complaints SET status='resolved', handled_by=?, resolved_at=NOW(), admin_response=? WHERE id=?",
        'isi', $admin_id, $res, $id
    );
    flash('success', 'Complaint marked as resolved.');
    redirect(APP_URL . '/admin/complaints.php');
}

// ── IN-PROGRESS ───────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'progress' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    db_exec($conn, "UPDATE complaints SET status='in_progress', handled_by=? WHERE id=?", 'ii', $admin_id, $id);
    flash('success', 'Complaint marked as in-progress.');
    redirect(APP_URL . '/admin/complaints.php');
}

// ── RESPOND / UPDATE ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'respond') {
    $id       = (int)$_POST['complaint_id'];
    $response = clean($_POST['admin_response'] ?? '');
    $status   = $_POST['new_status'] ?? 'in_progress';
    if (!in_array($status, ['in_progress', 'resolved', 'closed'])) $status = 'in_progress';
    $resolved_at = in_array($status, ['resolved', 'closed']) ? ', resolved_at=NOW()' : '';
    db_exec($conn,
        "UPDATE complaints SET status=?, admin_response=?, handled_by=?{$resolved_at} WHERE id=?",
        'ssii', $status, $response, $admin_id, $id
    );
    flash('success', 'Response saved successfully.');
    redirect(APP_URL . '/admin/complaints.php');
}

// ── DELETE ────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    db_exec($conn, "DELETE FROM complaints WHERE id=? AND status IN ('resolved','closed')", 'i', $id);
    flash('success', 'Complaint deleted.');
    redirect(APP_URL . '/admin/complaints.php');
}

// Filters
$fstatus   = $_GET['status']   ?? 'all';
$fpriority = $_GET['priority'] ?? '';
$fcat      = $_GET['category'] ?? '';

$where = ['1=1']; $ptypes = ''; $pvals = [];
if ($fstatus !== 'all' && in_array($fstatus, ['open','in_progress','resolved','closed'])) {
    $where[] = "c.status=?"; $ptypes .= 's'; $pvals[] = $fstatus;
}
if (in_array($fpriority, ['low','medium','high','urgent'])) {
    $where[] = "c.priority=?"; $ptypes .= 's'; $pvals[] = $fpriority;
}
$cat_opts = ['maintenance','plumbing','electricity','internet','cleanliness','security','other'];
if (in_array($fcat, $cat_opts)) {
    $where[] = "c.category=?"; $ptypes .= 's'; $pvals[] = $fcat;
}

$complaints = db_query($conn,
    "SELECT c.*, s.full_name, s.student_id AS sid, r.room_number, r.block,
            adm.full_name AS handler_name
     FROM complaints c
     JOIN students s ON s.id = c.student_id
     LEFT JOIN rooms r ON r.id = c.room_id
     LEFT JOIN admins adm ON adm.id = c.handled_by
     WHERE " . implode(' AND ', $where) . "
     ORDER BY
       FIELD(c.priority,'urgent','high','medium','low'),
       FIELD(c.status,'open','in_progress','resolved','closed'),
       c.created_at DESC",
    $ptypes, ...$pvals);

// Count tabs
$tab_counts = [];
foreach (['all', 'open', 'in_progress', 'resolved', 'closed'] as $ts) {
    $wh = $ts === 'all' ? '1=1' : "status='{$ts}'";
    $tab_counts[$ts] = db_row($conn, "SELECT COUNT(*) AS c FROM complaints WHERE {$wh}")['c'] ?? 0;
}

// Detail view
$view_id      = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_detail  = null;
if ($view_id) {
    $view_detail = db_row($conn,
        "SELECT c.*, s.full_name, s.student_id AS sid, s.email, s.phone,
                r.room_number, r.block, adm.full_name AS handler_name
         FROM complaints c
         JOIN students s ON s.id=c.student_id
         LEFT JOIN rooms r ON r.id=c.room_id
         LEFT JOIN admins adm ON adm.id=c.handled_by
         WHERE c.id=?", 'i', $view_id);
}

$cat_icons = [
    'maintenance' => '🔧', 'plumbing' => '💧', 'electricity' => '💡',
    'internet'    => '📶', 'cleanliness' => '🧹', 'security' => '🔒', 'other' => '📌'
];
$priority_color = [
    'low' => 'var(--success)', 'medium' => 'var(--warning)',
    'high' => 'var(--danger)', 'urgent' => 'var(--danger)'
];

$page_title = 'Complaints Management';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.85);backdrop-filter:blur(10px);z-index:500;align-items:center;justify-content:center;padding:1rem;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:540px;animation:mIn .3s ease;max-height:92vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(-8px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.3rem;}
.sub-tabs{display:flex;gap:.2rem;flex-wrap:wrap;margin-bottom:1.2rem;}
.sub-tab{padding:.4rem .9rem;border-radius:var(--r1);font-size:.82rem;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--ts);font-family:var(--fb);transition:all .2s;text-decoration:none;}
.sub-tab:hover{color:var(--tp);}
.sub-tab.active{background:rgba(99,179,237,.1);color:var(--blue);}
.comp-row{background:var(--card);border:1px solid var(--gb);border-radius:var(--r2);padding:1rem 1.1rem;display:flex;gap:.9rem;align-items:flex-start;transition:border-color .2s;margin-bottom:.6rem;}
.comp-row:hover{border-color:rgba(99,179,237,.2);}
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
    <div class="page-title">Complaints Management</div>
    <div class="page-sub">Track and resolve all student hostel complaints</div>

    <!-- STATS ROW -->
    <div class="g4" style="margin-bottom:1.3rem;">
      <div class="stat-card pink">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">📣</div>
        <div class="stat-val"><?= $tab_counts['open'] ?></div>
        <div class="stat-label">Open Complaints</div>
      </div>
      <div class="stat-card cyan">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">⚙️</div>
        <div class="stat-val"><?= $tab_counts['in_progress'] ?></div>
        <div class="stat-label">In Progress</div>
      </div>
      <div class="stat-card green">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">✅</div>
        <div class="stat-val"><?= $tab_counts['resolved'] ?></div>
        <div class="stat-label">Resolved</div>
      </div>
      <div class="stat-card blue">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">📊</div>
        <div class="stat-val"><?= $tab_counts['all'] ?></div>
        <div class="stat-label">Total</div>
      </div>
    </div>

    <!-- FILTERS BAR -->
    <div class="glass" style="padding:1rem;margin-bottom:1.2rem;">
      <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:130px;">
          <label class="form-label">Priority</label>
          <select class="form-input" name="priority">
            <option value="">All Priorities</option>
            <option value="urgent"  <?= $fpriority==='urgent'?'selected':''  ?>>🔴 Urgent</option>
            <option value="high"    <?= $fpriority==='high'?'selected':''    ?>>🟠 High</option>
            <option value="medium"  <?= $fpriority==='medium'?'selected':''  ?>>🟡 Medium</option>
            <option value="low"     <?= $fpriority==='low'?'selected':''     ?>>🟢 Low</option>
          </select>
        </div>
        <div class="form-group" style="flex:1;min-width:150px;">
          <label class="form-label">Category</label>
          <select class="form-input" name="category">
            <option value="">All Categories</option>
            <?php foreach($cat_icons as $cat => $icon): ?>
              <option value="<?= $cat ?>" <?= $fcat===$cat?'selected':'' ?>><?= $icon ?> <?= ucfirst($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="status" value="<?= clean($fstatus) ?>">
        <button class="btn btn-primary btn-sm" type="submit" style="height:42px;">Filter</button>
        <a class="btn btn-secondary btn-sm" href="complaints.php" style="height:42px;">Reset</a>
      </form>
    </div>

    <!-- STATUS TABS -->
    <div class="sub-tabs">
      <?php foreach(['all'=>'All','open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'] as $k=>$lbl): ?>
        <a class="sub-tab <?= $fstatus===$k?'active':'' ?>"
           href="complaints.php?status=<?= $k ?>&priority=<?= $fpriority ?>&category=<?= $fcat ?>">
          <?= $lbl ?> (<?= $tab_counts[$k] ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <!-- COMPLAINTS LIST -->
    <?php if(empty($complaints)): ?>
      <div class="glass" style="padding:3rem;text-align:center;opacity:.55;">
        <div style="font-size:2.5rem;margin-bottom:.75rem;">📣</div>
        <div style="font-family:var(--fd);font-size:1rem;font-weight:700;">No complaints found</div>
        <div style="color:var(--ts);margin-top:.3rem;font-size:.875rem;">Try adjusting the filters above.</div>
      </div>
    <?php else: ?>
      <?php foreach($complaints as $c): ?>
      <div class="comp-row">
        <!-- Category icon -->
        <div style="width:44px;height:44px;border-radius:var(--r2);background:rgba(255,255,255,.04);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;">
          <?= $cat_icons[$c['category']] ?? '📌' ?>
        </div>

        <!-- Main info -->
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
            <div>
              <div style="font-size:.9rem;font-weight:700;"><?= clean($c['subject']) ?></div>
              <div style="font-size:.78rem;color:var(--ts);margin-top:.15rem;">
                <?= clean($c['full_name']) ?> (<?= clean($c['sid']) ?>)
                <?= $c['room_number'] ? ' · Room '.$c['room_number'].' Block '.$c['block'] : '' ?>
                · <?= date('M d, Y · H:i', strtotime($c['created_at'])) ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:.4rem;flex-shrink:0;">
              <span style="font-size:.73rem;font-weight:600;color:<?= $priority_color[$c['priority']] ?>;">
                ● <?= ucfirst($c['priority']) ?>
              </span>
              <span class="badge badge-<?= str_replace('_','',$c['status']) ?>">
                <?= ucfirst(str_replace('_',' ',$c['status'])) ?>
              </span>
            </div>
          </div>

          <!-- Description -->
          <div style="font-size:.83rem;color:var(--ts);margin-top:.4rem;line-height:1.55;">
            <?= clean(substr($c['description'],0,160)) ?><?= strlen($c['description'])>160?'…':'' ?>
          </div>

          <!-- Admin response if any -->
          <?php if($c['admin_response']): ?>
          <div style="margin-top:.5rem;padding:.5rem .75rem;background:rgba(104,211,145,.06);border-left:2px solid var(--success);border-radius:0 var(--r1) var(--r1) 0;font-size:.8rem;">
            <strong style="color:var(--success);">Admin Response:</strong>
            <?= clean($c['admin_response']) ?>
            <?php if($c['handler_name']): ?>
              <span style="color:var(--ts);"> — <?= clean($c['handler_name']) ?></span>
            <?php endif; ?>
            <?php if($c['resolved_at']): ?>
              <span style="color:var(--ts);"> · <?= date('M d, Y',strtotime($c['resolved_at'])) ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Actions -->
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.65rem;">
            <button class="btn btn-secondary btn-sm"
                    onclick="openRespond(<?= $c['id'] ?>,'<?= addslashes(clean($c['subject'])) ?>','<?= addslashes(clean($c['admin_response']??'')) ?>','<?= $c['status'] ?>')">
              💬 Respond
            </button>
            <?php if($c['status']==='open'): ?>
              <a class="btn btn-secondary btn-sm" href="complaints.php?action=progress&id=<?= $c['id'] ?>&status=<?= $fstatus ?>&priority=<?= $fpriority ?>&category=<?= $fcat ?>">⚙️ In Progress</a>
            <?php endif; ?>
            <?php if(in_array($c['status'],['open','in_progress'])): ?>
              <a class="btn btn-success btn-sm"
                 href="complaints.php?action=resolve&id=<?= $c['id'] ?>&status=<?= $fstatus ?>"
                 onclick="return confirm('Mark as resolved?')">✓ Resolve</a>
            <?php endif; ?>
            <?php if(in_array($c['status'],['resolved','closed'])): ?>
              <a class="btn btn-danger btn-sm"
                 href="complaints.php?action=delete&id=<?= $c['id'] ?>"
                 onclick="return confirm('Delete this complaint record?')">🗑️ Delete</a>
            <?php endif; ?>
            <span style="font-size:.73rem;color:var(--tm);align-self:center;">
              #C<?= str_pad($c['id'],3,'0',STR_PAD_LEFT) ?> · <?= ucfirst($c['category']) ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<!-- RESPOND MODAL -->
<div class="modal-bg" id="modal-respond">
  <div class="modal-box">
    <div class="modal-title">💬 Respond to Complaint</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action"    value="respond">
      <input type="hidden" name="complaint_id"   id="resp-id">
      <div id="resp-subject" style="font-size:.875rem;font-weight:600;color:var(--blue);padding:.5rem .75rem;background:rgba(99,179,237,.06);border-radius:var(--r1);border-left:2px solid var(--blue);"></div>
      <div class="form-group">
        <label class="form-label">Update Status</label>
        <select class="form-input" name="new_status" id="resp-status">
          <option value="in_progress">⚙️ Mark In Progress</option>
          <option value="resolved">✅ Mark Resolved</option>
          <option value="closed">🔒 Close</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Admin Response / Note *</label>
        <textarea class="form-input" name="admin_response" id="resp-text" rows="5"
                  placeholder="Describe the action taken or provide information to the student…" required></textarea>
      </div>
      <div class="info-box" style="font-size:.8rem;">The student will see this response in their complaints section.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-respond').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Response</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRespond(id, subject, existingResp, currentStatus) {
  document.getElementById('resp-id').value       = id;
  document.getElementById('resp-subject').textContent = subject;
  document.getElementById('resp-text').value     = existingResp || '';
  const sel = document.getElementById('resp-status');
  if (currentStatus === 'in_progress') sel.value = 'resolved';
  else if (currentStatus === 'open')   sel.value = 'in_progress';
  else                                 sel.value = currentStatus;
  document.getElementById('modal-respond').classList.add('open');
}
document.querySelectorAll('.modal-bg').forEach(m =>
  m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); })
);
</script>
</body></html>
