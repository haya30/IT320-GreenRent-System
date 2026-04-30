<?php
session_start();
require_once 'db.php';
requireRole('admin');

// ─── Current Admin ───────────────────────────────────────────────────────────
$currentUser  = getCurrentUser();
$adminName    = htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']);
$adminInitial = strtoupper($currentUser['first_name'][0]);

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

// ── Handle Deactivate ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'deactivate') {
    $equipment_id = $_POST['equipment_id'];

    $stmt = $conn->prepare("UPDATE equipment SET availability_status = 'unavailable' WHERE equipment_id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Equipment deactivated successfully.'];
    header("Location: admin-listings.php");
    exit;
}

// ── Handle Activate ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'activate') {
    $equipment_id = $_POST['equipment_id'];

    $stmt = $conn->prepare("UPDATE equipment SET availability_status = 'available' WHERE equipment_id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Equipment activated successfully.'];
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
  <head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent – Equipment Listings</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="adminStyle.css" rel="stylesheet"/>
</head>
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

    /* ── Warn (deactivate) button & modal ── */
    .icon-btn.warn:hover  { background: rgba(180,83,9,.08); color: #b45309; border-color: rgba(180,83,9,.30); }
    .modal-icon.warn      { background: rgba(180,83,9,.10); }
    .btn-confirm.warn     { background: #b45309; color: #fff; }
    .btn-confirm.warn:hover { opacity: .88; }

    /* ── Success (activate) modal ── */
    .modal-icon.success   { background: rgba(82,183,136,.12); }
    .btn-confirm.success  { background: var(--green-mid); color: #fff; }
    .btn-confirm.success:hover { opacity: .88; }
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
    <div><h1>Equipment Listings</h1></div>
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
            <td colspan="7">No equipment found</td>
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
                <?php if ($row['availability_status'] === 'available') { ?>
                <button type="button" class="icon-btn warn"
                        onclick="openDeactivateModal(<?= $row['equipment_id'] ?>, '<?= htmlspecialchars(addslashes($row['equipment_name'])) ?>')">
                  Deactivate
                </button>
                <?php } else { ?>
                <button type="button" class="icon-btn success"
                        onclick="openActivateModal(<?= $row['equipment_id'] ?>, '<?= htmlspecialchars(addslashes($row['equipment_name'])) ?>')">
                  Activate
                </button>
                <?php } ?>
                <button type="button" class="icon-btn danger"
                        onclick="openDeleteModal(<?= $row['equipment_id'] ?>, '<?= htmlspecialchars(addslashes($row['equipment_name'])) ?>')">
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

<form id="deactivateForm" method="POST" action="">
  <input type="hidden" name="action" value="deactivate">
  <input type="hidden" name="equipment_id" id="deactivateId" value="">
</form>

<form id="activateForm" method="POST" action="">
  <input type="hidden" name="action" value="activate">
  <input type="hidden" name="equipment_id" id="activateId" value="">
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

<!-- Deactivate Confirmation Modal -->
<div class="modal-overlay" id="deactivate-modal">
  <div class="modal">
    <div class="modal-icon warn">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <rect x="6" y="4" width="4" height="16" rx="1" stroke="#b45309" stroke-width="2"/>
        <rect x="14" y="4" width="4" height="16" rx="1" stroke="#b45309" stroke-width="2"/>
      </svg>
    </div>
    <h3>Deactivate Equipment?</h3>
    <p><strong id="deactivate-listing-name"></strong> will be marked as inactive. The record will not be deleted and can be reactivated later.</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeDeactivateModal()">Cancel</button>
      <button class="btn-confirm warn" onclick="confirmDeactivate()">Yes, Deactivate</button>
    </div>
  </div>
</div>

<!-- Activate Confirmation Modal -->
<div class="modal-overlay" id="activate-modal">
  <div class="modal">
    <div class="modal-icon success">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" stroke="#52b788" stroke-width="2"/>
        <path d="M9 12l2 2 4-4" stroke="#52b788" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h3>Activate Equipment?</h3>
    <p><strong id="activate-listing-name"></strong> will be set back to available and visible to renters.</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeActivateModal()">Cancel</button>
      <button class="btn-confirm success" onclick="confirmActivate()">Yes, Activate</button>
    </div>
  </div>
</div>


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

  // ── Deactivate modal ─────────────────────
  function openDeactivateModal(id, name) {
    document.getElementById('deactivateId').value = id;
    document.getElementById('deactivate-listing-name').textContent = name;
    document.getElementById('deactivate-modal').classList.add('open');
  }

  function closeDeactivateModal() {
    document.getElementById('deactivate-modal').classList.remove('open');
    document.getElementById('deactivateId').value = '';
  }

  function confirmDeactivate() {
    document.getElementById('deactivateForm').submit();
  }

  // ── Activate modal ───────────────────────
  function openActivateModal(id, name) {
    document.getElementById('activateId').value = id;
    document.getElementById('activate-listing-name').textContent = name;
    document.getElementById('activate-modal').classList.add('open');
  }

  function closeActivateModal() {
    document.getElementById('activate-modal').classList.remove('open');
    document.getElementById('activateId').value = '';
  }

  function confirmActivate() {
    document.getElementById('activateForm').submit();
  }

  // Close any modal by clicking the backdrop
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });
</script>

</body>
</html>
