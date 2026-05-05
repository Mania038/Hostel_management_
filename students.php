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
    <a class="nav-link" href="<?= APP_URL