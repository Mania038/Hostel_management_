<?php
// admin/tasks.php — Post & manage tasks for student fee exchange
require_once __DIR__ . '/../config/db.php';
require_admin();
$admin_id   = (int)$_SESSION['admin_id'];
$is_super   = (($_SESSION['manager_level'] ?? '') === 'super' || ($_SESSION['admin_role'] ?? '') === 'super_admin');
$my_block   = $_SESSION['assigned_block'] ?? null;

// ── VERIFY TASK COMPLETION ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'verify') {
    $app_id  = (int)$_POST['app_id'];
    $verdict = $_POST['verdict'] ?? '';          // 'complete' or 'reject'
    $note    = clean($_POST['admin_note'] ?? '');

    $ta = db_row($conn,
        "SELECT ta.*, t.reward_amount FROM task_applications ta
         JOIN tasks t ON t.id=ta.task_id WHERE ta.id=?", 'i', $app_id);

    if ($ta && $verdict === 'complete') {
        // Mark application complete
        db_exec($conn,
            "UPDATE task_applications SET status='completed', admin_note=?, verified_by=?, completed_at=NOW() WHERE id=?",
            'sii', $note, $admin_id, $app_id);

        // If linked to a payment, reduce or clear it
        if ($ta['payment_id']) {
            $pay = db_row($conn, "SELECT * FROM payments WHERE id=?", 'i', $ta['payment_id']);
            if ($pay) {
                $remaining = max(0, $pay['amount'] - $ta['reward_amount']);
                if ($remaining <= 0) {
                    db_exec($conn, "UPDATE payments SET status='paid', paid_at=NOW(), notes=CONCAT(IFNULL(notes,''),' | Fee cleared via task exchange') WHERE id=?", 'i', $pay['id']);
                } else {
                    db_exec($conn, "UPDATE payments SET amount=?, notes=CONCAT(IFNULL(notes,''),' | ৳".number_format($ta['reward_amount'],0)." credited via task') WHERE id=?", 'di', $remaining, $pay['id']);
                }
            }
        }
        // Update task status if max applicants reached
        $done_count = db_row($conn,"SELECT COUNT(*) AS c FROM task_applications WHERE task_id=? AND status='completed'",'i',$ta['task_id'])['c']??0;
        $task = db_row($conn,"SELECT max_applicants FROM tasks WHERE id=?",'i',$ta['task_id']);
        if ($task && $done_count >= $task['max_applicants']) {
            db_exec($conn,"UPDATE tasks SET status='completed' WHERE id=?",'i',$ta['task_id']);
        }
        flash('success', 'Task verified as complete. Fee credit applied.');

    } elseif ($ta && $verdict === 'reject') {
        db_exec($conn,
            "UPDATE task_applications SET status='rejected', admin_note=?, verified_by=? WHERE id=?",
            'sii', $note, $admin_id, $app_id);
        // Re-open task slot
        db_exec($conn,"UPDATE tasks SET status='open' WHERE id=? AND status='in_progress'",'i',$ta['task_id']);
        flash('success', 'Task application rejected.');
    }
    redirect(APP_URL . '/admin/tasks.php');
}

// ── APPROVE APPLICATION ───────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'approve_app' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    db_exec($conn, "UPDATE task_applications SET status='in_progress' WHERE id=? AND status='applied'", 'i', $app_id);
    // Mark task in_progress if all slots taken
    $ta = db_row($conn,"SELECT task_id FROM task_applications WHERE id=?",'i',$app_id);
    if($ta){
        $approved = db_row($conn,"SELECT COUNT(*) AS c FROM task_applications WHERE task_id=? AND status IN ('in_progress','completed')",'i',$ta['task_id'])['c']??0;
        $mx = db_row($conn,"SELECT max_applicants FROM tasks WHERE id=?",'i',$ta['task_id'])['max_applicants']??1;
        if($approved >= $mx) db_exec($conn,"UPDATE tasks SET status='in_progress' WHERE id=?",'i',$ta['task_id']);
    }
    flash('success', 'Task application approved. Student can now begin.');
    redirect(APP_URL . '/admin/tasks.php');
}

// ── DELETE TASK ───────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    db_exec($conn, "DELETE FROM tasks WHERE id=? AND status IN ('open','cancelled')", 'i', $id);
    flash('success', 'Task deleted.');
    redirect(APP_URL . '/admin/tasks.php');
}

// ── ADD TASK ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'add') {
    $title    = clean($_POST['title']       ?? '');
    $desc     = clean($_POST['description'] ?? '');
    $cat      = $_POST['category']  ?? 'other';
    $reward   = (float)($_POST['reward_amount'] ?? 0);
    $maxapp   = max(1, (int)($_POST['max_applicants'] ?? 1));
    $deadline = $_POST['deadline']  ?? '';
    $block    = $_POST['block']     ?? 'any';
    $req      = clean($_POST['requirements'] ?? '');
    $cats     = ['admin_support','room_finding','cleaning','maintenance_assist','event_helper','data_entry','other'];
    if (!$title || !$desc || !in_array($cat,$cats) || $reward <= 0 || !$deadline) {
        flash('error', 'Please fill all required fields with valid data.');
    } else {
        $post_block = $is_super ? $block : ($my_block ?? 'any');
        $stmt = $conn->prepare("INSERT INTO tasks (title,description,category,reward_amount,max_applicants,deadline,block,posted_by,requirements) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssdiissi', $title,$desc,$cat,$reward,$maxapp,$deadline,$post_block,$admin_id,$req);
        $stmt->execute(); $stmt->close();
        flash('success', "Task '{$title}' posted. Students can now apply.");
    }
    redirect(APP_URL . '/admin/tasks.php');
}

// Filters
$ftab  = $_GET['tab']  ?? 'tasks';     // tasks | applications
$fstat = $_GET['status'] ?? 'open';

// Fetch tasks (manager sees only their block)
$twhere = $is_super ? "1=1" : "t.block IN ('any',?)";
if ($is_super) {
    $tasks = db_query($conn,
        "SELECT t.*, a.full_name AS poster_name,
                (SELECT COUNT(*) FROM task_applications ta WHERE ta.task_id=t.id) AS total_apps,
                (SELECT COUNT(*) FROM task_applications ta WHERE ta.task_id=t.id AND ta.status='applied') AS new_apps
         FROM tasks t JOIN admins a ON a.id=t.posted_by
         WHERE t.status=? ORDER BY t.deadline ASC", 's', $fstat);
} else {
    $tasks = db_query($conn,
        "SELECT t.*, a.full_name AS poster_name,
                (SELECT COUNT(*) FROM task_applications ta WHERE ta.task_id=t.id) AS total_apps,
                (SELECT COUNT(*) FROM task_applications ta WHERE ta.task_id=t.id AND ta.status='applied') AS new_apps
         FROM tasks t JOIN admins a ON a.id=t.posted_by
         WHERE t.status=? AND t.block IN ('any',?) ORDER BY t.deadline ASC", 'ss', $fstat, $my_block);
}

// Fetch pending task applications (submitted by students, awaiting verification)
if ($is_super) {
    $pending_apps = db_query($conn,
        "SELECT ta.*, t.title AS task_title, t.reward_amount, t.category,
                s.full_name, s.student_id AS sid, r.room_number
         FROM task_applications ta
         JOIN tasks t ON t.id=ta.task_id
         JOIN students s ON s.id=ta.student_id
         LEFT JOIN allocations al ON al.student_id=s.id AND al.status='active'
         LEFT JOIN rooms r ON r.id=al.room_id
         WHERE ta.status IN ('applied','submitted')
         ORDER BY ta.applied_at ASC");
} else {
    $pending_apps = db_query($conn,
        "SELECT ta.*, t.title AS task_title, t.reward_amount, t.category,
                s.full_name, s.student_id AS sid, r.room_number
         FROM task_applications ta
         JOIN tasks t ON t.id=ta.task_id
         JOIN students s ON s.id=ta.student_id
         LEFT JOIN allocations al ON al.student_id=s.id AND al.status='active'
         LEFT JOIN rooms r ON r.id=al.room_id
         WHERE ta.status IN ('applied','submitted') AND t.block IN ('any',?)
         ORDER BY ta.applied_at ASC", 's', $my_block);
}

$task_counts = [];
foreach(['open','in_progress','completed','cancelled'] as $ts) {
    $r = db_row($conn,"SELECT COUNT(*) AS c FROM tasks WHERE status=?", 's', $ts);
    $task_counts[$ts] = $r['c'] ?? 0;
}
$cat_labels = ['admin_support'=>'🗂️ Admin Support','room_finding'=>'🏠 Room Finding','cleaning'=>'🧹 Cleaning','maintenance_assist'=>'🔧 Maintenance','event_helper'=>'🎉 Event Helper','data_entry'=>'📝 Data Entry','other'=>'📌 Other'];

$page_title = 'Task Management';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.85);backdrop-filter:blur(10px);z-index:500;align-items:center;justify-content:center;padding:1rem;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:520px;animation:mIn .3s ease;max-height:92vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1.1rem;padding-bottom:.75rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.3rem;}
.sub-tabs{display:flex;gap:.2rem;flex-wrap:wrap;margin-bottom:1.2rem;}
.sub-tab{padding:.4rem .9rem;border-radius:var(--r1);font-size:.82rem;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--ts);font-family:var(--fb);transition:all .2s;text-decoration:none;}
.sub-tab:hover{color:var(--tp);}
.sub-tab.active{background:rgba(99,179,237,.1);color:var(--blue);}
.task-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r2);padding:1.1rem 1.2rem;margin-bottom:.65rem;transition:border-color .2s;}
.task-card:hover{border-color:rgba(99,179,237,.2);}
</style>
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest Admin</a>
  <div class="nav-links">
    <span style="font-size:.82rem;color:var(--ts);">👤 <?= clean($_SESSION['admin_name']) ?><?= $my_block?" (Block {$my_block})":'' ?></span>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>
<div class="sidebar-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main class="dash-main">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:.3rem;">
      <div class="page-title" style="margin:0;">Task Exchange System</div>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add').classList.add('open')">+ Post New Task</button>
    </div>
    <div class="page-sub">Students complete tasks to earn fee credits when they cannot pay on time</div>

    <!-- STATS -->
    <div class="g4" style="margin-bottom:1.3rem;">
      <div class="stat-card blue"><div class="stat-val"><?= $task_counts['open'] ?></div><div class="stat-label">Open Tasks</div></div>
      <div class="stat-card cyan"><div class="stat-val"><?= $task_counts['in_progress'] ?></div><div class="stat-label">In Progress</div></div>
      <div class="stat-card green"><div class="stat-val"><?= $task_counts['completed'] ?></div><div class="stat-label">Completed</div></div>
      <div class="stat-card pink"><div class="stat-val"><?= count($pending_apps) ?></div><div class="stat-label">Awaiting Verification</div></div>
    </div>

    <!-- MAIN TABS -->
    <div class="sub-tabs">
      <a class="sub-tab <?= $ftab==='tasks'?'active':'' ?>" href="tasks.php?tab=tasks&status=<?= $fstat ?>">📋 Tasks</a>
      <a class="sub-tab <?= $ftab==='applications'?'active':'' ?>" href="tasks.php?tab=applications">
        📥 Applications <?= count($pending_apps)>0?"<span style='color:var(--danger);font-weight:700;'>(".count($pending_apps).")</span>":'' ?>
      </a>
    </div>

    <?php if($ftab==='tasks'): ?>

    <!-- TASK STATUS SUB-TABS -->
    <div style="display:flex;gap:.2rem;flex-wrap:wrap;margin-bottom:1rem;">
      <?php foreach(['open'=>'Open','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$lbl): ?>
        <a style="padding:.3rem .7rem;border-radius:20px;font-size:.78rem;font-weight:500;text-decoration:none;
           <?= $fstat===$k?'background:rgba(99,179,237,.12);color:var(--blue);':'color:var(--ts);' ?>"
           href="tasks.php?tab=tasks&status=<?= $k ?>">
          <?= $lbl ?> (<?= $task_counts[$k] ?>)
        </a>
      <?php endforeach; ?>
    </div>

    <?php if(empty($tasks)): ?>
      <div class="glass" style="padding:3rem;text-align:center;opacity:.55;"><div style="font-size:2.5rem;">📋</div><div style="margin-top:.75rem;font-size:.875rem;color:var(--ts);">No <?= $fstat ?> tasks found.</div></div>
    <?php else:
      foreach($tasks as $t):
        $overdue = strtotime($t['deadline']) < time() && $t['status']==='open';
    ?>
    <div class="task-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.3rem;">
            <div style="font-size:.9rem;font-weight:700;"><?= clean($t['title']) ?></div>
            <span class="tag tag-<?= ['open'=>'blue','in_progress'=>'cyan','completed'=>'blue','cancelled'=>'purple'][$t['status']] ?>"><?= $cat_labels[$t['category']] ?></span>
            <?php if($t['new_apps']>0): ?><span style="background:rgba(252,129,129,.15);color:var(--danger);font-size:.72rem;padding:.2rem .5rem;border-radius:20px;font-weight:700;"><?= $t['new_apps'] ?> new</span><?php endif; ?>
            <?php if($overdue): ?><span style="background:rgba(252,129,129,.12);color:var(--danger);font-size:.72rem;padding:.2rem .5rem;border-radius:20px;">OVERDUE</span><?php endif; ?>
          </div>
          <div style="font-size:.82rem;color:var(--ts);line-height:1.55;margin-bottom:.5rem;"><?= clean(substr($t['description'],0,150)) ?><?= strlen($t['description'])>150?'…':'' ?></div>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;font-size:.78rem;color:var(--ts);">
            <span>💰 <strong style="color:var(--green);">৳<?= number_format($t['reward_amount'],0) ?></strong> credit</span>
            <span>👥 <?= $t['total_apps'] ?>/<?= $t['max_applicants'] ?> applicants</span>
            <span>📅 Deadline: <span style="color:<?= $overdue?'var(--danger)':'inherit' ?>"><?= date('M d, Y',strtotime($t['deadline'])) ?></span></span>
            <span>🏠 <?= $t['block']==='any'?'Any Block':'Block '.$t['block'] ?></span>
            <span>👤 <?= clean($t['poster_name']) ?></span>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.4rem;flex-shrink:0;align-items:flex-end;">
          <span class="badge badge-<?= ['open'=>'available','in_progress'=>'pending','completed'=>'approved','cancelled'=>'rejected'][$t['status']] ?>">
            <?= ucfirst(str_replace('_',' ',$t['status'])) ?>
          </span>
          <?php if($t['status']==='open'): ?>
            <a class="btn btn-danger btn-sm" href="tasks.php?action=delete&id=<?= $t['id'] ?>"
               onclick="return confirm('Delete this task?')">🗑️</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php else: // APPLICATIONS TAB ?>
    <?php if(empty($pending_apps)): ?>
      <div class="glass" style="padding:3rem;text-align:center;opacity:.55;"><div style="font-size:2.5rem;">📥</div><div style="margin-top:.75rem;font-size:.875rem;color:var(--ts);">No pending applications to review.</div></div>
    <?php else:
      foreach($pending_apps as $ap):
    ?>
    <div class="task-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:.88rem;font-weight:700;margin-bottom:.2rem;"><?= clean($ap['task_title']) ?></div>
          <div style="font-size:.8rem;color:var(--ts);margin-bottom:.4rem;">
            👤 <?= clean($ap['full_name']) ?> (<?= clean($ap['sid']) ?>)
            <?= $ap['room_number'] ? ' · Room '.$ap['room_number'] : '' ?>
            · Applied <?= date('M d, Y',strtotime($ap['applied_at'])) ?>
          </div>
          <?php if($ap['submission_note']): ?>
          <div style="font-size:.82rem;padding:.5rem .75rem;background:rgba(99,179,237,.06);border-left:2px solid var(--blue);border-radius:0 var(--r1) var(--r1) 0;margin-bottom:.5rem;">
            <strong style="color:var(--blue);">Submission note:</strong> <?= clean($ap['submission_note']) ?>
          </div>
          <?php elseif($ap['student_note']): ?>
          <div style="font-size:.82rem;color:var(--ts);margin-bottom:.4rem;">Note: <?= clean($ap['student_note']) ?></div>
          <?php endif; ?>
          <div style="font-size:.78rem;color:var(--ts);">
            💰 Reward: <strong style="color:var(--green);">৳<?= number_format($ap['reward_amount'],0) ?></strong>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.4rem;align-items:flex-end;">
          <span class="badge badge-<?= ['applied'=>'pending','submitted'=>'available','in_progress'=>'pending'][$ap['status']] ?? 'pending' ?>">
            <?= ucfirst($ap['status']) ?>
          </span>
          <?php if($ap['status']==='applied'): ?>
            <a class="btn btn-success btn-sm" href="tasks.php?action=approve_app&id=<?= $ap['id'] ?>">✓ Approve Start</a>
          <?php elseif($ap['status']==='submitted'): ?>
            <button class="btn btn-primary btn-sm" onclick="openVerify(<?= $ap['id'] ?>,'<?= addslashes(clean($ap['task_title'])) ?>')">🔍 Verify</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; endif; ?>
  </main>
</div>

<!-- ADD TASK MODAL -->
<div class="modal-bg" id="modal-add">
  <div class="modal-box">
    <div class="modal-title">📋 Post New Task</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="add">
      <div class="form-group"><label class="form-label">Task Title *</label><input class="form-input" name="title" placeholder="e.g. Data entry for room registry" required></div>
      <div class="form-group"><label class="form-label">Description *</label><textarea class="form-input" name="description" rows="3" placeholder="What exactly the student needs to do, how long, what skills…" required></textarea></div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Category *</label>
          <select class="form-input" name="category" required>
            <?php foreach($cat_labels as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Fee Credit (৳) *</label><input class="form-input" type="number" name="reward_amount" min="100" step="100" placeholder="1500" required></div>
      </div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Max Applicants</label><input class="form-input" type="number" name="max_applicants" min="1" max="10" value="1"></div>
        <div class="form-group"><label class="form-label">Deadline *</label><input class="form-input" type="date" name="deadline" value="<?= date('Y-m-d',strtotime('+14 days')) ?>" required></div>
      </div>
      <?php if($is_super): ?>
      <div class="form-group"><label class="form-label">Block</label>
        <select class="form-input" name="block">
          <option value="any">Any Block</option>
          <option value="A">Block A</option>
          <option value="B">Block B</option>
          <option value="C">Block C</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group"><label class="form-label">Requirements / Notes</label><textarea class="form-input" name="requirements" rows="2" placeholder="Skills, availability, conditions…"></textarea></div>
      <div class="info-box" style="font-size:.8rem;">💡 Students with unpaid fees can apply for this task. The reward amount will be deducted from their outstanding fee balance upon successful completion.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Post Task</button>
      </div>
    </form>
  </div>
</div>

<!-- VERIFY MODAL -->
<div class="modal-bg" id="modal-verify">
  <div class="modal-box">
    <div class="modal-title">🔍 Verify Task Completion</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="verify">
      <input type="hidden" name="app_id"      id="verify-app-id">
      <div id="verify-task-name" style="font-size:.875rem;font-weight:600;color:var(--blue);padding:.5rem .75rem;background:rgba(99,179,237,.06);border-radius:var(--r1);border-left:2px solid var(--blue);"></div>
      <div class="form-group"><label class="form-label">Verdict *</label>
        <select class="form-input" name="verdict" required>
          <option value="complete">✅ Mark Complete — apply fee credit</option>
          <option value="reject">❌ Reject — task not completed properly</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Admin Note</label><textarea class="form-input" name="admin_note" rows="3" placeholder="Feedback for the student…"></textarea></div>
      <div class="warn-box" style="font-size:.8rem;">If marked complete, the task reward amount will be automatically credited to the student's fee balance.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-verify').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Verdict</button>
      </div>
    </form>
  </div>
</div>
<script>
function openVerify(id, title) {
  document.getElementById('verify-app-id').value         = id;
  document.getElementById('verify-task-name').textContent = title;
  document.getElementById('modal-verify').classList.add('open');
}
document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));
</script>
</body></html>