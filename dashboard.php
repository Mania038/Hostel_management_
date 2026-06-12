<?php
// student/dashboard.php
require_once __DIR__ . '/../config/db.php';
require_student();

$sid = (int)$_SESSION['student_id'];

// Fetch student details
$student = db_row($conn, "SELECT * FROM students WHERE id=?", 'i', $sid);

// Fetch latest application
$app = db_row($conn,
    "SELECT * FROM applications WHERE student_id=? ORDER BY created_at DESC LIMIT 1", 'i', $sid);

// Fetch active allocation + room
$alloc = db_row($conn,
    "SELECT al.*, r.room_number, r.block, r.floor, r.room_type, r.fee_per_sem,
            r.has_ac, r.has_wifi, r.has_attached_bath
     FROM allocations al JOIN rooms r ON r.id=al.room_id
     WHERE al.student_id=? AND al.status='active' LIMIT 1", 'i', $sid);

// Fetch pending payment
$payment = db_row($conn,
    "SELECT * FROM payments WHERE student_id=? AND status IN ('pending','overdue')
     ORDER BY due_date ASC LIMIT 1", 'i', $sid);

// Fetch open complaints count
$comp_row = db_row($conn,
    "SELECT COUNT(*) AS cnt FROM complaints WHERE student_id=? AND status IN ('open','in_progress')", 'i', $sid);
$open_complaints = $comp_row['cnt'] ?? 0;

// Notices
$notices = db_query($conn, "SELECT * FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 4");

$page_title = 'My Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="<?= APP_URL ?>/student/rooms.php">Browse Rooms</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>

<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/student_sidebar.php'; ?>

  <main class="dash-main">
    <div class="page-title">Welcome back, <?= clean(explode(' ',$student['full_name'])[0]) ?> 👋</div>
    <div class="page-sub">Here's a snapshot of your hostel status</div>

    <!-- STAT CARDS -->
    <div class="g4" style="margin-bottom:1.4rem;">
      <div class="stat-card blue">
        <div style="font-size:1.5rem;margin-bottom:.3rem;">📋</div>
        <div class="stat-val"><?= $app ? 1 : 0 ?></div>
        <div class="stat-label">Application<?= $app ? '' : ' (none)' ?></div>
      </div>
      <div class="stat-card purple">
        <div style="font-size:1.5rem;margin-bottom:.3rem;">🏠</div>
        <div class="stat-val"><?= $alloc ? $alloc['room_number'] : '—' ?></div>
        <div class="stat-label">Assigned Room</div>
      </div>
      <div class="stat-card cyan">
        <div style="font-size:1.5rem;margin-bottom:.3rem;">💳</div>
        <div class="stat-val">৳<?= $payment ? number_format($payment['amount'],0) : '0' ?></div>
        <div class="stat-label">Balance Due</div>
      </div>
      <div class="stat-card green">
        <div style="font-size:1.5rem;margin-bottom:.3rem;">📣</div>
        <div class="stat-val"><?= $open_complaints ?></div>
        <div class="stat-label">Open Complaints</div>
      </div>
    </div>

    <div class="g21">
      <div>
        <!-- APPLICATION STATUS TRACKER -->
        <div class="table-wrapper" style="margin-bottom:1.2rem;">
          <div class="table-header">
            <div class="table-title">Application Status</div>
            <?php if($app): ?>
              <span class="badge badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
            <?php else: ?>
              <a class="btn btn-primary btn-sm" href="apply.php">Apply Now</a>
            <?php endif; ?>
          </div>
          <div style="padding:1.25rem;">
            <?php if(!$app): ?>
              <div class="info-box">You have not submitted an application yet. <a href="apply.php" style="color:var(--blue);font-weight:600;">Apply for a room →</a></div>
            <?php else:
              $steps = [
                ['label'=>'Application Submitted','done'=>true,'date'=>date('M d, Y H:i', strtotime($app['created_at']))],
                ['label'=>'Admin Review',          'done'=>in_array($app['status'],['approved','rejected','allocated']),'date'=>$app['reviewed_at']?date('M d, Y',strtotime($app['reviewed_at'])):'In progress…'],
                ['label'=>'Room Allocation',       'done'=>in_array($app['status'],['allocated']),'date'=>$alloc?date('M d, Y',strtotime($alloc['start_date'])):'Awaiting approval'],
                ['label'=>'Confirmation & Payment','done'=>$payment&&$payment['status']==='paid','date'=>'Final step'],
              ];
              foreach($steps as $i=>$step):
                $active = !$step['done'] && ($i===0 || $steps[$i-1]['done']); ?>
                <div style="display:flex;align-items:flex-start;gap:.75rem;margin-bottom:.5rem;">
                  <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;
                    <?= $step['done'] ? 'background:linear-gradient(135deg,#63b3ed,#b794f4);color:#fff;' : ($active?'border:2px solid var(--warning);color:var(--warning);background:rgba(246,224,94,.1);':'border:1px solid var(--gb);color:var(--tm);') ?>">
                    <?= $step['done'] ? '✓' : ($active ? '⋯' : ($i+1)) ?>
                  </div>
                  <div style="<?= !$step['done']&&!$active?'opacity:.45':'' ?>">
                    <div style="font-size:.875rem;font-weight:600;"><?= $step['label'] ?></div>
                    <div style="font-size:.75rem;color:var(--ts);"><?= $step['date'] ?></div>
                  </div>
                </div>
                <?php if($i<3): ?><div style="margin-left:14px;width:1px;height:20px;background:<?= $step['done']?'linear-gradient(180deg,var(--blue),transparent)':'var(--gb)' ?>;margin-bottom:.5rem;"></div><?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="glass" style="padding:1.1rem;">
          <div class="table-title" style="margin-bottom:.9rem;">Quick Actions</div>
          <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
            <a class="btn btn-secondary btn-sm" href="apply.php">📋 New Application</a>
            <a class="btn btn-secondary btn-sm" href="complaints.php">📣 File Complaint</a>
            <a class="btn btn-secondary btn-sm" href="payments.php">💳 View Payments</a>
            <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/student/rooms.php">🏠 Browse Rooms</a>
          </div>
        </div>
      </div>

      <!-- SIDEBAR WIDGETS -->
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <!-- NOTICES -->
        <div class="glass" style="padding:1.1rem;">
          <div class="table-title" style="margin-bottom:.8rem;">Notices 📢</div>
          <?php if(empty($notices)): ?>
            <div style="font-size:.83rem;color:var(--ts);">No notices at the moment.</div>
          <?php else: foreach($notices as $n):
            $bc = ['info'=>'var(--blue)','warning'=>'var(--warning)','danger'=>'var(--danger)','success'=>'var(--success)'];
            $col = $bc[$n['type']] ?? 'var(--blue)'; ?>
            <div style="font-size:.8rem;padding:.5rem;border-left:2px solid <?= $col ?>;background:rgba(255,255,255,.02);border-radius:0 var(--r1) var(--r1) 0;margin-bottom:.5rem;">
              <strong><?= clean($n['title']) ?></strong><br>
              <span style="color:var(--ts);"><?= clean(substr($n['body'],0,80)) ?>…</span>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <!-- ROOM PREFERENCE -->
        <?php if($app): ?>
        <div class="glass" style="padding:1.1rem;">
          <div class="table-title" style="margin-bottom:.75rem;">My Preferences</div>
          <div style="display:flex;flex-direction:column;gap:.35rem;font-size:.825rem;">
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Block:</span><span>Block <?= $app['preferred_block'] ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Type:</span><span><?= ucfirst($app['preferred_type']) ?> Room</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">App ID:</span><code><?= $app['app_code'] ?></code></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
</body></html>
