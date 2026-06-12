<?php
// student/pay_online.php
require_once __DIR__ . '/../config/db.php';
require_student();
$sid     = (int)$_SESSION['student_id'];
$student = db_row($conn, "SELECT * FROM students WHERE id=?", 'i', $sid);

// Get payment id from query string
$pay_id = (int)($_GET['pay_id'] ?? 0);
if (!$pay_id) redirect(APP_URL . '/student/payments.php');

$payment = db_row($conn,
    "SELECT p.*, r.room_number, r.block FROM payments p
     JOIN allocations al ON al.id=p.allocation_id
     JOIN rooms r ON r.id=al.room_id
     WHERE p.id=? AND p.student_id=? AND p.status IN ('pending','overdue')", 'ii', $pay_id, $sid);

if (!$payment) {
    flash('error', 'Payment not found or already completed.');
    redirect(APP_URL . '/student/payments.php');
}

// Check for existing initiated transaction
$existing_txn = db_row($conn,
    "SELECT * FROM payment_transactions WHERE payment_id=? AND status IN ('initiated','processing') ORDER BY initiated_at DESC LIMIT 1",
    'i', $pay_id);

$success_txn = null;

// ── PROCESS PAYMENT ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'pay') {
    $gateway = $_POST['gateway'] ?? '';
    $account = clean($_POST['account'] ?? '');   // phone / card number (masked)
    $allowed = ['bkash','nagad','card','bank_transfer'];
    if (!in_array($gateway, $allowed)) {
        flash('error', 'Invalid payment method.');
        redirect(APP_URL . '/student/pay_online.php?pay_id='.$pay_id);
    }

    // Generate unique transaction code
    $txn_code = strtoupper($gateway) . '-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()),0,8));

    // Mask account
    $masked = '';
    if ($gateway === 'card') {
        $digits = preg_replace('/\D/', '', $account);
        $masked = '****-****-****-' . substr($digits, -4);
    } else {
        $masked = substr($account, 0, 3) . '****' . substr($account, -3);
    }

    // Create transaction record
    $txn_id = db_insert($conn,
        "INSERT INTO payment_transactions (payment_id,student_id,txn_code,gateway,gateway_account,amount,status,ip_address)
         VALUES (?,?,?,?,?,?,'processing',?)",
        'iisssds', $pay_id, $sid, $txn_code, $gateway, $masked, $payment['amount'],
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    );

    // Simulate gateway processing — always succeeds in demo
    $gateway_ref = 'GW-' . strtoupper(substr(md5($txn_code),0,10));

    // Mark transaction success
    db_exec($conn,
        "UPDATE payment_transactions SET status='success', completed_at=NOW(), gateway_ref=? WHERE id=?",
        'si', $gateway_ref, $txn_id
    );

    // Mark payment as paid
    db_exec($conn,
        "UPDATE payments SET status='paid', paid_at=NOW(), payment_method=?, transaction_ref=? WHERE id=?",
        'ssi', $gateway, $txn_code, $pay_id
    );

    $success_txn = [
        'txn_code'    => $txn_code,
        'gateway'     => $gateway,
        'gateway_ref' => $gateway_ref,
        'amount'      => $payment['amount'],
        'masked'      => $masked,
        'paid_at'     => date('M d, Y · H:i'),
    ];
}

// Reload payment after processing
$payment = db_row($conn,
    "SELECT p.*, r.room_number, r.block FROM payments p
     JOIN allocations al ON al.id=p.allocation_id
     JOIN rooms r ON r.id=al.room_id
     WHERE p.id=? AND p.student_id=?", 'ii', $pay_id, $sid);

$past_txns = db_query($conn,
    "SELECT * FROM payment_transactions WHERE payment_id=? ORDER BY initiated_at DESC", 'i', $pay_id);

$page_title = 'Pay Online';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.pay-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);padding:1.75rem;transition:all .3s;}
.gw-option{display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;border:1px solid var(--gb);border-radius:var(--r2);cursor:pointer;transition:all .2s;background:rgba(255,255,255,.02);}
.gw-option:hover,.gw-option.selected{border-color:var(--blue);background:rgba(99,179,237,.06);}
.gw-option input[type=radio]{accent-color:var(--blue);width:16px;height:16px;flex-shrink:0;}
.gw-icon{width:42px;height:42px;border-radius:var(--r1);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.success-overlay{background:rgba(104,211,145,.05);border:1px solid rgba(104,211,145,.3);border-radius:var(--r3);padding:2rem;text-align:center;}
.input-group{position:relative;}
.input-prefix{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:.85rem;color:var(--ts);}
.input-with-prefix{padding-left:3rem;}
</style>
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="payments.php">← Payments</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/student_sidebar.php'; ?>
  <main class="dash-main">
    <div class="page-title">Online Payment</div>
    <div class="page-sub">Secure hostel fee payment portal</div>

    <?php if($success_txn || ($payment && $payment['status']==='paid')): ?>
    <!-- SUCCESS STATE -->
    <?php
    $show_txn = $success_txn ?? db_row($conn,"SELECT * FROM payment_transactions WHERE payment_id=? AND status='success' ORDER BY completed_at DESC LIMIT 1",'i',$pay_id);
    ?>
    <div style="max-width:540px;margin:0 auto;">
      <div class="success-overlay">
        <div style="font-size:4rem;margin-bottom:.75rem;animation:pop .5s ease;">✅</div>
        <div style="font-family:var(--fd);font-size:1.6rem;font-weight:800;color:var(--success);margin-bottom:.3rem;">Payment Successful!</div>
        <div style="color:var(--ts);font-size:.9rem;margin-bottom:1.5rem;">Your hostel fee has been received. Thank you!</div>
        <div style="background:rgba(0,0,0,.3);border-radius:var(--r2);padding:1.1rem;text-align:left;display:flex;flex-direction:column;gap:.5rem;font-size:.875rem;margin-bottom:1.5rem;">
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Transaction ID</span><code><?= clean($show_txn['txn_code'] ?? '—') ?></code></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Gateway Ref</span><code><?= clean($show_txn['gateway_ref'] ?? '—') ?></code></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Amount Paid</span><span style="color:var(--success);font-weight:700;font-family:var(--fm);">৳<?= number_format($show_txn['amount'] ?? $payment['amount'],0) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Method</span><span><?= ucfirst(str_replace('_',' ',$show_txn['gateway'] ?? $payment['payment_method'] ?? '—')) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Semester</span><span><?= clean($payment['semester']) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Paid On</span><span><?= date('M d, Y · H:i',strtotime($show_txn['completed_at'] ?? $payment['paid_at'] ?? 'now')) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Room</span><span><?= $payment['room_number'] ?> (Block <?= $payment['block'] ?>)</span></div>
        </div>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
          <a class="btn btn-success" href="payments.php">💳 View All Payments</a>
          <a class="btn btn-secondary" href="dashboard.php">📊 Dashboard</a>
        </div>
        <div style="margin-top:1rem;font-size:.78rem;color:var(--tm);">Keep this transaction ID for your records. Screenshot for a receipt.</div>
      </div>
    </div>
    @keyframes pop{0%{transform:scale(0)}70%{transform:scale(1.2)}100%{transform:scale(1)}}

    <?php else: ?>
    <!-- PAYMENT FORM -->
    <div class="g21" style="max-width:1000px;">
      <div>
        <!-- GATEWAY SELECTION -->
        <div class="pay-card" style="margin-bottom:1.2rem;">
          <div style="font-family:var(--fd);font-size:1rem;font-weight:700;margin-bottom:1.1rem;">Choose Payment Method</div>
          <form method="POST" id="payment-form" style="display:flex;flex-direction:column;gap:.85rem;">
            <input type="hidden" name="form_action" value="pay">

            <!-- bKash -->
            <label class="gw-option" id="gw-bkash">
              <input type="radio" name="gateway" value="bkash" onchange="showGwFields('bkash')" required>
              <div class="gw-icon" style="background:rgba(224,58,99,.15);">🅱️</div>
              <div>
                <div style="font-weight:600;font-size:.9rem;">bKash</div>
                <div style="font-size:.78rem;color:var(--ts);">Mobile banking · Instant</div>
              </div>
              <div style="margin-left:auto;font-size:.78rem;color:var(--success);">✓ Available</div>
            </label>

            <!-- Nagad -->
            <label class="gw-option" id="gw-nagad">
              <input type="radio" name="gateway" value="nagad" onchange="showGwFields('nagad')" required>
              <div class="gw-icon" style="background:rgba(246,174,45,.12);">📱</div>
              <div>
                <div style="font-weight:600;font-size:.9rem;">Nagad</div>
                <div style="font-size:.78rem;color:var(--ts);">Mobile banking · Instant</div>
              </div>
              <div style="margin-left:auto;font-size:.78rem;color:var(--success);">✓ Available</div>
            </label>

            <!-- Card -->
            <label class="gw-option" id="gw-card">
              <input type="radio" name="gateway" value="card" onchange="showGwFields('card')" required>
              <div class="gw-icon" style="background:rgba(99,179,237,.12);">💳</div>
              <div>
                <div style="font-weight:600;font-size:.9rem;">Debit / Credit Card</div>
                <div style="font-size:.78rem;color:var(--ts);">Visa · Mastercard · AMEX</div>
              </div>
              <div style="margin-left:auto;font-size:.78rem;color:var(--success);">✓ Available</div>
            </label>

            <!-- Bank Transfer -->
            <label class="gw-option" id="gw-bank_transfer">
              <input type="radio" name="gateway" value="bank_transfer" onchange="showGwFields('bank_transfer')" required>
              <div class="gw-icon" style="background:rgba(104,211,145,.1);">🏦</div>
              <div>
                <div style="font-weight:600;font-size:.9rem;">Bank Transfer</div>
                <div style="font-size:.78rem;color:var(--ts);">Online bank · 1-2 hours</div>
              </div>
              <div style="margin-left:auto;font-size:.78rem;color:var(--success);">✓ Available</div>
            </label>

            <!-- DYNAMIC ACCOUNT FIELDS -->
            <div id="gw-fields" style="display:none;padding:1rem;background:rgba(255,255,255,.03);border-radius:var(--r2);border:1px solid var(--gb);">
              <!-- Mobile money -->
              <div id="fields-mobile" style="display:none;flex-direction:column;gap:.75rem;">
                <div class="form-group">
                  <label class="form-label">Registered Phone Number</label>
                  <div class="input-group">
                    <span class="input-prefix">+880</span>
                    <input class="form-input input-with-prefix" name="account" id="mobile-number"
                           placeholder="01XXXXXXXXX" pattern="01[3-9][0-9]{8}" maxlength="11">
                  </div>
                </div>
                <div class="info-box" style="font-size:.8rem;">You will receive a confirmation prompt on your <span id="gw-name-label">bKash</span> app / USSD. Enter your PIN to complete.</div>
              </div>
              <!-- Card -->
              <div id="fields-card" style="display:none;flex-direction:column;gap:.75rem;">
                <div class="form-group">
                  <label class="form-label">Card Number</label>
                  <input class="form-input" name="account" placeholder="1234  5678  9012  3456" maxlength="19"
                         oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()">
                </div>
                <div class="g2">
                  <div class="form-group"><label class="form-label">Expiry (MM/YY)</label><input class="form-input" placeholder="MM/YY" maxlength="5"></div>
                  <div class="form-group"><label class="form-label">CVV</label><input class="form-input" type="password" placeholder="•••" maxlength="4"></div>
                </div>
                <div class="form-group"><label class="form-label">Cardholder Name</label><input class="form-input" placeholder="Name as on card"></div>
              </div>
              <!-- Bank -->
              <div id="fields-bank" style="display:none;flex-direction:column;gap:.75rem;">
                <div class="form-group"><label class="form-label">Bank Account Number</label><input class="form-input" name="account" placeholder="Your account number"></div>
                <div class="form-group"><label class="form-label">Bank Name</label><input class="form-input" placeholder="e.g. Dutch-Bangla Bank"></div>
                <div class="info-box" style="font-size:.8rem;">Transfer reference will be used to verify your payment within 1-2 business hours.</div>
              </div>
            </div>

            <!-- PAY BUTTON -->
            <button class="btn btn-primary btn-lg" type="submit" id="pay-btn" style="display:none;width:100%;justify-content:center;"
                    onclick="return confirmPay()">
              🔒 Pay ৳<?= number_format($payment['amount'],0) ?> Securely
            </button>
          </form>
        </div>

        <!-- PAST TRANSACTIONS -->
        <?php if(!empty($past_txns)): ?>
        <div class="table-wrapper">
          <div class="table-header"><div class="table-title">Transaction History</div></div>
          <table class="data-table">
            <thead><tr><th>Txn Code</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($past_txns as $tx): ?>
              <tr>
                <td><code><?= clean($tx['txn_code']) ?></code></td>
                <td><?= ucfirst($tx['gateway']) ?></td>
                <td style="font-family:var(--fm);color:var(--blue);">৳<?= number_format($tx['amount'],0) ?></td>
                <td><span class="badge badge-<?= ['success'=>'approved','failed'=>'rejected','processing'=>'pending','initiated'=>'pending','refunded'=>'pending'][$tx['status']] ?>"><?= ucfirst($tx['status']) ?></span></td>
                <td style="color:var(--ts);font-size:.8rem;"><?= date('M d, Y H:i',strtotime($tx['initiated_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- ORDER SUMMARY SIDEBAR -->
      <div>
        <div class="glass" style="padding:1.25rem;position:sticky;top:80px;">
          <div style="font-family:var(--fd);font-size:.95rem;font-weight:700;margin-bottom:1rem;">Payment Summary</div>
          <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.875rem;margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Student</span><span style="font-weight:600;"><?= clean($student['full_name']) ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Room</span><span><?= $payment['room_number'] ?> (Block <?= $payment['block'] ?>)</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Semester</span><span><?= clean($payment['semester']) ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Due Date</span>
              <span style="color:<?= strtotime($payment['due_date'])<time()?'var(--danger)':'inherit' ?>;">
                <?= date('M d, Y',strtotime($payment['due_date'])) ?>
              </span>
            </div>
          </div>
          <div class="divider"></div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.75rem;padding:.75rem;background:rgba(99,179,237,.06);border-radius:var(--r2);">
            <span style="font-weight:600;">Total Amount</span>
            <span style="font-family:var(--fm);font-size:1.3rem;font-weight:800;color:var(--blue);">৳<?= number_format($payment['amount'],0) ?></span>
          </div>
          <?php if($payment['status']==='overdue'): ?>
          <div class="err-box" style="margin-top:.75rem;font-size:.8rem;">⚠️ This payment is overdue. Please pay immediately to avoid room cancellation.</div>
          <?php endif; ?>
          <div style="margin-top:1rem;font-size:.75rem;color:var(--tm);text-align:center;line-height:1.6;">
            🔒 256-bit SSL encrypted · Your payment is secure
          </div>
          <div class="divider"></div>
          <div style="font-size:.78rem;color:var(--ts);">Can't pay now?</div>
          <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>/student/tasks.php" style="margin-top:.4rem;width:100%;justify-content:center;font-size:.8rem;">
            🔄 Apply for Task Exchange →
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
<script>
function showGwFields(gw) {
  document.getElementById('gw-fields').style.display = 'block';
  ['mobile','card','bank'].forEach(f=>document.getElementById('fields-'+f).style.display='none');
  document.getElementById('pay-btn').style.display = 'flex';
  document.querySelectorAll('.gw-option').forEach(el=>el.classList.remove('selected'));
  document.getElementById('gw-'+gw).classList.add('selected');
  if(gw==='bkash'||gw==='nagad'){
    document.getElementById('fields-mobile').style.display='flex';
    document.getElementById('gw-name-label').textContent = gw==='bkash'?'bKash':'Nagad';
  } else if(gw==='card'){
    document.getElementById('fields-card').style.display='flex';
  } else {
    document.getElementById('fields-bank').style.display='flex';
  }
}
function confirmPay() {
  const amount = '৳<?= number_format($payment['amount'],0) ?>';
  return confirm('Confirm payment of ' + amount + '? You will be charged this amount.');
}
</script>
</body></html>