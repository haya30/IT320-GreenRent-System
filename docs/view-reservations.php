<?php
session_start();
require_once 'db.php';

// التحقق من تسجيل الدخول وصلاحية المزارع
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$uid = $user['user_id'];
$initials = strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));

// ── جلب الحجوزات مع بيانات المعدة ──────────────────
$stmt = $conn->prepare("
    SELECT r.*,
           e.equipment_name, e.type, e.location, e.price_per_day, e.image_url,
           p.payment_method, p.payment_status, p.amount
    FROM reservations r
    JOIN equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN payments p ON p.reservation_id = r.reservation_id
    WHERE r.renter_id = ?
    ORDER BY r.reservation_id DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── إحصاءات ──────────────────────────────────────────
$total     = count($reservations);
$confirmed = count(array_filter($reservations, fn($r) => $r['reservation_status'] === 'confirmed'));
$pending   = count(array_filter($reservations, fn($r) => $r['reservation_status'] === 'pending'));
$completed = count(array_filter($reservations, fn($r) => $r['reservation_status'] === 'completed'));
$cancelled = count(array_filter($reservations, fn($r) => $r['reservation_status'] === 'cancelled'));

// ── إلغاء حجز ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cid = (int)$_POST['cancel_id'];
    $upd = $conn->prepare("UPDATE reservations SET reservation_status='cancelled' WHERE reservation_id=? AND renter_id=? AND reservation_status='pending'");
    $upd->bind_param("ii", $cid, $uid);
    $upd->execute();
    header("Location: view-reservations.php");
    exit;
}

// ── جلب قائمة المعدات للفلتر ──────────────────────────
$eq_names = $conn->prepare("SELECT DISTINCT e.equipment_name FROM reservations r JOIN equipment e ON r.equipment_id=e.equipment_id WHERE r.renter_id=? ORDER BY e.equipment_name");
$eq_names->bind_param("i",$uid); 
$eq_names->execute();
$equip_options = $eq_names->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Reservations | GreenRent</title>

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet"/>
  
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
    .farmer-badge { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--text-dark); background: var(--cream); padding: 4px 14px 4px 4px; border-radius: 30px; border: 1px solid rgba(82,183,136,.2); }
    .farmer-badge .avatar { width: 30px; height: 30px; background: var(--green-mid); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
    
    .btn-solid { display: inline-flex; align-items: center; justify-content: center; padding: 9px 18px; border-radius: 10px; font-weight: 600; font-size: 13.5px; background: var(--green-mid); color: white; transition: .2s; border: none; cursor: pointer; }
    .btn-solid:hover { background: var(--green-deep); transform: translateY(-1px); }

    /* ── PAGE LAYOUT ── */
    .reservations-page { max-width: 1200px; margin: 0 auto; padding: 36px 32px 0; }

    .reservations-hero { background: linear-gradient(135deg, rgba(26,60,43,0.98), rgba(45,106,79,0.95)); border-radius: 22px; padding: 34px 32px; color: white; display: flex; justify-content: space-between; align-items: center; gap: 24px; box-shadow: var(--shadow-md); position: relative; overflow: hidden; margin-bottom: 28px; }
    .reservations-hero::after { content: ""; position: absolute; right: -45px; top: -45px; width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,.06); }
    .reservations-hero-text, .reservations-hero-actions { position: relative; z-index: 1; }
    .reservations-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.16); border-radius: 20px; padding: 6px 14px; font-size: 12px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; margin-bottom: 16px; }
    .reservations-badge-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-light); }
    .reservations-hero h1 { font-family: 'DM Serif Display', serif; font-size: clamp(28px, 4vw, 40px); line-height: 1.15; margin-bottom: 10px; letter-spacing: -.4px; }
    .reservations-hero p { font-size: 14.5px; line-height: 1.7; color: rgba(255,255,255,.82); max-width: 670px; }
    .reservations-hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
    .btn-soft { background: rgba(255,255,255,.10); color: var(--white); border: 1.5px solid rgba(255,255,255,.16); padding: 9px 18px; border-radius: 10px; font-weight: 600; font-size: 13.5px; transition: .2s; }
    .btn-soft:hover { background: rgba(255,255,255,.16); }

    /* ── SUMMARY CARDS ── */
    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 26px; }
    .summary-card { background: var(--white); border: 1px solid rgba(82,183,136,.14); border-radius: 18px; padding: 22px 20px; box-shadow: var(--shadow-sm); }
    .summary-icon { width: 46px; height: 46px; border-radius: 12px; background: var(--green-pale); display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
    .summary-title { font-size: 13px; color: var(--text-muted); margin-bottom: 6px; font-weight: 600; }
    .summary-value { font-family: 'DM Serif Display', serif; font-size: 30px; color: var(--green-deep); line-height: 1; margin-bottom: 8px; }
    .summary-sub { font-size: 12.5px; color: var(--text-muted); line-height: 1.5; }

    /* ── FILTERS ── */
    .filters-panel, .table-panel { background: var(--white); border: 1px solid rgba(82,183,136,.14); border-radius: 20px; box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 24px; }
    .panel-head { padding: 22px 24px; border-bottom: 1px solid rgba(82,183,136,.12); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .panel-head h2 { font-size: 22px; color: var(--green-deep); font-family: 'DM Serif Display', serif; letter-spacing: -.2px; }
    .panel-head span { font-size: 12.5px; color: var(--text-muted); }
    .filters-body { padding: 22px 24px 24px; }
    .filters-grid { display: grid; grid-template-columns: 1.1fr 1fr 1fr 1fr; gap: 16px; }
    .filter-group { display: flex; flex-direction: column; gap: 8px; }
    .filter-label { font-size: 13px; font-weight: 700; color: var(--text-dark); }
    .filter-input, .filter-select { width: 100%; padding: 12px 14px; border: 1.5px solid rgba(82,183,136,.25); border-radius: 12px; background: var(--cream); font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--text-dark); outline: none; transition: .2s; }
    .filter-input:focus, .filter-select:focus { border-color: var(--green-light); box-shadow: 0 0 0 3px rgba(82,183,136,.15); }
    .filter-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a7a62' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
    .filter-actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; margin-top: 18px; padding-top: 18px; border-top: 1px solid rgba(82,183,136,.12); }
    .btn-outline { display: inline-flex; align-items: center; justify-content: center; padding: 9px 18px; border-radius: 10px; font-weight: 600; font-size: 13.5px; background: transparent; color: var(--green-mid); border: 1.5px solid var(--green-mid); transition: .2s; cursor: pointer; }
    .btn-outline:hover { background: var(--green-pale); }

    /* ── TABLE ── */
    .table-wrap { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 980px; }
    thead th { text-align: left; font-size: 12px; font-weight: 700; color: var(--text-muted); letter-spacing: .5px; text-transform: uppercase; padding: 16px 24px; border-bottom: 1px solid rgba(82,183,136,.12); background: #f8fcf8; }
    tbody td { padding: 20px 24px; border-bottom: 1px solid rgba(82,183,136,.10); vertical-align: middle; color: var(--text-dark); font-size: 14px; transition: .15s; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: rgba(216,243,220,.18); }

    .reservation-cell, .equipment-cell { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .cell-icon { width: 42px; height: 42px; border-radius: 12px; background: var(--green-pale); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .cell-text strong { display: block; color: var(--green-deep); font-size: 14.5px; margin-bottom: 3px; }
    .cell-text span { color: var(--text-muted); font-size: 12.5px; }

    .date-range { font-weight: 700; color: var(--green-deep); margin-bottom: 4px; }
    .date-note { font-size: 12.5px; color: var(--text-muted); }
    .amount { font-weight: 700; color: var(--green-deep); font-size: 15px; }

    .status-badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 7px 14px; font-size: 12px; font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
    .status-confirmed { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .status-pending { background: #fffbeb; color: #a16207; border-color: #fde047; }
    .status-completed { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    .status-cancelled { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

    .table-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .action-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 12px; border-radius: 10px; font-size: 12.5px; font-weight: 700; transition: .18s; border: 1.5px solid transparent; cursor: pointer; }
    .action-view { background: var(--green-pale); color: var(--green-mid); border-color: rgba(82,183,136,.18); }
    .action-view:hover { background: #c7ebce; }
    .action-cancel { background: transparent; color: #b91c1c; border-color: #b91c1c; }
    .action-cancel:hover { background: #fef2f2; }
    .action-review { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .action-review:hover { background: #dbeafe; }

    /* ── FOOTER ── */
    footer { background: var(--green-deep); color: white; margin-top: 60px; }
    .footer-wave { display: block; width: 100%; height: 50px; }
    .footer-main { max-width: 1200px; margin: 0 auto; padding: 28px 32px 20px; display: flex; flex-direction: column; align-items: center; gap: 14px; }
    .footer-logo img { height: 44px; filter: brightness(0) invert(1) opacity(.85); }
    .footer-tagline { font-size: 13px; color: rgba(255,255,255,.6); text-align: center; max-width: 440px; }
    .footer-badges { border-top: 1px solid rgba(255,255,255,.10); max-width: 1200px; margin: 0 auto; padding: 16px 32px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .f-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; padding: 5px 12px; font-size: 11.5px; color: rgba(255,255,255,.65); }
    .f-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green-light); }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,.10); }
    .footer-bottom-inner { max-width: 1200px; margin: 0 auto; padding: 14px 32px; text-align: center; font-size: 12.5px; color: rgba(255,255,255,.40); }

    @media (max-width: 1050px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } .filters-grid { grid-template-columns: 1fr 1fr; } .reservations-hero { flex-direction: column; align-items: flex-start; } .gr-navlinks { display: none; } }
    @media (max-width: 700px) { .reservations-page { padding: 24px 16px 0; } .summary-grid, .filters-grid { grid-template-columns: 1fr; } .reservations-hero { padding: 24px 20px; } .filters-body { padding: 20px; } }
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
        <li><a href="farmer-dashboard.php">Farmer Dashboard</a></li>
        <li><a href="view-reservations.php" class="active">My Reservations</a></li>
      </ul>

      <div class="gr-nav-actions">
        <div class="farmer-badge">
          <div class="avatar"><?= $initials ?></div>
          <?= htmlspecialchars($user['first_name']) ?>
        </div>
        <a href="logout.php" class="btn-outline" style="padding: 6px 12px; border-color: #e74c3c; color: #e74c3c;">Logout</a>
      </div>
    </nav>
  </header>

  <main class="reservations-page">
    <section class="reservations-hero">
      <div class="reservations-hero-text">
        <div class="reservations-badge">
          <span class="reservations-badge-dot"></span>
          My Reservations
        </div>
        <h1>Track Your Equipment Bookings</h1>
        <p>
          View all your current and past reservations. You can cancel pending bookings or leave a review after completing a rental.
        </p>
      </div>

      <div class="reservations-hero-actions">
        <a href="farmer-dashboard.php" class="btn-soft">Browse Equipment</a>
      </div>
    </section>

    <section class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon">📅</div>
        <div class="summary-title">Total Reservations</div>
        <div class="summary-value"><?= $total ?></div>
        <div class="summary-sub">All-time reservations made</div>
      </div>

      <div class="summary-card">
        <div class="summary-icon">⏳</div>
        <div class="summary-title">Pending</div>
        <div class="summary-value"><?= $pending ?></div>
        <div class="summary-sub">Awaiting owner confirmation</div>
      </div>

      <div class="summary-card">
        <div class="summary-icon">✅</div>
        <div class="summary-title">Confirmed</div>
        <div class="summary-value"><?= $confirmed ?></div>
        <div class="summary-sub">Approved and scheduled</div>
      </div>

      <div class="summary-card">
        <div class="summary-icon">🌾</div>
        <div class="summary-title">Completed</div>
        <div class="summary-value"><?= $completed ?></div>
        <div class="summary-sub">Past successful rentals</div>
      </div>
    </section>

    <section class="filters-panel">
      <div class="panel-head">
        <h2>Filter Reservations</h2>
        <span>Use filters to narrow the displayed results</span>
      </div>

      <div class="filters-body">
        <div class="filters-grid">
          <div class="filter-group">
            <label class="filter-label">Search</label>
            <input type="text" id="reservationSearch" class="filter-input" placeholder="Search ID or equipment..." />
          </div>

          <div class="filter-group">
            <label class="filter-label">Status</label>
            <select id="statusFilter" class="filter-select">
              <option>All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Equipment</label>
            <select id="equipmentFilter" class="filter-select">
              <option>All Equipment</option>
              <?php foreach($equip_options as $e): ?>
                <option value="<?= htmlspecialchars($e['equipment_name']) ?>"><?= htmlspecialchars($e['equipment_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Sort By</label>
            <select id="sortFilter" class="filter-select">
              <option>Newest First</option>
              <option>Oldest First</option>
              <option>Status</option>
            </select>
          </div>
        </div>

        <div class="filter-actions">
          <button id="resetFiltersBtn" class="btn-outline" type="button">Reset Filters</button>
          <button id="applyFiltersBtn" class="btn-solid" type="button">Apply Filters</button>
        </div>
      </div>
    </section>

    <section class="table-panel">
      <div class="panel-head">
        <h2>Reservations List</h2>
        <span>Your current and past equipment rentals</span>
      </div>

      <div class="table-wrap">
        <table id="reservationsTable">
          <thead>
            <tr>
              <th>Reservation #</th>
              <th>Equipment</th>
              <th>Rental Dates</th>
              <th>Total Amount</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($reservations)): ?>
              <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No reservations found. <a href="farmer-dashboard.php" style="color:var(--green-mid);font-weight:700">Browse equipment →</a></td></tr>
            <?php endif; ?>

            <?php foreach ($reservations as $r):
              $emoji = match($r['type']) { 'Tractor'=>'🚜','Harvester'=>'🌾','Plow'=>'⛏️','Irrigation'=>'💧',default=>'🔧' };
              $days = (strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400;
              if ($days < 1) $days = 1;
            ?>
            <tr data-date="<?= strtotime($r['start_date']) * 1000 ?>">
              <td>
                <div class="reservation-cell">
                  <div class="cell-icon">🏷️</div>
                  <div class="cell-text">
                    <strong>#<?= $r['reservation_id'] ?></strong>
                    <span>Booked: <?= date('d M Y', strtotime($r['created_at'])) ?></span>
                  </div>
                </div>
              </td>

              <td>
                <div class="equipment-cell">
                  <div class="cell-icon"><?= $emoji ?></div>
                  <div class="cell-text">
                    <strong><?= htmlspecialchars($r['equipment_name']) ?></strong>
                    <span><?= htmlspecialchars($r['location']) ?> • <?= htmlspecialchars($r['type']) ?></span>
                  </div>
                </div>
              </td>

              <td>
                <div class="date-range"><?= date('d M Y', strtotime($r['start_date'])) ?> — <?= date('d M Y', strtotime($r['end_date'])) ?></div>
                <div class="date-note"><?= (int)$days ?> rental day<?= $days!=1?'s':'' ?></div>
              </td>

              <td><span class="amount"><?= number_format($r['total_price'], 0) ?> SAR</span></td>

              <td><span class="status-badge status-<?= $r['reservation_status'] ?>"><?= ucfirst($r['reservation_status']) ?></span></td>

              <td>
                <div class="table-actions">
                  <a href="equipment-details.php?id=<?= $r['equipment_id'] ?>" class="action-btn action-view">View</a>

                  <?php if ($r['reservation_status'] === 'pending'): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to cancel this reservation?')">
                      <input type="hidden" name="cancel_id" value="<?= $r['reservation_id'] ?>">
                      <button type="submit" class="action-btn action-cancel">Cancel</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($r['reservation_status'] === 'completed'): ?>
                    <a href="add-review.php?eq=<?= $r['equipment_id'] ?>&res=<?= $r['reservation_id'] ?>" class="action-btn action-review">⭐ Review</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <footer>
    <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
      <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee"/>
    </svg>

    <div class="footer-main">
      <div class="footer-logo">
        <img src="logo.png" alt="GreenRent Logo" />
      </div>
      <p class="footer-tagline">
        A trusted platform connecting farmers and equipment owners across Riyadh.
      </p>
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

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const searchInput = document.getElementById("reservationSearch");
      const statusFilter = document.getElementById("statusFilter");
      const equipmentFilter = document.getElementById("equipmentFilter");
      const sortFilter = document.getElementById("sortFilter");
      const applyBtn = document.getElementById("applyFiltersBtn");
      const resetBtn = document.getElementById("resetFiltersBtn");
      const table = document.getElementById("reservationsTable");
      const tbody = table.querySelector("tbody");

      const originalRows = Array.from(tbody.querySelectorAll("tr")).map((row, index) => {
        if (!row.dataset.date) return row; // تخطي رسالة "لا يوجد حجوزات"
        row.dataset.originalIndex = index;
        return row;
      }).filter(r => r.dataset.date);

      function getRowData(row) {
        const resId = row.querySelector("td:nth-child(1) .cell-text strong")?.textContent.trim().toLowerCase() || "";
        const equipmentName = row.querySelector("td:nth-child(2) .cell-text strong")?.textContent.trim().toLowerCase() || "";
        const statusText = row.querySelector("td:nth-child(5) .status-badge")?.textContent.trim().toLowerCase() || "";
        
        return {
          resId,
          equipmentName,
          statusText,
          timestamp: parseInt(row.dataset.date, 10),
          originalIndex: parseInt(row.dataset.originalIndex, 10)
        };
      }

      function applyFilters() {
        if(originalRows.length === 0) return;

        const searchValue = searchInput.value.trim().toLowerCase();
        const statusValue = statusFilter.value.trim().toLowerCase();
        const equipmentValue = equipmentFilter.value.trim().toLowerCase();
        const sortValue = sortFilter.value.trim().toLowerCase();

        let rows = [...originalRows];

        rows = rows.filter((row) => {
          const data = getRowData(row);

          const matchesSearch =
            data.resId.includes(searchValue) ||
            data.equipmentName.includes(searchValue);

          const matchesStatus =
            statusValue === "all statuses" ||
            data.statusText === statusValue;

          const matchesEquipment =
            equipmentValue === "all equipment" ||
            data.equipmentName === equipmentValue;

          return matchesSearch && matchesStatus && matchesEquipment;
        });

        rows.sort((a, b) => {
          const dataA = getRowData(a);
          const dataB = getRowData(b);

          if (sortValue === "newest first") {
            return dataB.timestamp - dataA.timestamp;
          }

          if (sortValue === "oldest first") {
            return dataA.timestamp - dataB.timestamp;
          }

          if (sortValue === "status") {
            return dataA.statusText.localeCompare(dataB.statusText);
          }

          return dataA.originalIndex - dataB.originalIndex;
        });

        tbody.innerHTML = "";

        if (rows.length === 0) {
          const noRow = document.createElement("tr");
          noRow.innerHTML = '<td colspan="6" style="text-align:center; padding:24px; color:#5a7a62;">No matching reservations found.</td>';
          tbody.appendChild(noRow);
          return;
        }

        rows.forEach((row) => tbody.appendChild(row));
      }

      function resetFilters() {
        if(originalRows.length === 0) return;
        searchInput.value = "";
        statusFilter.selectedIndex = 0;
        equipmentFilter.selectedIndex = 0;
        sortFilter.selectedIndex = 0;

        tbody.innerHTML = "";
        originalRows.forEach((row) => tbody.appendChild(row));
      }

      applyBtn.addEventListener("click", applyFilters);
      resetBtn.addEventListener("click", resetFilters);
      searchInput.addEventListener("input", applyFilters);
    });
  </script>

</body>
</html>