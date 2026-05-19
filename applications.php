<?php
// admin/applications.php
require_once __DIR__ . '/../config/db.php';
require_admin();

$admin_id = (int)$_SESSION['admin_id'];

// APPROVE
if (isset($_GET['action']) && $_GET['action']==='approve' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $note= clean($_GET['note'] ?? '');
    db_exec($conn,
        "UPDATE applications SET status='approved', reviewed_by=?, reviewed_at=NOW(), admin_note=? WHERE id=? AND status='pending'",
        'isi', $admin_id, $note, $id
    );
    flash('success','Application approved. You can now allocate a room.');
    redirect(APP_URL.'/admin/applications.php');
}

// REJECT
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action']??'')==='reject') {
    $id   = (int)$_POST['app_id'];
    $note = clean($_POST['admin_note'] ?? '');
    if (!$note) { flash('error','Please provide a reason for rejection.'); redirect(APP_URL.'/admin/applications.php'); }
    db_exec($conn,
        "UPDATE applications SET status='rejected', reviewed_by=?, reviewed_at=NOW(), admin_note=? WHERE id=? AND status='pending'",
        'isi', $admin_id, $note, $id
    );
    flash('success','Application rejected. Student has been notified.');
    redirect(APP_URL.'/admin/applications.php');
}

$filter = $_GET['status'] ?? 'all';
$where  = ['1=1'];
if ($filter !== 'all' && in_array($filter,['pending','approved','rejected','allocated','withdrawn'])) {
    $where[] = "a.status='{$filter}'";
}

$apps = db_query($conn,
    "SELECT a.*, s.full_name, s.student_id AS sid, s.email, s.department, s.year_of_study, s.gender,
            adm.full_name AS reviewed_by_name
     FROM applications a
     JOIN students s ON s.id=a.student_id
     LEFT JOIN admins adm ON adm.id=a.reviewed_by
     WHERE ".implode(' AND ',$where)."
     ORDER BY a.created_at DESC");

// counts for tab badges
$counts = [];
foreach(['all','pending','approved','rejected','allocated','withdrawn'] as $st) {
    $wh = $st==='all' ? '1=1' : "status='{$st}'";
    $r  = db_row($conn,"SELECT COUNT(*) AS c FROM applications WHERE {$wh}");
    $counts[$st] = $r['c'] ?? 0;
}

$page_title = 'Applications';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.8);backdrop-filter:blur(8px);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:460px;animation:mIn .3s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.7rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.2rem;}
.sub-tabs{display:flex;gap:.2rem;flex-wrap:wrap;margin-bottom:1.2rem;}
.sub-tab{padding:.4rem .9rem;border-radius:var(--r1);font-size:.82rem;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--ts);font-family:var(--fb);transition:all .2s;text-decoration:none;}
.sub-tab:hover{color:var(--tp);}
.sub-tab.active{background:rgba(99,179,237,.1);color:var(--blue);}
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
    <div class="page-title">Room Applications</div>
    <div class="page-sub">Review and process all student hostel applications</div>

    <!-- TABS -->
    <div class="sub-tabs">
      <?php foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','allocated'=>'Allocated','withdrawn'=>'Withdrawn'] as $k=>$lbl): ?>
        <a class="sub-tab <?= $filter===$k?'active':'' ?>" href="applications.php?status=<?= $k ?>">
          <?= $lbl ?> (<?= $counts[$k] ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <div class="table-wrapper">
      <div class="table-header">
        <div class="table-title">Applications — <?= ucfirst($filter) ?></div>
      </div>
      <?php if(empty($apps)): ?>
        <div style="padding:2.5rem;text-align:center;opacity:.55;"><div style="font-size:2rem;">📋</div><div style="margin-top:.5rem;font-size:.875rem;color:var(--ts);">No applications found.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>App ID</th><th>Student</th><th>Block</th><th>Type</th><th>Gender</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($apps as $a): $ini=strtoupper(substr($a['full_name'],0,1).substr(explode(' ',$a['full_name'])[1]??'',0,1)); ?>
          <tr>
            <td><code><?= clean($a['app_code']) ?></code></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div class="avatar" style="width:28px;height:28px;font-size:.65rem;"><?= $ini ?></div>
                <div>
                  <div style="font-size:.85rem;font-weight:600;"><?= clean($a['full_name']) ?></div>
                  <div style="font-size:.73rem;color:var(--ts);"><?= clean($a['sid']) ?> · <?= clean($a['department']) ?></div>
                </div>
              </div>
            </td>
            <td>Block <?= $a['preferred_block'] ?></td>
            <td><span class="tag tag-<?= ['single'=>'blue','double'=>'purple','triple'=>'cyan','quad'=>'blue'][$a['preferred_type']] ?>"><?= ucfirst($a['preferred_type']) ?></span></td>
            <td><?= ucfirst($a['gender']) ?></td>
            <td style="color:var(--ts);font-size:.8rem;"><?= date('M d, Y',strtotime($a['created_at'])) ?></td>
            <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <?php if($a['status']==='pending'): ?>
                  <a class="btn btn-success btn-sm" href="applications.php?action=approve&id=<?= $a['id'] ?>"
                     onclick="return confirm('Approve application <?= $a['app_code'] ?>?')">✓ Approve</a>
                  <button class="btn btn-danger btn-sm"
                     onclick="openReject(<?= $a['id'] ?>,'<?= clean($a['app_code']) ?>')">✗ Reject</button>
                <?php elseif($a['status']==='approved'): ?>
                  <a class="btn btn-secondary btn-sm" href="allocations.php?app_id=<?= $a['id'] ?>">🗺️ Allocate</a>
                <?php else: ?>
                  <span style="font-size:.75rem;color:var(--tm);"><?= ucfirst($a['status']) ?></span>
                <?php endif; ?>
                <?php if($a['admin_note']): ?>
                  <span title="<?= clean($a['admin_note']) ?>" style="cursor:help;font-size:.75rem;color:var(--warning);">📝 Note</span>
                <?php endif; ?>
              </div>
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

<!-- REJECT MODAL -->
<div class="modal-bg" id="modal-reject">
  <div class="modal-box">
    <div class="modal-title">✗ Reject Application</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="reject">
      <input type="hidden" name="app_id" id="reject-app-id">
      <div style="font-size:.875rem;color:var(--ts);">Rejecting: <strong id="reject-app-code" style="color:var(--tp);"></strong></div>
      <div class="form-group">
        <label class="form-label">Reason for Rejection *</label>
        <textarea class="form-input" name="admin_note" rows="4" placeholder="Explain why the application is being rejected. The student will see this note." required></textarea>
      </div>
      <div class="warn-box" style="font-size:.82rem;">⚠️ This action cannot be undone. The student will need to submit a new application.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-reject').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm Reject</button>
      </div>
    </form>
  </div>
</div>
<script>
function openReject(id, code) {
  document.getElementById('reject-app-id').value   = id;
  document.getElementById('reject-app-code').textContent = code;
  document.getElementById('modal-reject').classList.add('open');
}
document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));
</script>
</body></html>
