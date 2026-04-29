<?php
// includes/header.php
// Usage: require_once _DIR_.'/../includes/header.php';
// Sets: $page_title (optional, default APP_NAME)
$page_title = $page_title ?? APP_NAME;
$flash      = get_flash();
$is_student = !empty($_SESSION['student_id']);
$is_admin   = !empty($_SESSION['admin_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — UniNest HMS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#050810;--bg2:#090d1a;--card:rgba(255,255,255,.04);--card-h:rgba(255,255,255,.07);--gb:rgba(255,255,255,.08);--gba:rgba(99,179,237,.4);--blue:#63b3ed;--cyan:#76e4f7;--purple:#b794f4;--pink:#f687b3;--green:#68d391;--ag:linear-gradient(135deg,#63b3ed,#b794f4);--tp:#f0f4ff;--ts:#8896b3;--tm:#4a5568;--danger:#fc8181;--success:#68d391;--warning:#f6e05e;--fd:'Syne',sans-serif;--fb:'Inter',sans-serif;--fm:'JetBrains Mono',monospace;--r1:8px;--r2:12px;--r3:18px;}
,::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--fb);background:var(--bg);color:var(--tp);min-height:100vh;}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:var(--bg);}::-webkit-scrollbar-thumb{background:rgba(99,179,237,.3);border-radius:3px;}
.navbar{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 2rem;height:60px;background:rgba(5,8,16,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--gb);}
.nav-logo{font-family:var(--fd);font-size:1.2rem;font-weight:800;background:var(--ag);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-decoration:none;}
.nav-links{display:flex;gap:.25rem;align-items:center;}
.nav-link{padding:.4rem .9rem;font-size:.85rem;font-weight:500;color:var(--ts);cursor:pointer;border-radius:var(--r1);transition:all .2s;border:none;background:none;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;}
.nav-link:hover,.nav-link.active{color:var(--blue);background:rgba(99,179,237,.08);}
.nav-btn{padding:.4rem 1.1rem;background:var(--ag);color:#fff;border:none;cursor:pointer;border-radius:var(--r1);font-size:.85rem;font-weight:600;font-family:var(--fb);text-decoration:none;}
.nav-btn:hover{opacity:.9;}
.glass{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);backdrop-filter:blur(16px);}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.3rem;border-radius:var(--r1);font-size:.875rem;font-weight:600;font-family:var(--fb);cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.btn-primary{background:linear-gradient(135deg,#4299e1,#9f7aea);color:#fff;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(99,179,237,.35);}
.btn-secondary{background:rgba(99,179,237,.1);color:var(--blue);border:1px solid rgba(99,179,237,.25);}
.btn-secondary:hover{background:rgba(99,179,237,.18);}
.btn-danger{background:rgba(252,129,129,.1);color:var(--danger);border:1px solid rgba(252,129,129,.25);}
.btn-danger:hover{background:rgba(252,129,129,.2);}
.btn-success{background:rgba(104,211,145,.1);color:var(--success);border:1px solid rgba(104,211,145,.25);}
.btn-success:hover{background:rgba(104,211,145,.2);}
.btn-sm{padding:.35rem .8rem;font-size:.8rem;}
.btn-lg{padding:.8rem 1.8rem;font-size:1rem;}
.form-group{display:flex;flex-direction:column;gap:.35rem;}
.form-label{font-size:.78rem;font-weight:600;color:var(--ts);letter-spacing:.06em;text-transform:uppercase;}
.form-input{background:rgba(255,255,255,.05);border:1px solid var(--gb);border-radius:var(--r1);padding:.65rem .9rem;color:var(--tp);font-family:var(--fb);font-size:.9rem;outline:none;transition:all .2s;width:100%;}
.form-input:focus{border-color:var(--blue);background:rgba(99,179,237,.05);box-shadow:0 0 0 3px rgba(99,179,237,.1);}
.form-input option{background:#0d1117;}
select.form-input{cursor:pointer;}textarea.form-input{resize:vertical;min-height:80px;}
.badge{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .65rem;border-radius:20px;font-size:.73rem;font-weight:600;font-family:var(--fm);}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;}
.badge-pending{background:rgba(246,224,94,.12);color:var(--warning);border:1px solid rgba(246,224,94,.25);}
.badge-pending::before{background:var(--warning);}
.badge-approved,.badge-paid,.badge-active,.badge-resolved{background:rgba(104,211,145,.12);color:var(--success);border:1px solid rgba(104,211,145,.25);}
.badge-approved::before,.badge-paid::before,.badge-active::before,.badge-resolved::before{background:var(--success);}
.badge-rejected,.badge-overdue,.badge-full{background:rgba(252,129,129,.12);color:var(--danger);border:1px solid rgba(252,129,129,.25);}
.badge-rejected::before,.badge-overdue::before,.badge-full::before{background:var(--danger);}
.badge-open,.badge-in_progress{background:rgba(246,224,94,.12);color:var(--warning);border:1px solid rgba(246,224,94,.25);}
.badge-open::before,.badge-in_progress::before{background:var(--warning);}
.badge-available{background:rgba(118,228,247,.12);color:var(--cyan);border:1px solid rgba(118,228,247,.25);}
.badge-available::before{background:var(--cyan);}
.table-wrapper{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);overflow:hidden;}
.table-header{padding:.9rem 1.2rem;border-bottom:1px solid var(--gb);display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;}
.table-title{font-family:var(--fd);font-size:.95rem;font-weight:700;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:600;color:var(--tm);text-transform:uppercase;letter-spacing:.08em;font-family:var(--fm);border-bottom:1px solid var(--gb);}
.data-table td{padding:.8rem 1rem;font-size:.875rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
.data-table tbody tr:hover{background:rgba(255,255,255,.02);}
.data-table tbody tr:last-child td{border-bottom:none;}
.stat-card{background:var(--card);border:1px solid var(--gb);border-radius:var(--r3);padding:1.3rem 1.4rem;position:relative;overflow:hidden;transition:all .3s;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;}
.stat-card.blue::before{background:linear-gradient(90deg,var(--blue),transparent);}
.stat-card.purple::before{background:linear-gradient(90deg,var(--purple),transparent);}
.stat-card.cyan::before{background:linear-gradient(90deg,var(--cyan),transparent);}
.stat-card.green::before{background:linear-gradient(90deg,var(--green),transparent);}
.stat-card.pink::before{background:linear-gradient(90deg,var(--pink),transparent);}
.stat-card:hover{transform:translateY(-3px);}
.stat-val{font-family:var(--fd);font-size:1.9rem;font-weight:700;background:var(--ag);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;}
.stat-label{font-size:.78rem;color:var(--ts);margin-top:.3rem;}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;}
.g2{display:grid;grid-template-columns:repeat(2,1fr);gap:1.1rem;}
.g21{display:grid;grid-template-columns:2fr 1fr;gap:1.1rem;}
.sidebar-layout{display:grid;grid-template-columns:230px 1fr;min-height:calc(100vh - 60px);}
.sidebar{background:rgba(9,13,26,.97);border-right:1px solid var(--gb);padding:1.2rem .75rem;display:flex;flex-direction:column;gap:.15rem;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
.sidebar-sec{font-size:.68rem;font-weight:700;color:var(--tm);text-transform:uppercase;letter-spacing:.1em;font-family:var(--fm);padding:.7rem .7rem .3rem;}
.sidebar-item{display:flex;align-items:center;gap:.65rem;padding:.55rem .7rem;border-radius:var(--r1);cursor:pointer;font-size:.85rem;font-weight:500;color:var(--ts);transition:all .2s;border:none;background:none;width:100%;text-align:left;text-decoration:none;}
.sidebar-item:hover{color:var(--tp);background:rgba(255,255,255,.05);}
.sidebar-item.active{color:var(--blue);background:rgba(99,179,237,.1);}
.sidebar-icon{width:18px;text-align:center;}
.dash-main{padding:1.75rem;overflow-y:auto;}
.page-title{font-family:var(--fd);font-size:1.55rem;font-weight:800;margin-bottom:.2rem;}
.page-sub{font-size:.875rem;color:var(--ts);margin-bottom:1.6rem;}
.divider{height:1px;background:var(--gb);margin:1rem 0;}
.info-box{background:rgba(99,179,237,.07);border:1px solid rgba(99,179,237,.2);border-radius:var(--r2);padding:.85rem 1rem;font-size:.85rem;color:var(--blue);line-height:1.55;}
.warn-box{background:rgba(246,224,94,.07);border:1px solid rgba(246,224,94,.2);border-radius:var(--r2);padding:.85rem 1rem;font-size:.85rem;color:var(--warning);line-height:1.55;}
.err-box{background:rgba(252,129,129,.07);border:1px solid rgba(252,129,129,.2);border-radius:var(--r2);padding:.85rem 1rem;font-size:.85rem;color:var(--danger);line-height:1.55;}
.ok-box{background:rgba(104,211,145,.07);border:1px solid rgba(104,211,145,.2);border-radius:var(--r2);padding:.85rem 1rem;font-size:.85rem;color:var(--success);line-height:1.55;}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--ag);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;}
.sidebar-user{margin-top:auto;padding:.7rem;background:rgba(255,255,255,.03);border-radius:var(--r2);display:flex;align-items:center;gap:.55rem;}
.sidebar-user-info{flex:1;min-width:0;}
.sidebar-user-name{font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sidebar-user-role{font-size:.68rem;color:var(--ts);font-family:var(--fm);}
.progress-bar{height:6px;border-radius:3px;background:rgba(255,255,255,.07);overflow:hidden;margin-top:.4rem;}
.progress-fill{height:100%;border-radius:3px;background:var(--ag);}
.tag{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.73rem;font-weight:500;}
.tag-blue{background:rgba(99,179,237,.12);color:var(--blue);}
.tag-purple{background:rgba(183,148,244,.12);color:var(--purple);}
.tag-cyan{background:rgba(118,228,247,.12);color:var(--cyan);}
code{font-family:var(--fm);font-size:.8rem;color:var(--blue);}
@media(max-width:900px){.g4,.g3{grid-template-columns:repeat(2,1fr);}.sidebar-layout{grid-template-columns:1fr;}.sidebar{display:none;}}
@media(max-width:600px){.g4,.g3,.g2,.g21{grid-template-columns:1fr;}}
</style>
</head>
<body>
<?php if($flash): ?>
<div id="flash-msg" style="position:fixed;top:1rem;right:1rem;z-index:9999;background:rgba(9,13,26,.95);border:1px solid var(--gb);border-left:3px solid <?= $flash['type']==='success'?'#68d391':($flash['type']==='error'?'#fc8181':'#63b3ed') ?>;border-radius:12px;padding:.85rem 1.2rem;display:flex;align-items:center;gap:.7rem;font-size:.875rem;max-width:320px;backdrop-filter:blur(20px);animation:slideIn .3s ease;">
  <span><?= $flash['type']==='success'?'✅':($flash['type']==='error'?'❌':'ℹ️') ?></span>
  <span><?= clean($flash['msg']) ?></span>
  <button onclick="this.parentElement.remove()" style="border:none;background:none;color:var(--ts);cursor:pointer;margin-left:.5rem;font-size:1rem;">×</button>
</div>
<style>@keyframes slideIn{from{transform:translateX(120%)}to{transform:translateX(0)}}</style>
<script>setTimeout(()=>{const f=document.getElementById('flash-msg');if(f)f.remove();},4000);</script>
<?php endif; ?>
fonts.googleapis.com