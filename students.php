<?php
// admin/students.php
require_once __DIR__ . '/../config/db.php';
require_admin();

// DELETE
if (isset($_GET['action']) && $_GET['action']==='delete' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $chk = db_row($conn,"SELECT id FROM allocations WHERE student_id=? AND status='active' LIMIT 1",'i',$id);
    if ($chk) { flash('error','Cannot delete — student has an active room allocation.'); }
    else {
        db_exec($conn,"DELETE FROM students WHERE id=?",'i',$id);
        flash('success','Student record deleted.');
    }
    redirect(APP_URL.'/admin/students.php');
}

// ADD student
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form_action']??'')==='add') {
    $fn   = clean($_POST['full_name']   ?? '');
    $sid  = clean($_POST['student_id']  ?? '');
    $em   = clean($_POST['email']       ?? '');
    $dept = clean($_POST['department']  ?? '');
    $yr   = (int)($_POST['year']        ?? 1);
    $gen  = $_POST['gender'] ?? 'male';
    $ph   = clean($_POST['phone']       ?? '');
    $pw   = password_hash('Student@123', PASSWORD_DEFAULT);
    if (!$fn||!$sid||!$em||!$dept) { flash('error','Fill all required fields.'); }
    else {
        $dup = db_row($conn,"SELECT id FROM students WHERE student_id=? OR email=?",'ss',$sid,$em);
        if ($dup) { flash('error','Student ID or email already exists.'); }
        else {
            db_insert($conn,
                "INSERT INTO students (full_name,student_id,email,password,phone,department,year_of_study,gender) VALUES (?,?,?,?,?,?,?,?)",
                'ssssssds', $fn,$sid,$em,$pw,$ph,$dept,$yr,$gen
            );
            flash('success',"Student {$fn} added. Default password: Student\@123");
        }
    }
    redirect(APP_URL.'/admin/students.php');
}

// TOGGLE STATUS
if (isset($_GET['action']) && $_GET['action']==='toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = db_row($conn,"SELECT status FROM students WHERE id=?",'i',$id);
    $ns = $st['status']==='active' ? 'inactive' : 'active';
    db_exec($conn,"UPDATE students SET status=? WHERE id=?",'si',$ns,$id);
    flash('success',"Student status changed to {$ns}.");
    redirect(APP_URL.'/admin/students.php');
}

// Search & filter
$q    = clean($_GET['q']    ?? '');
$dept = clean($_GET['dept'] ?? '');
$where = ['1=1']; $ptypes=''; $pvals=[];
if ($q)    { $like="%$q%"; $where[]="(full_name LIKE ? OR student_id LIKE ? OR email LIKE ?)"; $ptypes.='sss'; $pvals[]=$like;$pvals[]=$like;$pvals[]=$like; }
if ($dept) { $where[]="department=?"; $ptypes.='s'; $pvals[]=$dept; }

$students = db_query($conn,
    "SELECT s.*, (SELECT COUNT(*) FROM allocations al WHERE al.student_id=s.id AND al.status='active') AS has_room
     FROM students s WHERE ".implode(' AND ',$where)." ORDER BY s.created_at DESC",
    $ptypes, ...$pvals);

$departments = db_query($conn,"SELECT DISTINCT department FROM students ORDER BY department");
$total = db_row($conn,"SELECT COUNT(*) AS cnt FROM students")['cnt'];

$page_title = 'Students';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,16,.8);backdrop-filter:blur(8px);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#0d1117;border:1px solid var(--gb);border-radius:24px;padding:2rem;width:100%;max-width:500px;animation:mIn .3s ease;max-height:90vh;overflow-y:auto;}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
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
      <div class="page-title" style="margin:0;">Student Records</div>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add').classList.add('open')">+ Add Student</button>
    </div>
    <div class="page-sub">Manage all <?= $total ?> registered students</div>

    <!-- SEARCH & FILTER -->
    <div class="glass" style="padding:1rem;margin-bottom:1.2rem;">
      <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:2;min-width:200px;">
          <label class="form-label">Search</label>
          <input class="form-input" name="q" value="<?= clean($q) ?>" placeholder="Name, student ID, or email…">
        </div>
        <div class="form-group" style="flex:1;min-width:180px;">
          <label class="form-label">Department</label>
          <select class="form-input" name="dept">
            <option value="">All Departments</option>
            <?php foreach($departments as $d): ?>
              <option value="<?= clean($d['department']) ?>" <?= $dept===$d['department']?'selected':'' ?>><?= clean($d['department']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit" style="height:42px;">Search</button>
        <a class="btn btn-secondary btn-sm" href="students.php" style="height:42px;">Reset</a>
      </form>
    </div>

    <div class="table-wrapper">
      <div class="table-header">
        <div class="table-title">Students (<?= count($students) ?> shown)</div>
      </div>
      <?php if(empty($students)): ?>
        <div style="padding:2.5rem;text-align:center;opacity:.55;"><div style="font-size:2rem;">👥</div><div style="margin-top:.5rem;font-size:.875rem;color:var(--ts);">No students found.</div></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Student</th><th>ID</th><th>Department</th><th>Year</th><th>Gender</th><th>Room</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach($students as $i=>$s): $ini=strtoupper(substr($s['full_name'],0,1).substr(explode(' ',$s['full_name'])[1]??'',0,1)); ?>
          <tr>
            <td style="color:var(--tm);"><?= str_pad($i+1,2,'0',STR_PAD_LEFT) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div class="avatar" style="width:30px;height:30px;font-size:.68rem;"><?= $ini ?></div>
                <div>
                  <div style="font-size:.875rem;font-weight:600;"><?= clean($s['full_name']) ?></div>
                  <div style="font-size:.73rem;color:var(--ts);"><?= clean($s['email']) ?></div>
                </div>
              </div>
            </td>
            <td><code><?= clean($s['student_id']) ?></code></td>
            <td style="font-size:.82rem;"><?= clean($s['department']) ?></td>
            <td><?= $s['year_of_study'] ?>th</td>
            <td><?= ucfirst($s['gender']) ?></td>
            <td>
              <?php if($s['has_room']):
                $rm=db_row($conn,"SELECT r.room_number FROM allocations al JOIN rooms r ON r.id=al.room_id WHERE al.student_id=? AND al.status='active'",'i',$s['id']);
              ?>
                <span class="badge badge-active"><?= $rm['room_number']??'—' ?></span>
              <?php else: ?>
                <span style="color:var(--tm);font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $s['status']==='active'?'active':'rejected' ?>"><?= ucfirst($s['status']) ?></span></td>
            <td style="color:var(--ts);font-size:.8rem;"><?= date('M d, Y',strtotime($s['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <a class="btn btn-secondary btn-sm" href="students.php?action=toggle&id=<?= $s['id'] ?>"
                   onclick="return confirm('Toggle status for <?= clean($s['full_name']) ?>?')">
                  <?= $s['status']==='active'?'🔒 Deactivate':'✅ Activate' ?>
                </a>
                <?php if(!$s['has_room']): ?>
                <a class="btn btn-danger btn-sm" href="students.php?action=delete&id=<?= $s['id'] ?>"
                   onclick="return confirm('Delete <?= clean($s['full_name']) ?>? This cannot be undone.')">🗑️</a>
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

<!-- ADD STUDENT MODAL -->
<div class="modal-bg" id="modal-add">
  <div class="modal-box">
    <div class="modal-title">👤 Add New Student</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:.85rem;">
      <input type="hidden" name="form_action" value="add">
      <div class="form-group"><label class="form-label">Full Name *</label><input class="form-input" name="full_name" placeholder="e.g. Aryan Hossain" required></div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Student ID *</label><input class="form-input" name="student_id" placeholder="2024-CS-001" required></div>
        <div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" placeholder="+880 1XXX-XXXXXX"></div>
      </div>
      <div class="form-group"><label class="form-label">Email *</label><input class="form-input" type="email" name="email" placeholder="student@uni.edu" required></div>
      <div class="form-group"><label class="form-label">Department *</label>
        <select class="form-input" name="department" required>
          <option value="">Select</option>
          <?php foreach(['Computer Science & Engineering','Electrical Engineering','Mechanical Engineering','Civil Engineering','Business Administration','Mathematics','Physics'] as $d): ?>
            <option value="<?= $d ?>"><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="g2">
        <div class="form-group"><label class="form-label">Year *</label>
          <select class="form-input" name="year" required>
            <?php for($y=1;$y<=4;$y++): ?><option value="<?= $y ?>"><?= $y ?>th Year</option><?php endfor; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Gender *</label>
          <select class="form-input" name="gender" required>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
      <div class="info-box" style="font-size:.8rem;">Default password will be set to <code>Student@123</code>. Student can change it after login.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Student</button>
      </div>
    </form>
  </div>
</div>
<script>document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));</script>
</body></html>
