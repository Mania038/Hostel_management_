<?php
// admin/payments.php
require_once __DIR__ . '/../config/db.php';
require_admin();
$admin_id = (int)$_SESSION['admin_id'];

// MARK PAID
if (isset($_GET['action']) && $_GET['action']==='mark_paid' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $ref = clean($_GET['ref'] ?? 'CASH-'.date('Ymd').'-'.str_pad($id,4,'0',STR_PAD_LEFT));
    db_exec($conn,
        "UPDATE payments SET status='paid', paid_at=NOW(), transaction_ref=?, payment_method='cash' WHERE id=? AND status IN ('pending','overdue')",
        'si', $ref, $id
    );
    flash('success','Payment marked as paid.');
    redirect(APP_URL.'/admin/payments.php');
}

// ADD PAYMENT MANUALLY
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action']??'')==='add_payment') {
    $alloc_id = (int)$_POST['allocation_id'];
    $semester = clean($_POST['semester'] ?? '');
    $amount   = (float)$_POST['amount'];
    $method   = $_POST['method'] ?? 'cash';
    $ref      = clean($_POST['ref'] ?? '');
    $due      = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $al       = db_row($conn,"SELECT student_id FROM allocations WHERE id=?",'i',$alloc_id);
    if ($al && $semester && $amount > 0) {
        $stmt = $conn->prepare(
            "INSERT INTO payments
             (allocation_id, student_id, semester, amount, payment_method, transaction_ref, status, due_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)"
        );
        $stmt->bind_param('iisdssi',
            $alloc_id, $al['student_id'], $semester, $amount,
            $method, $ref, $due, $admin_id
        );
        $stmt->execute();
        $stmt->close();
        flash('success', 'Payment record created.');
    } else { flash('error', 'Invalid data — check allocation, semester and amount.'); }
    redirect(APP_URL.'/admin/payments.php');
}

// Filters
$fstat = $_GET['status'] ?? '';
$fsem  = $_GET['semester'] ?? '';
$where = ['1=1']; $ptypes=''; $pvals=[];
if (in_array($fstat,['pending','paid','overdue','waived'])) { $where[]="p.status=?"; $ptypes.='s'; $pvals[]=$fstat; }
if ($fsem) { $where[]="p.semester=?"; $ptypes.='s'; $pvals[]=$fsem; }

$payments = db_query($conn,
    "SELECT p.*, s.full_name, s.student_id AS sid, r.room_number, r.block
     FROM payments p
     JOIN students s ON s.id=p.student_id
     JOIN allocations al ON al.id=p.allocation_id
     JOIN rooms r ON r.id=al.room_id
     WHERE ".implode(' AND ',$where)."
     ORDER BY p.due_date ASC, p.created_at DESC",
    $ptypes, ...$pvals);

// Fee summary totals
$summary = db_row($conn,
    "SELECT COALESCE(SUM(amount),0) AS total,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid,
            COALESCE(SUM(CASE WHEN status IN ('pending','overdue') THEN amount ELSE 0 END),0) AS due,
            COUNT(*) AS cnt,
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS cnt_paid,
            SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END) AS cnt_overdue
     FROM payments");

$semesters = db_query($conn,"SELECT DISTINCT semester FROM payments ORDER BY semester DESC");
$allocs    = db_query($conn,"SELECT al.id, s.full_name, r.room_number, al.semester FROM allocations al JOIN students s ON s.id=al.student_id JOIN rooms r ON r.id=al.room_id WHERE al.status='active' ORDER BY s.full_name");

$page_title = 'Fee Tracking';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.8);backdrop-filter:blur(8px);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:460px;animation:mIn .3s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.7rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.2rem;}
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
      <div class="page-title" style="margin:0;">Fee Tracking</div>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add').classList.add('open')">+ Add Record</button>
    </div>
    <div class="page-sub">Manage and track all hostel fee payments</div>

    <!-- STATS -->
    <div class="g4" style="margin-bottom:1.3rem;">
      <div class="stat-card blue"><div class="stat-val">৳<?= number_format($summary['total'],0) ?></div><div class="stat-label">Total Expected</div></div>
      <div class="stat-card green"><div class="stat-val">৳<?= number_format($summary['paid'],0) ?></div><div class="stat-label">Collected (<?= $summary['cnt_paid'] ?>)</div></div>
      <div class="stat-card pink"><div class="stat-val">৳<?= number_format($summary['due'],0) ?></div><div class="stat-label">Outstanding</div></div>
      <div class="stat-card cyan">
        <div class="stat-val"><?= $summary['total']>0 ? round($summary['paid']/$summary['total']*100) : 0 ?>%</div>
        <div class="stat-label">Collection Rate</div>
        <div class="progress-bar" style="margin-top:.5rem;">
          <div class="progress-fill" style="width:<?= $summary['total']>0?round($summary['paid']/$summary['total']*100):0 ?>%;"></div>
        </div>
      </div>
    </div>

    <!-- FILTERS -->
    <div class="glass" style="padding:1rem;margin-bottom:1.2rem;">
      <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:140px;">
          <label class="form-label">Status</label>
          <select class="form-input" name="status">
            <option value="">All</option>
            <option value="pending"  <?= $fstat==='pending'?'selected':''  ?>>Pending</option>
            <option value="paid"     <?= $fstat==='paid'?'selected':''     ?>>Paid</option>
            <option value="overdue"  <?= $fstat==='overdue'?'selected':''  ?>>Overdue</option>
            <option value="waived"   <?= $fstat==='waived'?'selected':''   ?>>Waived</option>
          </select>
        </div>
        <div class="form-group" style="flex:1;min-width:160px;">
          <label class="form-label">Semester</label>
          <select class="form-input" name="semester">
            <option value="">All Semesters</option>
            <?php foreach($semesters as $sm): ?>
              <option value="<?= $sm['semester'] ?>" <?= $fsem===$sm['semester']?'selected':'' ?>><?= $sm['semester'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" style="height:42px;">Filter</button>
        <a class="btn btn-secondary btn-sm" href="payments.php" style="height:42px;">Reset</a>
      </form>
    </div>

    <!-- OVERDUE ALERT -->
    <?php if($summary['cnt_overdue']>0): ?>
    <div class="err-box" style="margin-bottom:1.2rem;">⚠️ <?= $summary['cnt_overdue'] ?> overdue payment<?= $summary['cnt_overdue']>1?'s':'' ?> require immediate attention.</div>
    <?php endif; ?>

    <!-- TABLE -->
    <div class="table-wrapper">
      <div class="table-header"><div class="table-title">Payment Records (<?= count($payments) ?>)</div></div>
      <?php if(empty($payments)): ?>
        <div style="padding:2.5rem;text-align:center;opacity:.55;"><div style="font-size:2rem;">💳</div><div style="margin-top:.5rem;font-size:.875rem;color:var(--ts);">No payment records found.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Student</th><th>ID</th><th>Room</th><th>Semester</th><th>Amount</th><th>Method</th><th>Due Date</th><th>Paid On</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($payments as $p): ?>
          <tr>
            <td style="font-size:.875rem;font-weight:600;"><?= clean($p['full_name']) ?></td>
            <td><code><?= clean($p['sid']) ?></code></td>
            <td><?= $p['room_number'] ?> (B<?= $p['block'] ?>)</td>
            <td style="font-size:.82rem;"><?= clean($p['semester']) ?></td>
            <td style="font-family:var(--fm);color:var(--blue);">৳<?= number_format($p['amount'],0) ?></td>
            <td style="font-size:.82rem;"><?= ucfirst(str_replace('_',' ',$p['payment_method']??'—')) ?></td>
            <td style="color:<?= (strtotime($p['due_date'])<time()&&$p['status']!=='paid')?'var(--danger)':'var(--ts)' ?>;font-size:.8rem;"><?= date('M d, Y',strtotime($p['due_date'])) ?></td>
            <td style="color:var(--ts);font-size:.8rem;"><?= $p['paid_at']?date('M d, Y',strtotime($p['paid_at'])):'—' ?></td>
            <td><span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
            <td>
              <?php if(in_array($p['status'],['pending','overdue'])): ?>
                <a class="btn btn-success btn-sm"
                   href="payments.php?action=mark_paid&id=<?= $p['id'] ?>"
                   onclick="return confirm('Mark payment as paid?')">✓ Mark Paid</a>
              <?php elseif($p['status']==='paid'): ?>
                <span style="color:var(--success);font-size:.8rem;">✅ Paid</span>
              <?php endif; ?>
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

<!-- ADD PAYMENT MODAL -->
<div class="modal-bg" id="modal-add">
  <div class="modal-box">
    <div class="modal-title">💳 Add Payment Record</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="add_payment">
      <div class="form-group"><label class="form-label">Allocation (Student – Room) *</label>
        <select class="form-input" name="allocation_id" required>
          <option value="">Select allocation</option>
          <?php foreach($allocs as $al): ?>
            <option value="<?= $al['id'] ?>"><?= clean($al['full_name']) ?> — <?= $al['room_number'] ?> (<?= $al['semester'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Semester *</label>
        <select class="form-input" name="semester" required>
          <option value="Spring-<?= date('Y') ?>">Spring <?= date('Y') ?></option>
          <option value="Fall-<?= date('Y') ?>">Fall <?= date('Y') ?></option>
          <option value="Spring-<?= date('Y')+1 ?>">Spring <?= date('Y')+1 ?></option>
        </select>
      </div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Amount (৳) *</label><input class="form-input" type="number" name="amount" min="100" step="100" placeholder="6000" required></div>
        <div class="form-group"><label class="form-label">Due Date *</label><input class="form-input" type="date" name="due_date" value="<?= date('Y-m-d',strtotime('+30 days')) ?>" required></div>
      </div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Payment Method</label>
          <select class="form-input" name="method">
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="mobile_banking">Mobile Banking</option>
            <option value="card">Card</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Transaction Ref</label><input class="form-input" name="ref" placeholder="Optional ref no."></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Record</button>
      </div>
    </form>
  </div>
</div>
<script>document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));</script>
</body></html>
