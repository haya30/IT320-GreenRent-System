<?php
session_start();
require_once 'db.php';
requireRole('renter');

$currentUser = getCurrentUser();
$renterId    = $currentUser['user_id'];

// ── Get equipment_id from URL ─────────────────────────────────────────────────
$equipmentId = isset($_GET['equipment_id']) ? (int) $_GET['equipment_id'] : 0;

if (!$equipmentId) {
    header("Location: farmer-dashboard.php");
    exit;
}

// ── Fetch equipment details ───────────────────────────────────────────────────
$eq = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
     FROM equipment e
     JOIN users u ON e.owner_id = u.user_id
     WHERE e.equipment_id = $equipmentId AND e.status = 'active'"
));

if (!$eq) {
    header("Location: farmer-dashboard.php");
    exit;
}

// ── Fetch already-booked date ranges for this equipment ───────────────────────
$bookedRanges = [];
$booked = mysqli_query($conn,
    "SELECT start_date, end_date FROM reservations
     WHERE equipment_id = $equipmentId
     AND reservation_status IN ('pending','confirmed')"
);
while ($b = mysqli_fetch_assoc($booked)) {
    $bookedRanges[] = ['start' => $b['start_date'], 'end' => $b['end_date']];
}
$bookedJson = json_encode($bookedRanges);

// ── Handle form submission ────────────────────────────────────────────────────
$error      = '';
$success    = false;
$newResId   = null;
$totalPrice = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate     = mysqli_real_escape_string($conn, $_POST['startDate'] ?? '');
    $endDate       = mysqli_real_escape_string($conn, $_POST['endDate'] ?? '');
    $paymentMethod = $_POST['paymentMethod'] === 'bank' ? 'bank transfer' : 'online payment';
    $today         = date('Y-m-d');

    // Validation
    if (!$startDate || !$endDate) {
        $error = 'Please select both start and end dates.';
    } elseif ($startDate < $today) {
        $error = 'Start date cannot be in the past.';
    } elseif ($endDate <= $startDate) {
        $error = 'End date must be after the start date.';
    } else {
        $days = (strtotime($endDate) - strtotime($startDate)) / 86400;
        if ($days < 1) {
            $error = 'Minimum rental period is 1 day.';
        } else {
            // Check availability — no overlap with existing reservations
            $overlap = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT reservation_id FROM reservations
                 WHERE equipment_id = $equipmentId
                 AND reservation_status IN ('pending','confirmed')
                 AND start_date < '$endDate' AND end_date > '$startDate'"
            ));

            if ($overlap) {
                $error = 'Sorry, this equipment is already booked for the selected dates. Please choose different dates.';
            } else {
                $totalPrice = $days * $eq['price_per_day'];

                // Insert reservation
                mysqli_query($conn,
                    "INSERT INTO reservations (equipment_id, renter_id, start_date, end_date, reservation_status, total_price)
                     VALUES ($equipmentId, $renterId, '$startDate', '$endDate', 'pending', $totalPrice)"
                );
                $newResId = mysqli_insert_id($conn);

                // Insert payment record
                $paymentDate = date('Y-m-d');
                mysqli_query($conn,
                    "INSERT INTO payments (reservation_id, amount, payment_method, payment_status, payment_date)
                     VALUES ($newResId, $totalPrice, '$paymentMethod', 'pending', '$paymentDate')"
                );

                // Mark equipment as unavailable if needed
                mysqli_query($conn,
                    "UPDATE equipment SET availability_status = 'unavailable'
                     WHERE equipment_id = $equipmentId"
                );

                $success = true;
            }
        }
    }
}

$pricePerDay = $eq['price_per_day'];
$operatorText = $eq['operator_included'] ? 'Operator Included' : 'No Operator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reservation — <?= htmlspecialchars($eq['equipment_name']) ?> | GreenRent</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet"/>
  <link href="pages.css" rel="stylesheet"/>
  <style>
    .gr-logo img { height:42px; width:auto; border-radius:0; background:transparent; }
    .footer-logo img { height:48px; width:auto; display:block; border-radius:10%; }

    .res-hero {
      background: linear-gradient(135deg, var(--green-deep) 0%, var(--green-mid) 55%, #3a8a5f 100%);
      padding:36px 0; position:relative; overflow:hidden;
    }
    .res-hero::before {
      content:''; position:absolute; top:-40px; right:-40px;
      width:280px; height:280px; border-radius:50%;
      background:rgba(255,255,255,.04); pointer-events:none;
    }
    .res-hero-inner { max-width:1200px; margin:0 auto; padding:0 32px; position:relative; z-index:1; }
    .res-hero-tag {
      display:inline-flex; align-items:center; gap:6px;
      background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
      border-radius:20px; padding:5px 14px; font-size:12px; font-weight:600;
      color:rgba(255,255,255,.85); margin-bottom:12px; text-transform:uppercase; letter-spacing:.5px;
    }
    .res-hero h1 { font-family:'DM Serif Display',serif; font-size:32px; color:white; margin-bottom:8px; }
    .res-hero p { font-size:14.5px; color:rgba(255,255,255,.68); }

    .steps-bar { background:var(--white); border-bottom:1px solid rgba(82,183,136,.15); }
    .steps-inner {
      max-width:1200px; margin:0 auto; padding:0 32px;
      display:flex; align-items:center; height:56px; gap:8px;
    }
    .step { display:flex; align-items:center; gap:8px; font-size:13.5px; font-weight:500; color:var(--text-muted); }
    .step.active { color:var(--green-mid); font-weight:700; }
    .step.done { color:var(--green-light); }
    .step-num {
      width:26px; height:26px; border-radius:50%; background:#e8f5e9;
      border:2px solid rgba(82,183,136,.25); display:flex; align-items:center;
      justify-content:center; font-size:12px; font-weight:700; color:var(--text-muted);
    }
    .step.active .step-num { background:var(--green-mid); border-color:var(--green-mid); color:white; }
    .step.done .step-num { background:var(--green-light); border-color:var(--green-light); color:white; }
    .step-divider { flex:1; max-width:60px; height:1px; background:rgba(82,183,136,.20); }

    .res-wrap {
      max-width:1200px; margin:0 auto; padding:36px 32px;
      display:grid; grid-template-columns:1fr 380px; gap:28px; align-items:start;
    }
    .form-card { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow-sm); overflow:hidden; margin-bottom:20px; }
    .form-card-header {
      padding:18px 24px; border-bottom:1px solid rgba(82,183,136,.12);
      display:flex; align-items:center; gap:12px;
    }
    .form-card-icon {
      width:36px; height:36px; border-radius:10px; background:var(--green-pale);
      display:flex; align-items:center; justify-content:center; color:var(--green-mid); flex-shrink:0;
    }
    .form-card-title { font-weight:700; font-size:15.5px; color:var(--green-deep); }
    .form-card-sub { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
    .form-card-body { padding:24px; display:flex; flex-direction:column; gap:16px; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-row.three { grid-template-columns:1fr 1fr 1fr; }

    .duration-pill {
      background:linear-gradient(135deg, var(--green-pale), #c8f0d4);
      border:1px solid rgba(82,183,136,.25); border-radius:10px;
      padding:14px 18px; display:flex; align-items:center; gap:14px;
    }
    .duration-icon {
      width:38px; height:38px; border-radius:10px; background:var(--green-mid);
      display:flex; align-items:center; justify-content:center; color:white; flex-shrink:0;
    }
    .duration-text .val { font-weight:700; font-size:16px; color:var(--green-deep); }
    .duration-text .lbl { font-size:12px; color:var(--text-muted); margin-top:2px; }

    .card-input-wrap { position:relative; }
    .card-icons { position:absolute; right:12px; top:50%; transform:translateY(-50%); display:flex; gap:4px; }
    .card-icon { width:32px; height:20px; background:#eee; border-radius:3px; display:flex; align-items:center; justify-content:center; font-size:8px; font-weight:700; letter-spacing:.3px; }
    .card-icon.visa { background:#1a1f71; color:white; }
    .card-icon.mc { background:#eb001b; color:white; }

    .payment-toggle { display:flex; gap:12px; margin-bottom:4px; }
    .payment-option {
      flex:1; padding:14px; border:2px solid rgba(82,183,136,.2); border-radius:12px;
      cursor:pointer; display:flex; align-items:center; gap:10px;
      font-size:13.5px; font-weight:600; color:var(--text-dark); transition:all .2s;
    }
    .payment-option.selected { border-color:var(--green-mid); background:var(--green-pale); }
    .payment-option input { accent-color:var(--green-mid); }

    .card-fields { transition:opacity .3s; }
    .card-fields.hidden { display:none; }

    .summary-card {
      background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow-md);
      overflow:hidden; position:sticky; top:88px;
    }
    .summary-header { background:linear-gradient(135deg, var(--green-deep), #2a5a3f); padding:20px 22px; }
    .summary-title { font-family:'DM Serif Display',serif; font-size:18px; color:white; margin-bottom:16px; }
    .summary-equip { display:flex; align-items:center; gap:12px; }
    .summary-equip-img {
      width:52px; height:52px; border-radius:10px; background:rgba(255,255,255,.15);
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .summary-equip-img svg { color:rgba(255,255,255,.7); }
    .summary-equip-name { font-weight:700; font-size:14.5px; color:white; }
    .summary-equip-meta { font-size:12px; color:rgba(255,255,255,.65); margin-top:3px; }
    .summary-body { padding:20px 22px; }
    .sum-row {
      display:flex; justify-content:space-between; align-items:center;
      padding:8px 0; font-size:13.5px; border-bottom:1px solid rgba(82,183,136,.08);
    }
    .sum-row:last-child { border-bottom:none; }
    .sum-row .key { color:var(--text-muted); }
    .sum-row .val { font-weight:600; color:var(--text-dark); }
    .sum-row.total-row { margin-top:8px; padding-top:14px; border-top:2px solid rgba(82,183,136,.18); border-bottom:none; }
    .sum-row.total-row .key { font-weight:700; font-size:15px; color:var(--green-deep); }
    .sum-row.total-row .val { font-family:'DM Serif Display',serif; font-size:22px; color:var(--green-mid); }

    .trust-block { background:var(--cream); border-top:1px solid rgba(82,183,136,.12); padding:16px 22px; display:flex; flex-direction:column; gap:10px; }
    .trust-item { display:flex; align-items:center; gap:10px; font-size:12.5px; color:var(--text-muted); }
    .trust-item svg { color:var(--green-mid); flex-shrink:0; }

    .error-banner {
      background:#fef2f2; border:1px solid #fca5a5; border-radius:12px;
      padding:14px 18px; color:#b91c1c; font-size:13.5px; margin-bottom:20px;
      display:flex; align-items:center; gap:10px;
    }

    .success-screen {
      display:none; text-align:center; padding:60px 32px;
      background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow-md);
    }
    .success-screen.show { display:block; }
    .success-icon {
      width:80px; height:80px; border-radius:50%;
      background:linear-gradient(135deg, #d8f3dc, #b7e4c7);
      display:flex; align-items:center; justify-content:center;
      margin:0 auto 20px; animation:pop .4s ease;
    }
    @keyframes pop { 0%{transform:scale(.5);opacity:0} 80%{transform:scale(1.08)} 100%{transform:scale(1);opacity:1} }
    .success-icon svg { color:var(--green-mid); }
    .success-screen h2 { font-family:'DM Serif Display',serif; font-size:28px; color:var(--green-deep); margin-bottom:10px; }
    .success-screen p { font-size:14.5px; color:var(--text-muted); max-width:420px; margin:0 auto 24px; line-height:1.65; }
    .success-ref { background:var(--green-pale); border-radius:10px; padding:14px 20px; display:inline-block; font-weight:700; font-size:14px; color:var(--green-deep); margin-bottom:24px; letter-spacing:.5px; }
    .success-details { background:var(--cream); border-radius:12px; padding:18px; text-align:left; max-width:400px; margin:0 auto 28px; }
    .success-detail-row { display:flex; justify-content:space-between; font-size:13.5px; padding:6px 0; border-bottom:1px solid rgba(82,183,136,.10); }
    .success-detail-row:last-child { border-bottom:none; }
    .success-detail-row .k { color:var(--text-muted); }
    .success-detail-row .v { font-weight:600; color:var(--text-dark); }

    .booked-dates-notice {
      background:#fefce8; border:1px solid #fde68a; border-radius:10px;
      padding:12px 16px; font-size:13px; color:#a16207; margin-top:8px;
    }

    @media (max-width:900px) {
      .res-wrap { grid-template-columns:1fr; }
      .summary-card { position:static; }
      .form-row { grid-template-columns:1fr; }
      .form-row.three { grid-template-columns:1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- HEADER -->
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
      <li><a href="farmer-dashboard.php">Dashboard</a></li>
      <li><a href="my-reservations.php">My Reservations</a></li>
      <li><a href="farmer-profile.php">Profile</a></li>
    </ul>
    <div class="gr-nav-actions">
      <div class="farmer-badge">
        <div class="avatar"><?= strtoupper($currentUser['first_name'][0] . $currentUser['last_name'][0]) ?></div>
        <?= htmlspecialchars($currentUser['first_name']) ?>
      </div>
      <a href="logout.php" class="btn btn-outline">Log Out</a>
    </div>
  </nav>
</header>

<!-- HERO -->
<section class="res-hero">
  <div class="res-hero-inner">
    <div class="breadcrumb" style="margin-bottom:14px;">
      <a href="farmer-dashboard.php" style="color:rgba(255,255,255,.7);">Dashboard</a>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <a href="equipment-details.php?id=<?= $equipmentId ?>" style="color:rgba(255,255,255,.7);">Equipment Details</a>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <span style="color:white;">Reservation</span>
    </div>
    <div class="res-hero-tag">📋 Reservation Page</div>
    <h1>Complete Your Reservation</h1>
    <p>Review your booking for <strong><?= htmlspecialchars($eq['equipment_name']) ?></strong>, select rental dates, and confirm your booking.</p>
  </div>
</section>

<!-- STEPS -->
<div class="steps-bar">
  <div class="steps-inner">
    <div class="step done">
      <div class="step-num"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
      Browse Equipment
    </div>
    <div class="step-divider"></div>
    <div class="step done">
      <div class="step-num"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
      View Details
    </div>
    <div class="step-divider"></div>
    <div class="step <?= $success ? 'done' : 'active' ?>">
      <div class="step-num"><?= $success ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>' : '3' ?></div>
      Reservation
    </div>
    <div class="step-divider"></div>
    <div class="step <?= $success ? 'active' : '' ?>">
      <div class="step-num">4</div>
      Confirmation
    </div>
  </div>
</div>

<!-- SUCCESS SCREEN -->
<?php if ($success): ?>
<div style="max-width:780px;margin:40px auto;padding:0 32px 60px;">
  <div class="success-screen show">
    <div class="success-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h2>Reservation Submitted! 🌾</h2>
    <p>Your equipment has been successfully reserved. The owner will review and confirm your booking shortly.</p>
    <div class="success-ref">Booking Reference: #GR-<?= str_pad($newResId, 4, '0', STR_PAD_LEFT) ?></div>
    <div class="success-details">
      <div class="success-detail-row">
        <span class="k">Equipment</span>
        <span class="v"><?= htmlspecialchars($eq['equipment_name']) ?></span>
      </div>
      <div class="success-detail-row">
        <span class="k">Rental Period</span>
        <span class="v"><?= date('d M Y', strtotime($_POST['startDate'])) ?> – <?= date('d M Y', strtotime($_POST['endDate'])) ?></span>
      </div>
      <div class="success-detail-row">
        <span class="k">Duration</span>
        <span class="v"><?= (strtotime($_POST['endDate']) - strtotime($_POST['startDate'])) / 86400 ?> days</span>
      </div>
      <div class="success-detail-row">
        <span class="k">Total Amount</span>
        <span class="v">SAR <?= number_format($totalPrice, 2) ?></span>
      </div>
      <div class="success-detail-row">
        <span class="k">Status</span>
        <span class="v" style="color:var(--green-mid);">⏳ Pending Owner Confirmation</span>
      </div>
    </div>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
      <a href="farmer-dashboard.php" class="btn btn-solid btn-lg">Back to Dashboard</a>
      <a href="my-reservations.php" class="btn btn-outline btn-lg">View My Reservations</a>
    </div>
  </div>
</div>

<?php else: ?>

<!-- MAIN FORM -->
<div class="res-wrap">

  <!-- LEFT: Forms -->
  <div id="formsCol">

    <?php if ($error): ?>
    <div class="error-banner">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="reservation.php?equipment_id=<?= $equipmentId ?>" id="resForm">

      <!-- SECTION 1: Dates -->
      <div class="form-card">
        <div class="form-card-header">
          <div class="form-card-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div>
            <div class="form-card-title">Rental Dates</div>
            <div class="form-card-sub">Select your start and end dates — unavailable dates are blocked</div>
          </div>
        </div>
        <div class="form-card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Start Date *</label>
              <input type="date" class="form-control" id="startDate" name="startDate"
                     min="<?= date('Y-m-d') ?>"
                     value="<?= htmlspecialchars($_POST['startDate'] ?? '') ?>" required/>
              <span class="error-msg" id="startDateErr">Please select a valid start date</span>
            </div>
            <div class="form-group">
              <label class="form-label">End Date *</label>
              <input type="date" class="form-control" id="endDate" name="endDate"
                     min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     value="<?= htmlspecialchars($_POST['endDate'] ?? '') ?>" required/>
              <span class="error-msg" id="endDateErr">End date must be after start date</span>
            </div>
          </div>

          <?php if (!empty($bookedRanges)): ?>
          <div class="booked-dates-notice">
            ⚠️ Some dates are already booked. Your selected dates must not overlap with existing reservations.
          </div>
          <?php endif; ?>

          <div class="duration-pill" id="durationPill">
            <div class="duration-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
            </div>
            <div class="duration-text">
              <div class="val" id="durationVal">Select dates above</div>
              <div class="lbl" id="durationLbl">Duration and subtotal will appear here</div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Special Notes (Optional)</label>
            <textarea class="form-control" name="notes" rows="3"
                      placeholder="e.g. Delivery to south field gate, preferred morning start time…"
                      style="resize:vertical;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- SECTION 2: Farmer Info (pre-filled from session) -->
      <div class="form-card">
        <div class="form-card-header">
          <div class="form-card-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div>
            <div class="form-card-title">Farmer Information</div>
            <div class="form-card-sub">Your contact details — pre-filled from your account</div>
          </div>
        </div>
        <div class="form-card-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['first_name']) ?>" readonly style="background:#f3f4f6;color:var(--text-muted);"/>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['last_name']) ?>" readonly style="background:#f3f4f6;color:var(--text-muted);"/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['phone_number']) ?>" readonly style="background:#f3f4f6;color:var(--text-muted);"/>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly style="background:#f3f4f6;color:var(--text-muted);"/>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Farm / Delivery Location *</label>
            <input type="text" class="form-control" id="location" name="location"
                   placeholder="e.g. Farm Al-Rawabi, Diriyah District, Riyadh"
                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required/>
            <span class="error-msg" id="locationErr">Delivery location is required</span>
          </div>
        </div>
      </div>

      <!-- SECTION 3: Payment -->
      <div class="form-card">
        <div class="form-card-header">
          <div class="form-card-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          </div>
          <div>
            <div class="form-card-title">Payment Method</div>
            <div class="form-card-sub">Secured by 256-bit SSL encryption · PCI DSS compliant</div>
          </div>
        </div>
        <div class="form-card-body">

          <!-- Payment toggle -->
          <div class="payment-toggle">
            <label class="payment-option selected" id="optOnline">
              <input type="radio" name="paymentMethod" value="online" checked onchange="togglePayment('online')"/>
              💳 Online Payment
            </label>
            <label class="payment-option" id="optBank">
              <input type="radio" name="paymentMethod" value="bank" onchange="togglePayment('bank')"/>
              🏦 Bank Transfer
            </label>
          </div>

          <!-- Card fields (online only) -->
          <div class="card-fields" id="cardFields">
            <div class="form-group">
              <label class="form-label">Cardholder Name *</label>
              <input type="text" class="form-control" id="cardName" name="cardName"
                     placeholder="Name as it appears on card"
                     value="<?= htmlspecialchars($_POST['cardName'] ?? '') ?>"/>
              <span class="error-msg" id="cardNameErr">Cardholder name is required</span>
            </div>
            <div class="form-group">
              <label class="form-label">Card Number *</label>
              <div class="card-input-wrap">
                <input type="text" class="form-control" id="cardNumber" name="cardNumber"
                       placeholder="1234  5678  9012  3456" maxlength="19" style="padding-right:100px;"
                       value="<?= htmlspecialchars($_POST['cardNumber'] ?? '') ?>"/>
                <div class="card-icons">
                  <div class="card-icon visa">VISA</div>
                  <div class="card-icon mc">MC</div>
                </div>
              </div>
              <span class="error-msg" id="cardNumberErr">Enter a valid 16-digit card number</span>
            </div>
            <div class="form-row three">
              <div class="form-group" style="grid-column:1/3;">
                <label class="form-label">Expiry Date *</label>
                <input type="text" class="form-control" id="expiry" name="expiry"
                       placeholder="MM / YY" maxlength="7"
                       value="<?= htmlspecialchars($_POST['expiry'] ?? '') ?>"/>
                <span class="error-msg" id="expiryErr">Enter valid expiry (MM/YY)</span>
              </div>
              <div class="form-group">
                <label class="form-label">CVV *</label>
                <input type="text" class="form-control" id="cvv" name="cvv"
                       placeholder="123" maxlength="4"
                       value="<?= htmlspecialchars($_POST['cvv'] ?? '') ?>"/>
                <span class="error-msg" id="cvvErr">Enter CVV</span>
              </div>
            </div>
          </div>

          <!-- Bank transfer note -->
          <div class="card-fields hidden" id="bankFields">
            <div style="background:rgba(82,183,136,.08);border:1px solid rgba(82,183,136,.20);border-radius:10px;padding:16px;font-size:13.5px;color:var(--text-muted);line-height:1.7;">
              🏦 <strong style="color:var(--text-dark);">Bank Transfer Instructions:</strong><br/>
              After submitting your reservation, you will receive an email with our bank account details.
              Please complete the transfer within 24 hours to confirm your booking.
            </div>
          </div>

          <div style="background:rgba(82,183,136,.08);border:1px solid rgba(82,183,136,.20);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text-muted);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--green-mid)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Your payment details are encrypted and never stored on our servers.
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="btn btn-solid btn-lg" id="confirmBtn"
                onclick="submitReservation()" style="flex:1;justify-content:center;min-width:200px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Confirm Reservation
        </button>
        <a href="equipment-details.php?id=<?= $equipmentId ?>" class="btn btn-outline btn-lg" style="justify-content:center;">
          ← Back to Details
        </a>
        <a href="farmer-dashboard.php" class="btn btn-sm" style="color:var(--text-muted);background:none;margin-left:auto;">Cancel</a>
      </div>

      <!-- Hidden submit trigger -->
      <button type="submit" id="realSubmit" style="display:none;">Submit</button>

    </form>
  </div><!-- /formsCol -->

  <!-- RIGHT: Summary -->
  <div>
    <div class="summary-card">
      <div class="summary-header">
        <div class="summary-title">Booking Summary</div>
        <div class="summary-equip">
          <div class="summary-equip-img">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="10" width="14" height="8" rx="2"/><circle cx="6" cy="18" r="2"/><circle cx="14" cy="18" r="2"/><path d="M16 12h4l2 4H16"/><circle cx="20" cy="18" r="2"/></svg>
          </div>
          <div>
            <div class="summary-equip-name"><?= htmlspecialchars($eq['equipment_name']) ?></div>
            <div class="summary-equip-meta"><?= htmlspecialchars($eq['type']) ?> · <?= htmlspecialchars($eq['location']) ?> · <?= $operatorText ?></div>
          </div>
        </div>
      </div>
      <div class="summary-body">
        <div class="sum-row">
          <span class="key">Daily Rate</span>
          <span class="val">SAR <?= number_format($eq['price_per_day'], 2) ?> / day</span>
        </div>
        <div class="sum-row">
          <span class="key">Rental Period</span>
          <span class="val" id="sumDays">—</span>
        </div>
        <div class="sum-row">
          <span class="key">Start Date</span>
          <span class="val" id="sumStart">—</span>
        </div>
        <div class="sum-row">
          <span class="key">End Date</span>
          <span class="val" id="sumEnd">—</span>
        </div>
        <div class="sum-row">
          <span class="key">Subtotal</span>
          <span class="val" id="sumSubtotal">—</span>
        </div>
        <div class="sum-row total-row">
          <span class="key">Total Due</span>
          <span class="val" id="sumTotal">—</span>
        </div>
      </div>
      <div class="trust-block">
        <div class="trust-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
          Free cancellation up to 48 hours before start
        </div>
        <div class="trust-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          Payments secured by 256-bit SSL encryption
        </div>
        <div class="trust-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Verified equipment — inspected before every rental
        </div>
      </div>
    </div>
  </div>

</div><!-- /res-wrap -->
<?php endif; ?>

<!-- FOOTER -->
<footer>
  <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
    <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee"/>
  </svg>
  <div class="footer-main">
    <div class="footer-logo"><img src="logo.png" alt="GreenRent Logo" /></div>
    <p class="footer-tagline">A trusted platform connecting farmers and equipment owners across Riyadh.</p>
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

<script>
  const PRICE_PER_DAY = <?= (float) $eq['price_per_day'] ?>;
  const bookedRanges  = <?= $bookedJson ?>;

  // ── Date inputs: block past dates ──
  const todayStr = '<?= date('Y-m-d') ?>';
  document.getElementById('startDate').min = todayStr;

  document.getElementById('startDate').addEventListener('change', function() {
    const s = this.value;
    if (s) {
      const next = new Date(s);
      next.setDate(next.getDate() + 1);
      document.getElementById('endDate').min = next.toISOString().split('T')[0];
    }
    updateSummary();
  });
  document.getElementById('endDate').addEventListener('change', updateSummary);

  function isOverlapping(start, end) {
    return bookedRanges.some(r => start < r.end && end > r.start);
  }

  function formatDate(d) {
    return d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
  }

  function updateSummary() {
    const sVal = document.getElementById('startDate').value;
    const eVal = document.getElementById('endDate').value;
    if (!sVal || !eVal) return;
    const s = new Date(sVal), e = new Date(eVal);
    if (e <= s) return;

    const days = Math.ceil((e - s) / 86400000);
    const subtotal = days * PRICE_PER_DAY;

    document.getElementById('durationVal').textContent = days + ' day' + (days > 1 ? 's' : '') + ' rental';
    document.getElementById('durationLbl').textContent = formatDate(s) + ' – ' + formatDate(e) + ' · SAR ' + subtotal.toLocaleString();
    document.getElementById('sumDays').textContent = days + ' days';
    document.getElementById('sumStart').textContent = formatDate(s);
    document.getElementById('sumEnd').textContent = formatDate(e);
    document.getElementById('sumSubtotal').textContent = 'SAR ' + subtotal.toLocaleString();
    document.getElementById('sumTotal').textContent = 'SAR ' + subtotal.toLocaleString();
  }

  // ── Payment toggle ──
  function togglePayment(type) {
    document.getElementById('cardFields').classList.toggle('hidden', type !== 'online');
    document.getElementById('bankFields').classList.toggle('hidden', type !== 'bank');
    document.getElementById('optOnline').classList.toggle('selected', type === 'online');
    document.getElementById('optBank').classList.toggle('selected', type === 'bank');
  }

  // ── Card number formatting ──
  document.getElementById('cardNumber').addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g,'').substring(0,16);
    e.target.value = v.replace(/(.{4})/g,'$1  ').trim();
  });

  // ── Expiry formatting ──
  document.getElementById('expiry').addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 2) v = v.substring(0,2) + ' / ' + v.substring(2);
    e.target.value = v;
  });

  function validateField(id, errId, condition) {
    const el = document.getElementById(id);
    const err = document.getElementById(errId);
    if (!condition(el.value)) {
      el.classList.add('error');
      err.classList.add('show');
      return false;
    }
    el.classList.remove('error');
    err.classList.remove('show');
    return true;
  }

  function submitReservation() {
    const sVal = document.getElementById('startDate').value;
    const eVal = document.getElementById('endDate').value;
    const isOnline = document.querySelector('input[name="paymentMethod"]:checked').value === 'online';

    let valid = true;
    if (!validateField('startDate','startDateErr', v => v !== '' && v >= todayStr)) valid = false;
    if (!validateField('endDate','endDateErr', v => v !== '' && v > sVal)) valid = false;
    if (!validateField('location','locationErr', v => v.trim().length > 0)) valid = false;

    if (isOnline) {
      if (!validateField('cardName','cardNameErr', v => v.trim().length > 0)) valid = false;
      if (!validateField('cardNumber','cardNumberErr', v => v.replace(/\s/g,'').length === 16)) valid = false;
      if (!validateField('expiry','expiryErr', v => /^\d{2}\s*\/\s*\d{2}$/.test(v))) valid = false;
      if (!validateField('cvv','cvvErr', v => /^\d{3,4}$/.test(v))) valid = false;
    }

    // Check overlap client-side before submitting
    if (sVal && eVal && isOverlapping(sVal, eVal)) {
      document.getElementById('startDateErr').textContent = 'These dates overlap with an existing booking. Please choose different dates.';
      document.getElementById('startDate').classList.add('error');
      document.getElementById('startDateErr').classList.add('show');
      valid = false;
    }

    if (!valid) {
      document.querySelector('.error-msg.show')?.scrollIntoView({behavior:'smooth', block:'center'});
      return;
    }

    const btn = document.getElementById('confirmBtn');
    btn.textContent = 'Processing…';
    btn.disabled = true;
    btn.style.opacity = '.7';

    document.getElementById('realSubmit').click();
  }

  // ── Init summary if dates already set (on error re-render) ──
  if (document.getElementById('startDate').value && document.getElementById('endDate').value) {
    updateSummary();
  }
</script>

</body>
</html>