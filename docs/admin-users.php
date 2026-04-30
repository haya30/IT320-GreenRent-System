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
if ($role !== 'admin') {
    if ($role === 'renter') header('Location: farmer-dashboard.php');
    elseif ($role === 'owner') header('Location: owner-dashboard.php');
    else header('Location: login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_status'])) {
    $targetUserId = (int)$_POST['user_id'];
    $newStatus = $_POST['new_status'];
    $allowed = ['active', 'suspended', 'inactive'];

    if (in_array($newStatus, $allowed, true) && $targetUserId !== (int)$userId) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role <> 'admin'");
        $stmt->bind_param('si', $newStatus, $targetUserId);
        $stmt->execute();
        $message = 'User account status updated successfully.';
    } else {
        $message = 'Action was not allowed.';
    }
}

$users = $conn->query("SELECT user_id, first_name, last_name, email, phone_number, role, status FROM users ORDER BY user_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent – User Accounts</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="adminStyle.css" rel="stylesheet"/>
</head>
<body>

<header class="admin-header">
  <div class="admin-header-inner">
    <div style="display:flex;align-items:center;flex-shrink:0;">
      <a href="admin-dashboard.php" class="admin-logo">
        <img src="logo.jpg" alt="GreenRent Logo"/>
        <div class="admin-logo-text"><span>GreenRent</span><span>Agricultural Equipment</span></div>
      </a>
    </div>

    <ul class="admin-nav">
      <li><a href="admin-dashboard.php">Dashboard</a></li>
      <li><a href="admin-users.php" class="active">User Accounts</a></li>
      <li><a href="admin-listings.html">Equipment Listings</a></li>
      <li><a href="admin-reviews.php">Reviews & Ratings</a></li>
    </ul>

    <div class="admin-header-right">
      <div class="admin-profile">
        <div class="admin-avatar">A</div>
        <span class="admin-profile-name">Admin</span>
      </div>
      <div class="admin-divider"></div>
      <a href="logout.php" class="btn-logout">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Log Out
      </a>
    </div>
  </div>
</header>

<main class="admin-main">

  <div class="page-title-row">
    <div>
      <h1>User Accounts</h1>
      <p>View all registered users - You can Suspend or reactivate accounts From here !</p>
    </div>
  </div>

  <?php if ($message): ?>
  <div style="background:#ecfdf3;border:1px solid #bbf7d0;color:#15803d;padding:12px 16px;border-radius:12px;margin-bottom:16px;"><?= e($message) ?></div>
  <?php endif; ?>

  <div class="table-card">
    <div class="table-card-header">
      <div>
        <h2>All Users</h2>
      </div>
      <div class="table-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="text" placeholder="Search users…" oninput="filterTable('users-tbody', this.value)"/>
      </div>
    </div>

    <table class="full-table">
      <thead>
        <tr>
          <th>Name &amp; Email</th>
          <th>Phone</th>
          <th>Role</th>
          <th>Account Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="users-tbody">
        <?php if ($users && $users->num_rows > 0): ?>
          <?php while($u = $users->fetch_assoc()):
            $name      = trim($u['first_name'] . ' ' . $u['last_name']);
            $roleLabel = $u['role'] === 'renter' ? 'Farmer' : ($u['role'] === 'owner' ? 'Equipment Owner' : 'Admin');
            $rowId     = 'urow-' . $u['user_id'];
            $statusId  = 'ustatus-' . $u['user_id'];
          ?>
          <tr id="<?= e($rowId) ?>">
            <td><div class="user-cell"><span><?= e($name) ?></span><span><?= e($u['email']) ?></span></div></td>
            <td><?= e($u['phone_number']) ?></td>
            <td><?= e($roleLabel) ?></td>
            <td><span class="pill <?= e($u['status'] === 'suspended' ? 'suspended' : 'active') ?>" id="<?= e($statusId) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
            <td>
              <div class="action-btns">
                <?php if ($u['role'] === 'admin'): ?>
                  <span style="font-size:12px;color:var(--text-muted);">Protected</span>
                <?php elseif ($u['status'] === 'suspended'): ?>
                  <form method="POST" style="display:inline;" id="form-<?= e($u['user_id']) ?>-active">
                    <input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>">
                    <input type="hidden" name="new_status" value="active">
                    <button class="icon-btn restore" type="button" onclick="reactivateUser('<?= e($rowId) ?>','<?= e($statusId) ?>','<?= e(addslashes($name)) ?>')">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/></svg>
                      Reactivate
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" style="display:inline;" id="form-<?= e($u['user_id']) ?>-suspended">
                    <input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>">
                    <input type="hidden" name="new_status" value="suspended">
                    <button class="icon-btn danger" type="button" onclick="openModal('suspend-modal','<?= e($rowId) ?>','<?= e($statusId) ?>','<?= e(addslashes($name)) ?>')">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="currentColor" stroke-width="2"/></svg>
                      Suspend
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<!-- Suspend Modal -->
<div class="modal-overlay" id="suspend-modal">
  <div class="modal">
    <div class="modal-icon danger"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#c0392b" stroke-width="2"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="#c0392b" stroke-width="2"/></svg></div>
    <h3>Suspend Account?</h3>
    <p>You are about to suspend <strong id="modal-user-name"></strong>. They will not be able to log in. This action is recorded in the system.</p>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('suspend-modal')">Cancel</button>
      <button class="btn-confirm danger" onclick="confirmSuspend()">Yes, Suspend</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"><span class="toast-dot"></span><span id="toast-msg"></span></div>

<script>
  let pendingRowId = null, pendingStatusId = null, pendingForm = null;

  function filterTable(tbodyId, query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#' + tbodyId + ' tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }

  function openModal(modalId, rowId, statusId, name) {
    pendingRowId = rowId;
    pendingStatusId = statusId;
    pendingForm = document.querySelector('#' + rowId + ' form');
    document.getElementById('modal-user-name').textContent = name;
    document.getElementById(modalId).classList.add('open');
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
    pendingRowId = pendingStatusId = pendingForm = null;
  }

  function confirmSuspend() {
    document.getElementById(pendingStatusId).className = 'pill suspended';
    document.getElementById(pendingStatusId).textContent = 'Suspended';
    document.querySelector('#' + pendingRowId + ' .action-btns').innerHTML =
      `<button class="icon-btn restore" onclick="reactivateUser('${pendingRowId}','${pendingStatusId}','User')">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/></svg>
        Reactivate</button>`;
    closeModal('suspend-modal');
    showToast('Account suspended — action recorded.');
    if (pendingForm) pendingForm.submit();
  }

  function reactivateUser(rowId, statusId, name) {
    document.getElementById(statusId).className = 'pill active';
    document.getElementById(statusId).textContent = 'Active';
    document.querySelector('#' + rowId + ' .action-btns').innerHTML =
      `<button class="icon-btn danger" onclick="openModal('suspend-modal','${rowId}','${statusId}','${name}')">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke="currentColor" stroke-width="2"/></svg>
        Suspend</button>`;
    showToast('Account reactivated successfully.');
    const form = document.querySelector('#' + rowId + ' form');
    if (form) form.submit();
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
  }

  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
  });
</script>
</body>
</html>
