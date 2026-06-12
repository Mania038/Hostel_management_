<?php
// includes/student_sidebar.php
$current  = basename($_SERVER['PHP_SELF']);
$initials = strtoupper(substr($student['full_name'],0,1).substr(explode(' ',$student['full_name'])[1]??'',0,1));
if (!function_exists('s_active')) {
    function s_active(string $page, string $cur): string { return $page === $cur ? 'active' : ''; }
}
// Count open tasks student can apply for (has unpaid fees)
$s_unpaid = db_row($conn,"SELECT COUNT(*) AS c FROM payments WHERE student_id=? AND status IN ('pending','overdue')",'i',$student['id'])['c']??0;
$s_tasks  = db_row($conn,"SELECT COUNT(*) AS c FROM tasks WHERE status='open' AND deadline >= CURDATE()")['c']??0;
$s_myapps = db_row($conn,"SELECT COUNT(*) AS c FROM task_applications WHERE student_id=? AND status IN ('applied','in_progress','submitted')",'i',$student['id'])['c']??0;
?>
<aside class="sidebar">
  <div class="sidebar-brand">UniNest</div>

  <div class="sidebar-sec">Main</div>
  <a class="sidebar-item <?= s_active('dashboard.php',$current) ?>" href="<?= APP_URL ?>/student/dashboard.php">
    <span class="sidebar-icon">📊</span> Overview
  </a>
  <a class="sidebar-item <?= s_active('rooms.php',$current) ?>" href="<?= APP_URL ?>/student/rooms.php">
    <span class="sidebar-icon">🏠</span> Browse Rooms
  </a>
  <a class="sidebar-item <?= s_active('apply.php',$current) ?>" href="<?= APP_URL ?>/student/apply.php">
    <span class="sidebar-icon">📋</span> Apply for Room
  </a>

  <div class="sidebar-sec">My Account</div>
  <a class="sidebar-item <?= s_active('application.php',$current) ?>" href="<?= APP_URL ?>/student/application.php">
    <span class="sidebar-icon">📄</span> My Application
  </a>
  <a class="sidebar-item <?= s_active('my_room.php',$current) ?>" href="<?= APP_URL ?>/student/my_room.php">
    <span class="sidebar-icon">🛏️</span> My Room
  </a>
  <a class="sidebar-item <?= s_active('payments.php',$current) ?>" href="<?= APP_URL ?>/student/payments.php">
    <span class="sidebar-icon">💳</span> Payments
    <?php if($s_unpaid>0): ?>
      <span style="margin-left:auto;background:rgba(252,129,129,.2);color:var(--danger);font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;"><?= $s_unpaid ?> due</span>
    <?php endif; ?>
  </a>
  <a class="sidebar-item <?= s_active('complaints.php',$current) ?>" href="<?= APP_URL ?>/student/complaints.php">
    <span class="sidebar-icon">📣</span> Complaints
  </a>

  <div class="sidebar-sec">Fee Exchange</div>
  <a class="sidebar-item <?= s_active('tasks.php',$current) ?>" href="<?= APP_URL ?>/student/tasks.php">
    <span class="sidebar-icon">🔄</span> Task Exchange
    <?php if($s_tasks>0 && $s_unpaid>0): ?>
      <span style="margin-left:auto;background:rgba(104,211,145,.2);color:var(--success);font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;"><?= $s_tasks ?> open</span>
    <?php elseif($s_myapps>0): ?>
      <span style="margin-left:auto;background:rgba(246,224,94,.2);color:var(--warning);font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;"><?= $s_myapps ?> active</span>
    <?php endif; ?>
  </a>

  <div class="sidebar-sec">Account</div>
  <a class="sidebar-item" href="<?= APP_URL ?>/auth/logout.php"><span class="sidebar-icon">🚪</span> Logout</a>

  <div class="sidebar-user">
    <div class="avatar"><?= $initials ?></div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= clean($student['full_name']) ?></div>
      <div class="sidebar-user-role"><?= clean($student['student_id']) ?></div>
    </div>
  </div>
</aside>