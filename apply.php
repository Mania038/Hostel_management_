<?php
// student/apply.php
require_once __DIR__ . '/../config/db.php';
require_student();

$sid   = (int)$_SESSION['student_id'];
$error = '';

// Block re-application if one is already pending/approved/allocated
$existing = db_row($conn,
    "SELECT id, app_code, status FROM applications WHERE student_id=? AND status NOT IN ('rejected','withdrawn') LIMIT 1",
    'i', $sid
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $block    = $_POST['preferred_block']  ?? '';
    $type     = $_POST['preferred_type']   ?? '';
    $floor    = $_POST['preferred_floor']  ? (int)$_POST['preferred_floor'] : null;
    $specreq  = clean($_POST['special_req']  ?? '');
    $reason   = clean($_POST['reason']       ?? '');
    $phone    = clean($_POST['phone']        ?? '');
    $em_name  = clean($_POST['em_name']      ?? '');
    $em_phone = clean($_POST['em_phone']     ?? '');
    $address  = clean($_POST['address']      ?? '');

    if (!in_array($block, ['A','B','C']) || !in_array($type, ['single','double','triple','quad'])) {
        $error = 'Please select a valid block and room type.';
    } else {
        // Update student extra info
        db_exec($conn,
            "UPDATE students SET phone=?,home_address=?,emergency_contact=?,emergency_phone=? WHERE id=?",
            'ssssi', $phone, $address, $em_name, $em_phone, $sid
        );
        // Create application
        $code = gen_app_code($conn);
        $inserted = db_insert($conn,
            "INSERT INTO applications (app_code,student_id,preferred_block,preferred_type,preferred_floor,special_req,reason)
             VALUES (?,?,?,?,?,?,?)",
            'sisssss', $code, $sid, $block, $type, $floor, $specreq, $reason
        );
        if ($inserted) {
            flash('success', "Application submitted! Your ID: {$code}");
            redirect(APP_URL . '/student/application.php');
        } else {
            $error = 'Could not submit application. Please try again.';
        }
    }
}

$student = db_row($conn,"SELECT * FROM students WHERE id=?",'i',$sid);
$page_title = 'Apply for Room';
require_once __DIR__ . '/../includes/header.php';
?>

<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link" href="dashboard.php">← Dashboard</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
  </div>
</nav>

<div style="max-width:680px;margin:0 auto;padding:2rem;">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.4rem;">
    <a class="btn btn-secondary btn-sm" href="dashboard.php">←</a>
    <div class="page-title" style="margin:0;">Room Application</div>
  </div>
  <div class="page-sub">Fill in your details and submit your hostel room application</div>

  <?php if($existing): ?>
    <div class="warn-box">
      ⚠️ You already have an active application
      <code><?= $existing['app_code'] ?></code> with status
      <strong><?= ucfirst($existing['status']) ?></strong>.
      <a href="application.php" style="color:var(--warning);font-weight:600;"> View it →</a>
    </div>
  <?php else: ?>

  <?php if($error): ?><div class="err-box" style="margin-bottom:1rem;">❌ <?= $error ?></div><?php endif; ?>

  <form method="POST" style="display:flex;flex-direction:column;gap:1.25rem;">

    <!-- SECTION 1: PERSONAL INFO -->
    <div class="glass" style="padding:1.6rem;">
      <div class="table-title" style="margin-bottom:1.1rem;">1 — Personal Information</div>
      <div style="display:flex;flex-direction:column;gap:.9rem;">
        <div class="g2">
          <div class="form-group"><label class="form-label">Full Name</label><input class="form-input" value="<?= clean($student['full_name']) ?>" readonly></div>
          <div class="form-group"><label class="form-label">Student ID</label><input class="form-input" value="<?= clean($student['student_id']) ?>" readonly></div>
        </div>
        <div class="g2">
          <div class="form-group"><label class="form-label">Department</label><input class="form-input" value="<?= clean($student['department']) ?>" readonly></div>
          <div class="form-group"><label class="form-label">Year</label><input class="form-input" value="<?= $student['year_of_study'] ?>th Year" readonly></div>
        </div>
        <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" value="<?= clean($student['email']) ?>" readonly></div>
        <div class="form-group">
          <label class="form-label">Phone Number *</label>
          <input class="form-input" name="phone" value="<?= clean($student['phone']??'') ?>" placeholder="+880 1XXX-XXXXXX" required>
        </div>
        <div class="g2">
          <div class="form-group"><label class="form-label">Emergency Contact Name</label><input class="form-input" name="em_name" value="<?= clean($student['emergency_contact']??'') ?>" placeholder="Guardian / Parent"></div>
          <div class="form-group"><label class="form-label">Emergency Phone</label><input class="form-input" name="em_phone" value="<?= clean($student['emergency_phone']??'') ?>" placeholder="+880 1XXX-XXXXXX"></div>
        </div>
        <div class="form-group"><label class="form-label">Home Address</label><textarea class="form-input" name="address" rows="2" placeholder="Your permanent home address…"><?= clean($student['home_address']??'') ?></textarea></div>
      </div>
    </div>

    <!-- SECTION 2: ROOM PREFERENCE -->
    <div class="glass" style="padding:1.6rem;">
      <div class="table-title" style="margin-bottom:1.1rem;">2 — Room Preference</div>
      <div style="display:flex;flex-direction:column;gap:.9rem;">
        <div class="form-group">
          <label class="form-label">Preferred Block *</label>
          <select class="form-input" name="preferred_block" required>
            <option value="">Select block</option>
            <?php if($student['gender']==='male'||$student['gender']==='other'): ?>
              <option value="A">Block A (Male)</option>
              <option value="C">Block C (Mixed)</option>
            <?php elseif($student['gender']==='female'): ?>
              <option value="B">Block B (Female)</option>
              <option value="C">Block C (Mixed)</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Room Type *</label>
          <select class="form-input" name="preferred_type" required>
            <option value="">Select room type</option>
            <option value="single">Single (1 seat) — ৳8,000/semester</option>
            <option value="double">Double (2 seats) — ৳6,000/semester</option>
            <option value="triple">Triple (3 seats) — ৳5,000/semester</option>
            <option value="quad">Quad (4 seats) — ৳4,000/semester</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Floor Preference</label>
          <select class="form-input" name="preferred_floor">
            <option value="">No Preference</option>
            <option value="1">Ground / 1st Floor</option>
            <option value="2">2nd Floor</option>
            <option value="3">3rd Floor</option>
            <option value="4">4th Floor</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Special Requirements</label>
          <select class="form-input" name="special_req">
            <option value="">None</option>
            <option value="disability_access">Disability Access (Ground Floor)</option>
            <option value="near_prayer_room">Near Prayer Room</option>
            <option value="near_study_hall">Near Study Hall</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Reason for Hostel (optional)</label>
          <textarea class="form-input" name="reason" rows="3" placeholder="Briefly describe why you need hostel accommodation…"></textarea>
        </div>
      </div>
    </div>

    <!-- SECTION 3: CONFIRM -->
    <div class="glass" style="padding:1.4rem;">
      <div class="table-title" style="margin-bottom:.9rem;">3 — Confirmation</div>
      <div class="info-box" style="margin-bottom:1rem;">By submitting, you agree to the hostel rules and regulations. Admin will review your application within 2–5 working days.</div>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.1rem;">
        <input type="checkbox" id="agree" name="agree" style="width:16px;height:16px;accent-color:var(--blue);" required>
        <label for="agree" style="font-size:.875rem;cursor:pointer;">I agree to the hostel rules and regulations</label>
      </div>
      <div style="display:flex;justify-content:flex-end;">
        <button class="btn btn-primary btn-lg" type="submit">✓ Submit Application</button>
      </div>
    </div>
  </form>

  <?php endif; ?>
</div>
</body></html>
