<?php
// admin/managers.php  — Super Admin only
require_once __DIR__ . '/../config/db.php';
require_admin();

// Only super admin can access
if (($_SESSION['admin_role'] ?? '') !== 'super_admin' && ($_SESSION['manager_level'] ?? '') !== 'super') {
    flash('error', 'Access denied. Super Admin only.');
    redirect(APP_URL . '/admin/dashboard.php');
}

$admin_id = (int)$_SESSION['admin_id'];

// ── TOGGLE ACTIVE ─────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $cur = db_row($conn, "SELECT is_active FROM admins WHERE id=? AND id != ?", 'ii', $id, $admin_id);
    if ($cur) {
        $new = $cur['is_active'] ? 0 : 1;
        db_exec($conn, "UPDATE admins SET is_active=? WHERE id=?", 'ii', $new, $id);
        flash('success', 'Manager account ' . ($new ? 'activated.' : 'deactivated.'));
    }
    redirect(APP_URL . '/admin/managers.php');
}

// ── DELETE ────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id === $admin_id) { flash('error', 'Cannot delete your own account.'); }
    else {
        db_exec($conn, "DELETE FROM admins WHERE id=? AND manager_level != 'super'", 'i', $id);
        flash('success', 'Manager account deleted.');
    }
    redirect(APP_URL . '/admin/managers.php');
}

// ── ADD MANAGER ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'add') {
    $uname  = clean($_POST['username']  ?? '');
    $email  = clean($_POST['email']     ?? '');
    $name   = clean($_POST['full_name'] ?? '');
    $block  = $_POST['assigned_block']  ?? '';
    $pw     = $_POST['password']        ?? 'Admin@123';
    if (!$uname || !$email || !$name || !in_array($block, ['A','B','C'])) {
        flash('error', 'All fields required. Block must be A, B, or C.');
    } else {
        $dup = db_row($conn, "SELECT id FROM admins WHERE username=? OR email=?", 'ss', $uname, $email);
        if ($dup) { flash('error', 'Username or email already exists.'); }
        else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            db_insert($conn,
                "INSERT INTO admins (username,email,password,full_name,role,assigned_block,manager_level,is_active)
                 VALUES (?,?,?,'?','admin',?,'manager',1)",
                'ssss', $uname, $email, $hash, $block
            );
            // use direct stmt to avoid placeholder issue
            $st = $conn->prepare("INSERT INTO admins (username,email,password,full_name,role,assigned_block,manager_level,is_active) VALUES (?,?,?,?,'admin',?,'manager',1)");
            $st->bind_param('sssss', $uname, $email, $hash, $name, $block);
            $st->execute(); $st->close();
            // remove the failed one if any
            flash('success', "Manager {$name} added. They can log in with username: {$uname}");
        }
    }
    redirect(APP_URL . '/admin/managers.php');
}

// ── EDIT ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'edit') {
    $id    = (int)$_POST['mgr_id'];
    $name  = clean($_POST['full_name'] ?? '');
    $block = $_POST['assigned_block']  ?? '';
    $active= isset($_POST['is_active']) ? 1 : 0;
    if ($name && in_array($block, ['A','B','C'])) {
        db_exec($conn, "UPDATE admins SET full_name=?,assigned_block=?,is_active=? WHERE id=?",
            'ssii', $name, $block, $active, $id);
        flash('success', 'Manager updated.');
    } else { flash('error', 'Invalid data.'); }
    redirect(APP_URL . '/admin/managers.php');
}

// Edit prefill
$edit_mgr = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_mgr = db_row($conn, "SELECT * FROM admins WHERE id=? AND manager_level='manager'", 'i', (int)$_GET['id']);
}

// Fetch all managers
$managers = db_query($conn,
    "SELECT a.*,
        (SELECT COUNT(*) FROM applications ap
         JOIN students s ON s.id=ap.student_id
         JOIN rooms r ON r.block=a.assigned_block
         WHERE ap.status='pending') AS pending_apps,
        (SELECT COUNT(*) FROM complaints c
         JOIN rooms r ON r.id=c.room_id
         WHERE r.block=a.assigned_block AND c.status IN ('open','in_progress')) AS open_complaints
     FROM admins a WHERE a.manager_level='manager' ORDER BY a.assigned_block, a.created_at");

$block_stats = db_query($conn,
    "SELECT block,
        SUM(capacity) AS cap, SUM(occupied) AS occ,
        ROUND(SUM(occupied)/SUM(capacity)*100,1) AS pct,
        COUNT(*) AS rooms
     FROM rooms WHERE status='available' GROUP BY block ORDER BY block");

$page_title = 'Hostel Managers';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.85);backdrop-filter:blur(10px);z-index:500;align-items:center;justify-content:center;padding:1rem;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:480px;animation:mIn .3s ease;max-height:92vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.3rem;}
.blk-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r2);padding:1.1rem;transition:border-color .2s;}
.blk-card:hover{border-color:rgba(99,179,237,.25);}
</style>
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest Admin</a>
  <div class="nav-links">
    <span style="font-size:.82rem;color:var(--ts);">👤 <?= clean($_SESSION['admin_name']) ?> (Super Admin)</span>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="dash-main">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:.3rem;">
      <div class="page-title" style="margin:0;">Hostel Manager Accounts</div>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add').classList.add('open')">+ Add Manager</button>
    </div>
    <div class="page-sub">Each block has a dedicated manager. Super Admin oversees all blocks.</div>

    <!-- BLOCK OVERVIEW CARDS -->
    <div class="g3" style="margin-bottom:1.4rem;">
      <?php
      $bc=['A'=>'blue','B'=>'purple','C'=>'cyan'];
      $blabel=['A'=>'Block A (Male)','B'=>'Block B (Female)','C'=>'Block C (Mixed)'];
      foreach($block_stats as $bs):
        $mgr = null;
        foreach($managers as $m) { if($m['assigned_block']===$bs['block']) { $mgr=$m; break; } }
      ?>
      <div class="blk-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
          <div style="font-family:var(--fd);font-size:.95rem;font-weight:700;"><?= $blabel[$bs['block']] ?></div>
          <span class="badge badge-<?= $mgr&&$mgr['is_active']?'active':'pending' ?>">
            <?= $mgr ? ($mgr['is_active']?'Active':'Inactive') : 'No Manager' ?>
          </span>
        </div>
        <div style="font-size:.8rem;color:var(--ts);margin-bottom:.3rem;"><?= $bs['rooms'] ?> rooms · <?= $bs['cap'] ?> seats</div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $bs['pct'] ?>%;"></div></div>
        <div style="font-size:.73rem;color:var(--ts);margin-top:.2rem;"><?= $bs['occ'] ?>/<?= $bs['cap'] ?> occupied (<?= $bs['pct'] ?>%)</div>
        <?php if($mgr): ?>
          <div style="margin-top:.75rem;padding-top:.65rem;border-top:1px solid var(--gb);font-size:.82rem;">
            <strong><?= clean($mgr['full_name']) ?></strong>
            <div style="color:var(--ts);font-size:.75rem;"><?= clean($mgr['email']) ?></div>
          </div>
        <?php else: ?>
          <div style="margin-top:.75rem;font-size:.8rem;color:var(--ts);">No manager assigned yet.</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ROLE EXPLANATION -->
    <div class="g2" style="margin-bottom:1.4rem;">
      <div class="glass" style="padding:1.1rem;">
        <div style="font-family:var(--fd);font-size:.9rem;font-weight:700;margin-bottom:.6rem;">🛡️ Super Admin</div>
        <div style="font-size:.82rem;color:var(--ts);line-height:1.6;">
          Full access to all system features. Can approve/reject everything, manage managers, override any decision, access all blocks, view all reports.
        </div>
        <div style="margin-top:.75rem;display:flex;flex-wrap:wrap;gap:.4rem;">
          <span class="tag tag-blue">All Blocks</span>
          <span class="tag tag-blue">Manage Managers</span>
          <span class="tag tag-blue">Full Reports</span>
          <span class="tag tag-blue">System Settings</span>
        </div>
      </div>
      <div class="glass" style="padding:1.1rem;">
        <div style="font-family:var(--fd);font-size:.9rem;font-weight:700;margin-bottom:.6rem;">🏠 Block Manager</div>
        <div style="font-size:.82rem;color:var(--ts);line-height:1.6;">
          Manages day-to-day operations for their assigned block only. Can approve applications, handle complaints, track payments, post tasks — all scoped to their block.
        </div>
        <div style="margin-top:.75rem;display:flex;flex-wrap:wrap;gap:.4rem;">
          <span class="tag tag-purple">Assigned Block Only</span>
          <span class="tag tag-purple">Applications</span>
          <span class="tag tag-purple">Complaints</span>
          <span class="tag tag-purple">Tasks</span>
        </div>
      </div>
    </div>

    <!-- MANAGERS TABLE -->
    <div class="table-wrapper">
      <div class="table-header"><div class="table-title">All Managers (<?= count($managers) ?>)</div></div>
      <?php if(empty($managers)): ?>
        <div style="padding:2.5rem;text-align:center;opacity:.55;"><div style="font-size:2rem;">👤</div><div style="margin-top:.5rem;font-size:.875rem;color:var(--ts);">No managers added yet.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Manager</th><th>Username</th><th>Assigned Block</th><th>Pending Apps</th><th>Open Issues</th><th>Status</th><th>Since</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($managers as $m): $ini=strtoupper(substr($m['full_name'],0,1).substr(explode(' ',$m['full_name'])[1]??'',0,1)); ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div class="avatar" style="width:30px;height:30px;font-size:.68rem;background:linear-gradient(135deg,#b794f4,#63b3ed);"><?= $ini ?></div>
                <div>
                  <div style="font-size:.875rem;font-weight:600;"><?= clean($m['full_name']) ?></div>
                  <div style="font-size:.73rem;color:var(--ts);"><?= clean($m['email']) ?></div>
                </div>
              </div>
            </td>
            <td><code><?= clean($m['username']) ?></code></td>
            <td>
              <span class="badge badge-<?= ['A'=>'available','B'=>'pending','C'=>'approved'][$m['assigned_block']] ?? 'available' ?>">
                Block <?= $m['assigned_block'] ?>
              </span>
            </td>
            <td style="font-family:var(--fm);color:<?= $m['pending_apps']>0?'var(--warning)':'var(--ts)' ?>;"><?= $m['pending_apps'] ?></td>
            <td style="font-family:var(--fm);color:<?= $m['open_complaints']>0?'var(--danger)':'var(--ts)' ?>;"><?= $m['open_complaints'] ?></td>
            <td><span class="badge badge-<?= $m['is_active']?'active':'rejected' ?>"><?= $m['is_active']?'Active':'Inactive' ?></span></td>
            <td style="color:var(--ts);font-size:.8rem;"><?= date('M d, Y',strtotime($m['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:4px;">
                <a class="btn btn-secondary btn-sm" href="managers.php?action=edit&id=<?= $m['id'] ?>">✏️</a>
                <a class="btn btn-secondary btn-sm" href="managers.php?action=toggle&id=<?= $m['id'] ?>"><?= $m['is_active']?'🔒':'✅' ?></a>
                <a class="btn btn-danger btn-sm" href="managers.php?action=delete&id=<?= $m['id'] ?>"
                   onclick="return confirm('Delete manager <?= clean($m['full_name']) ?>?')">🗑️</a>
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

<!-- ADD MODAL -->
<div class="modal-bg" id="modal-add">
  <div class="modal-box">
    <div class="modal-title">👤 Add Hostel Manager</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="add">
      <div class="form-group"><label class="form-label">Full Name *</label><input class="form-input" name="full_name" placeholder="e.g. Karim Ahmed" required></div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Username *</label><input class="form-input" name="username" placeholder="manager_a" required></div>
        <div class="form-group"><label class="form-label">Assigned Block *</label>
          <select class="form-input" name="assigned_block" required>
            <option value="">Select</option>
            <option value="A">Block A (Male)</option>
            <option value="B">Block B (Female)</option>
            
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Email *</label><input class="form-input" type="email" name="email" placeholder="manager@university.edu" required></div>
      <div class="form-group"><label class="form-label">Initial Password</label><input class="form-input" type="password" name="password" placeholder="Leave blank for Admin@123"></div>
      <div class="info-box" style="font-size:.8rem;">The manager will use these credentials to log in via the Admin Login tab. They will only see data for their assigned block.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Manager</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<?php if($edit_mgr): ?>
<div class="modal-bg open" id="modal-edit">
  <div class="modal-box">
    <div class="modal-title">✏️ Edit Manager — <?= clean($edit_mgr['full_name']) ?></div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="edit">
      <input type="hidden" name="mgr_id"      value="<?= $edit_mgr['id'] ?>">
      <div class="form-group"><label class="form-label">Full Name *</label><input class="form-input" name="full_name" value="<?= clean($edit_mgr['full_name']) ?>" required></div>
      <div class="form-group"><label class="form-label">Assigned Block *</label>
        <select class="form-input" name="assigned_block" required>
          <option value="A" <?= $edit_mgr['assigned_block']==='A'?'selected':'' ?>>Block A (Male)</option>
          <option value="B" <?= $edit_mgr['assigned_block']==='B'?'selected':'' ?>>Block B (Female)</option>
          <option value="C" <?= $edit_mgr['assigned_block']==='C'?'selected':'' ?>>Block C (Mixed)</option>
        </select>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;cursor:pointer;">
        <input type="checkbox" name="is_active" <?= $edit_mgr['is_active']?'checked':'' ?> style="accent-color:var(--blue);width:15px;height:15px;">
        Account Active
      </label>
      <div class="modal-footer">
        <a class="btn btn-secondary" href="managers.php">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<script>document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));</script>
</body></html>