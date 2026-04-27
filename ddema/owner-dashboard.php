<?php
session_start();
require_once 'db.php';

// التحقق من تسجيل الدخول وصلاحية المستخدم كـ "صاحب معدة" (owner)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'owner') {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$uid  = $user['user_id'];

// استخراج الحروف الأولى لاسم المستخدم لعرضها في الـ Avatar
$initials = strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
$short_name = htmlspecialchars($user['first_name'] . ' ' . mb_substr($user['last_name'], 0, 1) . '.');

// ── جلب الإحصائيات (Stats) الخاصة بصاحب المعدة ────────────────────────────

// 1. عدد المعدات المدرجة
$stmt = $conn->prepare("SELECT COUNT(*) c FROM equipment WHERE owner_id = ? AND availability_status != 'deleted'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$equip_count = $stmt->get_result()->fetch_assoc()['c'];

// 2. الحجوزات النشطة (Confirmed)
$stmt = $conn->prepare("SELECT COUNT(*) c FROM reservations r JOIN equipment e ON r.equipment_id = e.equipment_id WHERE e.owner_id = ? AND r.reservation_status = 'confirmed'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$active_res_count = $stmt->get_result()->fetch_assoc()['c'];

// 3. الطلبات المعلقة (Pending)
$stmt = $conn->prepare("SELECT COUNT(*) c FROM reservations r JOIN equipment e ON r.equipment_id = e.equipment_id WHERE e.owner_id = ? AND r.reservation_status = 'pending'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$pending_req_count = $stmt->get_result()->fetch_assoc()['c'];

// 4. متوسط التقييم (Average Rating)
$stmt = $conn->prepare("SELECT AVG(r.rating) a FROM reviews r JOIN equipment e ON r.equipment_id = e.equipment_id WHERE e.owner_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$avg_rating = round($stmt->get_result()->fetch_assoc()['a'] ?? 0, 1);

// ── جلب أحدث المعدات (أحدث 4) ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM equipment WHERE owner_id = ? AND availability_status != 'deleted' ORDER BY equipment_id DESC LIMIT 4");
$stmt->bind_param("i", $uid);
$stmt->execute();
$recent_equipment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── جلب أحدث الحجوزات (أحدث 3) ──────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT r.*, e.equipment_name, u.first_name, u.last_name 
    FROM reservations r 
    JOIN equipment e ON r.equipment_id = e.equipment_id 
    JOIN users u ON r.renter_id = u.user_id 
    WHERE e.owner_id = ? 
    ORDER BY r.reservation_id DESC LIMIT 3
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$latest_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Owner Dashboard | GreenRent</title>

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet" />

  <style>
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
    }
    body { font-family: 'DM Sans', sans-serif; background: #eef5ee; color: var(--text-dark); min-height: 100vh; }
    a { text-decoration: none; }

    /* ── HEADER ── */
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
    .btn-solid { background: var(--green-mid); color: white; box-shadow: 0 2px 8px rgba(45,106,79,.25); }
    .btn-solid:hover { background: var(--green-deep); transform: translateY(-1px); }
    .btn-outline { background: transparent; border: 1.5px solid var(--green-mid); color: var(--green-mid); }
    .btn-outline:hover { background: var(--green-pale); }
    .btn-logout { background: transparent; border: 1.5px solid #e74c3c; color: #e74c3c; padding: 8px 16px; border-radius: 10px; font-weight: 600; font-size: 13.5px; transition: .2s; }
    .btn-logout:hover { background: #fef2f2; }

    .farmer-badge { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--text-dark); background: var(--cream); padding: 4px 14px 4px 4px; border-radius: 30px; border: 1px solid rgba(82,183,136,.2); transition: background 0.2s; }
    .farmer-badge .avatar { width: 30px; height: 30px; background: var(--green-mid); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; }

    /* ── DASHBOARD LAYOUT ── */
    .owner-page { max-width: 1200px; margin: 0 auto; padding: 36px 32px 0; }

    .owner-hero { background: linear-gradient(135deg, rgba(26, 60, 43, 0.98), rgba(45, 106, 79, 0.95)); border-radius: 22px; padding: 34px 32px; color: white; display: flex; justify-content: space-between; align-items: center; gap: 24px; box-shadow: var(--shadow-md); overflow: hidden; position: relative; margin-bottom: 28px; }
    .owner-hero::after { content: ""; position: absolute; right: -40px; top: -40px; width: 180px; height: 180px; border-radius: 50%; background: rgba(255, 255, 255, .06); }
    .owner-hero-text { position: relative; z-index: 1; }
    .owner-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, .12); border: 1px solid rgba(255, 255, 255, .16); border-radius: 20px; padding: 6px 14px; font-size: 12px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; margin-bottom: 16px; }
    .owner-badge-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-light); }
    .owner-hero h1 { font-family: 'DM Serif Display', serif; font-size: clamp(28px, 4vw, 40px); line-height: 1.15; margin-bottom: 10px; letter-spacing: -.4px; }
    .owner-hero p { font-size: 14.5px; line-height: 1.7; color: rgba(255, 255, 255, .80); max-width: 620px; }
    .owner-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; position: relative; z-index: 1; }
    .btn-soft { background: rgba(255, 255, 255, .10); color: var(--white); border: 1.5px solid rgba(255, 255, 255, .16); display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 13.5px; border-radius: 10px; padding: 10px 18px; transition: .2s; }
    .btn-soft:hover { background: rgba(255, 255, 255, .16); }

    .dashboard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
    .stat-card { background: var(--white); border: 1px solid rgba(82, 183, 136, .14); border-radius: 18px; padding: 22px 20px; box-shadow: var(--shadow-sm); transition: transform .18s, box-shadow .18s; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
    .stat-icon { width: 46px; height: 46px; border-radius: 12px; background: var(--green-pale); display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
    .stat-title { font-size: 13px; color: var(--text-muted); margin-bottom: 6px; font-weight: 600; }
    .stat-value { font-family: 'DM Serif Display', serif; font-size: 30px; color: var(--green-deep); line-height: 1; margin-bottom: 8px; }
    .stat-sub { font-size: 12.5px; color: var(--text-muted); line-height: 1.5; }

    .quick-actions { margin-bottom: 28px; }
    .section-top { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px; flex-wrap: wrap; }
    .section-top h2 { font-family: 'DM Serif Display', serif; font-size: 28px; color: var(--green-deep); letter-spacing: -.3px; }
    .section-top p { color: var(--text-muted); font-size: 14px; }
    .action-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .action-card { background: var(--white); border: 1px solid rgba(82, 183, 136, .15); border-radius: 18px; padding: 24px; box-shadow: var(--shadow-sm); transition: transform .18s, box-shadow .18s; }
    .action-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .action-icon { width: 52px; height: 52px; border-radius: 14px; background: var(--green-pale); display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px; }
    .action-card h3 { font-size: 18px; color: var(--green-deep); margin-bottom: 8px; }
    .action-card p { font-size: 13.5px; color: var(--text-muted); line-height: 1.7; margin-bottom: 18px; }
    .action-link { display: inline-flex; align-items: center; gap: 8px; color: var(--green-mid); font-weight: 700; font-size: 13.5px; text-decoration: none; }

    .content-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 22px; margin-bottom: 30px; }
    .panel { background: var(--white); border: 1px solid rgba(82, 183, 136, .14); border-radius: 18px; box-shadow: var(--shadow-sm); overflow: hidden; align-self: start;}
    .panel-header { padding: 20px 22px; border-bottom: 1px solid rgba(82, 183, 136, .12); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .panel-header h3 { font-size: 18px; color: var(--green-deep); }
    .panel-header span { font-size: 12.5px; color: var(--text-muted); }
    .equipment-list, .reservation-list { padding: 8px 18px 18px; }
    .equipment-item, .reservation-item { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding: 16px 10px; border-bottom: 1px solid rgba(82, 183, 136, .10); }
    .equipment-item:last-child, .reservation-item:last-child { border-bottom: none; }
    
    .item-left { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .item-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--green-pale); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .item-text h4 { font-size: 14.5px; color: var(--green-deep); margin-bottom: 4px; }
    .item-text p { font-size: 12.5px; color: var(--text-muted); }
    .item-right { text-align: right; flex-shrink: 0; }
    .price-tag { font-weight: 700; color: var(--green-deep); font-size: 14px; margin-bottom: 4px; }
    
    .mini-status { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 10px; font-size: 11.5px; font-weight: 700; text-transform: capitalize; }
    .status-available { background: #ecfdf3; color: #15803d; border: 1px solid #bbf7d0; }
    .status-limited, .status-booked { background: #fff7ed; color: #c2410c; border: 1px solid #fdba74; }
    .status-pending { background: #fefce8; color: #a16207; border: 1px solid #fde68a; }
    .status-confirmed, .status-completed { background: #ecfdf3; color: #15803d; border: 1px solid #bbf7d0; }
    .status-cancelled { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .empty-note { padding: 18px 22px 22px; font-size: 13px; color: var(--text-muted); line-height: 1.7; text-align: center; }

    @media (max-width: 1000px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } .action-grid, .content-grid { grid-template-columns: 1fr; } .owner-hero { flex-direction: column; align-items: flex-start; } .gr-navlinks {display:none;} }
    @media (max-width: 640px) { .owner-page { padding: 24px 16px 0; } .dashboard-grid, .action-grid { grid-template-columns: 1fr; } .equipment-item, .reservation-item { flex-direction: column; align-items: flex-start; } .item-right { text-align: left; } .owner-hero { padding: 24px 20px; } }

    /* ── FOOTER ── */
    footer { background: var(--green-deep); color: white; margin-top: 40px; }
    .footer-wave { display: block; width: 100%; height: 50px; }
    .footer-main { max-width: 1200px; margin: 0 auto; padding: 28px 32px 20px; display: flex; flex-direction: column; align-items: center; gap: 14px; }
    .footer-logo img { height: 44px; filter: brightness(0) invert(1) opacity(.85); }
    .footer-tagline { font-size: 13px; color: rgba(255,255,255,.6); text-align: center; max-width: 440px; }
    .footer-social { display: flex; gap: 8px; }
    .footer-social a { width: 36px; height: 36px; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.75); text-decoration: none; transition: background .2s; }
    .footer-social a:hover { background: var(--green-light); }
    .footer-badges { border-top: 1px solid rgba(255,255,255,.10); max-width: 1200px; margin: 0 auto; padding: 16px 32px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .f-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; padding: 5px 12px; font-size: 11.5px; color: rgba(255,255,255,.65); }
    .f-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green-light); }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,.10); }
    .footer-bottom-inner { max-width: 1200px; margin: 0 auto; padding: 14px 32px; text-align: center; font-size: 12.5px; color: rgba(255,255,255,.40); }
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
        <li><a href="owner-dashboard.php" class="active">Owner Dashboard</a></li>
        <li><a href="add-edit-equipment.php">Manage Equipment</a></li>
        <li><a href="view-reservations.php">Reservations</a></li>
      </ul>

      <div class="gr-nav-actions">
        <div class="farmer-badge" title="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
          <div class="avatar"><?= $initials ?></div>
          <?= $short_name ?>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
      </div>
    </nav>
  </header>

  <main class="owner-page">
    <section class="owner-hero">
      <div class="owner-hero-text">
        <div class="owner-badge">
          <span class="owner-badge-dot"></span>
          Owner Dashboard
        </div>
        <h1>Manage your equipment and monitor reservations</h1>
        <p>
          Welcome back, <?= htmlspecialchars($user['first_name']) ?>. From here, you can track your listed equipment, check booking activity, and quickly move to the main owner functions in GreenRent.
        </p>
      </div>

      <div class="owner-hero-actions">
        <a href="add-edit-equipment.php" class="btn btn-solid">+ Add Equipment</a>
        <a href="view-reservations.php" class="btn btn-soft">View Reservations</a>
      </div>
    </section>

    <section class="dashboard-grid">
      <div class="stat-card">
        <div class="stat-icon">🚜</div>
        <div class="stat-title">Listed Equipment</div>
        <div class="stat-value"><?= $equip_count ?></div>
        <div class="stat-sub">Total equipment currently listed.</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-title">Active Reservations</div>
        <div class="stat-value"><?= $active_res_count ?></div>
        <div class="stat-sub">Bookings that are currently active.</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-title">Pending Requests</div>
        <div class="stat-value"><?= $pending_req_count ?></div>
        <div class="stat-sub">Requests waiting for confirmation.</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-title">Average Rating</div>
        <div class="stat-value"><?= $avg_rating > 0 ? $avg_rating : 'N/A' ?></div>
        <div class="stat-sub">Based on previous rental reviews.</div>
      </div>
    </section>

    <section class="quick-actions">
      <div class="section-top">
        <div>
          <h2>Quick Access</h2>
          <p>Use these shortcuts to move faster between your main tasks.</p>
        </div>
      </div>

      <div class="action-grid">
        <article class="action-card">
          <div class="action-icon">🛠️</div>
          <h3>Add / Edit Equipment</h3>
          <p>Create a new equipment listing or update existing details such as type, price, location, and availability.</p>
          <a href="add-edit-equipment.php" class="action-link">Open page →</a>
        </article>

        <article class="action-card">
          <div class="action-icon">📖</div>
          <h3>View Reservations</h3>
          <p>Check current and past reservations, review customer booking details, and track reservation status.</p>
          <a href="view-reservations.php" class="action-link">Open page →</a>
        </article>

        <article class="action-card">
          <div class="action-icon">📦</div>
          <h3>Equipment Overview</h3>
          <p>Quickly review your listed machines and monitor which items are available, booked, or pending.</p>
          <a href="add-edit-equipment.php" class="action-link">Manage equipment →</a>
        </article>
      </div>
    </section>

    <section class="content-grid">
      
      <div class="panel">
        <div class="panel-header">
          <h3>Recent Equipment</h3>
          <span>Latest listed items</span>
        </div>

        <div class="equipment-list">
          <?php if(empty($recent_equipment)): ?>
            <div class="empty-note">You haven't added any equipment yet.<br><a href="add-edit-equipment.php" style="color:var(--green-mid);font-weight:bold;margin-top:5px;display:inline-block">+ Add your first equipment</a></div>
          <?php else: ?>
            <?php foreach($recent_equipment as $eq): 
                $emoji = match($eq['type']) { 'Tractor'=>'🚜','Harvester'=>'🌾','Plow'=>'⛏️','Irrigation'=>'💧','Seeder'=>'🌱', default=>'🔧' };
            ?>
            <div class="equipment-item">
              <div class="item-left">
                <div class="item-icon"><?= $emoji ?></div>
                <div class="item-text">
                  <h4><?= htmlspecialchars($eq['equipment_name']) ?></h4>
                  <p><?= htmlspecialchars($eq['location']) ?> • <?= htmlspecialchars($eq['type']) ?></p>
                </div>
              </div>
              <div class="item-right">
                <div class="price-tag"><?= number_format($eq['price_per_day'], 0) ?> SAR / day</div>
                <span class="mini-status status-<?= strtolower($eq['availability_status']) ?>"><?= ucfirst($eq['availability_status']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h3>Latest Reservations</h3>
          <span>Recent booking activity</span>
        </div>

        <div class="reservation-list">
          <?php if(empty($latest_reservations)): ?>
            <div class="empty-note">No reservations have been made for your equipment yet.</div>
          <?php else: ?>
            <?php foreach($latest_reservations as $res): ?>
            <div class="reservation-item">
              <div class="item-left">
                <div class="item-icon">👨‍🌾</div>
                <div class="item-text">
                  <h4><?= htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) ?></h4>
                  <p><?= htmlspecialchars(mb_substr($res['equipment_name'],0,20)) ?> • <?= date('d M', strtotime($res['start_date'])) ?> – <?= date('d M', strtotime($res['end_date'])) ?></p>
                </div>
              </div>
              <div class="item-right">
                <span class="mini-status status-<?= strtolower($res['reservation_status']) ?>"><?= ucfirst($res['reservation_status']) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if(!empty($latest_reservations)): ?>
        <div class="empty-note" style="border-top: 1px solid rgba(82,183,136,.10); margin-top: -10px;">
          Need more details? <a href="view-reservations.php" style="color:var(--green-mid);font-weight:bold;">Open the reservations page</a>.
        </div>
        <?php endif; ?>
      </div>
      
    </section>
  </main>

  <footer>
    <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
      <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee" />
    </svg>

    <div class="footer-main">
      <div class="footer-logo">
        <img src="logo.png" alt="GreenRent Logo" />
      </div>

      <p class="footer-tagline">
        A trusted platform connecting farmers and equipment owners across Riyadh.
      </p>

      <div class="footer-social">
        <a href="#" aria-label="Twitter">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 01-1.93.07 4.28 4.28 0 004 2.98 8.521 8.521 0 01-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z" />
          </svg>
        </a>

        <a href="#" aria-label="Instagram">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
          </svg>
        </a>
      </div>
    </div>

    <div class="footer-badges">
      <span class="f-badge"><span class="f-badge-dot"></span> Verified Equipment</span>
      <span class="f-badge"><span class="f-badge-dot"></span> Secure Payments</span>
      <span class="f-badge"><span class="f-badge-dot"></span> Riyadh — Saudi Arabia</span>
    </div>

    <div class="footer-bottom">
      <div class="footer-bottom-inner">
        © 2026 GreenRent. All rights reserved.
      </div>
    </div>
  </footer>

</body>
</html>