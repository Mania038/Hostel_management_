<?php
// student/payments.php
require_once __DIR__ . '/../config/db.php';
require_student();

$sid     = (int)$_SESSION['student_id'];
$student = db_row($conn,"SELECT * FROM students WHERE id=?",'i',$sid);
$payments = db_query($conn,
    "SELECT p.*, r.room_number, r.block FROM payments p
     JOIN allocations al ON al.id=p.allocation_id
     JOIN rooms r ON r.id=al.room_id
     WHERE p.student_id=? ORDER BY p.created_at DESC", 'i', $sid);

$total_paid = 0; $total_due = 0;
foreach($payments as $p){
    if($p['status']==='paid') $total_paid += $p['amount'];
    else $total_due += $p['amount'];
}

$page_title = 'Payments';
require_once __DIR__ . '/../includes/header.php';
?>
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links"><a class="nav-link" href="dashboard.php">← Dashboard</a><a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a></div>
</nav>
<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/student_sidebar.php'; ?>
  <main class="dash-main">
    <div class="page-title">Payments</div>
    <div class="page-sub">Your hostel fee history and outstanding dues</div>
    <div class="g3" style="margin-bottom:1.4rem;">
      <div class="stat-card blue"><div class="stat-val" style="font-size:1.5rem;">৳<?= number_format($total_paid,0) ?></div><div class="stat-label">Total Paid</div></div>
      <div class="stat-card pink"><div class="stat-val" style="font-size:1.5rem;">৳<?= number_format($total_due,0) ?></div><div class="stat-label">Amount Due</div></div>
      <div class="stat-card green"><div class="stat-val" style="font-size:1.5rem;"><?= count($payments) ?></div><div class="stat-label">Total Records</div></div>
    </div>
    <div class="table-wrapper">
     <div class="table-header">
        <div class="table-title">Payment History</div>
        <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/student/tasks.php">🔄 Task Exchange</a>
      </div>
      <?php if(empty($payments)): ?>
        <div style="padding:3rem 1rem;text-align:center;opacity:.5;">
          <div style="font-size:2.5rem;margin-bottom:.5rem;">💳</div>
          <div style="font-size:.875rem;color:var(--ts);">No payment records yet. Payments appear after room allocation.</div>
          <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/student/tasks.php" style="margin-top:1rem;">🔄 Try Task Exchange</a>
        </div>
      <?php else: ?>
      <table class="data-table">
       <thead><tr><th>Semester</th><th>Room</th><th>Amount</th><th>Method</th><th>Due Date</th><th>Paid On</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($payments as $p): ?>
           <tr>
            <td><?= clean($p['semester']) ?></td>
            <td><?= $p['room_number'] ?> (Block <?= $p['block'] ?>)</td>
            <td style="font-family:var(--fm);color:var(--blue);">৳<?= number_format($p['amount'],0) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$p['payment_method']??'cash')) ?></td>
            <td style="color:var(--ts);"><?= date('M d, Y',strtotime($p['due_date'])) ?></td>
            <td style="color:var(--ts);"><?= $p['paid_at'] ? date('M d, Y',strtotime($p['paid_at'])) : '—' ?></td>
            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
          </tr>
         <tr>
            <td><?= clean($p['semester']) ?></td>
            <td><?= $p['room_number'] ?> (Block <?= $p['block'] ?>)</td>
            <td style="font-family:var(--fm);color:var(--blue);">৳<?= number_format($p['amount'],0) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$p['payment_method']??'cash')) ?></td>
            <td style="color:var(--ts);"><?= date('M d, Y',strtotime($p['due_date'])) ?></td>
            <td style="color:var(--ts);"><?= $p['paid_at'] ? date('M d, Y',strtotime($p['paid_at'])) : '—' ?></td>
            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
            <td>
              <?php if(in_array($p['status'],['pending','overdue'])): ?>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                  <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/student/pay_online.php?pay_id=<?= $p['id'] ?>">💳 Pay Online</a>
                  <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/student/tasks.php" title="Earn credit by completing tasks">🔄 Task</a>
                </div>
              <?php elseif($p['status']==='paid'): ?>
                <span style="color:var(--success);font-size:.8rem;">✅ Paid</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php if($total_due > 0): ?>
    <div class="warn-box" style="margin-top:1rem;">
      ⚠️ You have <strong>৳<?= number_format($total_due,0) ?></strong> outstanding. Please visit the hostel office or contact admin to arrange payment.
    </div>
    <?php endif; ?>
  </main>
</div>
</body></html>
