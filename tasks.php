<?php
// student/tasks.php  — Task Exchange for fee credit
require_once __DIR__ . '/../config/db.php';
require_student();
$sid     = (int)$_SESSION['student_id'];
$student = db_row($conn, "SELECT * FROM students WHERE id=?", 'i', $sid);

// ── APPLY FOR A TASK ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'apply') {
    $task_id   = (int)$_POST['task_id'];
    $pay_id    = $_POST['payment_id'] ? (int)$_POST['payment_id'] : null;
    $note      = clean($_POST['student_note'] ?? '');

    // Validate task is still open
    $task = db_row($conn, "SELECT * FROM tasks WHERE id=? AND status='open' AND deadline >= CURDATE()", 'i', $task_id);
    if (!$task) { flash('error', 'This task is no longer available.'); redirect(APP_URL.'/student/tasks.php'); }

    // Check not already applied
    $already = db_row($conn, "SELECT id FROM task_applications WHERE task_id=? AND student_id=?", 'ii', $task_id, $sid);
    if ($already) { flash('error', 'You have already applied for this task.'); redirect(APP_URL.'/student/tasks.php'); }

    // Check slots
    $taken = db_row($conn,"SELECT COUNT(*) AS c FROM task_applications WHERE task_id=? AND status NOT IN ('rejected')",'i',$task_id)['c']??0;
    if ($taken >= $task['max_applicants']) { flash('error','No slots available for this task.'); redirect(APP_URL.'/student/tasks.php'); }

    // Validate payment belongs to student if given
    if ($pay_id) {
        $pay_check = db_row($conn,"SELECT id FROM payments WHERE id=? AND student_id=? AND status IN ('pending','overdue')",'ii',$pay_id,$sid);
        if (!$pay_check) $pay_id = null;
    }

    $st = $conn->prepare("INSERT INTO task_applications (task_id,student_id,payment_id,student_note) VALUES (?,?,?,?)");
    $st->bind_param('iiis', $task_id, $sid, $pay_id, $note);
    $st->execute(); $st->close();

    flash('success', 'Application submitted! Wait for admin approval to begin the task.');
    redirect(APP_URL . '/student/tasks.php?tab=mine');
}

// ── SUBMIT COMPLETION ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'submit') {
    $app_id = (int)$_POST['app_id'];
    $note   = clean($_POST['submission_note'] ?? '');
    if (!$note) { flash('error','Please describe what you did.'); redirect(APP_URL.'/student/tasks.php?tab=mine'); }
    $chk = db_row($conn,"SELECT id FROM task_applications WHERE id=? AND student_id=? AND status='in_progress'",'ii',$app_id,$sid);
    if ($chk) {
        db_exec($conn,"UPDATE task_applications SET status='submitted', submission_note=? WHERE id=?",'si',$note,$app_id);
        flash('success','Work submitted! Admin will verify and apply your fee credit.');
    } else { flash('error','Cannot submit — check your application status.'); }
    redirect(APP_URL . '/student/tasks.php?tab=mine');
}

// ── CANCEL APPLICATION ────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    $chk = db_row($conn,"SELECT id,task_id FROM task_applications WHERE id=? AND student_id=? AND status='applied'",'ii',$app_id,$sid);
    if ($chk) {
        db_exec($conn,"DELETE FROM task_applications WHERE id=?",'i',$app_id);
        db_exec($conn,"UPDATE tasks SET status='open' WHERE id=? AND status='in_progress'",'i',$chk['task_id']);
        flash('success','Application cancelled.');
    }
    redirect(APP_URL . '/student/tasks.php?tab=mine');
}

$tab = $_GET['tab'] ?? 'browse';

// Fetch open tasks (not already applied)
$open_tasks = db_query($conn,
    "SELECT t.*, a.full_name AS poster_name,
            (SELECT COUNT(*) FROM task_applications ta WHERE ta.task_id=t.id AND ta.status NOT IN ('rejected')) AS taken
     FROM tasks t
     JOIN admins a ON a.id=t.posted_by
     WHERE t.status='open' AND t.deadline >= CURDATE()
       AND t.id NOT IN (SELECT task_id FROM task_applications WHERE student_id=?)
     ORDER BY t.reward_amount DESC, t.deadline ASC", 'i', $sid);

// Fetch my applications
$my_apps = db_query($conn,
    "SELECT ta.*, t.title, t.description, t.reward_amount, t.deadline, t.category,
            p.amount AS fee_amount, p.semester, p.status AS pay_status
     FROM task_applications ta
     JOIN tasks t ON t.id=ta.task_id
     LEFT JOIN payments p ON p.id=ta.payment_id
     WHERE ta.student_id=?
     ORDER BY ta.applied_at DESC", 'i', $sid);

// Unpaid fees for linking
$unpaid_fees = db_query($conn,
    "SELECT p.*, r.room_number FROM payments p
     JOIN allocations al ON al.id=p.allocation_id
     JOIN rooms r ON r.id=al.room_id
     WHERE p.student_id=? AND p.status IN ('pending','overdue')", 'i', $sid);

$cat_labels = [
    'admin_support'     => '🗂️ Admin Support',
    'room_finding'      => '🏠 Room Finding',
    'cleaning'          => '🧹 Cleaning',
    'maintenance_assist'=> '🔧 Maintenance',
    'event_helper'      => '🎉 Event Helper',
    'data_entry'        => '📝 Data Entry',
    'other'             => '📌 Other',
];
$status_badge = [
    'applied'    => 'pending',
    'approved'   => 'available',
    'in_progress'=> 'available',
    'submitted'  => 'pending',
    'completed'  => 'approved',
    'rejected'   => 'rejected',
];

$page_title = 'Task Exchange';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.88);backdrop-filter:blur(10px);z-index:500;align-items:center;justify-content:center;padding:1rem;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:500px;animation:mIn .3s ease;max-height:92vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal-title{font-family:var(--fd);font-size:1.1rem;font-weight:700;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:1px solid var(--gb);}
.modal-footer{display:flex;gap:.7rem;justify-content:flex-end;margin-top:1.2rem;}
.task-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r2);padding:1.1rem 1.2rem;transition:border-color .25s,transform .25s;margin-bottom:.65rem;}
.task-card:hover{border-color:rgba(99,179,237,.25);transform:translateY(-2px);}
.sub-tabs{display:flex;gap:.25rem;margin-bottom:1.2rem;}
.sub-tab{padding:.42rem 1rem;border-radius:var(--r1);font-size:.83rem;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--ts);font-family:var(--fb);transition:all .2s;text-decoration:none;}
.sub-tab.active{background:rgba(99,179,237,.1);color:var(--blue);}
.how-step{display:flex;gap:.75rem;align-items:flex-start;padding:.9rem;background:rgba(255,255,255,.02);border-radius:var(--r2);border:1px solid var(--gb);}
.how-num{width:32px;height:32px;border-radius:50%;background:var(--ag);display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-weight:800;font-size:.85rem;color:#fff;flex-shrink:0;}
</style>

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
    <div class="page-title">🔄 Task Exchange</div>
    <div class="page-sub">Can't pay your fee right now? Complete tasks to earn fee credits instead</div>

    <!-- HERO BANNER (shown when student has unpaid fees) -->
    <?php if(!empty($unpaid_fees)): ?>
    <div style="background:linear-gradient(135deg,rgba(183,148,244,.12),rgba(99,179,237,.1));border:1px solid rgba(183,148,244,.3);border-radius:var(--r3);padding:1.4rem 1.6rem;margin-bottom:1.4rem;display:flex;gap:1.25rem;align-items:center;flex-wrap:wrap;">
      <div style="font-size:2.2rem;">💡</div>
      <div style="flex:1;min-width:200px;">
        <div style="font-family:var(--fd);font-size:1rem;font-weight:700;margin-bottom:.3rem;">You have <?= count($unpaid_fees) ?> unpaid fee<?= count($unpaid_fees)>1?'s':'' ?></div>
        <div style="font-size:.85rem;color:var(--ts);line-height:1.55;">Apply for admin-posted tasks below. Completing a task earns you <strong style="color:var(--green);">fee credit</strong> that is automatically deducted from your outstanding balance.</div>
      </div>
      <?php foreach($unpaid_fees as $uf): ?>
        <div style="background:rgba(252,129,129,.1);border:1px solid rgba(252,129,129,.25);border-radius:var(--r2);padding:.65rem 1rem;font-size:.82rem;text-align:center;">
          <div style="color:var(--danger);font-family:var(--fm);font-weight:700;">৳<?= number_format($uf['amount'],0) ?></div>
          <div style="color:var(--ts);"><?= clean($uf['semester']) ?></div>
          <div style="color:var(--ts);font-size:.75rem;">Due <?= date('M d',strtotime($uf['due_date'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- HOW IT WORKS (compact) -->
    <div class="glass" style="padding:1.1rem;margin-bottom:1.4rem;">
      <div style="font-family:var(--fd);font-size:.9rem;font-weight:700;margin-bottom:.85rem;">How Task Exchange Works</div>
      <div class="g4" style="gap:.65rem;">
        <div class="how-step"><div class="how-num">1</div><div style="font-size:.8rem;"><strong>Browse</strong><br><span style="color:var(--ts);">Find a task you can do</span></div></div>
        <div class="how-step"><div class="how-num">2</div><div style="font-size:.8rem;"><strong>Apply</strong><br><span style="color:var(--ts);">Link it to your unpaid fee</span></div></div>
        <div class="how-step"><div class="how-num">3</div><div style="font-size:.8rem;"><strong>Complete</strong><br><span style="color:var(--ts);">Do the task & submit proof</span></div></div>
        <div class="how-step"><div class="how-num">4</div><div style="font-size:.8rem;"><strong>Credit</strong><br><span style="color:var(--ts);">Fee is reduced automatically</span></div></div>
      </div>
    </div>

    <!-- TABS -->
    <div class="sub-tabs">
      <a class="sub-tab <?= $tab==='browse'?'active':'' ?>" href="tasks.php?tab=browse">
        🔍 Browse Tasks (<?= count($open_tasks) ?>)
      </a>
      <a class="sub-tab <?= $tab==='mine'?'active':'' ?>" href="tasks.php?tab=mine">
        📋 My Applications (<?= count($my_apps) ?>)
      </a>
    </div>

    <?php if($tab === 'browse'): ?>
    <!-- ─── BROWSE OPEN TASKS ─── -->
    <?php if(empty($open_tasks)): ?>
      <div class="glass" style="padding:3rem;text-align:center;opacity:.6;">
        <div style="font-size:2.5rem;margin-bottom:.75rem;">📭</div>
        <div style="font-family:var(--fd);font-size:1rem;font-weight:700;">No tasks available right now</div>
        <div style="color:var(--ts);margin-top:.3rem;font-size:.875rem;">Check back soon — admin posts new tasks regularly.</div>
      </div>
    <?php else:
      foreach($open_tasks as $t):
        $slots_left = $t['max_applicants'] - $t['taken'];
        $overdue    = strtotime($t['deadline']) < time();
    ?>
    <div class="task-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
          <!-- Title row -->
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.35rem;">
            <span style="font-family:var(--fd);font-size:.95rem;font-weight:700;"><?= clean($t['title']) ?></span>
            <span class="tag tag-<?= ['open'=>'blue','in_progress'=>'cyan'][$t['status']]??'blue' ?>"><?= $cat_labels[$t['category']] ?></span>
            <?php if($t['block']!=='any'): ?>
              <span class="tag tag-purple">Block <?= $t['block'] ?></span>
            <?php endif; ?>
          </div>
          <!-- Description -->
          <div style="font-size:.84rem;color:var(--ts);line-height:1.6;margin-bottom:.6rem;">
            <?= clean($t['description']) ?>
          </div>
          <?php if($t['requirements']): ?>
          <div style="font-size:.8rem;color:var(--ts);margin-bottom:.5rem;">
            <strong style="color:var(--warning);">Requirements:</strong> <?= clean($t['requirements']) ?>
          </div>
          <?php endif; ?>
          <!-- Meta -->
          <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.79rem;color:var(--ts);">
            <span>📅 Deadline: <span style="color:<?= $overdue?'var(--danger)':'var(--tp)' ?>"><?= date('M d, Y',strtotime($t['deadline'])) ?></span></span>
            <span>👥 <?= $slots_left ?> slot<?= $slots_left!==1?'s':'' ?> left</span>
            <span>👤 Posted by <?= clean($t['poster_name']) ?></span>
          </div>
        </div>
        <!-- Reward + Apply -->
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;flex-shrink:0;">
          <div style="text-align:center;background:rgba(104,211,145,.08);border:1px solid rgba(104,211,145,.25);border-radius:var(--r2);padding:.5rem .9rem;">
            <div style="font-family:var(--fm);font-size:1.2rem;font-weight:800;color:var(--success);">৳<?= number_format($t['reward_amount'],0) ?></div>
            <div style="font-size:.7rem;color:var(--ts);">fee credit</div>
          </div>
          <?php if($slots_left > 0 && !$overdue): ?>
            <button class="btn btn-primary btn-sm"
                    onclick="openApply(<?= $t['id'] ?>,'<?= addslashes(clean($t['title'])) ?>','<?= number_format($t['reward_amount'],0) ?>')">
              Apply Now →
            </button>
          <?php else: ?>
            <span style="font-size:.78rem;color:var(--tm);"><?= $overdue?'Expired':'Full' ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>

    <?php else: ?>
    <!-- ─── MY APPLICATIONS ─── -->
    <?php if(empty($my_apps)): ?>
      <div class="glass" style="padding:3rem;text-align:center;opacity:.6;">
        <div style="font-size:2.5rem;margin-bottom:.75rem;">📋</div>
        <div style="font-family:var(--fd);font-size:1rem;font-weight:700;">No applications yet</div>
        <div style="color:var(--ts);margin-top:.3rem;font-size:.875rem;">Browse open tasks and apply to earn fee credits.</div>
        <a class="btn btn-primary" href="tasks.php?tab=browse" style="margin-top:1rem;">Browse Tasks →</a>
      </div>
    <?php else:
      foreach($my_apps as $a):
    ?>
    <div class="task-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
          <div style="font-family:var(--fd);font-size:.9rem;font-weight:700;margin-bottom:.25rem;"><?= clean($a['title']) ?></div>
          <div style="font-size:.78rem;color:var(--ts);margin-bottom:.4rem;">
            Applied <?= date('M d, Y',strtotime($a['applied_at'])) ?>
            · <?= $cat_labels[$a['category']] ?>
            · Reward: <strong style="color:var(--success);">৳<?= number_format($a['reward_amount'],0) ?></strong>
            <?php if($a['fee_amount']): ?>
              · Linked to fee: ৳<?= number_format($a['fee_amount'],0) ?> (<?= clean($a['semester']) ?>)
            <?php endif; ?>
          </div>

          <!-- Status-specific messages -->
          <?php if($a['status']==='applied'): ?>
            <div class="info-box" style="font-size:.8rem;">⏳ Waiting for admin to approve your application to start.</div>
          <?php elseif($a['status']==='in_progress'): ?>
            <div class="ok-box" style="font-size:.8rem;">✅ Approved! Complete the task and submit your work below.</div>
          <?php elseif($a['status']==='submitted'): ?>
            <div class="warn-box" style="font-size:.8rem;">🔍 Submitted for verification. Admin will review and apply your credit.</div>
          <?php elseif($a['status']==='completed'): ?>
            <div class="ok-box" style="font-size:.8rem;">🎉 Completed! ৳<?= number_format($a['reward_amount'],0) ?> has been credited to your fee.</div>
          <?php elseif($a['status']==='rejected'): ?>
            <div class="err-box" style="font-size:.8rem;">❌ Rejected. <?= $a['admin_note']?clean($a['admin_note']):'Please contact admin for details.' ?></div>
          <?php endif; ?>

          <?php if($a['admin_note'] && in_array($a['status'],['completed','rejected'])): ?>
          <div style="font-size:.8rem;margin-top:.4rem;color:var(--ts);">Admin note: <?= clean($a['admin_note']) ?></div>
          <?php endif; ?>
        </div>

        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0;">
          <span class="badge badge-<?= $status_badge[$a['status']] ?? 'pending' ?>">
            <?= ucfirst(str_replace('_',' ',$a['status'])) ?>
          </span>
          <!-- Actions per status -->
          <?php if($a['status']==='in_progress'): ?>
            <button class="btn btn-primary btn-sm" onclick="openSubmit(<?= $a['id'] ?>,'<?= addslashes(clean($a['title'])) ?>')">
              📤 Submit Work
            </button>
          <?php elseif($a['status']==='applied'): ?>
            <a class="btn btn-danger btn-sm" href="tasks.php?action=cancel&id=<?= $a['id'] ?>"
               onclick="return confirm('Cancel this task application?')">Cancel</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; endif; ?>
  </main>
</div>

<!-- APPLY MODAL -->
<div class="modal-bg" id="modal-apply">
  <div class="modal-box">
    <div class="modal-title">📋 Apply for Task</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="apply">
      <input type="hidden" name="task_id"     id="apply-task-id">
      <div id="apply-task-name" style="font-size:.9rem;font-weight:600;color:var(--blue);padding:.6rem .85rem;background:rgba(99,179,237,.07);border-radius:var(--r2);border-left:2px solid var(--blue);"></div>
      <div id="apply-reward" style="font-size:.85rem;color:var(--ts);margin-top:-.3rem;"></div>
      <?php if(!empty($unpaid_fees)): ?>
      <div class="form-group">
        <label class="form-label">Link to Unpaid Fee (optional but recommended)</label>
        <select class="form-input" name="payment_id">
          <option value="">— No link —</option>
          <?php foreach($unpaid_fees as $uf): ?>
            <option value="<?= $uf['id'] ?>">
              ৳<?= number_format($uf['amount'],0) ?> — <?= clean($uf['semester']) ?> — Room <?= $uf['room_number'] ?> (Due <?= date('M d',strtotime($uf['due_date'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="payment_id" value="">
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label">Brief note to admin (optional)</label>
        <textarea class="form-input" name="student_note" rows="3" placeholder="Why you'd like this task, your availability, relevant experience…"></textarea>
      </div>
      <div class="info-box" style="font-size:.8rem;">Once approved by admin, you will see the task under <strong>My Applications</strong> with status "In Progress". Complete the work and submit proof to receive your fee credit.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-apply').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Application</button>
      </div>
    </form>
  </div>
</div>

<!-- SUBMIT WORK MODAL -->
<div class="modal-bg" id="modal-submit">
  <div class="modal-box">
    <div class="modal-title">📤 Submit Completed Work</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="form_action" value="submit">
      <input type="hidden" name="app_id"      id="submit-app-id">
      <div id="submit-task-name" style="font-size:.9rem;font-weight:600;color:var(--blue);padding:.6rem .85rem;background:rgba(99,179,237,.07);border-radius:var(--r2);border-left:2px solid var(--blue);"></div>
      <div class="form-group">
        <label class="form-label">Describe what you did *</label>
        <textarea class="form-input" name="submission_note" rows="5"
                  placeholder="Describe in detail: what you did, when, any results or outcomes. Admin will use this to verify your work." required></textarea>
      </div>
      <div class="warn-box" style="font-size:.8rem;">⚠️ Once submitted, admin will verify. Fee credit is applied only after admin confirms completion.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-submit').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Work</button>
      </div>
    </form>
  </div>
</div>

<script>
function openApply(id, title, reward) {
  document.getElementById('apply-task-id').value         = id;
  document.getElementById('apply-task-name').textContent = title;
  document.getElementById('apply-reward').textContent    = '💰 Fee credit if completed: ৳' + reward;
  document.getElementById('modal-apply').classList.add('open');
}
function openSubmit(id, title) {
  document.getElementById('submit-app-id').value         = id;
  document.getElementById('submit-task-name').textContent= title;
  document.getElementById('modal-submit').classList.add('open');
}
document.querySelectorAll('.modal-bg').forEach(m =>
  m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); })
);
</script>
</body></html>