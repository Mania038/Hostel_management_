<?php
// index.php — Public home page (UniNest HMS)
require_once __DIR__ . '/config/db.php';
session_start_safe();

$stats   = db_row($conn, "SELECT * FROM v_dashboard_stats");
$total_rooms = $stats['total_rooms']    ?? 0;
$avail       = $stats['available_seats']?? 0;
$ts_row      = db_row($conn,"SELECT SUM(capacity) AS ts FROM rooms");
$total_seats = $ts_row['ts'] ?? 0;
$housed      = $stats['housed_students'] ?? 0;
$open_tasks  = $stats['open_tasks'] ?? 0;

$is_student = !empty($_SESSION['student_id']);
$is_admin   = !empty($_SESSION['admin_id']);

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* ── Layout ── */
.hero{min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;flex-direction:column;text-align:center;padding:2rem 1.5rem;gap:1.5rem;position:relative;overflow:hidden;}
.orb{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none;}
.orb1{width:400px;height:400px;background:rgba(99,179,237,.07);top:0;left:-5%;animation:orbf 10s ease-in-out infinite;}
.orb2{width:350px;height:350px;background:rgba(183,148,244,.07);bottom:0;right:-5%;animation:orbf 10s ease-in-out infinite 3s;}
.orb3{width:250px;height:250px;background:rgba(118,228,247,.04);top:40%;left:40%;animation:orbf 10s ease-in-out infinite 6s;}
@keyframes orbf{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-24px) scale(1.05)}}
/* ── Hero Typography ── */
.hero-badge{display:inline-flex;align-items:center;gap:.45rem;padding:.32rem .95rem;border-radius:20px;background:rgba(99,179,237,.1);border:1px solid rgba(99,179,237,.28);font-size:.79rem;font-weight:600;color:var(--blue);font-family:var(--fm);letter-spacing:.02em;}
.hero-title{font-family:var(--fd);font-size:clamp(2.2rem,5.5vw,4.4rem);font-weight:800;line-height:1.05;letter-spacing:-.03em;max-width:760px;}
.grad{background:linear-gradient(135deg,#63b3ed,#b794f4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{font-size:1.05rem;color:var(--ts);max-width:540px;line-height:1.68;}
.hero-ctas{display:flex;gap:.85rem;flex-wrap:wrap;justify-content:center;margin-top:.4rem;}
.hero-stats{display:flex;gap:2.5rem;flex-wrap:wrap;justify-content:center;padding:1.3rem 2.2rem;border-radius:22px;background:var(--card);border:1px solid var(--gb);margin-top:.75rem;}
.hstat-val{font-family:var(--fd);font-size:1.7rem;font-weight:800;background:linear-gradient(135deg,#63b3ed,#b794f4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.hstat-lbl{font-size:.76rem;color:var(--ts);}
/* ── Sections ── */
section{padding:4rem 1.5rem;}
.s-inner{max-width:1100px;margin:0 auto;}
.s-hd{font-family:var(--fd);font-size:1.9rem;font-weight:800;text-align:center;margin-bottom:.4rem;}
.s-sub{text-align:center;color:var(--ts);margin-bottom:2.8rem;font-size:.98rem;}
/* ── Feature cards ── */
.feat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.1rem;}
.feat-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);padding:1.4rem;transition:all .3s;position:relative;overflow:hidden;}
.feat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;opacity:0;transition:opacity .3s;}
.feat-card:hover{transform:translateY(-5px);border-color:rgba(99,179,237,.28);box-shadow:0 14px 40px rgba(0,0,0,.35);}
.feat-card:hover::before{opacity:1;}
.feat-card.blue::before{background:linear-gradient(90deg,var(--blue),transparent);}
.feat-card.purple::before{background:linear-gradient(90deg,var(--purple),transparent);}
.feat-card.cyan::before{background:linear-gradient(90deg,var(--cyan),transparent);}
.feat-card.green::before{background:linear-gradient(90deg,var(--green),transparent);}
.feat-card.pink::before{background:linear-gradient(90deg,var(--pink),transparent);}
.feat-icon{width:46px;height:46px;border-radius:var(--r2);display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:.85rem;}
.feat-title{font-family:var(--fd);font-size:.95rem;font-weight:700;margin-bottom:.35rem;}
.feat-desc{font-size:.84rem;color:var(--ts);line-height:1.6;}
/* ── NEW Features highlight ── */
.new-badge{display:inline-block;background:linear-gradient(135deg,#b794f4,#63b3ed);color:#fff;font-size:.65rem;font-weight:700;padding:.1rem .45rem;border-radius:8px;margin-left:.35rem;vertical-align:middle;letter-spacing:.04em;}
/* ── Feature Showcase (3 new features) ── */
.showcase{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;}
.show-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);padding:1.75rem;transition:all .3s;text-align:center;}
.show-card:hover{transform:translateY(-4px);border-color:rgba(183,148,244,.3);}
.show-icon{font-size:3rem;margin-bottom:.85rem;display:block;}
.show-title{font-family:var(--fd);font-size:1.05rem;font-weight:800;margin-bottom:.4rem;}
.show-desc{font-size:.85rem;color:var(--ts);line-height:1.65;margin-bottom:1rem;}
.show-steps{display:flex;flex-direction:column;gap:.35rem;text-align:left;font-size:.8rem;color:var(--ts);}
.show-step{display:flex;gap:.5rem;align-items:flex-start;}
.show-step-num{width:20px;height:20px;border-radius:50%;background:var(--ag);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;color:#fff;flex-shrink:0;margin-top:.05rem;}
/* ── Steps ── */
.steps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;}
.step-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r2);padding:1.2rem;display:flex;gap:.85rem;align-items:flex-start;}
.step-num{width:36px;height:36px;border-radius:50%;background:var(--ag);display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-weight:800;font-size:.9rem;color:#fff;flex-shrink:0;}
/* ── CTA ── */
.cta-box{background:linear-gradient(135deg,rgba(99,179,237,.09),rgba(183,148,244,.09));border:1px solid rgba(183,148,244,.25);border-radius:var(--r3);padding:2.5rem;text-align:center;max-width:680px;margin:0 auto;}
/* ── Footer ── */
footer{border-top:1px solid var(--gb);text-align:center;padding:2rem;color:var(--tm);font-size:.78rem;font-family:var(--fm);}
footer a{color:var(--ts);text-decoration:none;}footer a:hover{color:var(--blue);}
@media(max-width:600px){.hero-stats{gap:1.5rem;padding:1rem 1.4rem;}.hstat-val{font-size:1.35rem;}}
</style>

<!-- ── NAVBAR ── -->
<nav class="navbar">
  <a class="nav-logo" href="<?= APP_URL ?>">UniNest</a>
  <div class="nav-links">
    <a class="nav-link active" href="<?= APP_URL ?>">Home</a>
    <a class="nav-link" href="<?= APP_URL ?>/student/rooms.php">Rooms</a>
    <?php if($is_student): ?>
      <a class="nav-link" href="<?= APP_URL ?>/student/dashboard.php">Dashboard</a>
      <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
    <?php elseif($is_admin): ?>
      <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php">Admin Panel</a>
      <a class="nav-link" href="<?= APP_URL ?>/auth/logout.php">Logout</a>
    <?php else: ?>
      <a class="nav-link" href="<?= APP_URL ?>/auth/login.php">Login</a>
      <a class="nav-btn" href="<?= APP_URL ?>/auth/register.php">Apply Now</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
  <div class="orb orb1"></div><div class="orb orb2"></div><div class="orb orb3"></div>
  <div class="hero-badge">🏠 University Hostel Management System</div>
  <h1 class="hero-title">
    Your Home Away<br><span class="grad">From Home.</span>
  </h1>
  <p class="hero-sub">Apply for rooms, pay fees online, resolve complaints — and earn fee credits by helping your hostel community.</p>
  <div class="hero-ctas">
    <?php if($is_student): ?>
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/student/dashboard.php">Go to Dashboard →</a>
      <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/rooms.php">Browse Rooms</a>
    <?php elseif($is_admin): ?>
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/admin/dashboard.php">Admin Dashboard →</a>
    <?php else: ?>
      <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/auth/register.php">🚀 Get Started</a>
      <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/rooms.php">🏠 Browse Rooms</a>
    <?php endif; ?>
  </div>
  <div class="hero-stats">
    <div><div class="hstat-val"><?= $total_rooms ?></div><div class="hstat-lbl">Total Rooms</div></div>
    <div><div class="hstat-val"><?= number_format($total_seats) ?></div><div class="hstat-lbl">Total Seats</div></div>
    <div><div class="hstat-val"><?= $avail ?></div><div class="hstat-lbl">Available Now</div></div>
    <div><div class="hstat-val"><?= $housed ?></div><div class="hstat-lbl">Housed Students</div></div>
    <?php if($open_tasks>0): ?><div><div class="hstat-val"><?= $open_tasks ?></div><div class="hstat-lbl">Open Tasks</div></div><?php endif; ?>
  </div>
</section>

<!-- ── NEW 3 FEATURES SHOWCASE ── -->
<section style="background:rgba(255,255,255,.01);border-top:1px solid var(--gb);border-bottom:1px solid var(--gb);">
  <div class="s-inner">
    <div class="s-hd">What's New <span style="font-size:1.2rem;">✨</span></div>
    <div class="s-sub">Three powerful features that make UniNest stand out</div>
    <div class="showcase">

      <!-- 1. Multi-Level Admin -->
      <div class="show-card">
        <span class="show-icon">👤</span>
        <div class="show-title">Multi-Level Admin System <span class="new-badge">NEW</span></div>
        <div class="show-desc">University appoints a Super Admin who manages dedicated Block Managers — each responsible only for their assigned hostel block.</div>
        <div class="show-steps">
          <div class="show-step"><div class="show-step-num">1</div><span><strong style="color:var(--tp);">Super Admin</strong> has full control — manages rooms, all students, fee approvals, and managers.</span></div>
          <div class="show-step"><div class="show-step-num">2</div><span><strong style="color:var(--tp);">Block Manager</strong> sees only their block's applications, complaints, and payments.</span></div>
          <div class="show-step"><div class="show-step-num">3</div><span>Permissions auto-scoped — no configuration needed per page.</span></div>
        </div>
      </div>

      <!-- 2. Online Payment -->
      <div class="show-card">
        <span class="show-icon">💳</span>
        <div class="show-title">Online Payment Gateway <span class="new-badge">NEW</span></div>
        <div class="show-desc">Students can pay semester fees online via bKash, Nagad, card, or bank transfer — with instant confirmation and transaction history.</div>
        <div class="show-steps">
          <div class="show-step"><div class="show-step-num">1</div><span>Student logs in and sees their <strong style="color:var(--tp);">unpaid fees</strong> with "Pay Online" button.</span></div>
          <div class="show-step"><div class="show-step-num">2</div><span>Chooses gateway (bKash / Nagad / Card / Bank) and pays securely.</span></div>
          <div class="show-step"><div class="show-step-num">3</div><span>Fee marked as <strong style="color:var(--success);">paid instantly</strong> — full transaction record stored.</span></div>
        </div>
      </div>

      <!-- 3. Task Exchange -->
      <div class="show-card">
        <span class="show-icon">🔄</span>
        <div class="show-title">Task Exchange System <span class="new-badge">NEW</span></div>
        <div class="show-desc">Students who can't pay fees on time can apply for admin-posted tasks — completing them earns fee credits automatically deducted from their balance.</div>
        <div class="show-steps">
          <div class="show-step"><div class="show-step-num">1</div><span>Admin posts tasks with a <strong style="color:var(--tp);">fee credit reward</strong> (e.g. ৳1,500).</span></div>
          <div class="show-step"><div class="show-step-num">2</div><span>Student applies, links it to their unpaid fee, and completes the work.</span></div>
          <div class="show-step"><div class="show-step-num">3</div><span>Admin verifies → credit <strong style="color:var(--success);">automatically reduces</strong> outstanding fee.</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── ALL FEATURES ── -->
<section>
  <div class="s-inner">
    <div class="s-hd">Everything in One Place</div>
    <div class="s-sub">A complete hostel management experience for students and administrators</div>
    <div class="feat-grid">
      <div class="feat-card blue"><div class="feat-icon" style="background:rgba(99,179,237,.12);">🏠</div><div class="feat-title">Room Browsing</div><div class="feat-desc">Browse rooms by block, type, and availability. See real-time seat counts and all amenities.</div></div>
      <div class="feat-card purple"><div class="feat-icon" style="background:rgba(183,148,244,.12);">📋</div><div class="feat-title">Online Application</div><div class="feat-desc">Multi-step application with personal info, room preferences, and live status tracking.</div></div>
      <div class="feat-card cyan"><div class="feat-icon" style="background:rgba(118,228,247,.12);">💳</div><div class="feat-title">Online Payments <span class="new-badge">NEW</span></div><div class="feat-desc">Pay via bKash, Nagad, card, or bank transfer with transaction receipt and full history.</div></div>
      <div class="feat-card green"><div class="feat-icon" style="background:rgba(104,211,145,.12);">🔄</div><div class="feat-title">Task Exchange <span class="new-badge">NEW</span></div><div class="feat-desc">Earn fee credits by completing admin tasks when you can't pay — no student left behind.</div></div>
      <div class="feat-card pink"><div class="feat-icon" style="background:rgba(246,135,179,.12);">📣</div><div class="feat-title">Complaint System</div><div class="feat-desc">Submit categorised maintenance requests. Track resolution status in real time.</div></div>
      <div class="feat-card blue"><div class="feat-icon" style="background:rgba(99,179,237,.12);">👤</div><div class="feat-title">Multi-Level Admin <span class="new-badge">NEW</span></div><div class="feat-desc">Super Admin + per-block Hostel Managers. Scoped permissions, no overlap.</div></div>
      <div class="feat-card purple"><div class="feat-icon" style="background:rgba(183,148,244,.12);">🗺️</div><div class="feat-title">Room Allocation</div><div class="feat-desc">Admin approves applications and assigns rooms. Auto-payment record on allocation.</div></div>
      <div class="feat-card cyan"><div class="feat-icon" style="background:rgba(118,228,247,.12);">📢</div><div class="feat-title">Notice Board</div><div class="feat-desc">Admin publishes hostel notices with urgency levels. Students see them on their dashboard.</div></div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section style="background:rgba(255,255,255,.01);border-top:1px solid var(--gb);border-bottom:1px solid var(--gb);">
  <div class="s-inner">
    <div class="s-hd">How It Works</div>
    <div class="s-sub">Get your hostel room in 4 simple steps</div>
    <div class="steps-grid">
      <div class="step-card"><div class="step-num">1</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.25rem;">Register &amp; Login</div><div style="font-size:.84rem;color:var(--ts);">Create your student account with your university ID and email.</div></div></div>
      <div class="step-card"><div class="step-num">2</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.25rem;">Browse &amp; Apply</div><div style="font-size:.84rem;color:var(--ts);">Browse rooms, pick your preferred type and block, submit your application.</div></div></div>
      <div class="step-card"><div class="step-num">3</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.25rem;">Admin Approval</div><div style="font-size:.84rem;color:var(--ts);">Manager reviews your application and allocates a room within 2–5 days.</div></div></div>
      <div class="step-card"><div class="step-num">4</div><div><div style="font-family:var(--fd);font-weight:700;margin-bottom:.25rem;">Pay &amp; Move In</div><div style="font-size:.84rem;color:var(--ts);">Pay your semester fee online instantly. Can't pay? Apply for a task to earn credits.</div></div></div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section>
  <div class="s-inner">
    <div class="cta-box">
      <div style="font-family:var(--fd);font-size:1.75rem;font-weight:800;margin-bottom:.5rem;">Ready to Apply?</div>
      <div style="color:var(--ts);margin-bottom:1.5rem;font-size:.95rem;">Join <?= $housed ?> students already living in UniNest hostels.</div>
      <div style="display:flex;gap:.85rem;justify-content:center;flex-wrap:wrap;">
        <?php if($is_student): ?>
          <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/student/dashboard.php">My Dashboard →</a>
          <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/tasks.php">🔄 Task Exchange</a>
        <?php elseif($is_admin): ?>
          <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/admin/dashboard.php">Admin Dashboard →</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/auth/register.php">Register Now →</a>
          <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/student/rooms.php">View Rooms</a>
          <a class="btn btn-secondary btn-lg" href="<?= APP_URL ?>/auth/login.php?role=admin">Admin Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<footer>
  UniNest HMS v2.0 &nbsp;·&nbsp;
  Built with PHP + MySQL (XAMPP) &nbsp;·&nbsp;
  <a href="<?= APP_URL ?>/student/rooms.php">Rooms</a> ·
  <a href="<?= APP_URL ?>/auth/login.php">Student Login</a> ·
  <a href="<?= APP_URL ?>/auth/login.php?role=admin">Admin Login</a> ·
  <a href="<?= APP_URL ?>/auth/register.php">Register</a>
  &nbsp;·&nbsp; <?= date('Y') ?>
</footer>
</body></html>