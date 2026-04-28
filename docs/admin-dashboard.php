<?php
session_start();
require_once 'db.php';
requireRole('admin');

// ─── Current Admin ───────────────────────────────────────────────────────────
$currentUser  = getCurrentUser();
$adminName    = htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']);
$adminInitial = strtoupper($currentUser['first_name'][0]);

// ─── Dynamic Stats ────────────────────────────────────────────────────────────

$totalUsers     = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role != 'admin'"))[0];
$activeListings = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM equipment WHERE status = 'active'"))[0];
$flaggedReviews = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM reviews WHERE status = 'flagged'"))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent – Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="adminStyle.css" rel="stylesheet"/>
</head>
<body>

<header class="admin-header">
  <div class="admin-header-inner">
    <div style="display:flex;align-items:center;flex-shrink:0;">
      <a href="admin-dashboard.php" class="admin-logo">
        <img src="logo.png" alt="GreenRent Logo"/>
        <div class="admin-logo-text"><span>GreenRent</span><span>Agricultural Equipment</span></div>
      </a>
    </div>

    <ul class="admin-nav">
      <li><a href="admin-dashboard.php" class="active">Dashboard</a></li>
      <li><a href="admin-users.php">User Accounts</a></li>
      <li><a href="admin-listings.php">Equipment Listings</a></li>
      <li><a href="admin-reviews.php">Reviews & Ratings</a></li>
    </ul>

    <div class="admin-header-right">
      <div class="admin-profile">
        <div class="admin-avatar"><?= $adminInitial ?></div>
        <span class="admin-profile-name"><?= $adminName ?></span>
      </div>
      <div class="admin-divider"></div>
      <a href="logout.php" class="btn-logout">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Log Out
      </a>
    </div>
  </div>
</header>

<main class="admin-main">

  <div class="page-title-row">
    <div>
      <h1>Dashboard</h1>
      <p>Welcome back. Here's what's happening on GreenRent today.</p>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">

    <!-- Total Users -->
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon green">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round"/>
            <circle cx="9" cy="7" r="4" stroke="#2d6a4f" stroke-width="2"/>
          </svg>
        </div>
      </div>
      <div class="stat-value"><?= (int) $totalUsers ?></div>
      <div class="stat-label">Total Users</div>
    </div>

    <!-- Active Listings -->
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon green">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>
      <div class="stat-value"><?= (int) $activeListings ?></div>
      <div class="stat-label">Active Listings</div>
    </div>

    <!-- Flagged Reviews -->
    <div class="stat-card">
      <div class="stat-card-top">
        <div class="stat-icon red">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="#c0392b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <?php if ($flaggedReviews > 0): ?>
          <span class="stat-trend down"><?= (int) $flaggedReviews ?> flagged</span>
        <?php endif; ?>
      </div>
      <div class="stat-value"><?= (int) $flaggedReviews ?></div>
      <div class="stat-label">Flagged Reviews</div>
    </div>

  </div>

  <!-- Quick Nav Shortcuts -->
  <div class="shortcuts-grid">
    <a href="admin-users.php" class="shortcut-card">
      <div class="stat-icon green" style="flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round"/>
          <circle cx="9" cy="7" r="4" stroke="#2d6a4f" stroke-width="2"/>
        </svg>
      </div>
      <div class="shortcut-card-text">
        <strong>User Accounts</strong>
        <span>View, suspend, or reactivate accounts</span>
      </div>
    </a>

    <a href="admin-listings.php" class="shortcut-card">
      <div class="stat-icon green" style="flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M12 2L2 7l10 5 10-5-10-5z" stroke="#2d6a4f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="shortcut-card-text">
        <strong>Equipment Listings</strong>
        <span>Review and deactivate listings</span>
      </div>
    </a>

    <a href="admin-reviews.php" class="shortcut-card">
      <div class="stat-icon red" style="flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="#c0392b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="shortcut-card-text">
        <strong>Reviews & Ratings</strong>
        <span>Monitor reviews</span>
      </div>
    </a>
  </div>

</main>
</body>
</html>