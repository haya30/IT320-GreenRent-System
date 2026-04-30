<?php
session_start();
require_once __DIR__ . '/db.php';

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$currentUser  = getCurrentUser();
$adminName    = htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']);
$adminInitial = strtoupper($currentUser['first_name'][0]);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['action'])) {
    $reviewId = (int)$_POST['review_id'];
    $action = $_POST['action'];

    if ($action === 'flag') {
        $stmt = $conn->prepare("UPDATE reviews SET status = 'flagged' WHERE review_id = ?");
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        $message = 'Review flagged successfully.';
    } elseif ($action === 'normal') {
        $stmt = $conn->prepare("UPDATE reviews SET status = 'normal' WHERE review_id = ?");
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        $message = 'Review marked as normal.';
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->bind_param('i', $reviewId);
        $stmt->execute();
        $message = 'Review deleted successfully.';
    }
}

$summary = $conn->query("SELECT COUNT(*) AS total_reviews, ROUND(AVG(rating), 1) AS avg_rating FROM reviews")->fetch_assoc();
$totalReviews = (int)($summary['total_reviews'] ?? 0);
$avgRating = $summary['avg_rating'] ?? 'N/A';

$reviews = $conn->query("SELECT rv.review_id, rv.rating, rv.comment, rv.review_date, rv.status, u.first_name, u.last_name, u.email, e.equipment_name FROM reviews rv JOIN users u ON rv.renter_id = u.user_id JOIN equipment e ON rv.equipment_id = e.equipment_id ORDER BY rv.review_date DESC, rv.review_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent – Reviews & Ratings</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="adminStyle.css" rel="stylesheet"/>
  <style>
    .stars     { color: #e8b500; font-size: 13px; letter-spacing: 1px; }
    .stars-low { color: #c0392b; font-size: 13px; letter-spacing: 1px; }

    .pill.flagged { background: rgba(220,60,60,.10); color: #c0392b; }
    .row-flagged td { background: rgba(220,60,60,.03); }

    .comment-cell {
      max-width: 340px;
      font-size: 13px;
      color: var(--text-muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Summary — only 2 chips */
    .summary-strip {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
    }
    .summary-chip {
      background: var(--white);
      border: 1px solid rgba(82,183,136,.12);
      border-radius: 12px;
      padding: 18px 28px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }
    .summary-chip .num {
      font-family: 'DM Serif Display', serif;
      font-size: 30px;
      line-height: 1;
      color: var(--green-deep);
    }
    .summary-chip .lbl {
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 500;
    }

    /* Sort buttons only — no search wrapper needed */
    .sort-bar {
      display: flex;
      align-items: center;
      gap: 7px;
    }
    .sort-label { font-size: 12px; font-weight: 600; color: var(--text-muted); white-space: nowrap; }
    .sort-btn {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 5px 11px; border-radius: 8px;
      border: 1.5px solid transparent; background: transparent;
      font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600;
      color: var(--text-muted); cursor: pointer; transition: all .15s;
    }
    .sort-btn:hover { background: var(--green-pale); color: var(--green-mid); }
    .sort-btn.active { background: var(--green-pale); color: var(--green-mid); border-color: rgba(82,183,136,.35); }

    .message-success {
      background:#ecfdf3;
      border:1px solid #bbf7d0;
      color:#15803d;
      padding:12px 16px;
      border-radius:12px;
      margin-bottom:16px;
    }
  </style>
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
      <li><a href="admin-dashboard.php">Dashboard</a></li>
      <li><a href="admin-users.php">User Accounts</a></li>
      <li><a href="admin-listings.php">Equipment Listings</a></li>
      <li><a href="admin-reviews.php" class="active">Reviews & Ratings</a></li>
    </ul>

    <div class="admin-header-right">
      <div class="admin-profile">
        <div class="admin-avatar">A</div>
        <span class="admin-profile-name">Admin</span>
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
      <h1>Reviews &amp; Ratings</h1>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="message-success"><?= e($message) ?></div>
  <?php endif; ?>

  <!-- 2 summary chips only -->
  <div class="summary-strip">
    <div class="summary-chip">
      <div class="num"><?= e($totalReviews) ?></div>
      <div class="lbl">Total Reviews</div>
    </div>
    <div class="summary-chip">
      <div class="num"><?= e($avgRating ?: 'N/A') ?></div>
      <div class="lbl">Platform Avg. Rating</div>
    </div>
  </div>

  <!-- Table card -->
  <div class="table-card">
    <div class="table-card-header">
      <div><h2>All Reviews</h2></div>

      <!-- Sort only -->
      <div class="sort-bar">
        <span class="sort-label">Sort by rating:</span>
        <button class="sort-btn" id="sort-asc" onclick="sortByRating('asc')">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Lowest
        </button>
        <button class="sort-btn" id="sort-desc" onclick="sortByRating('desc')">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Highest
        </button>
      </div>
    </div>

    <table class="full-table">
      <thead>
        <tr>
          <th>Reviewer</th>
          <th>Equipment</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="reviews-tbody">

        <?php if ($reviews && $reviews->num_rows > 0): ?>
          <?php while($r = $reviews->fetch_assoc()): ?>
            <?php
              $reviewer = trim($r['first_name'] . ' ' . $r['last_name']);
              $stars = str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']);
              $isFlagged = $r['status'] === 'flagged' || (int)$r['rating'] <= 2;
            ?>
            <tr class="<?= $isFlagged ? 'row-flagged' : '' ?>" data-rating="<?= e($r['rating']) ?>">
              <td><div class="user-cell"><span><?= e($reviewer) ?></span><span><?= e($r['email']) ?></span></div></td>
              <td><?= e($r['equipment_name']) ?></td>
              <td><span class="<?= (int)$r['rating'] <= 2 ? 'stars-low' : 'stars' ?>"><?= e($stars) ?></span> <?= e($r['rating']) ?></td>
              <td><div class="comment-cell">"<?= e($r['comment'] ?: 'No comment') ?>"</div></td>
              <td><?= e($r['review_date']) ?></td>
              <td><span class="pill <?= $isFlagged ? 'flagged' : 'active' ?>"><?= $isFlagged ? '⚠ Flagged' : 'Normal' ?></span></td>
              <td>
                <div class="action-btns">
                  <?php if ($r['status'] === 'flagged'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="review_id" value="<?= e($r['review_id']) ?>">
                      <input type="hidden" name="action" value="normal">
                      <button class="icon-btn restore" type="submit">Mark Normal</button>
                    </form>
                  <?php else: ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="review_id" value="<?= e($r['review_id']) ?>">
                      <input type="hidden" name="action" value="flag">
                      <button class="icon-btn danger" type="submit">Flag</button>
                    </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review?');">
                    <input type="hidden" name="review_id" value="<?= e($r['review_id']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="icon-btn danger" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No reviews found.</td></tr>
        <?php endif; ?>

      </tbody>
    </table>
  </div>

</main>

<script>
  let currentSort = null;

  function sortByRating(direction) {
    const tbody = document.getElementById('reviews-tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr')).filter(row => row.dataset.rating);

    if (!rows.length) return;

    if (currentSort === direction) {
      currentSort = null;
      document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
      rows.sort((a, b) => parseFloat(a.dataset.rating) - parseFloat(b.dataset.rating));
      rows.forEach(r => tbody.appendChild(r));
      return;
    }

    currentSort = direction;
    document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('sort-' + direction).classList.add('active');

    rows.sort((a, b) => {
      const ar = parseFloat(a.dataset.rating);
      const br = parseFloat(b.dataset.rating);
      return direction === 'asc' ? ar - br : br - ar;
    });
    rows.forEach(r => tbody.appendChild(r));
  }
</script>

</body>
</html>
