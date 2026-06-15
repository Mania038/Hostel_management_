<?php
// auth/register.php
require_once __DIR__ . '/../config/db.php';
session_start_safe();

if (!empty($_SESSION['student_id'])) redirect(APP_URL . '/student/dashboard.php');

$error = '';
$data  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'first_name'   => trim($_POST['first_name']   ?? ''),
        'last_name'    => trim($_POST['last_name']    ?? ''),
        'student_id'   => trim($_POST['student_id']   ?? ''),
        'email'        => trim($_POST['email']        ?? ''),
        'department'   => trim($_POST['department']   ?? ''),
        'year'         => (int)($_POST['year']        ?? 1),
        'gender'       => trim($_POST['gender']       ?? ''),
        'phone'        => trim($_POST['phone']        ?? ''),
        'password'     => $_POST['password']          ?? '',
        'password2'    => $_POST['password2']         ?? '',
    ];

    // Validation
    if (empty($data['first_name']) || empty($data['last_name']) ||
        empty($data['student_id']) || empty($data['email'])     ||
        empty($data['department']) || empty($data['gender'])    ||
        empty($data['password'])) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($data['password']) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($data['password'] !== $data['password2']) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicates
        $dup = db_row($conn,
            "SELECT id FROM students WHERE student_id=? OR email=?",
            'ss', $data['student_id'], $data['email']
        );
        if ($dup) {
            $error = 'Student ID or email already registered.';
        } else {
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $full = $data['first_name'] . ' ' . $data['last_name'];
            $id   = db_insert($conn,
                "INSERT INTO students (student_id,full_name,email,password,phone,department,year_of_study,gender)
                 VALUES (?,?,?,?,?,?,?,?)",
                'ssssssds',
                $data['student_id'], $full, $data['email'], $hash,
                $data['phone'], $data['department'], $data['year'], $data['gender']
            );
            if ($id) {
                flash('success', 'Account created! Please sign in.');
                redirect(APP_URL . '/auth/login.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.auth-wrap{min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;padding:2rem;position:relative;}
.orb{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none;}
.orb1{width:300px;height:300px;background:rgba(183,148,244,.07);top:5%;right:5%;}
.orb2{width:250px;height:250px;background:rgba(99,179,237,.06);bottom:5%;left:5%;}
.auth-card{width:100%;max-width:600px;background:rgba(243, 243, 243, 0.92);border:1px solid var(--gb);border-radius:24px;padding:2.2rem;backdrop-filter:blur(20px);}
</style>

<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">Sign In</a>
  </div>
</nav>

<div class="auth-wrap">
  <div class="orb orb1"></div><div class="orb orb2"></div>
  <div class="auth-card">
    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="font-family:var(--fd);font-size:1.5rem;font-weight:800;background:var(--ag);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Create Account</div>
      <div style="font-size:.8rem;color:var(--ts);margin-top:.2rem;">UniNest Hostel Management System</div>
    </div>

    <?php if($error): ?><div class="err-box" style="margin-bottom:1rem;">❌ <?= clean($error) ?></div><?php endif; ?>

    <form method="POST" style="display:flex;flex-direction:column;gap:.9rem;">
      <div class="g2">
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input class="form-input" name="first_name" value="<?= clean($data['first_name']??'') ?>" placeholder="e.g. Aryan" required>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input class="form-input" name="last_name" value="<?= clean($data['last_name']??'') ?>" placeholder="e.g. Hossain" required>
        </div>
      </div>
      <div class="g2">
        <div class="form-group">
          <label class="form-label">Student ID *</label>
          <input class="form-input" name="student_id" value="<?= clean($data['student_id']??'') ?>" placeholder="2024-CS-045" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-input" name="phone" value="<?= clean($data['phone']??'') ?>" placeholder="+880 1XXX-XXXXXX">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address *</label>
        <input class="form-input" type="email" name="email" value="<?= clean($data['email']??'') ?>" placeholder="student@university.edu" required>
      </div>
      <div class="g2">
        <div class="form-group">
          <label class="form-label">Department *</label>
          <select class="form-input" name="department" required>
            <option value="">Select department</option>
            <?php
            $depts = ['Computer Science & Engineering','Electrical Engineering','Mechanical Engineering',
                      'Civil Engineering','Business Administration','Mathematics','Physics','Chemistry'];
            foreach($depts as $d): ?>
              <option <?= ($data['department']??'')===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Year of Study *</label>
          <select class="form-input" name="year" required>
            <?php for($y=1;$y<=4;$y++): ?>
              <option value="<?= $y ?>" <?= ($data['year']??1)==$y?'selected':'' ?>><?= $y ?>th Year</option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Gender *</label>
        <select class="form-input" name="gender" required>
          <option value="">Select gender</option>
          <option value="male"   <?= ($data['gender']??'')==='male'?'selected':'' ?>>Male</option>
          <option value="female" <?= ($data['gender']??'')==='female'?'selected':'' ?>>Female</option>
          <option value="other"  <?= ($data['gender']??'')==='other'?'selected':'' ?>>Other</option>
        </select>
      </div>
      <div class="g2">
        <div class="form-group">
          <label class="form-label">Password * (min 8 chars)</label>
          <input class="form-input" type="password" name="password" placeholder="Create a strong password" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password *</label>
          <input class="form-input" type="password" name="password2" placeholder="Repeat password" required>
        </div>
      </div>
      <button class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:.25rem;" type="submit">Create Account →</button>
      <div style="text-align:center;font-size:.825rem;color:var(--ts);">
        Already have an account? <a href="<?= APP_URL ?>/auth/login.php" style="color:var(--blue);">Sign in here</a>
      </div>
    </form>
  </div>
</div>
</body></html>
