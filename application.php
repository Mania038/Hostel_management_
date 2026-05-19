<?php
// student/application.php
require_once __DIR__ . '/../config/db.php';
require_student();
$sid     = (int)$_SESSION['student_id'];
$student = db_row($conn, "SELECT * FROM students WHERE id=?", 'i', $sid);

// Withdraw action
if (isset($_GET['action']) && $_GET['action'] === 'withdraw' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    $check  = db_row($conn, "SELECT id,status FROM applications WHERE id=? AND student_id=?", 'ii', $app_id, $sid);
    if ($check && in_array($check['status'], ['pending'])) {
        db_exec($conn, "UPDATE applications SET status='withdrawn' WHERE id=?", 'i', $app_id);
        flash('success', 'Application withdrawn successfully.');
    } else {
        flash('error', 'Cannot withdraw this application.');
    }
    redirect(APP_URL . '/student/application.php');
}

// Fetch all applications for this student
$apps = db_query($conn,
    "SELECT a.*, adm.full_name AS reviewed_by_name
     FROM applications a
     LEFT JOIN admins adm ON adm.id = a.reviewed_by
     WHERE a.student_id = ?
     ORDER BY a.created_at DESC", 'i', $sid);

$page_title = 'My Application';
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
    <div class="page-title">My Application</div>
    <div class="page-sub">Track the status of your hostel room application</div>

    <?php if (empty($apps)): ?>
      <div class="info-box" style="margin-bottom:1.5rem;">
        You have not submitted any application yet.
        <a href="apply.php" style="color:var(--blue);font-weight:600;"> Apply for a room →</a>
      </div>
    <?php else:
      $steps_map = [
        'pending'   => 1,
        'approved'  => 2,
        'rejected'  => 2,
        'allocated' => 3,
        'withdrawn' => 0,
      ];
      foreach ($apps as $app):
        $step = $steps_map[$app['status']] ?? 1;
    ?>
    <div class="table-wrapper" style="margin-bottom:1.5rem;">
      <div class="table-header">
        <div>
          <div class="table-title">Application <code><?= clean($app['app_code']) ?></code></div>
          <div style="font-size:.78rem;color:var(--ts);margin-top:.2rem;">Submitted <?= date('M d, Y · H:i', strtotime($app['created_at'])) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <span class="badge badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
          <?php if ($app['status'] === 'pending'): ?>
            <a class="btn btn-danger btn-sm"
               href="application.php?action=withdraw&id=<?= $app['id'] ?>"
               onclick="return confirm('Withdraw this application?')">🗑 Withdraw</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Application details table -->
      <table class="data-table" style="max-width:600px;">
        <tbody>
          <tr><td style="color:var(--ts);width:40%;">Application ID</td><td><code><?= clean($app['app_code']) ?></code></td></tr>
          <tr><td style="color:var(--ts);">Preferred Block</td><td>Block <?= $app['preferred_block'] ?></td></tr>
          <tr><td style="color:var(--ts);">Room Type</td><td><?= ucfirst($app['preferred_type']) ?> Room</td></tr>
          <tr><td style="color:var(--ts);">Floor Preference</td><td><?= $app['preferred_floor'] ? 'Floor '.$app['preferred_floor'] : 'No preference' ?></td></tr>
          <tr><td style="color:var(--ts);">Special Request</td><td><?= $app['special_req'] ? clean(str_replace('_',' ',ucfirst($app['special_req']))) : '—' ?></td></tr>
          <tr><td style="color:var(--ts);">Status</td><td><span class="badge badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span></td></tr>
          <?php if ($app['reviewed_by_name']): ?>
          <tr><td style="color:var(--ts);">Reviewed By</td><td><?= clean($app['reviewed_by_name']) ?></td></tr>
          <tr><td style="color:var(--ts);">Reviewed On</td><td><?= date('M d, Y', strtotime($app['reviewed_at'])) ?></td></tr>
          <?php endif; ?>
          <?php if ($app['admin_note']): ?>
          <tr><td style="color:var(--ts);">Admin Note</td><td style="color:var(--warning);"><?= clean($app['admin_note']) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Progress tracker (hidden for withdrawn) -->
      <?php if ($app['status'] !== 'withdrawn'): ?>
      <div style="padding:1.25rem 1.25rem .5rem;">
        <div style="font-size:.8rem;font-weight:600;color:var(--ts);margin-bottom:1rem;text-transform:uppercase;letter-spacing:.06em;">Application Progress</div>
        <div style="display:flex;align-items:center;gap:0;">
          <?php
          $prog_steps = [
            ['label'=>'Submitted',  'icon'=>'📝'],
            ['label'=>'Reviewed',   'icon'=>'🔍'],
            ['label'=>'Allocated',  'icon'=>'🏠'],
            ['label'=>'Move-In',    'icon'=>'✅'],
          ];
          foreach ($prog_steps as $pi => $ps):
            $done   = ($pi < $step) || ($pi === $step && in_array($app['status'],['allocated']));
            $active = ($pi === $step) && !in_array($app['status'],['allocated']);
            $reject = $app['status']==='rejected' && $pi===1;
          ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:.35rem;flex:1;text-align:center;">
              <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;
                <?= $reject ? 'background:rgba(252,129,129,.15);border:2px solid var(--danger);color:var(--danger);' :
                   ($done   ? 'background:linear-gradient(135deg,#63b3ed,#b794f4);color:#fff;' :
                   ($active ? 'background:rgba(246,224,94,.12);border:2px solid var(--warning);color:var(--warning);' :
                              'background:rgba(255,255,255,.06);border:1px solid var(--gb);color:var(--tm);')) ?>">
                <?= $reject ? '✗' : ($done ? '✓' : ($active ? '⋯' : ($pi+1))) ?>
              </div>
              <div style="font-size:.73rem;color:<?= $done?'var(--blue)':($active?'var(--warning)':($reject?'var(--danger)':'var(--tm)')) ?>;">
                <?= $reject && $pi===1 ? 'Rejected' : $ps['label'] ?>
              </div>
            </div>
            <?php if ($pi < count($prog_steps)-1): ?>
            <div style="flex:1;height:2px;background:<?= $done?'linear-gradient(90deg,var(--blue),rgba(99,179,237,.2))':'var(--gb)' ?>;margin-bottom:1.5rem;margin-top:-.8rem;"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>

    <div style="display:flex;gap:.75rem;">
      <?php $has_active = !empty(array_filter($apps, fn($a)=>!in_array($a['status'],['rejected','withdrawn']))); ?>
      <?php if (!$has_active): ?>
        <a class="btn btn-primary" href="apply.php">📋 New Application</a>
      <?php endif; ?>
      <a class="btn btn-secondary" href="<?= APP_URL ?>/student/rooms.php">🏠 Browse Rooms</a>
    </div>
  </main>
</div>
</body></html>
