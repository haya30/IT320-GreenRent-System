<?php
session_start();
require_once 'db.php';

// التحقق من تسجيل الدخول (مثل الداشبورد)
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION['user'];
$user_initials = strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
$user_short_name = htmlspecialchars($user['first_name'] . ' ' . mb_substr($user['last_name'], 0, 1) . '.');

// جلب رقم المعدة من الرابط
$equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

try {
    // 1. جلب بيانات المعدة وصاحبها
    $stmt = $conn->prepare("
        SELECT e.*, u.first_name AS owner_first, u.last_name AS owner_last 
        FROM equipment e 
        JOIN users u ON e.owner_id = u.user_id 
        WHERE e.equipment_id = ?
    ");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $equipment = $stmt->get_result()->fetch_assoc();

    if (!$equipment) {
        die("المعدة غير موجودة أو تم حذفها.");
    }

    // 2. جلب التقييمات الخاصة بهذه المعدة
    $rev_stmt = $conn->prepare("
        SELECT r.*, u.first_name AS renter_first, u.last_name AS renter_last 
        FROM reviews r 
        JOIN users u ON r.renter_id = u.user_id 
        WHERE r.equipment_id = ? AND r.status = 'normal'
        ORDER BY r.review_date DESC
    ");
    $rev_stmt->bind_param("i", $equipment_id);
    $rev_stmt->execute();
    $reviews = $rev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// 3. حسابات وتقييمات
$total_reviews = count($reviews);
$avg_rating = 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($total_reviews > 0) {
    $sum = 0;
    foreach ($reviews as $rev) {
        $sum += $rev['rating'];
        $rating_counts[$rev['rating']]++;
    }
    $avg_rating = number_format($sum / $total_reviews, 1);
}

$owner_initials = strtoupper(mb_substr($equipment['owner_first'], 0, 1) . mb_substr($equipment['owner_last'], 0, 1));
$price = (float)$equipment['price_per_day'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($equipment['equipment_name']) ?> — GreenRent</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet"/>
  <style>
    /* ── BASE & CSS VARIABLES ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green-deep:  #1a3c2b;
      --green-mid:   #2d6a4f;
      --green-light: #52b788;
      --green-pale:  #d8f3dc;
      --cream:       #faf7f0;
      --gold:        #f4a800;
      --text-dark:   #1a2e1e;
      --text-muted:  #5a7a62;
      --white:       #ffffff;
      --shadow-sm:   0 2px 8px rgba(26,60,43,.10);
      --shadow-md:   0 6px 24px rgba(26,60,43,.16);
      --radius:      16px;
    }

    body { font-family: 'DM Sans', sans-serif; background: #eef5ee; color: var(--text-dark); min-height: 100vh; }
    
    /* ── HEADER (Same as your HTML) ── */
    .gr-header { position: sticky; top: 0; z-index: 100; background: var(--white); border-bottom: 1px solid rgba(82,183,136,.20); box-shadow: var(--shadow-sm); }
    .gr-nav { max-width: 1200px; margin: 0 auto; padding: 0 32px; height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 32px; }
    .gr-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
    .gr-logo img { height: 42px; width: auto; border-radius: 0; background: transparent; }
    .gr-logo-text { line-height: 1; }
    .gr-logo-text span:first-child { display: block; font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--green-deep); letter-spacing: -.3px; }
    .gr-logo-text span:last-child { display: block; font-size: 10.5px; color: var(--text-muted); font-weight: 500; letter-spacing: .8px; text-transform: uppercase; }
    .gr-navlinks { display: flex; gap: 24px; list-style: none; margin: 0 auto; }
    .gr-navlinks a { text-decoration: none; font-size: 14px; font-weight: 500; color: var(--text-muted); transition: color .18s; }
    .gr-navlinks a:hover, .gr-navlinks a.active { color: var(--green-mid); }
    
    .gr-search { position: relative; flex: 0 0 220px; }
    .gr-search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .gr-search input { width: 100%; padding: 8px 12px 8px 32px; border: 1.5px solid rgba(82,183,136,.25); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--text-dark); background: var(--cream); outline: none; }
    .gr-search input:focus { border-color: var(--green-light); }
    
    .gr-nav-actions { display: flex; align-items: center; gap: 10px; }
    .farmer-badge { display: flex; align-items: center; gap: 8px; background: var(--green-pale); border-radius: 10px; padding: 6px 12px; font-size: 13px; font-weight: 600; color: var(--green-deep); }
    .farmer-badge .avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--green-mid); color: white; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }

    /* ── GENERAL STYLES ── */
    .btn { display: inline-flex; align-items: center; gap: 6px; font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 13.5px; border-radius: 10px; padding: 9px 18px; cursor: pointer; border: none; transition: all .18s; text-decoration: none; }
    .btn-outline { background: transparent; border: 1.5px solid var(--green-mid); color: var(--green-mid); }
    .btn-outline:hover { background: var(--green-pale); }
    .btn-solid { background: var(--green-mid); color: var(--white); box-shadow: 0 2px 8px rgba(45,106,79,.30); }
    .btn-solid:hover { background: var(--green-deep); transform: translateY(-1px); }
    .btn-lg { padding: 12px 22px; font-size: 14.5px; border-radius: 12px; }

    .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-muted); margin-bottom: 24px; }
    .breadcrumb a { color: var(--green-mid); text-decoration: none; font-weight: 500; }
    .breadcrumb a:hover { text-decoration: underline; }
    
    .badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600; }
    .badge-available { background: rgba(82,183,136,.15); color: var(--green-mid); }
    .badge-unavailable { background: rgba(231,76,60,.15); color: #c0392b; }

    .form-control { width: 100%; padding: 9px 12px; border: 1.5px solid rgba(82,183,136,.25); border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--text-dark); background: var(--cream); outline: none; }
    .form-control:focus { border-color: var(--green-light); }

    /* ── LAYOUT ── */
    .detail-wrap { max-width: 1200px; margin: 0 auto; padding: 36px 32px; }
    .detail-layout { display: grid; grid-template-columns: 1fr 360px; gap: 28px; align-items: start; }

    /* ── GALLERY ── */
    .detail-gallery { background: linear-gradient(135deg, var(--green-pale) 0%, #b7e4c7 100%); border-radius: var(--radius); height: 380px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
    .detail-gallery img { width: 100%; height: 100%; object-fit: cover; }
    .gallery-badge-wrap { position: absolute; top: 16px; left: 16px; display: flex; gap: 8px; }

    /* ── TITLE BLOCK ── */
    .detail-title-block { margin-bottom: 20px; }
    .detail-category { display: inline-flex; align-items: center; gap: 6px; background: var(--green-pale); color: var(--green-mid); border-radius: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; }
    .detail-title { font-family: 'DM Serif Display', serif; font-size: 30px; color: var(--green-deep); line-height: 1.25; margin-bottom: 12px; }
    .detail-meta-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .detail-rating { display: flex; align-items: center; gap: 6px; }
    .stars-row { display: flex; gap: 2px; }
    .star { color: var(--gold); font-size: 15px; }
    .star.empty { color: #ddd; }
    .rating-val { font-weight: 700; font-size: 15px; }
    .rating-count { font-size: 13px; color: var(--text-muted); }
    .detail-location { display: flex; align-items: center; gap: 5px; font-size: 13.5px; color: var(--text-muted); }

    /* ── INFO PILLS ── */
    .info-pills { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
    .info-pill { background: var(--white); border: 1px solid rgba(82,183,136,.18); border-radius: 12px; padding: 14px 18px; display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 120px; box-shadow: var(--shadow-sm); }
    .info-pill .pill-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); }
    .info-pill .pill-val { font-size: 15px; font-weight: 700; color: var(--text-dark); }
    .info-pill .pill-val.green { color: var(--green-mid); }

    /* ── TABS ── */
    .detail-tabs { display: flex; gap: 0; border-bottom: 2px solid rgba(82,183,136,.15); margin-bottom: 24px; }
    .tab-btn { background: none; border: none; padding: 12px 20px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; color: var(--text-muted); cursor: pointer; position: relative; transition: color .18s; }
    .tab-btn.active { color: var(--green-mid); }
    .tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 2px; background: var(--green-mid); border-radius: 2px 2px 0 0; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── TAB CONTENTS ── */
    .detail-desc { font-size: 14.5px; line-height: 1.75; color: var(--text-dark); margin-bottom: 20px; white-space: pre-wrap; }
    
    .specs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .spec-row { background: var(--cream); border: 1px solid rgba(82,183,136,.15); border-radius: 10px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; }
    .spec-key { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .spec-val { font-size: 13.5px; font-weight: 700; color: var(--text-dark); }

    .review-item { background: var(--white); border: 1px solid rgba(82,183,136,.14); border-radius: 12px; padding: 18px; margin-bottom: 12px; }
    .review-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .reviewer { display: flex; align-items: center; gap: 10px; }
    .reviewer-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--green-mid), var(--green-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; }
    .reviewer-name { font-weight: 600; font-size: 14px; }
    .reviewer-date { font-size: 12px; color: var(--text-muted); }
    .review-text { font-size: 13.5px; line-height: 1.65; color: var(--text-dark); }

    /* ── SIDEBAR ── */
    .booking-sidebar { position: sticky; top: 88px; }
    .booking-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow-md); overflow: hidden; }
    .booking-card-header { background: linear-gradient(135deg, var(--green-mid), #3a8a5f); padding: 20px 22px; }
    .booking-price-row { display: flex; align-items: baseline; gap: 6px; }
    .booking-price { font-family: 'DM Serif Display', serif; font-size: 34px; color: white; }
    .booking-per { font-size: 13px; color: rgba(255,255,255,.70); }
    .booking-avail-row { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
    .avail-dot { width: 8px; height: 8px; border-radius: 50%; background: #74e094; flex-shrink: 0; }
    .avail-text { font-size: 13px; color: rgba(255,255,255,.80); }
    
    .booking-card-body { padding: 22px; }
    .date-range-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
    .date-input-wrap { display: flex; flex-direction: column; gap: 5px; }
    .date-label { font-size: 11.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }
    .booking-line { display: flex; justify-content: space-between; align-items: center; font-size: 13.5px; padding: 6px 0; color: var(--text-dark); }
    .booking-line .key { color: var(--text-muted); }
    .booking-line.total { border-top: 1.5px solid rgba(82,183,136,.18); margin-top: 8px; padding-top: 12px; font-weight: 700; font-size: 15px; color: var(--green-deep); }
    .booking-cta { margin-top: 18px; display: flex; flex-direction: column; gap: 10px; }
    .booking-cta .btn { justify-content: center; width: 100%; }

    .owner-card { background: var(--white); border: 1px solid rgba(82,183,136,.18); border-radius: var(--radius); padding: 20px; margin-top: 16px; }
    .owner-card-title { font-weight: 700; font-size: 13px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px; }
    .owner-info { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .owner-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--green-deep), var(--green-mid)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
    .owner-name { font-weight: 700; font-size: 15px; }
    .owner-since { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
    
    .terms-card { background: var(--cream); border: 1px solid rgba(82,183,136,.18); border-radius: var(--radius); padding: 18px 20px; margin-top: 16px; }
    .terms-card-title { font-weight: 700; font-size: 13px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; }
    .term-item { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--text-dark); margin-bottom: 8px; line-height: 1.5; }

    /* ── FOOTER ── */
    footer { background: var(--green-deep); color: rgba(255,255,255,.85); }
    .footer-wave { display: block; width: 100%; height: 50px; }
    .footer-main { max-width: 1200px; margin: 0 auto; padding: 48px 32px 36px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 20px; }
    .footer-logo img { height: 48px; width: auto; display: block; border-radius: 10%; }
    .footer-social { display: flex; gap: 10px; }
    .footer-social a { width: 36px; height: 36px; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.75); text-decoration: none; }
    .footer-badges { border-top: 1px solid rgba(255,255,255,.10); padding: 18px 32px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .f-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; padding: 5px 12px; font-size: 11.5px; color: rgba(255,255,255,.65); }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,.10); text-align: center; padding: 16px 32px; font-size: 12.5px; }

    @media (max-width: 900px) {
      .detail-layout { grid-template-columns: 1fr; }
      .booking-sidebar { position: static; }
      .specs-grid { grid-template-columns: 1fr; }
      .gr-navlinks { display: none; }
    }
  </style>
</head>
<body>

<header class="gr-header">
  <nav class="gr-nav">
    <a href="farmer-dashboard.php" class="gr-logo">
      <img src="logo.png" alt="GreenRent Logo" />
      <div class="gr-logo-text">
        <span>GreenRent</span>
        <span>Agricultural Equipment</span>
      </div>
    </a>
    <ul class="gr-navlinks">
      <li><a href="farmer-dashboard.php" class="active">Farmer Dashboard</a></li>
      <li><a href="my-reservations.php">My Reservations</a></li>
      <li><a href="farmer-profile.php">Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
    <div class="gr-search">
      <svg class="gr-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21" stroke-linecap="round"/>
      </svg>
      <input type="text" placeholder="Search equipment…"/>
    </div>
    <div class="gr-nav-actions">
      <div class="farmer-badge" title="Logged in as <?= htmlspecialchars($user['first_name']) ?>">
        <div class="avatar"><?= $user_initials ?></div>
        <?= $user_short_name ?>
      </div>
    </div>
  </nav>
</header>

<div class="detail-wrap">

  <div class="breadcrumb">
    <a href="farmer-dashboard.php">Farmer Dashboard</a>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span><?= htmlspecialchars($equipment['equipment_name']) ?></span>
  </div>

  <div class="detail-layout">
    <div class="detail-main">

      <div class="detail-gallery">
        <?php if (!empty($equipment['image_url']) && file_exists('uploads/' . $equipment['image_url'])): ?>
            <img src="uploads/<?= htmlspecialchars($equipment['image_url']) ?>" alt="Equipment Image">
        <?php else: ?>
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="color:var(--green-mid);opacity:0.3;">
              <rect x="2" y="10" width="14" height="8" rx="2"/><circle cx="6" cy="18" r="2"/><circle cx="14" cy="18" r="2"/><path d="M16 12h4l2 4H16"/><circle cx="20" cy="18" r="2"/>
            </svg>
        <?php endif; ?>
        
        <div class="gallery-badge-wrap">
          <?php if($equipment['availability_status'] === 'available'): ?>
             <span class="badge badge-available">● Available Now</span>
          <?php else: ?>
             <span class="badge badge-unavailable">● Unavailable</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="detail-title-block">
        <div class="detail-category">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="10" width="14" height="8" rx="2"/></svg>
          <?= htmlspecialchars($equipment['type']) ?>
        </div>
        <h1 class="detail-title"><?= htmlspecialchars($equipment['equipment_name']) ?></h1>
        <div class="detail-meta-row">
          <div class="detail-rating">
            <div class="stars-row">
              <span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span><span class="star">★</span>
            </div>
            <span class="rating-val"><?= $avg_rating > 0 ? $avg_rating : 'New' ?></span>
            <span class="rating-count">(<?= $total_reviews ?> reviews)</span>
          </div>
          <div class="detail-location">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= htmlspecialchars($equipment['location']) ?>
          </div>
        </div>
      </div>

      <div class="info-pills">
        <div class="info-pill">
          <span class="pill-label">Condition</span>
          <span class="pill-val"><?= htmlspecialchars($equipment['condition']) ?></span>
        </div>
        <div class="info-pill">
          <span class="pill-label">Type</span>
          <span class="pill-val"><?= htmlspecialchars($equipment['type']) ?></span>
        </div>
        <div class="info-pill">
          <span class="pill-label">Operator</span>
          <span class="pill-val <?= $equipment['operator_included'] ? 'green' : '' ?>">
            <?= $equipment['operator_included'] ? 'Included' : 'Not Included' ?>
          </span>
        </div>
      </div>

      <div class="detail-tabs">
        <button class="tab-btn active" data-tab="description">Description</button>
        <button class="tab-btn" data-tab="specs">Quick Specs</button>
        <button class="tab-btn" data-tab="reviews">Reviews (<?= $total_reviews ?>)</button>
      </div>

      <div class="tab-panel active" id="tab-description">
        <div class="detail-desc"><?= nl2br(htmlspecialchars($equipment['description'])) ?></div>
      </div>

      <div class="tab-panel" id="tab-specs">
        <div class="specs-grid">
          <div class="spec-row"><span class="spec-key">Equipment Type</span><span class="spec-val"><?= htmlspecialchars($equipment['type']) ?></span></div>
          <div class="spec-row"><span class="spec-key">Condition</span><span class="spec-val"><?= htmlspecialchars($equipment['condition']) ?></span></div>
          <div class="spec-row"><span class="spec-key">Location</span><span class="spec-val"><?= htmlspecialchars($equipment['location']) ?></span></div>
          <div class="spec-row"><span class="spec-key">Operator</span><span class="spec-val"><?= $equipment['operator_included'] ? 'Yes' : 'No' ?></span></div>
        </div>
        <p style="margin-top:15px; font-size:12px; color:var(--text-muted);">* Detailed specifications depend on the exact model described in the description.</p>
      </div>

      <div class="tab-panel" id="tab-reviews">
        
        <?php if($total_reviews > 0): ?>
        <div style="background:var(--cream);border-radius:12px;padding:20px;margin-bottom:20px;display:flex;align-items:center;gap:20px;">
          <div style="text-align:center;">
            <div style="font-family:'DM Serif Display',serif;font-size:52px;color:var(--green-deep);line-height:1;"><?= $avg_rating ?></div>
            <div style="font-size:12.5px;color:var(--text-muted); margin-top:8px;"><?= $total_reviews ?> reviews</div>
          </div>
          <div style="flex:1;">
            <div style="display:flex;flex-direction:column;gap:6px;">
              <?php for($i=5; $i>=1; $i--): 
                  $percent = ($rating_counts[$i] / $total_reviews) * 100;
              ?>
              <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                <span style="width:40px;color:var(--text-muted);"><?= $i ?> ★</span>
                <div style="flex:1;height:6px;background:#eee;border-radius:3px;overflow:hidden;">
                    <div style="width:<?= $percent ?>%;height:100%;background:var(--gold);border-radius:3px;"></div>
                </div>
                <span style="width:30px;color:var(--text-muted);"><?= $rating_counts[$i] ?></span>
              </div>
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <?php foreach($reviews as $rev): 
            $r_initials = strtoupper(mb_substr($rev['renter_first'], 0, 1) . mb_substr($rev['renter_last'], 0, 1));
        ?>
        <div class="review-item">
          <div class="review-header">
            <div class="reviewer">
              <div class="reviewer-avatar"><?= $r_initials ?></div>
              <div>
                <div class="reviewer-name"><?= htmlspecialchars($rev['renter_first'] . ' ' . $rev['renter_last']) ?></div>
                <div class="reviewer-date"><?= date('F Y', strtotime($rev['review_date'])) ?></div>
              </div>
            </div>
            <div style="color:var(--gold);font-size:13px;">
                <?= str_repeat('★', $rev['rating']) ?><span style="color:#ddd;"><?= str_repeat('★', 5 - $rev['rating']) ?></span>
            </div>
          </div>
          <p class="review-text"><?= htmlspecialchars($rev['comment']) ?></p>
        </div>
        <?php endforeach; ?>

        <?php else: ?>
            <div style="text-align:center; padding: 40px; color: var(--text-muted);">
                <p>No reviews yet for this equipment. Be the first to rent and review!</p>
            </div>
        <?php endif; ?>
      </div>

    </div><div class="booking-sidebar">

      <div class="booking-card">
        <div class="booking-card-header">
          <div class="booking-price-row">
            <span class="booking-price">SAR <?= number_format($price, 0) ?></span>
            <span class="booking-per">/ day</span>
          </div>
          <div class="booking-avail-row">
            <?php if($equipment['availability_status'] === 'available'): ?>
                <div class="avail-dot"></div>
                <span class="avail-text">Available — Book instantly</span>
            <?php else: ?>
                <div class="avail-dot" style="background:#e74c3c;"></div>
                <span class="avail-text">Currently Unavailable</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="booking-card-body">
          <form action="reservation.php" method="GET">
              <input type="hidden" name="equipment_id" value="<?= $equipment['equipment_id'] ?>">
              <div class="date-range-row">
                <div class="date-input-wrap">
                  <label class="date-label">Start Date</label>
                  <input type="date" class="form-control" name="start_date" id="sidebarStart" required/>
                </div>
                <div class="date-input-wrap">
                  <label class="date-label">End Date</label>
                  <input type="date" class="form-control" name="end_date" id="sidebarEnd" required/>
                </div>
              </div>
              <div class="booking-line">
                <span class="key">SAR <?= number_format($price, 0) ?> × <span id="sidebar-days">3</span> days</span>
                <span id="sidebar-subtotal">SAR ...</span>
              </div>
              <div class="booking-line">
                <span class="key">Service fee</span>
                <span>SAR 85</span>
              </div>
              <div class="booking-line total">
                <span>Total</span>
                <span id="sidebar-total">SAR ...</span>
              </div>
              <div class="booking-cta">
                <?php if($equipment['availability_status'] === 'available'): ?>
                    <button type="submit" class="btn btn-solid btn-lg" style="width:100%;">Reserve Now</button>
                <?php else: ?>
                    <button type="button" class="btn btn-solid btn-lg" style="width:100%; background:#ccc; cursor:not-allowed;" disabled>Not Available</button>
                <?php endif; ?>
                <a href="farmer-dashboard.php" class="btn btn-outline" style="justify-content:center;">← Back to Dashboard</a>
              </div>
          </form>
        </div>
      </div>

      <div class="owner-card">
        <div class="owner-card-title">Equipment Owner</div>
        <div class="owner-info">
          <div class="owner-avatar"><?= $owner_initials ?></div>
          <div>
            <div class="owner-name"><?= htmlspecialchars($equipment['owner_first'] . ' ' . $equipment['owner_last']) ?></div>
            <div class="owner-since">Verified GreenRent Partner</div>
          </div>
        </div>
      </div>

    </div></div></div><footer>
  <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
    <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee"/>
  </svg>
  <div class="footer-main">
    <div class="footer-logo">
      <img src="logo.png" alt="GreenRent Logo" />
    </div>
    <p class="footer-tagline">A trusted platform connecting farmers and equipment owners across Riyadh.</p>
    <div class="footer-badges">
      <span class="f-badge"><span class="f-badge-dot"></span> Verified Equipment</span>
      <span class="f-badge"><span class="f-badge-dot"></span> Secure Payments</span>
      <span class="f-badge"><span class="f-badge-dot"></span> Riyadh — Saudi Arabia</span>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-bottom-inner">© 2026 GreenRent. All rights reserved.</div>
  </div>
</footer>

<script>
  // Tab switching logic
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
  });

  // Price Calculation Logic using PHP data
  const startEl = document.getElementById('sidebarStart');
  const endEl   = document.getElementById('sidebarEnd');
  const daysEl  = document.getElementById('sidebar-days');
  const subtotalEl = document.getElementById('sidebar-subtotal');
  const totalEl    = document.getElementById('sidebar-total');
  
  // تمرير السعر من الداتا بيس للجافاسكربت!
  const PRICE = <?= $price ?>;
  const FEE   = 85;

  function calcPrice() {
    const s = new Date(startEl.value);
    const e = new Date(endEl.value);
    
    if (startEl.value && endEl.value && e > s) {
      const days = Math.ceil((e - s) / 86400000); // تحويل الميلي سكند لأيام
      const subtotal = days * PRICE;
      
      daysEl.textContent     = days;
      subtotalEl.textContent = 'SAR ' + subtotal.toLocaleString();
      totalEl.textContent    = 'SAR ' + (subtotal + FEE).toLocaleString();
    } else {
      daysEl.textContent     = 0;
      subtotalEl.textContent = 'SAR 0';
      totalEl.textContent    = 'SAR 0';
    }
  }
  
  startEl.addEventListener('change', calcPrice);
  endEl.addEventListener('change', calcPrice);

  // Set default dates (Today and +3 days)
  const today = new Date();
  const next3  = new Date(today); 
  next3.setDate(today.getDate() + 3);
  
  startEl.value = today.toISOString().split('T')[0];
  endEl.value   = next3.toISOString().split('T')[0];
  calcPrice();
</script>
</body>
</html>