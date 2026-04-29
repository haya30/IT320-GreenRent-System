<?php
session_start();
require_once __DIR__ . '/db.php';

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit();
}

if ($role !== 'renter') {
    if ($role === 'owner') header('Location: owner-dashboard.php');
    elseif ($role === 'admin') header('Location: admin-dashboard.php');
    else header('Location: login.php');
    exit();
}

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, phone_number, role, status FROM users WHERE user_id = ? AND role = 'renter' LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();

if (!$farmer) {
    header('Location: login.php');
    exit();
}

$fullName = trim($farmer['first_name'] . ' ' . $farmer['last_name']);

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reservations WHERE renter_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalReservations = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reservations WHERE renter_id = ? AND reservation_status = 'confirmed'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$confirmedReservations = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reviews WHERE renter_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalReviews = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $conn->prepare("SELECT e.equipment_name, r.start_date, r.end_date, r.reservation_status FROM reservations r JOIN equipment e ON r.equipment_id = e.equipment_id WHERE r.renter_id = ? ORDER BY r.reservation_id DESC LIMIT 3");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentReservations = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Farmer Profile | GreenRent</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  <style>
    .profile-page{max-width:1200px;margin:0 auto;padding:36px 32px 0}.profile-hero{background:linear-gradient(135deg,rgba(26,60,43,.98),rgba(45,106,79,.95));border-radius:22px;padding:34px 32px;color:white;display:flex;justify-content:space-between;align-items:center;gap:24px;box-shadow:var(--shadow-md);margin-bottom:28px}.profile-hero h1{font-family:'DM Serif Display',serif;font-size:clamp(28px,4vw,40px);margin-bottom:10px}.profile-hero p{font-size:14.5px;line-height:1.7;color:rgba(255,255,255,.82);max-width:670px}.hero-badge{display:inline-flex;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:700;text-transform:uppercase;margin-bottom:16px}.hero-badge-dot{width:7px;height:7px;border-radius:50%;background:var(--green-light)}.hero-actions{display:flex;gap:12px;flex-wrap:wrap}.btn-soft{background:rgba(255,255,255,.10);color:var(--white);border:1.5px solid rgba(255,255,255,.16)}.profile-layout{display:grid;grid-template-columns:.95fr 1.05fr;gap:24px;margin-bottom:30px}.profile-card,.details-card,.activity-card{background:var(--white);border:1px solid rgba(82,183,136,.14);border-radius:20px;box-shadow:var(--shadow-sm);overflow:hidden}.profile-main{padding:28px 24px;text-align:center}.avatar{width:110px;height:110px;border-radius:50%;margin:0 auto 18px;background:linear-gradient(135deg,#d8f3dc,#b7e4c7);display:flex;align-items:center;justify-content:center;font-size:48px;color:var(--green-deep);border:4px solid rgba(82,183,136,.18)}.profile-main h2{font-family:'DM Serif Display',serif;font-size:30px;color:var(--green-deep);margin-bottom:8px}.role-badge{display:inline-flex;padding:7px 14px;border-radius:999px;background:var(--green-pale);color:var(--green-mid);font-size:12px;font-weight:700;border:1px solid rgba(82,183,136,.18);margin-bottom:16px}.profile-main p{font-size:13.5px;color:var(--text-muted);line-height:1.8;max-width:430px;margin:0 auto 20px}.contact-list{display:grid;gap:12px;text-align:left;margin-top:18px}.contact-item{display:flex;align-items:center;gap:12px;background:#f8fcf8;border:1px solid rgba(82,183,136,.12);border-radius:14px;padding:14px}.contact-icon{width:40px;height:40px;border-radius:12px;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:18px}.contact-text strong{display:block;color:var(--green-deep);font-size:13.5px;margin-bottom:2px}.contact-text span{font-size:12.5px;color:var(--text-muted)}.card-head{padding:22px 24px;border-bottom:1px solid rgba(82,183,136,.12);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.card-head h3{font-size:22px;color:var(--green-deep);font-family:'DM Serif Display',serif}.card-head span{font-size:12.5px;color:var(--text-muted)}.details-body,.activity-body{padding:24px}.details-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.detail-box,.stat-card,.recent-box{background:#f8fcf8;border:1px solid rgba(82,183,136,.12);border-radius:16px;padding:16px}.detail-label{font-size:12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}.detail-value{font-size:15px;color:var(--green-deep);font-weight:700;line-height:1.6}.about-box{grid-column:1/-1}.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:22px}.stat-card{text-align:center}.stat-icon{font-size:22px;margin-bottom:10px}.stat-value{font-family:'DM Serif Display',serif;font-size:28px;color:var(--green-deep);line-height:1;margin-bottom:6px}.stat-label{font-size:12.5px;color:var(--text-muted)}.recent-box h4{font-size:17px;color:var(--green-deep);margin-bottom:14px}.recent-list{display:grid;gap:12px}.recent-item{display:flex;justify-content:space-between;gap:12px;align-items:center;padding-bottom:12px;border-bottom:1px solid rgba(82,183,136,.10)}.recent-item:last-child{border-bottom:none;padding-bottom:0}.recent-item strong{display:block;color:var(--text-dark);font-size:13.5px;margin-bottom:3px}.recent-item span{font-size:12.5px;color:var(--text-muted)}.status-badge{border-radius:999px;padding:7px 12px;font-size:11.5px;font-weight:700}.status-confirmed,.status-completed{background:#ecfdf3;color:#15803d;border:1px solid #bbf7d0}.status-pending{background:#fefce8;color:#a16207;border:1px solid #fde68a}.status-cancelled{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}@media(max-width:1000px){.profile-layout{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr 1fr}.profile-hero{flex-direction:column;align-items:flex-start}}@media(max-width:700px){.profile-page{padding:24px 16px 0}.details-grid,.stats-grid{grid-template-columns:1fr}.profile-hero{padding:24px 20px}}
  </style>
</head>
<body>
<header class="gr-header"><nav class="gr-nav"><a href="index.php" class="gr-logo"><img src="logo.jpg" alt="GreenRent Logo"/><div class="gr-logo-text"><span>GreenRent</span><span>Agricultural Equipment</span></div></a><ul class="gr-navlinks"><li><a href="farmer-dashboard.php">Dashboard</a></li><li><a href="my-reservations.php">My Reservations</a></li></ul><div class="gr-nav-actions"><a href="farmer-profile.php" class="btn btn-outline">Profile</a><a href="logout.php" class="btn btn-solid">Log Out</a></div></nav></header>
<main class="profile-page">
<section class="profile-hero"><div><div class="hero-badge"><span class="hero-badge-dot"></span>Farmer Profile</div><h1>Manage your account and reservation activity</h1><p>View your personal information, track your reservation summary, and keep your farmer profile updated in one organized place.</p></div><div class="hero-actions"><a href="my-reservations.php" class="btn btn-soft">My Reservations</a><a href="#" class="btn btn-solid">Edit Profile</a></div></section>
<section class="profile-layout"><div class="profile-card"><div class="profile-main"><div class="avatar">👨‍🌾</div><h2><?= e($fullName) ?></h2><div class="role-badge">Farmer</div><p>Farmer interested in renting reliable agricultural equipment for seasonal and daily farm operations.</p><div class="contact-list"><div class="contact-item"><div class="contact-icon">📧</div><div class="contact-text"><strong>Email Address</strong><span><?= e($farmer['email']) ?></span></div></div><div class="contact-item"><div class="contact-icon">📱</div><div class="contact-text"><strong>Phone Number</strong><span><?= e($farmer['phone_number']) ?></span></div></div><div class="contact-item"><div class="contact-icon">📌</div><div class="contact-text"><strong>Account Status</strong><span><?= e(ucfirst($farmer['status'])) ?></span></div></div></div></div></div>
<div><section class="details-card" style="margin-bottom:24px;"><div class="card-head"><h3>Profile Details</h3><span>Basic account information</span></div><div class="details-body"><div class="details-grid"><div class="detail-box"><div class="detail-label">Full Name</div><div class="detail-value"><?= e($fullName) ?></div></div><div class="detail-box"><div class="detail-label">User Role</div><div class="detail-value">Farmer</div></div><div class="detail-box"><div class="detail-label">Email</div><div class="detail-value"><?= e($farmer['email']) ?></div></div><div class="detail-box"><div class="detail-label">Phone</div><div class="detail-value"><?= e($farmer['phone_number']) ?></div></div><div class="detail-box about-box"><div class="detail-label">About</div><div class="detail-value">This account can browse equipment, make reservations, view reservations, and submit reviews after completed bookings.</div></div></div></div></section>
<section class="activity-card"><div class="card-head"><h3>Activity Overview</h3><span>Reservation summary</span></div><div class="activity-body"><div class="stats-grid"><div class="stat-card"><div class="stat-icon">📅</div><div class="stat-value"><?= e($totalReservations) ?></div><div class="stat-label">Total Reservations</div></div><div class="stat-card"><div class="stat-icon">✅</div><div class="stat-value"><?= e($confirmedReservations) ?></div><div class="stat-label">Confirmed</div></div><div class="stat-card"><div class="stat-icon">⭐</div><div class="stat-value"><?= e($totalReviews) ?></div><div class="stat-label">Submitted Reviews</div></div></div><div class="recent-box"><h4>Recent Reservations</h4><div class="recent-list"><?php if ($recentReservations->num_rows > 0): ?><?php while($res = $recentReservations->fetch_assoc()): ?><div class="recent-item"><div><strong><?= e($res['equipment_name']) ?></strong><span><?= e($res['start_date']) ?> — <?= e($res['end_date']) ?></span></div><span class="status-badge status-<?= e($res['reservation_status']) ?>"><?= e(ucfirst($res['reservation_status'])) ?></span></div><?php endwhile; ?><?php else: ?><p>No reservations yet.</p><?php endif; ?></div></div></div></section></div></section>
</main>
</body>
</html>
