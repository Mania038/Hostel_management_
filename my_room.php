<?php
// student/my_room.php
require_once __DIR__ . '/../config/db.php';
require_student();
$sid     = (int)$_SESSION['student_id'];
$student = db_row($conn, "SELECT * FROM students WHERE id=?", 'i', $sid);

// Fetch active allocation with full room details
$alloc = db_row($conn,
    "SELECT al.*, r.room_number, r.block, r.floor, r.room_type, r.capacity,
            r.fee_per_sem, r.has_ac, r.has_wifi, r.has_attached_bath, r.has_study_desk,
            r.description, adm.full_name AS allocated_by_name
     FROM allocations al
     JOIN rooms r ON r.id = al.room_id
     LEFT JOIN admins adm ON adm.id = al.allocated_by
     WHERE al.student_id=? AND al.status='active'
     LIMIT 1", 'i', $sid);

// Roommates (other active allocations in same room)
$roommates = [];
if ($alloc) {
    $roommates = db_query($conn,
        "SELECT s.full_name, s.student_id AS sid, s.department, s.year_of_study
         FROM allocations al
         JOIN students s ON s.id = al.student_id
         WHERE al.room_id = (SELECT room_id FROM allocations WHERE student_id=? AND status='active' LIMIT 1)
           AND al.student_id != ?
           AND al.status = 'active'",
        'ii', $sid, $sid);
}

// Latest pending payment
$payment = db_row($conn,
    "SELECT * FROM payments WHERE student_id=? ORDER BY due_date ASC LIMIT 1", 'i', $sid);

$page_title = 'My Room';
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
    <div class="page-title">My Room</div>
    <div class="page-sub">Your hostel room assignment and details</div>

    <?php if (!$alloc): ?>
      <!-- No room assigned yet -->
      <div class="glass" style="padding:3rem;text-align:center;max-width:520px;margin:0 auto;">
        <div style="font-size:3.5rem;margin-bottom:1rem;">🏠</div>
        <div style="font-family:var(--fd);font-size:1.2rem;font-weight:700;margin-bottom:.5rem;">No Room Assigned Yet</div>
        <div style="color:var(--ts);font-size:.9rem;line-height:1.65;margin-bottom:1.5rem;">
          Once an admin approves your application and allocates a room, all details will appear here.
        </div>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
          <a class="btn btn-primary" href="apply.php">📋 Apply for Room</a>
          <a class="btn btn-secondary" href="application.php">📄 Check Application</a>
        </div>
      </div>
    <?php else:
      $room_emojis = ['single'=>'🛏️','double'=>'🛋️','triple'=>'🏠','quad'=>'🏘️'];
      $blk_colors  = ['A'=>'rgba(99,179,237,.15)','B'=>'rgba(246,135,179,.12)','C'=>'rgba(118,228,247,.12)'];
      $blk_label   = ['A'=>'Male','B'=>'Female','C'=>'Mixed'];
    ?>

    <div class="g21">
      <!-- MAIN ROOM CARD -->
      <div>
        <div class="glass" style="overflow:hidden;margin-bottom:1.2rem;">
          <!-- Room visual header -->
          <div style="height:160px;background:<?= $blk_colors[$alloc['block']] ?? 'var(--card)' ?>;display:flex;align-items:center;justify-content:center;font-size:4rem;position:relative;">
            <?= $room_emojis[$alloc['room_type']] ?? '🏠' ?>
            <span class="badge badge-active" style="position:absolute;top:1rem;right:1rem;font-size:.75rem;">Active Allocation</span>
          </div>
          <!-- Details -->
          <div style="padding:1.5rem;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
              <div>
                <div style="font-family:var(--fd);font-size:1.6rem;font-weight:800;">Room <?= clean($alloc['room_number']) ?></div>
                <div style="color:var(--ts);font-size:.875rem;margin-top:.15rem;">
                  Block <?= $alloc['block'] ?> (<?= $blk_label[$alloc['block']] ?>) · Floor <?= $alloc['floor'] ?> · <?= ucfirst($alloc['room_type']) ?>
                </div>
              </div>
              <div style="font-family:var(--fm);font-size:1.1rem;color:var(--green);font-weight:700;">৳<?= number_format($alloc['fee_per_sem'],0) ?>/sem</div>
            </div>
            <!-- Amenities -->
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.1rem;">
              <?php if($alloc['has_wifi']): ?><span class="tag tag-blue">📶 WiFi</span><?php endif; ?>
              <?php if($alloc['has_ac']): ?><span class="tag tag-purple">❄️ Air Conditioning</span><?php endif; ?>
              <?php if($alloc['has_attached_bath']): ?><span class="tag tag-cyan">🚿 Attached Bathroom</span><?php endif; ?>
              <?php if($alloc['has_study_desk']): ?><span class="tag tag-blue">📚 Study Desk</span><?php endif; ?>
            </div>
            <!-- Meta info -->
            <div class="divider"></div>
            <div class="g2" style="gap:.6rem 1.5rem;margin-top:.75rem;font-size:.875rem;">
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Semester</span><span style="font-weight:600;"><?= clean($alloc['semester']) ?></span></div>
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Start Date</span><span><?= date('M d, Y',strtotime($alloc['start_date'])) ?></span></div>
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Room Capacity</span><span><?= $alloc['capacity'] ?> beds</span></div>
              <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Allocated By</span><span><?= clean($alloc['allocated_by_name'] ?? 'Admin') ?></span></div>
            </div>
          </div>
        </div>

        <!-- ROOMMATES -->
        <?php if (!empty($roommates)): ?>
        <div class="table-wrapper">
          <div class="table-header"><div class="table-title">Roommates (<?= count($roommates) ?>)</div></div>
          <table class="data-table">
            <thead><tr><th>Name</th><th>Student ID</th><th>Department</th><th>Year</th></tr></thead>
            <tbody>
              <?php foreach($roommates as $rm): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:.5rem;">
                    <div class="avatar" style="width:28px;height:28px;font-size:.65rem;"><?= strtoupper(substr($rm['full_name'],0,1).substr(explode(' ',$rm['full_name'])[1]??'',0,1)) ?></div>
                    <?= clean($rm['full_name']) ?>
                  </div>
                </td>
                <td><code><?= clean($rm['sid']) ?></code></td>
                <td><?= clean($rm['department']) ?></td>
                <td><?= $rm['year_of_study'] ?>th Year</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php elseif ($alloc['room_type'] !== 'single'): ?>
          <div class="info-box">No roommates yet — room has available beds.</div>
        <?php endif; ?>
      </div>

      <!-- SIDEBAR WIDGETS -->
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <!-- Payment status -->
        <?php if ($payment): ?>
        <div class="glass" style="padding:1.1rem;">
          <div class="table-title" style="margin-bottom:.75rem;">💳 Fee Status</div>
          <div style="display:flex;flex-direction:column;gap:.4rem;font-size:.875rem;">
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Semester</span><span><?= clean($payment['semester']) ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Amount</span><span style="font-family:var(--fm);color:var(--blue);">৳<?= number_format($payment['amount'],0) ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Due Date</span><span><?= date('M d, Y',strtotime($payment['due_date'])) ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Status</span><span class="badge badge-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></span></div>
          </div>
          <a class="btn btn-secondary btn-sm" href="payments.php" style="margin-top:.85rem;width:100%;justify-content:center;">View All Payments</a>
        </div>
        <?php endif; ?>

        <!-- Quick actions -->
        <div class="glass" style="padding:1.1rem;">
          <div class="table-title" style="margin-bottom:.85rem;">Quick Actions</div>
          <div style="display:flex;flex-direction:column;gap:.5rem;">
            <a class="btn btn-secondary btn-sm" href="complaints.php" style="justify-content:center;">📣 Report an Issue</a>
            <a class="btn btn-secondary btn-sm" href="payments.php" style="justify-content:center;">💳 View Payments</a>
            <a class="btn btn-secondary btn-sm" href="dashboard.php" style="justify-content:center;">📊 Dashboard</a>
          </div>
        </div>

        <!-- Room rules reminder -->
        <div class="warn-box">
          <strong>📋 Hostel Rules</strong><br>
          Quiet hours: 10 PM – 7 AM · No guests after 8 PM · Keep common areas clean · Report issues via the complaint system.
        </div>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
</body></html>
