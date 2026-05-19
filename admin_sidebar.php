<?php
// includes/admin_sidebar.php
$current = basename($_SERVER['PHP_SELF']);
if (!function_exists('a_active')) {
    function a_active(string $page, string $current): string {
        return $page === $current ? 'active' : '';
    }
}
?>
<aside class="sidebar">
  <div class="sidebar-brand" style="background:linear-gradient(135deg,#b794f4,#63b3ed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">UniNest Admin</div>
  <div class="sidebar-sec">Dashboard</div>
  <a class="sidebar-item <?= a_active('dashboard.php',$current) ?>" href="<?= APP_URL ?>/admin/dashboard.php">
    <span class="sidebar-icon">📊</span> Overview
  </a>
  <div class="sidebar-sec">Management</div>
  <a class="sidebar-item <?= a_active('rooms.php',$current) ?>" href="<?= APP_URL ?>/admin/rooms.php">
    <span class="sidebar-icon">🏠</span> Room Management
  </a>
  <a class="sidebar-item <?= a_active('students.php',$current) ?>" href="<?= APP_URL ?>/admin/students.php">
    <span class="sidebar-icon">👥</span> Students
  </a>
  <a class="sidebar-item <?= a_active('applications.php',$current) ?>" href="<?= APP_URL ?>/admin/applications.php">
    <span class="sidebar-icon">📋</span> Applications
  </a>
  <a class="sidebar-item <?= a_active('allocations.php',$current) ?>" href="<?= APP_URL ?>/admin/allocations.php">
    <span class="sidebar-icon">🗺️</span> Allocations
  </a>
  <div class="sidebar-sec">Finance &amp; Support</div>
  <a class="sidebar-item <?= a_active('payments.php',$current) ?>" href="<?= APP_URL ?>/admin/payments.php">
    <span class="sidebar-icon">💳</span> Fee Tracking
  </a>
  <a class="sidebar-item <?= a_active('complaints.php',$current) ?>" href="<?= APP_URL ?>/admin/complaints.php">
    <span class="sidebar-icon">📣</span> Complaints
  </a>
  <a class="sidebar-item <?= a_active('notices.php',$current) ?>" href="<?= APP_URL ?>/admin/notices.php">
    <span class="sidebar-icon">📢</span> Notices
  </a>
  <div class="sidebar-sec">System</div>
  <a class="sidebar-item" href="<?= APP_URL ?>/auth/logout.php"><span class="sidebar-icon">🚪</span> Logout</a>
  <div class="sidebar-user">
    <div class="avatar" style="background:linear-gradient(135deg,#b794f4,#63b3ed);">AD</div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= clean($_SESSION['admin_name'] ?? 'Admin') ?></div>
      <div class="sidebar-user-role"><?= clean($_SESSION['admin_role'] ?? 'admin') ?></div>
    </div>
  </div>
</aside>
