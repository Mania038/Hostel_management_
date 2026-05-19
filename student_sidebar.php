<?php
// includes/student_sidebar.php
// Requires $student array already fetched, session already started
$initials = strtoupper(
    substr($student['full_name'], 0, 1) .
    substr(explode(' ', $student['full_name'])[1] ?? '', 0, 1)
);
$current = basename($_SERVER['PHP_SELF']);
if (!function_exists('s_active')) {
    function s_active(string $page, string $current): string {
        return $page === $current ? 'active' : '';
    }
}
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
  </a>
  <a class="sidebar-item <?= s_active('complaints.php',$current) ?>" href="<?= APP_URL ?>/student/complaints.php">
    <span class="sidebar-icon">📣</span> Complaints
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
