<?php
// admin/dashboard.php
require_once __DIR__ . '/../config/db.php';
require_admin();

// Dashboard stats via view
$stats = db_row($conn, "SELECT * FROM v_dashboard_stats");

// Recent applications
$recent_apps = db_query($conn,
    "SELECT a.*, s.full_name, s.student_id AS sid, s.department
     FROM applications a JOIN students s ON s.id=a.student_id
     ORDER BY a.created_at DESC LIMIT 8");

// Open complaints
$open_comps = db_query($conn,
    "SELECT c.*, s.full_name, r.room_number FROM complaints c
     JOIN students s ON s.id=c.student_id
     LEFT JOIN rooms r ON r.id=c.room_id
     WHERE c.status IN ('open','in_progress')
     ORDER BY c.created_at DESC LIMIT 5");

// Block occupancy
$block_occ = db_query($conn,
    "SELECT block, SUM(capacity) AS cap, SUM(occupied) AS occ,
            ROUND(SUM(occupied)/SUM(capacity)*100,1) AS pct
     FROM rooms WHERE status='available' GROUP BY block ORDER BY block");

// Fee summary
$fee_stats = db_row($conn,
    "SELECT COALESCE(SUM(amount),0) AS expected,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS collected,
            COALESCE(SUM(CASE WHEN status IN ('pending','overdue') THEN amount ELSE 0 END),0) AS outstanding
     FROM payments");

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
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
    <div class="page-title">Admin Dashboard</div>
    <div class="page-sub">System overview — <?= date('F Y') ?></div>

    <!-- STAT CARDS -->
    <div class="g4" style="margin-bottom:1.4rem;">
      <div class="stat-card blue">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">🏠</div>
        <div class="stat-val"><?= $stats['total_rooms'] ?></div>
        <div class="stat-label">Total Rooms</div>
      </div>
      <div class="stat-card purple">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">👥</div>
        <div class="stat-val"><?= $stats['housed_students'] ?></div>
        <div class="stat-label">Students Housed</div>
      </div>
      <div class="stat-card cyan">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">📋</div>
        <div class="stat-val"><?= $stats['pending_apps'] ?></div>
        <div class="stat-label">Pending Applications</div>
      </div>
      <div class="stat-card green">
        <div style="font-size:1.4rem;margin-bottom:.3rem;">💰</div>
        <div class="stat-val">৳<?= number_format($stats['fees_collected']/1000,0) ?>K</div>
        <div class="stat-label">Fees Collected</div>
      </div>
    </div>

    <div class="g2" style="margin-bottom:1.2rem;">
      <!-- RECENT APPLICATIONS -->
      <div class="table-wrapper">
        <div class="table-header">
          <div class="table-title">Recent Applications</div>
          <a class="btn btn-secondary btn-sm" href="applications.php">View All →</a>
        </div>
        <table class="data-table">
          <thead><tr><th>Student</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach(array_slice($recent_apps,0,5) as $a): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:.5rem;">
                  <div class="avatar" style="width:28px;height:28px;font-size:.65rem;"><?= strtoupper(substr($a['full_name'],0,1).substr(explode(' ',$a['full_name'])[1]??'',0,1)) ?></div>
                  <div>
                    <div style="font-size:.85rem;font-weight:600;"><?= clean($a['full_name']) ?></div>
                    <div style="font-size:.73rem;color:var(--ts);"><?= clean($a['sid']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="tag tag-blue"><?= ucfirst($a['preferred_type']) ?></span></td>
              <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
              <td>
                <?php if($a['status']==='pending'): ?>
                <div style="display:flex;gap:4px;">
                  <a class="btn btn-success btn-sm" href="applications.php?action=approve&id=<?= $a['id'] ?>">✓</a>
                  <a class="btn btn-danger btn-sm"  href="applications.php?action=reject&id=<?= $a['id'] ?>">✗</a>
                </div>
                <?php elseif($a['status']==='approved'): ?>
                  <a class="btn btn-secondary btn-sm" href="allocations.php?app_id=<?= $a['id'] ?>">Allocate</a>
                <?php else: ?>
                  <span style="font-size:.75rem;color:var(--tm);"><?= ucfirst($a['status']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- BLOCK OCCUPANCY -->
      <div class="glass" style="padding:1.2rem;">
        <div class="table-title" style="margin-bottom:.9rem;">Block Occupancy</div>
        <?php foreach($block_occ as $b): ?>
        <div style="margin-bottom:1rem;">
          <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.35rem;">
            <span>Block <?= $b['block'] ?></span>
            <span style="color:var(--blue);font-family:var(--fm);"><?= $b['pct'] ?>%</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $b['pct'] ?>%;"></div></div>
          <div style="font-size:.73rem;color:var(--ts);margin-top:.2rem;"><?= $b['occ'] ?>/<?= $b['cap'] ?> seats</div>
        </div>
        <?php endforeach; ?>

        <div class="divider"></div>
        <div class="table-title" style="margin-bottom:.75rem;">Fee Collection</div>
        <div style="display:flex;flex-direction:column;gap:.4rem;font-size:.85rem;">
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Collected</span><span style="color:var(--success);font-family:var(--fm);">৳<?= number_format($fee_stats['collected'],0) ?></span></div>
          <div style="display:flex;justify-content:space-between;"><span style="color:var(--ts);">Outstanding</span><span style="color:var(--danger);font-family:var(--fm);">৳<?= number_format($fee_stats['outstanding'],0) ?></span></div>
        </div>
        <div class="progress-bar" style="margin-top:.75rem;">
          <div class="progress-fill" style="width:<?= $fee_stats['expected']>0?round($fee_stats['collected']/$fee_stats['expected']*100):0 ?>%;"></div>
        </div>
        <a class="btn btn-secondary btn-sm" href="payments.php" style="margin-top:.75rem;width:100%;justify-content:center;">Full Fee Report →</a>
      </div>
    </div>

    <!-- OPEN COMPLAINTS -->
    <?php if(!empty($open_comps)): ?>
    <div class="table-wrapper">
      <div class="table-header">
        <div class="table-title">Open Complaints (<?= count($open_comps) ?>)</div>
        <a class="btn btn-secondary btn-sm" href="complaints.php">Manage All →</a>
      </div>
      <table class="data-table">
        <thead><tr><th>Complaint</th><th>Student</th><th>Room</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($open_comps as $c): $icons=['maintenance'=>'🔧','plumbing'=>'💧','electricity'=>'💡','internet'=>'📶','cleanliness'=>'🧹','security'=>'🔒','other'=>'📌']; ?>
          <tr>
            <td><?= ($icons[$c['category']]??'📌') ?> <?= clean(substr($c['subject'],0,30)) ?></td>
            <td><?= clean($c['full_name']) ?></td>
            <td><?= $c['room_number'] ?? 'N/A' ?></td>
            <td><span class="badge badge-<?= in_array($c['priority'],['urgent','high'])?'rejected':($c['priority']==='low'?'available':'pending') ?>"><?= ucfirst($c['priority']) ?></span></td>
            <td><span class="badge badge-<?= str_replace('_','',$c['status']) ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
            <td><a class="btn btn-success btn-sm" href="complaints.php?action=resolve&id=<?= $c['id'] ?>">✓ Resolve</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>
</div>
</body></html>
