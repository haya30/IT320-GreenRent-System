<?php
session_start();
require_once 'db.php';

// التحقق من تسجيل الدخول وصلاحية المزارع (renter)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$uid = $user['user_id'];

// استخراج الحروف الأولى لاسم المستخدم والاسم المختصر لعرضها في الـ Avatar
$initials = strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
$short_name = htmlspecialchars($user['first_name'] . ' ' . mb_substr($user['last_name'], 0, 1) . '.');

// ── جلب المعدات مع بحث وفلتر وترتيب ──────────────────────
$search   = trim($_GET['search']   ?? '');
$type_f   = trim($_GET['type']     ?? '');
$loc_f    = trim($_GET['location'] ?? '');
$avail_f  = trim($_GET['avail']    ?? '');
$price_f  = trim($_GET['price']    ?? '');
$sort_f   = trim($_GET['sort']     ?? '');

$where  = ["e.availability_status != 'deleted'"]; // لا تعرض المحذوف
$params = [];
$types  = '';

if ($search) {
    $where[]  = "(e.equipment_name LIKE ? OR e.description LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= 'ss';
}
if ($type_f) { $where[] = "e.type = ?";     $params[] = $type_f; $types .= 's'; }
if ($loc_f)  { $where[] = "e.location = ?"; $params[] = $loc_f;  $types .= 's'; }
if ($avail_f) { $where[] = "e.availability_status = ?"; $params[] = $avail_f; $types .= 's'; }
else { $where[] = "e.availability_status IN ('available', 'limited')"; } // الافتراضي

if ($price_f === 'low')  { $where[] = "e.price_per_day < 200";  }
if ($price_f === 'mid')  { $where[] = "e.price_per_day BETWEEN 200 AND 500"; }
if ($price_f === 'high') { $where[] = "e.price_per_day > 500";  }

// تحديد الترتيب
$orderBy = "e.equipment_id DESC"; // الأحدث افتراضياً
if ($sort_f === 'price_asc')  $orderBy = "e.price_per_day ASC";
if ($sort_f === 'price_desc') $orderBy = "e.price_per_day DESC";
if ($sort_f === 'rating')     $orderBy = "avg_rating DESC";

$whereStr = implode(' AND ', $where);
$sql = "SELECT e.*, u.first_name, u.last_name,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.equipment_id=e.equipment_id) AS avg_rating,
        (SELECT COUNT(*)      FROM reviews r WHERE r.equipment_id=e.equipment_id) AS review_count
        FROM equipment e
        JOIN users u ON e.owner_id=u.user_id
        WHERE $whereStr ORDER BY $orderBy";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $equipment_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $equipment_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// ── إحصاءات الحجوزات الخاصة بالمزارع ──────────────────────────────────
$total_res = $conn->prepare("SELECT COUNT(*) c FROM reservations WHERE renter_id=?");
$total_res->bind_param("i", $uid); 
$total_res->execute();
$res_count = $total_res->get_result()->fetch_assoc()['c'];

// متوسط تقييم المزارع (للمعدات التي استأجرها أو قيمها) كقيمة جمالية للداشبورد
$avg_rating_query = $conn->prepare("SELECT AVG(rating) a FROM reviews WHERE renter_id=?");
$avg_rating_query->bind_param("i", $uid);
$avg_rating_query->execute();
$avg_rating_res = $avg_rating_query->get_result()->fetch_assoc()['a'];
$avg_rating_display = $avg_rating_res ? number_format($avg_rating_res, 1) . '★' : 'N/A';

$recent_res_query = $conn->prepare("
    SELECT r.start_date, r.end_date, e.equipment_name 
    FROM reservations r 
    JOIN equipment e ON r.equipment_id = e.equipment_id 
    WHERE r.renter_id = ? 
    ORDER BY r.reservation_id DESC LIMIT 2
");
$recent_res_query->bind_param("i", $uid); 
$recent_res_query->execute();
$recent_reservations = $recent_res_query->get_result()->fetch_all(MYSQLI_ASSOC);

// عدد المعدات المتوفرة بناء على البحث
$avail_eq_count = count($equipment_list);

// مصفوفة الأيقونات (SVGs) في حال عدم وجود صورة للمعدة
$svg_map = [
    'Tractor'   => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="2" y="10" width="14" height="8" rx="2"/><circle cx="6" cy="18" r="2"/><circle cx="14" cy="18" r="2"/><path d="M16 12h4l2 4H16"/><circle cx="20" cy="18" r="2"/></svg>',
    'Harvester' => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 20h16M4 20V8l8-4 8 4v12"/><rect x="9" y="13" width="6" height="7"/><path d="M9 13V9h6v4"/></svg>',
    'Irrigation'=> '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M12 2v4M6.34 6.34l2.83 2.83M2 12h4M6.34 17.66l2.83-2.83M12 22v-4M17.66 17.66l-2.83-2.83M22 12h-4M17.66 6.34l-2.83 2.83"/><circle cx="12" cy="12" r="3"/></svg>',
    'Seeder'    => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="4"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>',
    'Plow'      => '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M3 17l4-8 4 4 4-6 4 10"/><line x1="3" y1="17" x2="21" y2="17"/></svg>',
];
$default_svg = '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Farmer Dashboard — GreenRent</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet"/>
  
  <style>
    /* ── BASE & VARIABLES ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --green-deep:  #1a3c2b;
      --green-mid:   #2d6a4f;
      --green-light: #52b788;
      --green-pale:  #d8f3dc;
      --cream:       #faf7f0;
      --text-dark:   #1a2e1e;
      --text-muted:  #5a7a62;
      --white:       #ffffff;
      --shadow-sm:   0 2px 8px rgba(26,60,43,.10);
      --shadow-md:   0 6px 24px rgba(26,60,43,.16);
      --radius:      12px;
    }
    body { font-family: 'DM Sans', sans-serif; background: #eef5ee; color: var(--text-dark); min-height: 100vh; }
    a { text-decoration: none; }

    /* ── HEADER & NAVBAR (Style matched exactly to Owner Dashboard) ── */
    .gr-header { position: sticky; top: 0; z-index: 100; background: var(--white); border-bottom: 1px solid rgba(82,183,136,.20); box-shadow: var(--shadow-sm); }
    .gr-nav { max-width: 1200px; margin: 0 auto; padding: 0 32px; height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
    .gr-logo { display: flex; align-items: center; gap: 10px; }
    .gr-logo img { height: 40px; width: auto; }
    .gr-logo-text span:first-child { display: block; font-family: 'DM Serif Display', serif; font-size: 21px; color: var(--green-deep); line-height: 1; }
    .gr-logo-text span:last-child { display: block; font-size: 10px; color: var(--text-muted); font-weight: 500; letter-spacing: .8px; text-transform: uppercase; margin-top: 2px; }
    
    .gr-navlinks { display: flex; gap: 24px; list-style: none; margin: 0 auto; }
    .gr-navlinks a { color: var(--text-muted); font-weight: 500; font-size: 14.5px; transition: color 0.2s; }
    .gr-navlinks a:hover, .gr-navlinks a.active { color: var(--green-deep); font-weight: 700; }
    
    .gr-nav-actions { display: flex; align-items: center; gap: 16px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 13.5px; border-radius: 10px; padding: 10px 18px; cursor: pointer; border: none; transition: all .2s; }
    .btn-solid { background: var(--green-mid); color: white; box-shadow: 0 2px 8px rgba(45,106,79,.30); }
    .btn-solid:hover { background: var(--green-deep); transform: translateY(-1px); }
    .btn-outline { background: transparent; border: 1.5px solid var(--green-mid); color: var(--green-mid); }
    .btn-outline:hover { background: var(--green-pale); }
    
    .btn-logout { background: transparent; border: 1.5px solid #e74c3c; color: #e74c3c; padding: 8px 16px; border-radius: 10px; font-weight: 600; font-size: 13.5px; transition: .2s; }
    .btn-logout:hover { background: #fef2f2; }

    .farmer-badge { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--text-dark); background: var(--cream); padding: 4px 14px 4px 4px; border-radius: 30px; border: 1px solid rgba(82,183,136,.2); transition: background 0.2s; text-decoration: none; cursor: pointer; }
    .farmer-badge .avatar { width: 30px; height: 30px; background: var(--green-mid); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; }

    /* ── DASHBOARD HERO ── */
    .dash-hero { background: linear-gradient(135deg, var(--green-deep) 0%, var(--green-mid) 55%, #3a8a5f 100%); padding: 40px 0 50px; position: relative; overflow: hidden; }
    .dash-hero::before { content: ''; position: absolute; top: -40px; right: -40px; width: 320px; height: 320px; border-radius: 50%; background: rgba(255,255,255,.04); pointer-events: none; }
    .dash-hero::after { content: ''; position: absolute; bottom: -80px; left: 5%; width: 220px; height: 220px; border-radius: 50%; background: rgba(255,255,255,.03); pointer-events: none; }
    .dash-hero-inner { max-width: 1200px; margin: 0 auto; padding: 0 32px; position: relative; z-index: 1; display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
    .dash-hero-left .greeting { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 20px; padding: 5px 14px; font-size: 12px; font-weight: 600; color: rgba(255,255,255,.85); margin-bottom: 12px; text-transform: uppercase; letter-spacing: .5px; }
    .dash-hero-left h1 { font-family: 'DM Serif Display', serif; font-size: 34px; color: white; line-height: 1.2; margin-bottom: 8px; }
    .dash-hero-left p { font-size: 14.5px; color: rgba(255,255,255,.68); max-width: 420px; line-height: 1.65; }
    .dash-stats { display: flex; gap: 12px; flex-wrap: wrap; }
    .stat-pill { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 12px; padding: 12px 18px; text-align: center; }
    .stat-pill .val { font-family: 'DM Serif Display', serif; font-size: 22px; color: white; }
    .stat-pill .lbl { font-size: 11px; color: rgba(255,255,255,.60); font-weight: 500; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

    /* ── MY RESERVATIONS QUICK-BAR ── */
    .reservations-bar { max-width: 1200px; margin: 36px auto 0; padding: 0 32px; }
    .reservations-card { background: linear-gradient(135deg, #eef5ee 0%, var(--green-pale) 100%); border: 1px solid rgba(82,183,136,.25); border-radius: var(--radius); padding: 22px 28px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
    .reservations-icon { width: 50px; height: 50px; border-radius: 12px; background: var(--green-mid); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .reservations-icon svg { color: white; }
    .reservations-info h3 { font-weight: 700; font-size: 15.5px; color: var(--green-deep); }
    .reservations-info p { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
    .reservations-items { display: flex; gap: 10px; flex-wrap: wrap; margin-left: auto; align-items: center; }
    .res-mini { background: var(--white); border: 1px solid rgba(82,183,136,.20); border-radius: 10px; padding: 10px 14px; font-size: 12.5px; }
    .res-mini .name { font-weight: 600; color: var(--text-dark); }
    .res-mini .dates { color: var(--text-muted); margin-top: 2px; font-size: 11.5px; }

    /* ── FILTER BAR ── */
    .filter-bar-wrap { background: var(--white); border-bottom: 1px solid rgba(82,183,136,.15); box-shadow: 0 2px 12px rgba(26,60,43,.07); position: sticky; top: 68px; z-index: 50; }
    .filter-bar { max-width: 1200px; margin: 0 auto; padding: 16px 32px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .filter-search { flex: 1; min-width: 220px; max-width: 360px; position: relative; }
    .filter-search input { width: 100%; padding: 10px 16px 10px 42px; border: 1.5px solid rgba(82,183,136,.28); border-radius: 10px; background: var(--cream); font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--text-dark); outline: none; transition: border-color .2s, box-shadow .2s; }
    .filter-search input::placeholder { color: var(--text-muted); }
    .filter-search input:focus { border-color: var(--green-light); box-shadow: 0 0 0 3px rgba(82,183,136,.13); }
    .filter-search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .filter-select { padding: 10px 14px; border: 1.5px solid rgba(82,183,136,.28); border-radius: 10px; background: var(--cream); font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--text-dark); outline: none; cursor: pointer; transition: border-color .2s; }
    .filter-select:focus { border-color: var(--green-light); }
    .filter-label { font-size: 12.5px; font-weight: 600; color: var(--text-muted); white-space: nowrap; }
    .filter-result-count { margin-left: auto; font-size: 13px; color: var(--text-muted); white-space: nowrap; }

    /* ── EQUIPMENT GRID ── */
    .section-wrap { max-width: 1200px; margin: 0 auto; padding: 40px 32px 0; }
    .section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    .section-title { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--green-deep); margin-bottom: 4px; }
    .section-sub { font-size: 14px; color: var(--text-muted); }
    
    .equip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 22px; }
    .equip-card { background: var(--white); border-radius: 18px; overflow: hidden; box-shadow: var(--shadow-sm); transition: transform .2s, box-shadow .2s; display: flex; flex-direction: column; }
    .equip-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .equip-card-img { height: 170px; background: linear-gradient(135deg, #1a3c2b, #2d6a4f); display: flex; align-items: center; justify-content: center; position: relative; }
    .equip-card-img img { width: 100%; height: 100%; object-fit: cover; }
    .equip-card-img svg { opacity: .55; color: white; }
    .badge { position: absolute; top: 12px; left: 12px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .badge-available { background: rgba(82,183,136,.95); color: white; }
    .badge-limited { background: rgba(245,158,11,.95); color: white; }
    .rating-pill { position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,.45); backdrop-filter: blur(6px); color: white; font-size: 11.5px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
    .equip-card-body { padding: 18px; flex: 1; }
    .equip-card-title { font-weight: 700; font-size: 15.5px; color: var(--green-deep); margin-bottom: 8px; }
    .equip-card-meta { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
    .meta-tag { font-size: 11.5px; background: var(--green-pale); color: var(--green-mid); padding: 3px 10px; border-radius: 20px; font-weight: 600; }
    .equip-card-desc { font-size: 13px; color: var(--text-muted); line-height: 1.6; }
    .equip-card-footer { padding: 14px 18px; border-top: 1px solid var(--green-pale); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .price-tag { display: flex; align-items: baseline; gap: 4px; flex-shrink: 0; }
    .price { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--green-deep); }
    .per { font-size: 12px; color: var(--text-muted); }
    .card-actions { display: flex; flex: 1; justify-content: flex-end; }
    
    /* ── NO RESULTS ── */
    .no-results { text-align: center; padding: 60px 20px; color: var(--text-muted); }
    .no-results svg { margin-bottom: 14px; opacity: .35; }
    .no-results p { font-size: 15px; }

    /* ── FOOTER ── */
    footer { background: var(--green-deep); color: white; margin-top: 60px; }
    .footer-wave { display: block; width: 100%; height: 50px; }
    .footer-main { max-width: 1200px; margin: 0 auto; padding: 28px 32px 20px; display: flex; flex-direction: column; align-items: center; gap: 14px; }
    .footer-logo img { height: 44px; }
    .footer-tagline { font-size: 13px; color: rgba(255,255,255,.6); text-align: center; max-width: 440px; }
    .footer-social { display: flex; gap: 8px; }
    .footer-social a { width: 36px; height: 36px; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.75); text-decoration: none; transition: background .2s; }
    .footer-social a:hover { background: var(--green-light); }
    .footer-badges { border-top: 1px solid rgba(255,255,255,.10); max-width: 1200px; margin: 0 auto; padding: 16px 32px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .f-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; padding: 5px 12px; font-size: 11.5px; color: rgba(255,255,255,.65); }
    .f-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green-light); }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,.10); }
    .footer-bottom-inner { max-width: 1200px; margin: 0 auto; padding: 14px 32px; text-align: center; font-size: 12.5px; color: rgba(255,255,255,.40); }

    @media(max-width: 900px) {
      .gr-navlinks { display: none; }
    }
    @media(max-width: 768px) { 
      .dash-hero-inner { flex-direction: column; align-items: flex-start; } 
      .filter-bar { flex-wrap: wrap; } 
      .section-wrap { padding: 24px 16px 0; } 
    }
  </style>
</head>
<body>

<header class="gr-header">
  <nav class="gr-nav">
    <a href="index.php" class="gr-logo">
      <img src="logo.png" alt="GreenRent Logo" />
      <div class="gr-logo-text">
        <span>GreenRent</span>
        <span>Agricultural Equipment</span>
      </div>
    </a>

    <ul class="gr-navlinks">
      <li><a href="farmer-dashboard.php" class="active">Farmer Dashboard</a></li>
      <li><a href="my-reservations.php">My Reservations</a></li>
      <li><a href="farmer-profile.php">My Profile</a></li>
    </ul>

    <div class="gr-nav-actions">
      <a href="farmer-profile.php" class="farmer-badge" title="Go to your profile">
        <div class="avatar"><?= $initials ?></div>
        <?= $short_name ?>
      </a>
      <a href="logout.php" class="btn-logout">Logout</a>
    </div>
  </nav>
</header>

<section class="dash-hero">
  <div class="dash-hero-inner">
    <div class="dash-hero-left">
      <div class="greeting">🌾 Farmer Dashboard</div>
      <h1>Good morning, <?= htmlspecialchars($user['first_name']) ?>!</h1>
      <p>Discover verified agricultural equipment near you. Search, compare, and reserve with confidence.</p>
    </div>
    <div class="dash-stats">
      <div class="stat-pill">
        <div class="val"><?= $avail_eq_count ?></div>
        <div class="lbl">Available Now</div>
      </div>
      <div class="stat-pill">
        <div class="val"><?= $res_count ?></div>
        <div class="lbl">My Reservations</div>
      </div>
      <div class="stat-pill">
        <div class="val"><?= $avg_rating_display ?></div>
        <div class="lbl">Avg Rating</div>
      </div>
    </div>
  </div>
</section>

<div class="reservations-bar">
  <div class="reservations-card">
    <div class="reservations-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
    </div>
    <div class="reservations-info">
      <h3>Active Reservations</h3>
      <p>Track your ongoing or upcoming equipment rentals</p>
    </div>
    
    <div class="reservations-items">
      <?php if (!empty($recent_reservations)): ?>
        <?php foreach($recent_reservations as $res): ?>
          <div class="res-mini">
            <div class="name"><?= htmlspecialchars(mb_substr($res['equipment_name'], 0, 20)) ?>...</div>
            <div class="dates"><?= date('M d', strtotime($res['start_date'])) ?> – <?= date('M d', strtotime($res['end_date'])) ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <span style="font-size: 13px; color: var(--text-muted); font-style: italic;">No active reservations yet.</span>
      <?php endif; ?>
      
      <a href="my-reservations.php" class="btn btn-outline btn-sm" style="margin-left: 10px;">View All</a>
    </div>
  </div>
</div>

<div class="filter-bar-wrap">
  <form method="GET" action="farmer-dashboard.php" id="filterForm">
    <div class="filter-bar">
      
      <div class="filter-search">
        <svg class="filter-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21" stroke-linecap="round"/>
        </svg>
        <input type="text" name="search" placeholder="Search tractors, harvesters, seeders…" value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()"/>
      </div>

      <span class="filter-label">Type:</span>
      <select name="type" class="filter-select" onchange="this.form.submit()">
        <option value="">All Types</option>
        <?php
          $types_q = $conn->query("SELECT DISTINCT type FROM equipment WHERE availability_status != 'deleted' ORDER BY type");
          while($t = $types_q->fetch_assoc()):
        ?>
          <option value="<?= htmlspecialchars($t['type']) ?>" <?= $type_f===$t['type']?'selected':'' ?>><?= htmlspecialchars($t['type']) ?></option>
        <?php endwhile; ?>
      </select>

      <span class="filter-label">Location:</span>
      <select name="location" class="filter-select" onchange="this.form.submit()">
        <option value="">All Locations</option>
        <?php
          $locs_q = $conn->query("SELECT DISTINCT location FROM equipment WHERE availability_status != 'deleted' ORDER BY location");
          while($l = $locs_q->fetch_assoc()):
        ?>
          <option value="<?= htmlspecialchars($l['location']) ?>" <?= $loc_f===$l['location']?'selected':'' ?>><?= htmlspecialchars($l['location']) ?></option>
        <?php endwhile; ?>
      </select>

      <span class="filter-label">Price:</span>
      <select name="price" class="filter-select" onchange="this.form.submit()">
        <option value="">Any Price</option>
        <option value="low"  <?= $price_f==='low' ?'selected':'' ?>>Under SAR 200/day</option>
        <option value="mid"  <?= $price_f==='mid' ?'selected':'' ?>>SAR 200–500/day</option>
        <option value="high" <?= $price_f==='high'?'selected':'' ?>>Above SAR 500/day</option>
      </select>

      <span class="filter-label">Availability:</span>
      <select name="avail" class="filter-select" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="available" <?= $avail_f==='available'?'selected':'' ?>>Available Now</option>
        <option value="limited" <?= $avail_f==='limited'?'selected':'' ?>>Limited</option>
      </select>

      <?php if ($search || $type_f || $loc_f || $price_f || $avail_f || $sort_f): ?>
        <a href="farmer-dashboard.php" class="btn btn-outline" title="Clear Filters" style="padding: 10px;">✖</a>
      <?php endif; ?>

      <span class="filter-result-count" id="resultCount">Showing <?= count($equipment_list) ?> results</span>
      
      <input type="hidden" name="sort" id="hiddenSort" value="<?= htmlspecialchars($sort_f) ?>">

    </div>
  </form>
</div>

<section class="section-wrap">
  <div class="section-header">
    <div>
      <div class="section-title">Available Equipment</div>
      <div class="section-sub">Verified, quality-checked, ready for your farm</div>
    </div>
    
    <div style="display:flex;gap:8px;align-items:center;">
      <span style="font-size:13px;color:var(--text-muted);">Sort by:</span>
      <select class="filter-select" id="sortSelect" style="padding:8px 12px;" onchange="document.getElementById('hiddenSort').value = this.value; document.getElementById('filterForm').submit();">
        <option value="">Recommended</option>
        <option value="price_asc"  <?= $sort_f==='price_asc' ?'selected':'' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sort_f==='price_desc'?'selected':'' ?>>Price: High to Low</option>
        <option value="rating"     <?= $sort_f==='rating'    ?'selected':'' ?>>Rating</option>
      </select>
    </div>
  </div>

  <div class="equip-grid">

    <?php if (empty($equipment_list)): ?>
      <div class="no-results" style="display:block; grid-column:1/-1;">
        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21" stroke-linecap="round"/></svg>
        <p>No equipment matches your filters.<br>Try adjusting your search criteria.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($equipment_list as $eq): 
        $type = $eq['type'];
        $svg_icon = $svg_map[$type] ?? $default_svg;
        $avail_status = $eq['availability_status'];
    ?>
      <div class="equip-card">
        <div class="equip-card-img">
          <?php if (!empty($eq['image_url']) && file_exists('uploads/' . $eq['image_url'])): ?>
            <img src="uploads/<?= htmlspecialchars($eq['image_url']) ?>" alt="<?= htmlspecialchars($eq['equipment_name']) ?>">
          <?php else: ?>
            <?= $svg_icon ?>
          <?php endif; ?>
          
          <?php if ($avail_status === 'available'): ?>
            <span class="badge badge-available">● Available</span>
          <?php else: ?>
            <span class="badge badge-limited">◐ Limited</span>
          <?php endif; ?>

          <?php if ($eq['review_count'] > 0): ?>
            <span class="rating-pill">⭐ <?= number_format($eq['avg_rating'], 1) ?></span>
          <?php endif; ?>
        </div>

        <div class="equip-card-body">
          <div class="equip-card-title"><?= htmlspecialchars($eq['equipment_name']) ?></div>
          <div class="equip-card-meta">
            <span class="meta-tag"><?= htmlspecialchars($eq['type']) ?></span>
            <span class="meta-tag">🌍 <?= htmlspecialchars($eq['location']) ?></span>
            <span class="meta-tag"><?= htmlspecialchars($eq['condition']) ?></span>
          </div>
          <div class="equip-card-desc"><?= htmlspecialchars(mb_substr($eq['description'], 0, 100)) ?>...</div>
        </div>

        <div class="equip-card-footer">
          <div class="price-tag">
            <span class="price">SAR <?= number_format($eq['price_per_day'], 0) ?></span>
            <span class="per">per day</span>
          </div>
          <div class="card-actions">
            <a href="equipment-details.php?id=<?= $eq['equipment_id'] ?>" class="btn btn-outline btn-sm" style="width: 100%; justify-content: center;">Details</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  </div><div style="height:60px;"></div>
</section>

<footer>
  <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
    <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee"/>
  </svg>
  <div class="footer-main">
    <div class="footer-logo">
      <img src="logo.png" alt="GreenRent Logo" />
    </div>
    <p class="footer-tagline">A trusted platform connecting farmers and equipment owners across Riyadh.</p>
    <div class="footer-social">
      <a href="#" aria-label="Twitter">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 01-1.93.07 4.28 4.28 0 004 2.98 8.521 8.521 0 01-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/></svg>
      </a>
      <a href="#" aria-label="Instagram">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
      </a>
    </div>
  </div>
  <div class="footer-badges">
    <span class="f-badge"><span class="f-badge-dot"></span> Verified Equipment</span>
    <span class="f-badge"><span class="f-badge-dot"></span> Secure Payments</span>
    <span class="f-badge"><span class="f-badge-dot"></span> Riyadh — Saudi Arabia</span>
  </div>
  <div class="footer-bottom">
    <div class="footer-bottom-inner">© 2026 GreenRent. All rights reserved.</div>
  </div>
</footer>

</body>
</html>
