<?php
// admin/rooms.php — Full CRUD for rooms
require_once __DIR__ . '/../config/db.php';
require_admin();

$action = $_GET['action'] ?? '';
$msg    = '';

// ── DELETE ────────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $chk = db_row($conn, "SELECT occupied FROM rooms WHERE id=?", 'i', $id);
    if ($chk && $chk['occupied'] > 0) {
        flash('error', 'Cannot delete a room with students currently assigned.');
    } else {
        db_exec($conn, "DELETE FROM rooms WHERE id=?", 'i', $id);
        flash('success', 'Room deleted successfully.');
    }
    redirect(APP_URL . '/admin/rooms.php');
}

// ── ADD ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'add') {
    $rn    = clean($_POST['room_number'] ?? '');
    $blk   = $_POST['block']     ?? '';
    $fl    = (int)($_POST['floor'] ?? 1);
    $rtype = $_POST['room_type'] ?? '';
    $fee   = (float)($_POST['fee'] ?? 0);
    $desc  = clean($_POST['description'] ?? '');
    $ac    = isset($_POST['has_ac'])   ? 1 : 0;
    $bath  = isset($_POST['has_bath']) ? 1 : 0;
    $wifi  = isset($_POST['has_wifi']) ? 1 : 0;
    $desk  = isset($_POST['has_desk']) ? 1 : 0;
    $cap_map = ['single'=>1,'double'=>2,'triple'=>3,'quad'=>4];
    $cap   = $cap_map[$rtype] ?? 1;
    $bg    = ['A'=>'male','B'=>'female','C'=>'mixed'];
    $bg_v  = $bg[$blk] ?? 'male';

    if (!$rn || !in_array($blk,['A','B','C']) || !in_array($rtype,array_keys($cap_map)) || $fee <= 0) {
        flash('error', 'Please fill all required fields correctly.');
    } else {
        $dup = db_row($conn,"SELECT id FROM rooms WHERE room_number=?",'s',$rn);
        if ($dup) { flash('error', "Room number {$rn} already exists."); }
        else {
            db_insert($conn,
                "INSERT INTO rooms (room_number,block,block_gender,floor,room_type,capacity,fee_per_sem,has_ac,has_attached_bath,has_wifi,has_study_desk,description)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                'sssisidiiis', $rn, $blk, $bg_v, $fl, $rtype, $cap, $fee, $ac, $bath, $wifi, $desk, $desc
            );
            flash('success', "Room {$rn} added successfully.");
        }
    }
    redirect(APP_URL . '/admin/rooms.php');
}

// ── EDIT / UPDATE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'edit') {
    $id    = (int)$_POST['room_id'];
    $fee   = (float)$_POST['fee'];
    $desc  = clean($_POST['description'] ?? '');
    $status= $_POST['status'] ?? 'available';
    $ac    = isset($_POST['has_ac'])   ? 1 : 0;
    $bath  = isset($_POST['has_bath']) ? 1 : 0;
    $wifi  = isset($_POST['has_wifi']) ? 1 : 0;
    $desk  = isset($_POST['has_desk']) ? 1 : 0;
    $allowed_status = ['available','maintenance','closed'];
    if (!in_array($status, $allowed_status)) $status = 'available';
    db_exec($conn,
        "UPDATE rooms SET fee_per_sem=?,has_ac=?,has_attached_bath=?,has_wifi=?,has_study_desk=?,description=?,status=? WHERE id=?",
        'diiiissi', $fee, $ac, $bath, $wifi, $desk, $desc, $status, $id
    );
    flash('success', 'Room updated successfully.');
    redirect(APP_URL . '/admin/rooms.php');
}

// Fetch edit room if needed
$edit_room = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_room = db_row($conn, "SELECT * FROM rooms WHERE id=?", 'i', (int)$_GET['id']);
}

// Filters
$fblk  = $_GET['block']   ?? '';
$ftype = $_GET['type']    ?? '';
$fstat = $_GET['status']  ?? '';
$where = ['1=1']; $ptypes=''; $pvals=[];
if (in_array($fblk,  ['A','B','C']))                           { $where[]="block=?";     $ptypes.='s'; $pvals[]=$fblk; }
if (in_array($ftype, ['single','double','triple','quad']))      { $where[]="room_type=?"; $ptypes.='s'; $pvals[]=$ftype; }
if (in_array($fstat, ['available','maintenance','closed']))     { $where[]="status=?";    $ptypes.='s'; $pvals[]=$fstat; }

$rooms = db_query($conn, "SELECT * FROM rooms WHERE ".implode(' AND ',$where)." ORDER BY block,floor,room_number", $ptypes, ...$pvals);
$stats_row = db_row($conn,"SELECT COUNT(*) AS tr, SUM(capacity) AS ts, SUM(occupied) AS to2, SUM(capacity-occupied) AS tf FROM rooms");

$page_title = 'Room Management';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.8);backdrop-filter:blur(8px);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:500px;animation:mIn .3s ease;max-height:90vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95) translateY(-10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.7rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.4rem;}
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
      <div class="page-title" style="margin:0;">Room Management</div>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add').classList.add('open')">+ Add Room</button>
    </div>
    <div class="page-sub">Add, edit, and manage all hostel rooms</div>

    <!-- STATS -->
    <div class="g4" style="margin-bottom:1.3rem;">
      <div class="stat-card blue"><div class="stat-val"><?= $stats_row['tr'] ?></div><div class="stat-label">Total Rooms</div></div>
      <div class="stat-card cyan"><div class="stat-val"><?= $stats_row['ts'] ?></div><div class="stat-label">Total Seats</div></div>
      <div class="stat-card green"><div class="stat-val"><?= $stats_row['tf'] ?></div><div class="stat-label">Available Seats</div></div>
      <div class="stat-card purple"><div class="stat-val"><?= $stats_row['to2'] ?></div><div class="stat-label">Occupied</div></div>
    </div>

    <!-- FILTERS -->
    <div class="glass" style="padding:1rem;margin-bottom:1.2rem;">
      <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:130px;">
          <label class="form-label">Block</label>
          <select class="form-input" name="block">
            <option value="">All</option>
            <option value="A" <?= $fblk==='A'?'selected':'' ?>>Block A</option>
            <option value="B" <?= $fblk==='B'?'selected':'' ?>>Block B</option>
            <option value="C" <?= $fblk==='C'?'selected':'' ?>>Block C</option>
          </select>
        </div>
        <div class="form-group" style="flex:1;min-width:130px;">
          <label class="form-label">Room Type</label>
          <select class="form-input" name="type">
            <option value="">All</option>
            <option value="single" <?= $ftype==='single'?'selected':'' ?>>Single</option>
            <option value="double" <?= $ftype==='double'?'selected':'' ?>>Double</option>
            <option value="triple" <?= $ftype==='triple'?'selected':'' ?>>Triple</option>
            <option value="quad"   <?= $ftype==='quad'?'selected':'' ?>>Quad</option>
          </select>
        </div>
        <div class="form-group" style="flex:1;min-width:130px;">
          <label class="form-label">Status</label>
          <select class="form-input" name="status">
            <option value="">All</option>
            <option value="available"   <?= $fstat==='available'?'selected':'' ?>>Available</option>
            <option value="maintenance" <?= $fstat==='maintenance'?'selected':'' ?>>Maintenance</option>
            <option value="closed"      <?= $fstat==='closed'?'selected':'' ?>>Closed</option>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" style="height:42px;">Filter</button>
        <a class="btn btn-secondary btn-sm" href="rooms.php" style="height:42px;">Reset</a>
      </form>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper">
      <div class="table-header">
        <div class="table-title">All Rooms (<?= count($rooms) ?>)</div>
      </div>
      <?php if(empty($rooms)): ?>
        <div style="padding:2.5rem;text-align:center;opacity:.55;"><div style="font-size:2rem;">🏠</div><div style="margin-top:.5rem;font-size:.875rem;color:var(--ts);">No rooms found.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Room No.</th><th>Block</th><th>Floor</th><th>Type</th>
            <th>Capacity</th><th>Occupied</th><th>Free</th>
            <th>Fee/Sem</th><th>Amenities</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rooms as $r):
            $free = $r['capacity'] - $r['occupied'];
            $occ_pct = $r['capacity'] > 0 ? round($r['occupied']/$r['capacity']*100) : 0;
          ?>
          <tr>
            <td><strong><?= clean($r['room_number']) ?></strong></td>
            <td>Block <?= $r['block'] ?></td>
            <td><?= $r['floor'] ?></td>
            <td><span class="tag tag-<?= ['single'=>'blue','double'=>'purple','triple'=>'cyan','quad'=>'blue'][$r['room_type']] ?>"><?= ucfirst($r['room_type']) ?></span></td>
            <td><?= $r['capacity'] ?></td>
            <td>
              <div><?= $r['occupied'] ?></div>
              <div class="progress-bar" style="width:60px;"><div class="progress-fill" style="width:<?= $occ_pct ?>%;"></div></div>
            </td>
            <td style="color:<?= $free>0?'var(--success)':'var(--danger)' ?>;font-weight:600;"><?= $free ?></td>
            <td style="font-family:var(--fm);color:var(--green);">৳<?= number_format($r['fee_per_sem'],0) ?></td>
            <td style="font-size:.78rem;">
              <?= $r['has_wifi']?'📶 ':'— ' ?>
              <?= $r['has_ac']?'❄️ ':'' ?>
              <?= $r['has_attached_bath']?'🚿 ':'' ?>
              <?= $r['has_study_desk']?'📚':'' ?>
            </td>
            <td>
              <span class="badge badge-<?= $r['status']==='available'?'available':($r['status']==='maintenance'?'pending':'rejected') ?>">
                <?= ucfirst($r['status']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:4px;">
                <a class="btn btn-secondary btn-sm" href="rooms.php?action=edit&id=<?= $r['id'] ?>">✏️ Edit</a>
                <?php if($r['occupied']==0): ?>
                  <a class="btn btn-danger btn-sm"
                     href="rooms.php?action=delete&id=<?= $r['id'] ?>"
                     onclick="return confirm('Delete room <?= $r['room_number'] ?>?')">🗑️</a>
                <?php endif; ?>
              </div>
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

<!-- ADD ROOM MODAL -->
<div class="modal-bg" id="modal-add">
  <div class="modal-box">
    <div class="modal-title">🏠 Add New Room</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="add">
      <div class="g2">
        <div class="form-group"><label class="form-label">Room Number *</label><input class="form-input" name="room_number" placeholder="e.g. A-401" required></div>
        <div class="form-group"><label class="form-label">Block *</label>
          <select class="form-input" name="block" required>
            <option value="">Select</option>
            <option value="A">Block A (Male)</option>
            <option value="B">Block B (Female)</option>
            <option value="C">Block C (Mixed)</option>
          </select>
        </div>
      </div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Floor *</label>
          <select class="form-input" name="floor" required>
            <option value="1">Ground/1st Floor</option>
            <option value="2">2nd Floor</option>
            <option value="3">3rd Floor</option>
            <option value="4">4th Floor</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Room Type *</label>
          <select class="form-input" name="room_type" required>
            <option value="">Select</option>
            <option value="single">Single (1 seat)</option>
            <option value="double">Double (2 seats)</option>
            <option value="triple">Triple (3 seats)</option>
            <option value="quad">Quad (4 seats)</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Semester Fee (৳) *</label><input class="form-input" type="number" name="fee" min="1000" step="500" placeholder="e.g. 6000" required></div>
      <div style="display:flex;gap:1.2rem;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_wifi" checked style="accent-color:var(--blue);"> WiFi</label>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_desk" checked style="accent-color:var(--blue);"> Study Desk</label>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_ac" style="accent-color:var(--blue);"> AC</label>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_bath" style="accent-color:var(--blue);"> Attached Bath</label>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" rows="2" placeholder="Optional notes…"></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Room</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT ROOM MODAL (auto-opens if editing) -->
<?php if($edit_room): ?>
<div class="modal-bg open" id="modal-edit">
  <div class="modal-box">
    <div class="modal-title">✏️ Edit Room — <?= clean($edit_room['room_number']) ?></div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="edit">
      <input type="hidden" name="room_id" value="<?= $edit_room['id'] ?>">
      <div class="g2">
        <div class="form-group"><label class="form-label">Room Number</label><input class="form-input" value="<?= clean($edit_room['room_number']) ?>" readonly></div>
        <div class="form-group"><label class="form-label">Type</label><input class="form-input" value="<?= ucfirst($edit_room['room_type']) ?>" readonly></div>
      </div>
      <div class="form-group"><label class="form-label">Semester Fee (৳) *</label><input class="form-input" type="number" name="fee" value="<?= $edit_room['fee_per_sem'] ?>" min="1000" step="500" required></div>
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-input" name="status">
          <option value="available"   <?= $edit_room['status']==='available'?'selected':'' ?>>Available</option>
          <option value="maintenance" <?= $edit_room['status']==='maintenance'?'selected':'' ?>>Under Maintenance</option>
          <option value="closed"      <?= $edit_room['status']==='closed'?'selected':'' ?>>Closed</option>
        </select>
      </div>
      <div style="display:flex;gap:1.2rem;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_wifi" <?= $edit_room['has_wifi']?'checked':'' ?> style="accent-color:var(--blue);"> WiFi</label>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_desk" <?= $edit_room['has_study_desk']?'checked':'' ?> style="accent-color:var(--blue);"> Study Desk</label>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_ac" <?= $edit_room['has_ac']?'checked':'' ?> style="accent-color:var(--blue);"> AC</label>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;cursor:pointer;"><input type="checkbox" name="has_bath" <?= $edit_room['has_attached_bath']?'checked':'' ?> style="accent-color:var(--blue);"> Attached Bath</label>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" rows="2"><?= clean($edit_room['description']??'') ?></textarea></div>
      <div class="modal-footer">
        <a class="btn btn-secondary" href="rooms.php">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.modal-bg').forEach(m=>{
  m.addEventListener('click',e=>{ if(e.target===m) m.classList.remove('open'); });
});
</script>
</body></html>
