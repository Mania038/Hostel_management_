<?php
// auth/login.php
require_once __DIR__ . '/../config/db.php';
session_start_safe();

// Already logged in?
if (!empty($_SESSION['student_id'])) redirect(APP_URL . '/student/dashboard.php');
if (!empty($_SESSION['admin_id']))   redirect(APP_URL . '/admin/dashboard.php');

$role    = $_GET['role']    ?? 'student';
$timeout = $_GET['timeout'] ?? false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = $_POST['role']     ?? 'student';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif ($role === 'admin') {
        $admin = db_row($conn,
            "SELECT * FROM admins WHERE username=? OR email=? LIMIT 1",
            'ss', $username, $username
        );
        // Demo: accept "Admin@123" OR check bcrypt (XAMPP sample password = "password")
        if ($admin && (password_verify($password, $admin['password']) || $password === 'Admin@123')) {
             $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_name']     = $admin['full_name'];
            $_SESSION['admin_role']     = $admin['role'];
            $_SESSION['manager_level']  = $admin['manager_level'] ?? 'super';
            $_SESSION['assigned_block'] = $admin['assigned_block'] ?? null;
            $_SESSION['last_active']    = time();
            flash('success', 'Welcome back, ' . $admin['full_name'] . '!');
            redirect(APP_URL . '/admin/dashboard.php');
        } else {
            $error = 'Invalid admin credentials.';
        }
    } else {
        $student = db_row($conn,
            "SELECT * FROM students WHERE (student_id=? OR email=?) AND status='active' LIMIT 1",
            'ss', $username, $username
        );
        if ($student && (password_verify($password, $student['password']) || $password === 'Student@123')) {
            $_SESSION['student_id']   = $student['id'];
            $_SESSION['student_name'] = $student['full_name'];
            $_SESSION['student_sid']  = $student['student_id'];
            $_SESSION['last_active']  = time();
            flash('success', 'Welcome back, ' . $student['full_name'] . '!');
            redirect(APP_URL . '/student/dashboard.php');
        } else {
            $error = 'Invalid Student ID / password, or account inactive.';
        }
    }
}

$page_title = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
body{background:rgba(240,241,245,.9);}
.auth-wrap{min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;padding:2rem;position:relative;overflow:hidden;}
.orb{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none;}
.orb1{width:300px;height:300px;background:rgba(99,179,237,.06);top:10%;left:10%;animation:orbf 8s ease-in-out infinite;}
.orb2{width:250px;height:250px;background:rgba(183,148,244,.07);bottom:10%;right:10%;animation:orbf 8s ease-in-out infinite 3s;}
@keyframes orbf{0%,100%{transform:translateY(0)}50%{transform:translateY(-20px)}}
.auth-card{width:100%;max-width:430px;background:rgba(246, 247, 251, 0.92);border:1px solid var(--gb);border-radius:24px;padding:2.2rem;backdrop-filter:blur(20px);}
.auth-logo{text-align:center;margin-bottom:1.6rem;}
.auth-logo-txt{font-family:var(--fd);font-size:1.55rem;font-weight:800;background:var(--ag);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.auth-tabs{display:flex;gap:0;margin-bottom:1.6rem;border-radius:var(--r1);overflow:hidden;background:rgba(255,255,255,.04);padding:3px;}
.auth-tab{flex:1;padding:.5rem;font-size:.875rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:var(--ts);font-family:var(--fb);border-radius:calc(var(--r1) - 2px);transition:all .2s;}
.auth-tab.active{background:rgba(99,179,237,.15);color:var(--blue);}
</style>

<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="<?= APP_URL ?>">Home</a>
    <a class="nav-link" href="<?= APP_URL ?>/student/rooms.php">Rooms</a>
  </div>
</nav>

<div class="auth-wrap">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-txt">UniNest</div>
      <div style="font-size:.8rem;color:var(--ts);margin-top:.2rem;">Hostel Management System</div>
    </div>

    <!-- TABS -->
    <div class="auth-tabs">
      <button class="auth-tab <?= $role!=='admin'?'active':'' ?>" onclick="switchTab('student')">Student Login</button>
      <button class="auth-tab <?= $role==='admin'?'active':'' ?>" onclick="switchTab('admin')">Admin Login</button>
    </div>

    <?php if($timeout): ?>
      <div class="warn-box" style="margin-bottom:1rem;">⏱ Session expired. Please sign in again.</div>
    <?php endif; ?>
    <?php if($error): ?>
      <div class="err-box" style="margin-bottom:1rem;">❌ <?= clean($error) ?></div>
    <?php endif; ?>

    <!-- STUDENT FORM -->
    <form method="POST" id="form-student" style="display:<?= $role!=='admin'?'flex':'none' ?>;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="role" value="student">
      <div class="form-group">
        <label class="form-label">Student ID or Email</label>
        <input class="form-input" name="username" placeholder="2024-CS-001 or email@uni.edu" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" name="password" placeholder="Your password" required>
      </div>
      <div style="display:flex;justify-content:flex-end;">
        <a style="font-size:.8rem;color:var(--blue);">Forgot password?</a>
      </div>
      <button class="btn btn-primary btn-lg" style="width:100%;justify-content:center;" type="submit">Sign In →</button>
      <div class="info-box">🔑 Demo: Student ID <code>2024-CS-045</code>, Password <code>Student@123</code></div>
      <div style="text-align:center;font-size:.825rem;color:var(--ts);">
        No account? <a href="<?= APP_URL ?>/auth/register.php" style="color:var(--blue);">Register here</a>
      </div>
    </form>

    <!-- ADMIN FORM -->
    <form method="POST" id="form-admin" style="display:<?= $role==='admin'?'flex':'none' ?>;flex-direction:column;gap:.9rem;">
      <input type="hidden" name="role" value="admin">
      <div class="warn-box">⚠️ Restricted area. Authorized personnel only.</div>
      <div class="form-group">
        <label class="form-label">Admin Username or Email</label>
        <input class="form-input" name="username" placeholder="admin" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" name="password" placeholder="Admin password" required>
      </div>
      <button class="btn btn-primary btn-lg" style="width:100%;justify-content:center;background:linear-gradient(135deg,#b794f4,#63b3ed);" type="submit">Admin Login →</button>
      <div class="info-box">🔑 Demo: username <code>admin</code>, password <code>Admin@123</code></div>
    </form>

    <div style="margin-top:1.25rem;text-align:center;">
      <a class="btn btn-secondary btn-sm" href="<?= APP_URL ?>">← Back to Home</a>
    </div>
  </div>
</div>

<script>
function switchTab(role){
  document.getElementById('form-student').style.display = role==='student'?'flex':'none';
  document.getElementById('form-admin').style.display   = role==='admin'?'flex':'none';
  document.querySelectorAll('.auth-tab').forEach((t,i)=>{
    t.classList.toggle('active',(i===0&&role==='student')||(i===1&&role==='admin'));
  });
}
</script>
</body></html>
