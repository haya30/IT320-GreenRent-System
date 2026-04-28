<?php
session_start();
require_once 'db.php';
requireRole('renter');

$currentUser  = getCurrentUser();
$renterId     = $currentUser['user_id'];
$renterName   = htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']);

// ── Handle review submission ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $resId     = (int) $_POST['reservation_id'];
    $equipId   = (int) $_POST['equipment_id'];
    $rating    = (int) $_POST['rating'];
    $comment   = mysqli_real_escape_string($conn, trim($_POST['comment']));
    $today     = date('Y-m-d');

    // Only allow if reservation belongs to this renter and is completed
    $check = mysqli_query($conn,
        "SELECT reservation_id FROM reservations
         WHERE reservation_id = $resId AND renter_id = $renterId AND reservation_status = 'completed'"
    );

    if (mysqli_num_rows($check) > 0 && $rating >= 1 && $rating <= 5) {
        // Insert review (ignore if duplicate key — reservation_id is unique in reviews)
        mysqli_query($conn,
            "INSERT IGNORE INTO reviews
             (reservation_id, equipment_id, renter_id, rating, comment, review_date, status)
             VALUES ($resId, $equipId, $renterId, $rating, '$comment', '$today', 'normal')"
        );
    }

    header("Location: my-reservations.php");
    exit;
}

// ── Fetch reservations for this renter ───────────────────────────────────────
$statusFilter = '';
$searchFilter = '';
$orderBy      = 'r.reservation_id DESC';

if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $s = mysqli_real_escape_string($conn, $_GET['status']);
    $statusFilter = "AND r.reservation_status = '$s'";
}
if (!empty($_GET['search'])) {
    $q = mysqli_real_escape_string($conn, $_GET['search']);
    $searchFilter = "AND e.equipment_name LIKE '%$q%'";
}
if (!empty($_GET['sort'])) {
    $orderBy = $_GET['sort'] === 'oldest' ? 'r.reservation_id ASC' : 'r.reservation_id DESC';
}

$reservations = mysqli_query($conn,
    "SELECT r.*, e.equipment_name, e.type, e.location, e.image_url,
            CONCAT(u.first_name, ' ', u.last_name) AS owner_name,
            p.payment_method, p.payment_status,
            rv.review_id, rv.rating AS review_rating, rv.comment AS review_comment
     FROM reservations r
     JOIN equipment e ON r.equipment_id = e.equipment_id
     JOIN users u ON e.owner_id = u.user_id
     LEFT JOIN payments p ON r.reservation_id = p.reservation_id
     LEFT JOIN reviews rv ON r.reservation_id = rv.reservation_id
     WHERE r.renter_id = $renterId $statusFilter $searchFilter
     ORDER BY $orderBy"
);

// ── Summary counts ────────────────────────────────────────────────────────────
$counts = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        COUNT(*) AS total,
        SUM(reservation_status = 'pending')   AS pending,
        SUM(reservation_status = 'confirmed') AS confirmed,
        SUM(reservation_status = 'completed') AS completed,
        SUM(reservation_status = 'cancelled') AS cancelled
     FROM reservations WHERE renter_id = $renterId"
));

$reviewedCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS cnt FROM reviews WHERE renter_id = $renterId"
))['cnt'];

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusBadge($status) {
    $map = [
        'pending'   => ['class' => 'status-pending',   'label' => 'Pending'],
        'confirmed' => ['class' => 'status-confirmed', 'label' => 'Confirmed'],
        'completed' => ['class' => 'status-completed', 'label' => 'Completed'],
        'cancelled' => ['class' => 'status-cancelled', 'label' => 'Cancelled'],
    ];
    $s = $map[$status] ?? ['class' => 'status-pending', 'label' => ucfirst($status)];
    return "<span class=\"status-badge {$s['class']}\">{$s['label']}</span>";
}

function statusNote($status) {
    $notes = [
        'pending'   => 'Waiting for the owner to confirm your request.',
        'confirmed' => 'Your reservation is approved and scheduled.',
        'completed' => 'This reservation has been completed successfully.',
        'cancelled' => 'This reservation was cancelled.',
    ];
    return $notes[$status] ?? '';
}

function equipmentIcon($type) {
    $icons = [
        'Tractor'   => '🚜',
        'Harvester' => '🌾',
        'Plow'      => '⛏️',
        'Irrigation'=> '💧',
    ];
    return $icons[$type] ?? '🚛';
}

function rentalDays($start, $end) {
    $s = new DateTime($start);
    $e = new DateTime($end);
    return $s->diff($e)->days ?: 1;
}

function starsHtml($rating) {
    $html = '<div class="submitted-stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '★' : '☆';
    }
    return $html . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Reservations | GreenRent</title>

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>

  <style>
    .reservations-page { max-width:1200px; margin:0 auto; padding:36px 32px 0; }

    .reservations-hero {
      background: linear-gradient(135deg, rgba(26,60,43,0.98), rgba(45,106,79,0.95));
      border-radius: 22px; padding:34px 32px; color:white;
      display:flex; justify-content:space-between; align-items:center;
      gap:24px; box-shadow:var(--shadow-md); position:relative;
      overflow:hidden; margin-bottom:28px;
    }
    .reservations-hero::after {
      content:""; position:absolute; right:-45px; top:-45px;
      width:180px; height:180px; border-radius:50%;
      background:rgba(255,255,255,.06);
    }
    .hero-content,.hero-actions { position:relative; z-index:1; }
    .hero-badge {
      display:inline-flex; align-items:center; gap:8px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16);
      border-radius:20px; padding:6px 14px; font-size:12px;
      font-weight:700; letter-spacing:.6px; text-transform:uppercase; margin-bottom:16px;
    }
    .hero-badge-dot { width:7px; height:7px; border-radius:50%; background:var(--green-light); }
    .reservations-hero h1 {
      font-family:'DM Serif Display',serif; font-size:clamp(28px,4vw,40px);
      line-height:1.15; margin-bottom:10px; letter-spacing:-.4px;
    }
    .reservations-hero p { font-size:14.5px; line-height:1.7; color:rgba(255,255,255,.82); max-width:680px; }
    .hero-actions { display:flex; gap:12px; flex-wrap:wrap; }
    .btn-soft { background:rgba(255,255,255,.10); color:var(--white); border:1.5px solid rgba(255,255,255,.16); }
    .btn-soft:hover { background:rgba(255,255,255,.16); }

    .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:26px; }
    .summary-card {
      background:var(--white); border:1px solid rgba(82,183,136,.14);
      border-radius:18px; padding:22px 20px; box-shadow:var(--shadow-sm);
    }
    .summary-icon { width:46px; height:46px; border-radius:12px; background:var(--green-pale);
      display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:16px; }
    .summary-title { font-size:13px; color:var(--text-muted); margin-bottom:6px; font-weight:600; }
    .summary-value { font-family:'DM Serif Display',serif; font-size:30px; color:var(--green-deep); line-height:1; margin-bottom:8px; }
    .summary-sub { font-size:12.5px; color:var(--text-muted); line-height:1.5; }

    .filters-panel,.reservation-card {
      background:var(--white); border:1px solid rgba(82,183,136,.14);
      border-radius:20px; box-shadow:var(--shadow-sm); overflow:hidden;
    }
    .filters-panel { margin-bottom:24px; }
    .panel-head {
      padding:22px 24px; border-bottom:1px solid rgba(82,183,136,.12);
      display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;
    }
    .panel-head h2 { font-size:22px; color:var(--green-deep); font-family:'DM Serif Display',serif; letter-spacing:-.2px; }
    .panel-head span { font-size:12.5px; color:var(--text-muted); }
    .filters-body { padding:22px 24px 24px; }
    .filters-grid { display:grid; grid-template-columns:1.3fr 1fr 1fr; gap:16px; }
    .filter-group { display:flex; flex-direction:column; gap:8px; }
    .filter-label { font-size:13px; font-weight:700; color:var(--text-dark); }
    .filter-input,.filter-select,.review-textarea {
      width:100%; padding:12px 14px; border:1.5px solid rgba(82,183,136,.25);
      border-radius:12px; background:var(--cream); font-family:'DM Sans',sans-serif;
      font-size:14px; color:var(--text-dark); outline:none; transition:border-color .2s,box-shadow .2s;
    }
    .filter-input:focus,.filter-select:focus,.review-textarea:focus {
      border-color:var(--green-light); box-shadow:0 0 0 3px rgba(82,183,136,.15);
    }
    .filter-select {
      appearance:none;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a7a62' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right 14px center; padding-right:36px;
    }
    .filter-actions {
      display:flex; justify-content:flex-end; gap:12px; flex-wrap:wrap;
      margin-top:18px; padding-top:18px; border-top:1px solid rgba(82,183,136,.12);
    }

    .reservations-list { display:grid; gap:22px; margin-bottom:30px; }
    .reservation-top {
      padding:24px; display:grid; grid-template-columns:1.2fr 0.9fr 0.7fr;
      gap:20px; align-items:center; border-bottom:1px solid rgba(82,183,136,.10);
    }
    .equipment-info { display:flex; align-items:center; gap:16px; min-width:0; }
    .equipment-icon {
      width:58px; height:58px; border-radius:16px; background:var(--green-pale);
      display:flex; align-items:center; justify-content:center; font-size:28px; flex-shrink:0;
    }
    .equipment-text h3 { font-size:22px; color:var(--green-deep); margin-bottom:6px; font-family:'DM Serif Display',serif; }
    .equipment-text p { font-size:13.5px; color:var(--text-muted); line-height:1.7; }
    .reservation-meta { display:grid; gap:10px; }
    .meta-row { display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:13.5px; color:var(--text-dark); }
    .meta-label { color:var(--text-muted); font-weight:600; }
    .meta-value { color:var(--green-deep); font-weight:700; text-align:right; }
    .reservation-status-box { text-align:right; }
    .status-badge {
      display:inline-flex; align-items:center; justify-content:center;
      border-radius:999px; padding:8px 14px; font-size:12px; font-weight:700;
      border:1px solid transparent; margin-bottom:10px; white-space:nowrap;
    }
    .status-confirmed { background:#fff7ed; color:#ea580c; border-color:#fdba74; }
    .status-pending   { background:#fefce8; color:#a16207; border-color:#fde68a; }
    .status-completed { background:#ecfdf3; color:#15803d; border-color:#bbf7d0; }
    .status-cancelled { background:#fef2f2; color:#b91c1c; border-color:#fca5a5; }
    .status-note { font-size:12.5px; color:var(--text-muted); line-height:1.6; }

    .reservation-bottom { padding:22px 24px 24px; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .info-box,.review-box {
      background:#f8fcf8; border:1px solid rgba(82,183,136,.14); border-radius:16px; padding:18px;
    }
    .info-box h4,.review-box h4 { font-size:17px; color:var(--green-deep); margin-bottom:12px; }
    .info-list { display:grid; gap:10px; font-size:13.5px; color:var(--text-muted); line-height:1.7; }
    .info-list strong { color:var(--text-dark); }

    .stars { display:flex; gap:8px; margin-bottom:14px; font-size:28px; color:#d1d5db; cursor:pointer; }
    .star.active,.star:hover { color:#f4b400; }
    .review-textarea { min-height:100px; resize:vertical; line-height:1.7; margin-bottom:14px; }
    .review-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
    .submitted-review { display:grid; gap:10px; font-size:13.5px; color:var(--text-muted); line-height:1.8; }
    .submitted-stars { color:#f4b400; font-size:18px; letter-spacing:2px; }
    .empty-note { padding:18px 24px 24px; font-size:13px; color:var(--text-muted); line-height:1.7; }

    .no-reservations {
      text-align:center; padding:60px 24px; color:var(--text-muted);
      background:var(--white); border-radius:20px; border:1px solid rgba(82,183,136,.14);
    }
    .no-reservations .no-icon { font-size:48px; margin-bottom:16px; }
    .no-reservations h3 { font-size:20px; color:var(--green-deep); margin-bottom:8px; }

    @media (max-width:1050px) {
      .summary-grid { grid-template-columns:repeat(2,1fr); }
      .filters-grid { grid-template-columns:1fr 1fr; }
      .reservation-top,.reservation-bottom,.reservations-hero { grid-template-columns:1fr; flex-direction:column; align-items:flex-start; }
      .reservation-status-box { text-align:left; }
    }
    @media (max-width:700px) {
      .reservations-page { padding:24px 16px 0; }
      .summary-grid,.filters-grid { grid-template-columns:1fr; }
      .reservations-hero { padding:24px 20px; }
      .filters-body,.reservation-top,.reservation-bottom { padding:20px; }
      .equipment-info { align-items:flex-start; }
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
        <li><a href="farmer-dashboard.php">Dashboard</a></li>
        <li><a href="my-reservations.php" class="active">My Reservations</a></li>
      </ul>

      <div class="gr-nav-actions">
        <a href="farmer-profile.php" class="btn btn-outline">Profile</a>
        <a href="logout.php" class="btn btn-solid">Log Out</a>
      </div>
    </nav>
  </header>

  <main class="reservations-page">

    <!-- Hero -->
    <section class="reservations-hero">
      <div class="hero-content">
        <div class="hero-badge">
          <span class="hero-badge-dot"></span>
          My Reservations
        </div>
        <h1>Track your bookings and share your feedback</h1>
        <p>
          Welcome back, <?= htmlspecialchars($currentUser['first_name']) ?>. View all your equipment reservations,
          check their current status, and rate completed rentals based on your experience.
        </p>
      </div>
      <div class="hero-actions">
        <a href="farmer-dashboard.php" class="btn btn-soft">Back to Dashboard</a>
      </div>
    </section>

    <!-- Summary Cards -->
    <section class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon">📅</div>
        <div class="summary-title">Total Reservations</div>
        <div class="summary-value"><?= (int) $counts['total'] ?></div>
        <div class="summary-sub">All reservations made through your account.</div>
      </div>
      <div class="summary-card">
        <div class="summary-icon">⏳</div>
        <div class="summary-title">Pending</div>
        <div class="summary-value"><?= (int) $counts['pending'] ?></div>
        <div class="summary-sub">Reservations waiting for confirmation.</div>
      </div>
      <div class="summary-card">
        <div class="summary-icon">✅</div>
        <div class="summary-title">Confirmed</div>
        <div class="summary-value"><?= (int) $counts['confirmed'] ?></div>
        <div class="summary-sub">Upcoming or active confirmed bookings.</div>
      </div>
      <div class="summary-card">
        <div class="summary-icon">⭐</div>
        <div class="summary-title">Reviewed</div>
        <div class="summary-value"><?= (int) $reviewedCount ?></div>
        <div class="summary-sub">Completed reservations with submitted feedback.</div>
      </div>
    </section>

    <!-- Filters -->
    <section class="filters-panel">
      <div class="panel-head">
        <h2>Filter Reservations</h2>
        <span>Quickly find a booking by status or equipment name</span>
      </div>
      <div class="filters-body">
        <form method="GET" action="my-reservations.php">
          <div class="filters-grid">
            <div class="filter-group">
              <label class="filter-label">Search</label>
              <input type="text" name="search" class="filter-input"
                     placeholder="Search by equipment name"
                     value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"/>
            </div>
            <div class="filter-group">
              <label class="filter-label">Status</label>
              <select name="status" class="filter-select">
                <option value="all">All Statuses</option>
                <?php foreach (['pending','confirmed','completed','cancelled'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= (($_GET['status'] ?? '') === $opt) ? 'selected' : '' ?>>
                    <?= ucfirst($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group">
              <label class="filter-label">Sort By</label>
              <select name="sort" class="filter-select">
                <option value="newest" <?= (($_GET['sort'] ?? '') === 'newest') ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= (($_GET['sort'] ?? '') === 'oldest') ? 'selected' : '' ?>>Oldest First</option>
              </select>
            </div>
          </div>
          <div class="filter-actions">
            <a href="my-reservations.php" class="btn btn-outline">Reset Filters</a>
            <button type="submit" class="btn btn-solid">Apply Filters</button>
          </div>
        </form>
      </div>
    </section>

    <!-- Reservations List -->
    <section class="reservations-list">
      <?php if (mysqli_num_rows($reservations) === 0): ?>
        <div class="no-reservations">
          <div class="no-icon">📭</div>
          <h3>No reservations found</h3>
          <p>Try adjusting your filters or browse available equipment to make a booking.</p>
        </div>

      <?php else: ?>
        <?php while ($r = mysqli_fetch_assoc($reservations)): ?>
          <?php
            $days        = rentalDays($r['start_date'], $r['end_date']);
            $startFmt    = date('d M', strtotime($r['start_date']));
            $endFmt      = date('d M', strtotime($r['end_date']));
            $hasReview   = !empty($r['review_id']);
            $isCompleted = $r['reservation_status'] === 'completed';
          ?>
          <article class="reservation-card">
            <div class="reservation-top">

              <!-- Equipment Info -->
              <div class="equipment-info">
                <div class="equipment-icon"><?= equipmentIcon($r['type']) ?></div>
                <div class="equipment-text">
                  <h3><?= htmlspecialchars($r['equipment_name']) ?></h3>
                  <p>
                    <?= htmlspecialchars($r['location']) ?> &bull;
                    <?= htmlspecialchars($r['type']) ?> &bull;
                    Owner: <?= htmlspecialchars($r['owner_name']) ?>
                  </p>
                </div>
              </div>

              <!-- Meta -->
              <div class="reservation-meta">
                <div class="meta-row">
                  <span class="meta-label">Reservation Dates</span>
                  <span class="meta-value"><?= $startFmt ?> — <?= $endFmt ?></span>
                </div>
                <div class="meta-row">
                  <span class="meta-label">Total Amount</span>
                  <span class="meta-value"><?= number_format($r['total_price'], 2) ?> SAR</span>
                </div>
                <div class="meta-row">
                  <span class="meta-label">Booking ID</span>
                  <span class="meta-value">#GR-<?= str_pad($r['reservation_id'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
              </div>

              <!-- Status -->
              <div class="reservation-status-box">
                <?= statusBadge($r['reservation_status']) ?>
                <div class="status-note"><?= statusNote($r['reservation_status']) ?></div>
              </div>

            </div><!-- /reservation-top -->

            <div class="reservation-bottom">

              <!-- Details -->
              <div class="info-box">
                <h4>Reservation Details</h4>
                <div class="info-list">
                  <div><strong>Pickup Location:</strong> <?= htmlspecialchars($r['location']) ?></div>
                  <div><strong>Rental Period:</strong> <?= $days ?> day<?= $days !== 1 ? 's' : '' ?></div>
                  <div><strong>Payment Method:</strong> <?= $r['payment_method'] ? htmlspecialchars(ucwords($r['payment_method'])) : 'N/A' ?></div>
                  <div><strong>Payment Status:</strong> <?= $r['payment_status'] ? ucfirst($r['payment_status']) : 'N/A' ?></div>
                </div>
              </div>

              <!-- Review Box -->
              <div class="review-box">
                <h4>Rating & Review</h4>

                <?php if ($hasReview): ?>
                  <!-- Already reviewed -->
                  <div class="submitted-review">
                    <?= starsHtml($r['review_rating']) ?>
                    <div><?= htmlspecialchars($r['review_comment'] ?: 'No written comment.') ?></div>
                  </div>

                <?php elseif ($isCompleted): ?>
                  <!-- Completed — show review form -->
                  <form method="POST" action="my-reservations.php">
                    <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>"/>
                    <input type="hidden" name="equipment_id"   value="<?= $r['equipment_id'] ?>"/>
                    <input type="hidden" name="rating"         value="1" id="rating-<?= $r['reservation_id'] ?>"/>

                    <div class="stars" id="stars-<?= $r['reservation_id'] ?>">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-val="<?= $i ?>" data-res="<?= $r['reservation_id'] ?>">★</span>
                      <?php endfor; ?>
                    </div>

                    <textarea name="comment" class="review-textarea"
                              placeholder="Write your feedback about the equipment and rental experience..."></textarea>

                    <div class="review-actions">
                      <button type="reset" class="btn btn-outline">Clear</button>
                      <button type="submit" name="submit_review" class="btn btn-solid">Submit Review</button>
                    </div>
                  </form>

                <?php else: ?>
                  <!-- Not completed yet -->
                  <div class="empty-note" style="padding:0;">
                    <?= $r['reservation_status'] === 'cancelled'
                        ? 'Reviews are not available for cancelled reservations.'
                        : 'You can submit a rating after the rental is completed.' ?>
                  </div>
                <?php endif; ?>

              </div><!-- /review-box -->
            </div><!-- /reservation-bottom -->
          </article>
        <?php endwhile; ?>
      <?php endif; ?>
    </section>

  </main>

  <footer>
    <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
      <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee"/>
    </svg>
    <div class="footer-main">
      <div class="footer-logo"><img src="logo.png" alt="GreenRent Logo" /></div>
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

  <!-- Star rating interaction -->
  <script>
    document.querySelectorAll('.stars .star').forEach(star => {
      star.addEventListener('click', function () {
        const val = parseInt(this.dataset.val);
        const resId = this.dataset.res;
        const container = document.getElementById('stars-' + resId);
        const hiddenInput = document.getElementById('rating-' + resId);

        hiddenInput.value = val;
        container.querySelectorAll('.star').forEach((s, idx) => {
          s.classList.toggle('active', idx < val);
        });
      });

      star.addEventListener('mouseover', function () {
        const val = parseInt(this.dataset.val);
        const resId = this.dataset.res;
        document.getElementById('stars-' + resId).querySelectorAll('.star').forEach((s, idx) => {
          s.classList.toggle('active', idx < val);
        });
      });

      star.addEventListener('mouseout', function () {
        const resId = this.dataset.res;
        const current = parseInt(document.getElementById('rating-' + resId).value);
        document.getElementById('stars-' + resId).querySelectorAll('.star').forEach((s, idx) => {
          s.classList.toggle('active', idx < current);
        });
      });
    });
  </script>

</body>
</html>