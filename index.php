<?php
// index.php — Public home page
require_once __DIR__ . '/config/db.php';
session_start_safe();

// Live stats from DB
$stats = db_row($conn, "SELECT * FROM v_dashboard_stats");
$total_rooms  = $stats['total_rooms']    ?? 248;
$avail_seats  = $stats['available_seats']?? 312;
$total_seats  = 0;
$ts = db_row($conn,"SELECT SUM(capacity) AS ts FROM rooms");
$total_seats  = $ts['ts'] ?? 1240;
$housed       = $stats['housed_students'] ?? 928;

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.hero{min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:2rem;gap:1.5rem;position:relative;overflow:hidden;}
.orb{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none;}
.orb1{width:350px;height:350px;background:rgba(99,179,237,.07);top:5%;left:5%;animation:orbf 9s ease-in-out infinite;}
.orb2{width:280px;height:280px;background:rgba(183,148,244,.07);bottom:10%;right:5%;animation:orbf 9s ease-in-out infinite 3s;}
.orb3{width:220px;height:220px;background:rgba(118,228,247,.05);top:40%;left:50%;transform:translateX(-50%);animation:orbf 9s ease-in-out infinite 6s;}
@keyframes orbf{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-22px) scale(1.05)}}
.hero-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem 1rem;border-radius:20px;background:rgba(99,179,237,.1);border:1px solid rgba(99,179,237,.25);font-size:.8rem;font-weight:600;color:var(--blue);font-family:var(--fm);}
.hero-title{font-family:var(--fd);font-size:clamp(2.2rem,5.5vw,4.2rem);font-weight:800;line-height:1.05;letter-spacing:-.03em;max-width:700px;}
.hero-sub{font-size:1.05rem;color:var(--ts);max-width:520px;line-height:1.65;}
.hero-actions{display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;margin-top:.5rem;}
.hero-stats{display:flex;gap:2.5rem;flex-wrap:wrap;justify-content:center;padding:1.4rem 2.5rem;border-radius:24px;background:var(--card);border:1px solid var(--gb);margin-top:1rem;}
.hstat-val{font-family:var(--fd);font-size:1.7rem;font-weight:800;background:var(--ag);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.hstat-lbl{font-size:.78rem;color:var(--ts);margin-top:.15rem;}
.text-grad{background:var(--ag);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.features{padding:5rem 2rem;max-width:1100px;margin:0 auto;}
.sec-hd{font-family:var(--fd);font-size:1.9rem;font-weight:800;text-align:center;margin-bottom:.4rem;}
.sec-sub{text-align:center;color:var(--ts);margin-bottom:2.8rem;font-size:1rem;}
.feat-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);padding:1.4rem;transition:all .3s;}
.feat-card:hover{transform:translateY(-5px);border-color:rgba(99,179,237,.25);box-shadow:0 12px 40px rgba(0,0,0,.3);}
.feat-icon{width:46px;height:46px;border-radius:var(--r2);display:flex;align-items:center;justify-content:center;font-size:1.25rem;margin-bottom:.9rem;}
.feat-title{font-family:var(--fd);font-size:.95rem;font-weight:700;margin-bottom:.35rem;}
.feat-desc{font-size:.84rem;color:var(--ts);line-height:1.6;}
.steps{padding:4rem 2rem;max-width:1000px;margin:0 auto;}
.step-item{display:flex;gap:1.1rem;align-items:flex-start;margin-bottom:2rem;}
.step-num{width:40px;height:40px;border-radius:50%;background:var(--ag);display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-weight:800;font-size:1rem;color:#fff;flex-shrink:0;}
.cta-section{padding:4rem 2rem;text-align:center;max-width:700px;margin:0 auto;}
footer{text-align:center;padding:2rem;border-top:1px solid var(--gb);color:var(--tm);font-size:.8rem;font-family:var(--fm);}
</style>

<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link active" href="<?= APP_URL ?>">Home</a>
    <a class="nav-link" href="<?= APP_URL ?>/student/rooms.php">Rooms</a>
    <?php if(!empty($_SESSION['student_id'])): ?>
      <a class="nav-link" href="<?= APP_URL ?>/student/dashboard.php">Dashboard</a>
      <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
    <?php elseif(!empty($_SESSION['admin_id'])): ?>
      <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php">Admin Panel</a>
      <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
    <?php else: ?>
      <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">Login</a>
      <a class="nav-btn" href="<?= APP_URL ?>/auth/register.php">Apply Now</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div>
  <div class="hero-badge">🏠 University Hostel Management System</div>
  <h1 class="hero-title">Your Home Away<br><span class="text-grad">From Home.</span></h1>
  <p class="hero-sub">Seamlessly apply for hostel rooms, track your application, manage payments, and resolve complaints — all from one unified platform.</p>
  <div class="hero-actions">
    <?php if(!empty($_SESSION['student_id'])): ?>
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/student/dashboard.php">Go to Dashboard →</a>
      <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/rooms.php">🏠 Browse Rooms</a>
    <?php else: ?>
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/auth/register.php">🚀 Get Started</a>
      <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/rooms.php">🏠 Browse Rooms</a>
    <?php endif; ?>
  </div>
  <div class="hero-stats">
    <div style="text-align:center;"><div class="hstat-val"><?= $total_rooms ?></div><div class="hstat-lbl">Total Rooms</div></div>
    <div style="text-align:center;"><div class="hstat-val"><?= number_format($total_seats) ?></div><div class="hstat-lbl">Total Seats</div></div>
    <div style="text-align:center;"><div class="hstat-val"><?= $avail_seats ?></div><div class="hstat-lbl">Available Now</div></div>
    <div style="text-align:center;"><div class="hstat-val"><?= $housed ?></div><div class="hstat-lbl">Students Housed</div></div>
  </div>
</section>

<!-- FEATURES -->
<section class="features">
  <div class="sec-hd">Everything You Need</div>
  <div class="sec-sub">A complete hostel management experience built for students and administrators</div>
  <div class="g3">
    <div class="feat-card"><div class="feat-icon" style="background:rgba(99,179,237,.12);">🏠</div><div class="feat-title">Room Browsing</div><div class="feat-desc">View all available rooms with real-time seat availability, amenities, and pricing details.</div></div>
    <div class="feat-card"><div class="feat-icon" style="background:rgba(183,148,244,.12);">📋</div><div class="feat-title">Easy Application</div><div class="feat-desc">Apply for your preferred room online with a simple, guided application process.</div></div>
    <div class="feat-card"><div class="feat-icon" style="background:rgba(118,228,247,.12);">📊</div><div class="feat-title">Live Status Tracking</div><div class="feat-desc">Track your application in real-time, from pending review to approved allocation.</div></div>
    <div class="feat-card"><div class="feat-icon" style="background:rgba(104,211,145,.12);">💳</div><div class="feat-title">Payment Management</div><div class="feat-desc">View, track and pay hostel fees with full semester-wise payment history.</div></div>
    <div class="feat-card"><div class="feat-icon" style="background:rgba(246,224,94,.12);">🔔</div><div class="feat-title">Complaint System</div><div class="feat-desc">Submit maintenance requests and get notified when they are resolved by admin.</div></div>
    <div class="feat-card"><div class="feat-icon" style="background:rgba(246,135,179,.12);">🛡️</div><div class="feat-title">Admin Control</div><div class="feat-desc">Full administrative dashboard to manage rooms, students, allocations, and reports.</div></div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="steps">
  <div class="sec-hd" style="text-align:center;margin-bottom:.4rem;">How It Works</div>
  <div class="sec-sub">Get your hostel room in 4 simple steps</div>
  <div class="g2">
    <div class="step-item"><div class="step-num">1</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.3rem;">Register &amp; Login</div><div style="font-size:.875rem;color:var(--ts);">Create your student account using your university ID and email address.</div></div></div>
    <div class="step-item"><div class="step-num">2</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.3rem;">Browse &amp; Apply</div><div style="font-size:.875rem;color:var(--ts);">Browse available rooms by block and type, then submit your application with preferences.</div></div></div>
    <div class="step-item"><div class="step-num">3</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.3rem;">Admin Approval</div><div style="font-size:.875rem;color:var(--ts);">Admin reviews your application and allocates a suitable room within 2–5 working days.</div></div></div>
    <div class="step-item"><div class="step-num">4</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.3rem;">Pay &amp; Move In</div><div style="font-size:.875rem;color:var(--ts);">Pay your semester fee and move into your assigned hostel room. Track everything online.</div></div></div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="glass" style="padding:2.5rem;text-align:center;">
    <div style="font-family:var(--fd);font-size:1.7rem;font-weight:800;margin-bottom:.5rem;">Ready to Apply?</div>
    <div style="color:var(--ts);margin-bottom:1.5rem;font-size:.95rem;">Join <?= $housed ?> students already living in UniNest hostels.</div>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/auth/register.php">Register Now →</a>
      <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/rooms.php">View Rooms</a>
    </div>
  </div>
</section>

<footer>UniNest HMS v2.0 &nbsp;·&nbsp; Built with PHP + MySQL (XAMPP) &nbsp;·&nbsp; University Project &nbsp;·&nbsp; <?= date('Y') ?></footer>
</body></html>
