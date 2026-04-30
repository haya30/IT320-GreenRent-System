<?php
session_start();
include 'db.php';

requireRole('admin');

// ── Flash message ─────────────────────────────────────────────
$message     = "";
$messageType = "success";

if (isset($_SESSION['flash_message'])) {
    $message     = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? "success";
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// ── Delete handler ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id   = intval($_POST['equipment_id']);
    $stmt = $conn->prepare("DELETE FROM equipment WHERE equipment_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Equipment deleted successfully.";
        $_SESSION['flash_type']    = "success";
    } else {
        $_SESSION['flash_message'] = "Error deleting equipment: " . $conn->error;
        $_SESSION['flash_type']    = "error";
    }

    header("Location: admin-listings.php");
    exit;
}

// ── Fetch all equipment (with owner name) ─────────────────────
$equipmentList = [];

$result = $conn->query("
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
    FROM   equipment e
    LEFT   JOIN users u ON e.owner_id = u.user_id
    ORDER  BY e.equipment_id DESC
");

while ($row = $result->fetch_assoc()) {
    $equipmentList[] = $row;
}

// ── Status badge helper ───────────────────────────────────────
function statusBadge($status) {
    switch ($status) {
        case 'available': return ['pill active',    'Available'];
        case 'reserved':  return ['pill suspended', 'Reserved'];
        case 'inactive':  return ['pill inactive',  'Inactive'];
        default:          return ['pill inactive',  ucfirst($status)];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent – Equipment Listings</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  <link href="adminStyle.css" rel="stylesheet"/>
  <style>
    /* ── Controls bar ── */
    .controls-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #f3f8f4;
      border: 1.5px solid rgba(82,183,136,.20);
      border-radius: 12px;
      padding: 6px 10px;
    }
    .ctrl-search {
      display: flex;
      align-items: center;
      gap: 8px;
      flex: 1;
    }
    .ctrl-search svg { color: var(--text-muted); flex-shrink: 0; }
    .ctrl-search input {
      border: none;
      background: transparent;
      font-family: 'DM Sans', sans-serif;
      font-size: 13px;
      color: var(--text-dark);
      outline: none;
      width: 180px;
    }
    .ctrl-search input::placeholder { color: var(--text-muted); }
    .ctrl-divider {
      width: 1px;
      height: 20px;
      background: rgba(82,183,136,.25);
      flex-shrink: 0;
    }
    .ctrl-sort {
      display: flex;
      align-items: center;
      gap: 7px;
      flex-shrink: 0;
    }
    .ctrl-sort-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      white-space: nowrap;
    }
    .sort-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 11px;
      border-radius: 8px;
      border: 1.5px solid transparent;
      background: transparent;
      font-family: 'DM Sans', sans-serif;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      cursor: pointer;
      transition: all .15s;
      white-space: nowrap;
    }
    .sort-btn:hover   { background: var(--green-pale); color: var(--green-mid); }
    .sort-btn.active  { background: var(--green-pale); color: var(--green-mid); border-color: rgba(82,183,136,.35); }

    /* ── Flash message banner ── */
    .flash-message {
      border-radius: 12px;
      padding: 13px 16px;
      margin-bottom: 20px;
      font-weight: 600;
      font-size: 14px;
    }
    .flash-message.success {
      background: #d8f3dc;
      border: 1px solid #52b788;
      color: #1b4332;
    }
    .flash-message.error {
      background: #fee2e2;
      border: 1px solid #ef4444;
      color: #991b1b;
    }

    /* ── Equipment image thumbnail ── */
    .equip-thumb {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      object-fit: cover;
      flex-shrink: 0;
    }
    .equip-thumb-placeholder {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      background: #d8f3dc;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      flex-shrink: 0;
    }
    .equip-row {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ── Empty state ── */
    .empty-row td {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-muted);
      font-size: 14px;
    }
  </style>
</head>
<body>

<header class="admin-header">
  <div class="admin-header-inner">
    <div style="display:flex;align-items:center;flex-shrink:0;">
      <a href="admin-dashboard.html" class="admin-logo">
        <img src="logo.png" alt="GreenRent Logo"/>
        <div class="admin-logo-text">
          <span>GreenRent</span>
          <span>Agricultural Equipment</span>
        </div>
      </a>
    </div>

    <ul class="admin-nav">
      <li><a href="admin-dashboard.php">Dashboard</a></li>
      <li><a href="admin-users.php">User Accounts</a></li>
      <li><a href="admin-listings.php" class="active">Equipment Listings</a></li>
      <li><a href="admin-reviews.html">Reviews &amp; Ratings</a></li>
    </ul>

    <div class="admin-header-right">
      <div class="admin-profile">
        <div class="admin-avatar">A</div>
        <span class="admin-profile-name">Admin</span>
      </div>
      <div class="admin-divider"></div>
      <a href="login.html" class="btn-logout">
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
    <div><h1>Equipment Listings</h1></div>
    <div>
      <a href="add-edit-equipment.php" class="btn btn-solid">+ Add Equipment</a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="flash-message <?php echo $messageType; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <div class="table-card">
    <div class="table-card-header">
      <div><h2>All Listings <span style="font-size:13px;font-weight:400;color:var(--text-muted);">(<?php echo count($equipmentList); ?>)</span></h2></div>

      <div class="controls-bar">
        <div class="ctrl-search">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
            <path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <input type="text" placeholder="Search listings…" oninput="filterTable(this.value)"/>
        </div>

        <div class="ctrl-divider"></div>

        <div class="ctrl-sort">
          <span class="ctrl-sort-label">Price:</span>
          <button class="sort-btn" id="sort-asc" onclick="sortByPrice('asc')">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
              <path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Lowest
          </button>
          <button class="sort-btn" id="sort-desc" onclick="sortByPrice('desc')">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Highest
          </button>
        </div>
      </div>
    </div>

    <table class="full-table">
      <thead>
        <tr>
          <th>Equipment</th>
          <th>Owner</th>
          <th>Price / day</th>
          <th>Location</th>
          <th>Condition</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="listings-tbody">

        <?php if (empty($equipmentList)): ?>
          <tr class="empty-row">
            <td colspan="7">No equipment found. <a href="add-edit-equipment.php" style="color:var(--green-mid);">Add the first listing.</a></td>
          </tr>
        <?php else: ?>
          <?php foreach ($equipmentList as $row):
            [$badgeClass, $badgeLabel] = statusBadge($row['availability_status']);
          ?>
          <tr id="lrow-<?php echo $row['equipment_id']; ?>"
              data-id="<?php echo $row['equipment_id']; ?>"
              data-price="<?php echo $row['price_per_day']; ?>">

            <td>
              <div class="equip-row">
                <?php if (!empty($row['image_url'])): ?>
                  <img src="<?php echo htmlspecialchars($row['image_url']); ?>"
                       alt="<?php echo htmlspecialchars($row['equipment_name']); ?>"
                       class="equip-thumb">
                <?php else: ?>
                  <div class="equip-thumb-placeholder">🚜</div>
                <?php endif; ?>
                <div class="equip-cell">
                  <span><?php echo htmlspecialchars($row['equipment_name']); ?></span>
                  <span><?php echo htmlspecialchars($row['type']); ?><?php echo $row['operator_included'] ? ' · Operator included' : ''; ?></span>
                </div>
              </div>
            </td>

            <td><?php echo htmlspecialchars($row['owner_name']); ?></td>

            <td>SAR <?php echo number_format($row['price_per_day'], 0); ?></td>

            <td><?php echo htmlspecialchars($row['location']); ?></td>

            <td><?php echo htmlspecialchars($row['condition']); ?></td>

            <td><span class="<?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span></td>

            <td>
              <div class="action-btns">
                <a href="add-edit-equipment.php?edit_id=<?php echo $row['equipment_id']; ?>" class="icon-btn">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Edit
                </a>
                <button class="icon-btn danger"
                        onclick="openDeleteModal(<?php echo $row['equipment_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['equipment_name'])); ?>')">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
                    <polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Delete
                </button>
              </div>
            </td>

          </tr>
          <?php endforeach; ?>
        <?php endif; ?>

      </tbody>
    </table>
  </div>

</main>

<!-- Hidden delete form — one form reused for all rows -->
<form id="deleteForm" method="POST" action="">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="equipment_id" id="deleteId" value="">
</form>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="delete-modal">
  <div class="modal">
    <div class="modal-icon danger">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <polyline points="3 6 5 6 21 6" stroke="#c0392b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" stroke="#c0392b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M10 11v6M14 11v6" stroke="#c0392b" stroke-width="2" stroke-linecap="round"/>
        <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" stroke="#c0392b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h3>Delete Equipment?</h3>
    <p><strong id="modal-listing-name"></strong> will be permanently deleted from the database. This cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn-confirm danger" onclick="confirmDelete()">Yes, Delete</button>
    </div>
  </div>
</div>

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

<script>
  let currentSort = null;

  // ── Search ──────────────────────────────
  function filterTable(query) {
    var q = query.toLowerCase();
    document.querySelectorAll('#listings-tbody tr').forEach(function(row) {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }

  // ── Sort by price ───────────────────────
  function sortByPrice(direction) {
    var tbody = document.getElementById('listings-tbody');
    var rows  = Array.from(tbody.querySelectorAll('tr'));

    if (currentSort === direction) {
      currentSort = null;
      document.querySelectorAll('.sort-btn').forEach(function(b) { b.classList.remove('active'); });
      // restore original order: equipment_id DESC (matching PHP query)
      rows.sort(function(a, b) { return parseInt(b.dataset.id) - parseInt(a.dataset.id); });
      rows.forEach(function(r) { tbody.appendChild(r); });
      return;
    }

    currentSort = direction;
    document.querySelectorAll('.sort-btn').forEach(function(b) { b.classList.remove('active'); });
    document.getElementById('sort-' + direction).classList.add('active');

    rows.sort(function(a, b) {
      var ap = parseFloat(a.dataset.price);
      var bp = parseFloat(b.dataset.price);
      return direction === 'asc' ? ap - bp : bp - ap;
    });
    rows.forEach(function(r) { tbody.appendChild(r); });
  }

  // ── Delete modal ────────────────────────
  function openDeleteModal(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('modal-listing-name').textContent = name;
    document.getElementById('delete-modal').classList.add('open');
  }

  function closeDeleteModal() {
    document.getElementById('delete-modal').classList.remove('open');
    document.getElementById('deleteId').value = '';
  }

  function confirmDelete() {
    document.getElementById('deleteForm').submit();
  }

  // Close modal by clicking the backdrop
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });
</script>

</body>
</html>
