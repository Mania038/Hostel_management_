<?php
// admin/notices.php
require_once __DIR__ . '/../config/db.php';
require_admin();
$admin_id = (int)$_SESSION['admin_id'];

// DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    db_exec($conn, "DELETE FROM notices WHERE id=?", 'i', (int)$_GET['id']);
    flash('success', 'Notice deleted.');
    redirect(APP_URL . '/admin/notices.php');
}

// TOGGLE ACTIVE
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $cur = db_row($conn, "SELECT is_active FROM notices WHERE id=?", 'i', $id);
    $new = $cur['is_active'] ? 0 : 1;
    db_exec($conn, "UPDATE notices SET is_active=? WHERE id=?", 'ii', $new, $id);
    flash('success', 'Notice ' . ($new ? 'published' : 'unpublished') . '.');
    redirect(APP_URL . '/admin/notices.php');
}

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'add') {
    $title   = clean($_POST['title']   ?? '');
    $body    = clean($_POST['body']    ?? '');
    $type    = $_POST['type']   ?? 'info';
    $target  = $_POST['target'] ?? 'all';
    $active  = isset($_POST['is_active']) ? 1 : 0;

    $allowed_types   = ['info', 'warning', 'danger', 'success'];
    $allowed_targets = ['all', 'students', 'block_a', 'block_b', 'block_c'];

    if (!$title || !$body) {
        flash('error', 'Title and body are required.');
    } elseif (!in_array($type, $allowed_types) || !in_array($target, $allowed_targets)) {
        flash('error', 'Invalid type or target.');
    } else {
        db_insert($conn,
            "INSERT INTO notices (title, body, type, target, published_by, is_active) VALUES (?,?,?,?,?,?)",
            'ssssii', $title, $body, $type, $target, $admin_id, $active
        );
        flash('success', 'Notice published successfully.');
    }
    redirect(APP_URL . '/admin/notices.php');
}

// EDIT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'edit') {
    $id     = (int)$_POST['notice_id'];
    $title  = clean($_POST['title']  ?? '');
    $body   = clean($_POST['body']   ?? '');
    $type   = $_POST['type']   ?? 'info';
    $target = $_POST['target'] ?? 'all';
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($title && $body) {
        db_exec($conn,
            "UPDATE notices SET title=?, body=?, type=?, target=?, is_active=? WHERE id=?",
            'sssiii', $title, $body, $type, $target, $active, $id
        );
        flash('success', 'Notice updated.');
    } else {
        flash('error', 'Title and body required.');
    }
    redirect(APP_URL . '/admin/notices.php');
}

// Edit prefill
$edit_notice = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_notice = db_row($conn, "SELECT * FROM notices WHERE id=?", 'i', (int)$_GET['id']);
}

// Fetch all notices
$notices = db_query($conn,
    "SELECT n.*, a.full_name AS author FROM notices n
     JOIN admins a ON a.id = n.published_by
     ORDER BY n.created_at DESC");

$type_colors = [
    'info'    => ['bg' => 'rgba(99,179,237,.1)',  'border' => 'var(--blue)',    'label' => '🔵 Info'],
    'warning' => ['bg' => 'rgba(246,224,94,.1)',  'border' => 'var(--warning)', 'label' => '🟡 Warning'],
    'danger'  => ['bg' => 'rgba(252,129,129,.1)', 'border' => 'var(--danger)',  'label' => '🔴 Danger'],
    'success' => ['bg' => 'rgba(104,211,145,.1)', 'border' => 'var(--success)', 'label' => '🟢 Success'],
];
$target_labels = [
    'all'      => '👥 Everyone',
    'students' => '🎓 All Students',
    'block_a'  => '🏠 Block A Only',
    'block_b'  => '🏠 Block B Only',
    'block_c'  => '🏠 Block C Only',
];

$page_title = 'Notices';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.85);backdrop-filter:blur(10px);z-index:500;align-items:center;justify-content:center;padding:1rem;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:520px;animation:mIn .3s ease;max-height:92vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.3rem;}
.notice-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r2);padding:1.1rem 1.2rem;display:flex;gap:1rem;align-items:flex-start;transition:border-color .2s;margin-bottom:.65rem;position:relative;overflow:hidden;}
.notice-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;}
.notice-card.info::before{background:var(--blue);}
.notice-card.warning::before{background:var(--warning);}
.notice-card.danger::before{background:var(--danger);}
.notice-card.success::before{background:var(--success);}
.notice-card:hover{border-color:rgba(99,179,237,.2);}
.notice-inactive{opacity:.45;}
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
      <div class="page-title" style="margin:0;">Notices &amp; Announcements</div>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add').classList.add('open')">+ New Notice</button>
    </div>
    <div class="page-sub">Publish announcements visible to students on their dashboard</div>

    <!-- SUMMARY -->
    <div class="g4" style="margin-bottom:1.4rem;">
      <?php
      $total_notices   = count($notices);
      $active_notices  = count(array_filter($notices, fn($n) => $n['is_active']));
      $info_cnt        = count(array_filter($notices, fn($n) => $n['type']==='info'));
      $urgent_cnt      = count(array_filter($notices, fn($n) => $n['type']==='danger'));
      ?>
      <div class="stat-card blue"><div class="stat-val"><?= $total_notices ?></div><div class="stat-label">Total Notices</div></div>
      <div class="stat-card green"><div class="stat-val"><?= $active_notices ?></div><div class="stat-label">Published</div></div>
      <div class="stat-card cyan"><div class="stat-val"><?= $info_cnt ?></div><div class="stat-label">Info Notices</div></div>
      <div class="stat-card pink"><div class="stat-val"><?= $urgent_cnt ?></div><div class="stat-label">Danger Alerts</div></div>
    </div>

    <!-- NOTICES LIST -->
    <?php if(empty($notices)): ?>
      <div class="glass" style="padding:3rem;text-align:center;opacity:.55;">
        <div style="font-size:2.5rem;margin-bottom:.75rem;">📢</div>
        <div style="font-family:var(--fd);font-size:1rem;font-weight:700;">No notices yet</div>
        <div style="color:var(--ts);margin-top:.3rem;font-size:.875rem;">Click "New Notice" to publish your first announcement.</div>
      </div>
    <?php else:
      foreach($notices as $n):
        $tc = $type_colors[$n['type']] ?? $type_colors['info'];
    ?>
    <div class="notice-card <?= $n['type'] ?> <?= !$n['is_active']?'notice-inactive':'' ?>">
      <!-- Type indicator -->
      <div style="width:42px;height:42px;border-radius:var(--r2);background:<?= $tc['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;border:1px solid <?= $tc['border'] ?>30;">
        <?= explode(' ',$tc['label'])[0] ?>
      </div>

      <!-- Content -->
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
          <div>
            <div style="font-size:.9rem;font-weight:700;display:flex;align-items:center;gap:.5rem;">
              <?= clean($n['title']) ?>
              <?php if(!$n['is_active']): ?>
                <span style="font-size:.7rem;font-weight:500;color:var(--tm);background:rgba(255,255,255,.05);padding:.15rem .5rem;border-radius:10px;border:1px solid var(--gb);">DRAFT</span>
              <?php endif; ?>
            </div>
            <div style="font-size:.78rem;color:var(--ts);margin-top:.15rem;">
              <?= $tc['label'] ?> · <?= $target_labels[$n['target']] ?? $n['target'] ?>
              · By <?= clean($n['author']) ?> · <?= date('M d, Y', strtotime($n['created_at'])) ?>
            </div>
          </div>
          <!-- Actions -->
          <div style="display:flex;gap:.4rem;flex-shrink:0;">
            <a class="btn btn-secondary btn-sm" href="notices.php?action=edit&id=<?= $n['id'] ?>">✏️</a>
            <a class="btn btn-secondary btn-sm"
               href="notices.php?action=toggle&id=<?= $n['id'] ?>"
               title="<?= $n['is_active']?'Unpublish':'Publish' ?>">
              <?= $n['is_active'] ? '🔕' : '📢' ?>
            </a>
            <a class="btn btn-danger btn-sm"
               href="notices.php?action=delete&id=<?= $n['id'] ?>"
               onclick="return confirm('Delete this notice permanently?')">🗑️</a>
          </div>
        </div>
        <!-- Body preview -->
        <div style="font-size:.85rem;color:var(--ts);margin-top:.5rem;line-height:1.6;">
          <?= clean(substr($n['body'], 0, 200)) ?><?= strlen($n['body'])>200?'…':'' ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </main>
</div>

<!-- ADD NOTICE MODAL -->
<div class="modal-bg" id="modal-add">
  <div class="modal-box">
    <div class="modal-title">📢 Publish New Notice</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="add">
      <div class="form-group">
        <label class="form-label">Title *</label>
        <input class="form-input" name="title" placeholder="e.g. Water Outage — Block A" required>
      </div>
      <div class="form-group">
        <label class="form-label">Body *</label>
        <textarea class="form-input" name="body" rows="5"
                  placeholder="Full announcement text that students will read…" required></textarea>
      </div>
      <div class="g2">
        <div class="form-group">
          <label class="form-label">Notice Type</label>
          <select class="form-input" name="type">
            <option value="info">🔵 Info</option>
            <option value="warning">🟡 Warning</option>
            <option value="danger">🔴 Danger / Urgent</option>
            <option value="success">🟢 Success / Good news</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Target Audience</label>
          <select class="form-input" name="target">
            <option value="all">👥 Everyone</option>
            <option value="students">🎓 All Students</option>
            <option value="block_a">🏠 Block A Only</option>
            <option value="block_b">🏠 Block B Only</option>
            <option value="block_c">🏠 Block C Only</option>
          </select>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;cursor:pointer;">
        <input type="checkbox" name="is_active" checked style="accent-color:var(--blue);width:15px;height:15px;">
        Publish immediately (uncheck to save as draft)
      </label>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">📢 Publish Notice</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT NOTICE MODAL (auto-opens when ?action=edit) -->
<?php if($edit_notice): ?>
<div class="modal-bg open" id="modal-edit">
  <div class="modal-box">
    <div class="modal-title">✏️ Edit Notice</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="edit">
      <input type="hidden" name="notice_id"   value="<?= $edit_notice['id'] ?>">
      <div class="form-group">
        <label class="form-label">Title *</label>
        <input class="form-input" name="title" value="<?= clean($edit_notice['title']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Body *</label>
        <textarea class="form-input" name="body" rows="5" required><?= clean($edit_notice['body']) ?></textarea>
      </div>
      <div class="g2">
        <div class="form-group">
          <label class="form-label">Notice Type</label>
          <select class="form-input" name="type">
            <?php foreach(['info'=>'🔵 Info','warning'=>'🟡 Warning','danger'=>'🔴 Danger','success'=>'🟢 Success'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $edit_notice['type']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Target</label>
          <select class="form-input" name="target">
            <?php foreach($target_labels as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $edit_notice['target']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;cursor:pointer;">
        <input type="checkbox" name="is_active" <?= $edit_notice['is_active']?'checked':'' ?> style="accent-color:var(--blue);width:15px;height:15px;">
        Published (visible to students)
      </label>
      <div class="modal-footer">
        <a class="btn btn-secondary" href="notices.php">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.modal-bg').forEach(m =>
  m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); })
);
</script>
</body></html>
