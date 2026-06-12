<?php
// student/rooms.php  (public — no login required)
require_once __DIR__ . '/../config/db.php';
session_start_safe();

$block  = $_GET['block']  ?? '';
$type   = $_GET['type']   ?? '';
$avail  = $_GET['avail']  ?? '';

$where = ["r.status='available'"];
$params = []; $types = '';

if (in_array($block, ['A','B','C']))                               { $where[] = "r.block=?";     $params[]=$block; $types.='s'; }
if (in_array($type,  ['single','double','triple','quad']))          { $where[] = "r.room_type=?"; $params[]=$type;  $types.='s'; }
if ($avail === '1')                                                 { $where[] = "r.occupied < r.capacity"; }
elseif ($avail === '0')                                             { $where[] = "r.occupied >= r.capacity"; }

$sql = "SELECT r.*, (r.capacity-r.occupied) AS free_seats FROM rooms r WHERE " . implode(' AND ', $where) . " ORDER BY r.block, r.floor, r.room_number";
$rooms = db_query($conn, $sql, $types, ...$params);

// Summary
$stats = db_row($conn, "SELECT SUM(capacity) AS total_seats, SUM(occupied) AS total_occ, SUM(capacity-occupied) AS total_free, COUNT(*) AS total_rooms FROM rooms WHERE status='available'");

$page_title = 'Room Availability';
$is_logged  = !empty($_SESSION['student_id']);
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.room-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);overflow:hidden;transition:all .3s;}
.room-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.4);border-color:rgba(99,179,237,.25);}
.room-img{height:110px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;position:relative;}
.room-img.A{background:linear-gradient(135deg,rgba(99,179,237,.15),rgba(183,148,244,.15));}
.room-img.B{background:linear-gradient(135deg,rgba(246,135,179,.12),rgba(183,148,244,.12));}
.room-img.C{background:linear-gradient(135deg,rgba(118,228,247,.12),rgba(104,211,145,.1));}
.room-body{padding:.95rem 1.05rem 1.05rem;}
.seat-dot{width:10px;height:10px;border-radius:50%;border:1px solid var(--gb);}
.seat-taken{background:rgba(252,129,129,.7);border-color:rgba(252,129,129,.4);}
.seat-open{background:rgba(104,211,145,.7);border-color:rgba(104,211,145,.4);}
</style>

<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="<?= APP_URL ?>">Home</a>
    <a class="nav-link active" href="rooms.php">Rooms</a>
    <?php if($is_logged): ?>
      <a class="nav-link" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
    <?php else: ?>
      <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">Login</a>
      <a class="nav-btn" href="<?= APP_URL ?>/auth/register.php">Register</a>
    <?php endif; ?>
  </div>
</nav>

<div style="max-width:1200px;margin:0 auto;padding:2rem;">
  <div style="margin-bottom:2rem;">
    <h1 style="font-family:var(--fd);font-size:2rem;font-weight:800;margin-bottom:.2rem;">Room Availability</h1>
    <p style="color:var(--ts);">Browse all hostel rooms and check real-time seat availability</p>
  </div>

  <!-- FILTERS -->
  <div class="glass" style="padding:1.2rem;margin-bottom:1.6rem;">
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
      <div class="form-group" style="flex:1;min-width:160px;">
        <label class="form-label">Block</label>
        <select class="form-input" name="block">
          <option value="">All Blocks</option>
          <option value="A" <?= $block==='A'?'selected':'' ?>>Block A (Male)</option>
          <option value="B" <?= $block==='B'?'selected':'' ?>>Block B (Female)</option>
          <option value="C" <?= $block==='C'?'selected':'' ?>>Block C (Mixed)</option>
        </select>
      </div>
      <div class="form-group" style="flex:1;min-width:160px;">
        <label class="form-label">Room Type</label>
        <select class="form-input" name="type">
          <option value="">All Types</option>
          <option value="single" <?= $type==='single'?'selected':'' ?>>Single</option>
          <option value="double" <?= $type==='double'?'selected':'' ?>>Double</option>
          <option value="triple" <?= $type==='triple'?'selected':'' ?>>Triple</option>
          <option value="quad"   <?= $type==='quad'?'selected':'' ?>>Quad</option>
        </select>
      </div>
      <div class="form-group" style="flex:1;min-width:160px;">
        <label class="form-label">Availability</label>
        <select class="form-input" name="avail">
          <option value="">All</option>
          <option value="1" <?= $avail==='1'?'selected':'' ?>>Has Free Seats</option>
          <option value="0" <?= $avail==='0'?'selected':'' ?>>Fully Occupied</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit" style="height:42px;">🔍 Filter</button>
      <a class="btn btn-secondary" href="rooms.php" style="height:42px;">✕ Reset</a>
    </form>
  </div>

  <!-- STATS -->
  <div class="g4" style="margin-bottom:1.6rem;">
    <div class="stat-card blue"><div class="stat-val"><?= $stats['total_rooms'] ?></div><div class="stat-label">Total Rooms</div></div>
    <div class="stat-card cyan"><div class="stat-val"><?= $stats['total_seats'] ?></div><div class="stat-label">Total Seats</div></div>
    <div class="stat-card green"><div class="stat-val"><?= $stats['total_free'] ?></div><div class="stat-label">Available Seats</div></div>
    <div class="stat-card purple"><div class="stat-val"><?= $stats['total_occ'] ?></div><div class="stat-label">Occupied</div></div>
  </div>

  <!-- ROOM GRID -->
  <?php if(empty($rooms)): ?>
    <div class="glass" style="padding:3rem;text-align:center;opacity:.6;">
      <div style="font-size:2.5rem;margin-bottom:.75rem;">🔍</div>
      <div style="font-family:var(--fd);font-size:1rem;font-weight:700;">No rooms found</div>
      <div style="color:var(--ts);margin-top:.3rem;font-size:.875rem;">Try adjusting your filters.</div>
    </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.1rem;">
    <?php foreach($rooms as $r):
      $free   = (int)$r['free_seats'];
      $full   = $free === 0;
      $emojis = ['single'=>'🛏️','double'=>'🛋️','triple'=>'🏠','quad'=>'🏘️'];
      $type_tags=['single'=>'tag-blue','double'=>'tag-purple','triple'=>'tag-cyan','quad'=>'tag-blue'];
      $seats_html=''; for($i=0;$i<$r['capacity'];$i++) $seats_html.='<div class="seat-dot '.($i<$r['occupied']?'seat-taken':'seat-open').'"></div>';
    ?>
    <div class="room-card">
      <div class="room-img <?= $r['block'] ?>">
        <span><?= $emojis[$r['room_type']] ?? '🏠' ?></span>
        <span class="badge <?= $full?'badge-full':'badge-available' ?>" style="position:absolute;top:.7rem;right:.7rem;"><?= $full?'Full':"{$free} free" ?></span>
      </div>
      <div class="room-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.2rem;">
          <div style="font-family:var(--fd);font-size:1rem;font-weight:700;"><?= $r['room_number'] ?></div>
          <span class="tag <?= $type_tags[$r['room_type']]??'tag-blue' ?>"><?= ucfirst($r['room_type']) ?></span>
        </div>
        <div style="font-size:.78rem;color:var(--ts);margin-bottom:.5rem;">Block <?= $r['block'] ?> · Floor <?= $r['floor'] ?></div>
        <div style="display:flex;gap:.3rem;margin-bottom:.4rem;"><?= $seats_html ?></div>
        <div style="font-size:.78rem;color:var(--ts);margin-bottom:.6rem;"><?= $r['occupied'] ?>/<?= $r['capacity'] ?> seats occupied</div>
        <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:.75rem;">
          <?php if($r['has_wifi']): ?><span class="tag tag-blue" style="font-size:.7rem;">📶 WiFi</span><?php endif; ?>
          <?php if($r['has_ac']): ?><span class="tag tag-purple" style="font-size:.7rem;">❄️ AC</span><?php endif; ?>
          <?php if($r['has_attached_bath']): ?><span class="tag tag-cyan" style="font-size:.7rem;">🚿 Bath</span><?php endif; ?>
          <?php if($r['has_study_desk']): ?><span class="tag tag-blue" style="font-size:.7rem;">📚 Desk</span><?php endif; ?>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:.65rem;border-top:1px solid var(--gb);">
          <span style="font-family:var(--fm);font-size:.85rem;color:var(--green);font-weight:700;">৳<?= number_format($r['fee_per_sem'],0) ?>/sem</span>
          <?php if(!$full): ?>
            <a class="btn btn-primary btn-sm" href="<?= $is_logged?'apply.php':APP_URL.'/auth/login.php' ?>">Apply</a>
          <?php else: ?>
            <span style="font-size:.75rem;color:var(--tm);">No seats</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</body></html>
