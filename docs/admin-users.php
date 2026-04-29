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
<header class="admin-header"><div class="admin-header-inner"><div style="display:flex;align-items:center;flex-shrink:0;"><a href="admin-dashboard.php" class="admin-logo"><img src="logo.jpg" alt="GreenRent Logo"/><div class="admin-logo-text"><span>GreenRent</span><span>Agricultural Equipment</span></div></a></div><ul class="admin-nav"><li><a href="admin-dashboard.php">Dashboard</a></li><li><a href="admin-users.php" class="active">User Accounts</a></li><li><a href="admin-listings.html">Equipment Listings</a></li><li><a href="admin-reviews.php">Reviews & Ratings</a></li></ul><div class="admin-header-right"><div class="admin-profile"><div class="admin-avatar">A</div><span class="admin-profile-name">Admin</span></div><div class="admin-divider"></div><a href="logout.php" class="btn-logout">Log Out</a></div></div></header>
<main class="admin-main"><div class="page-title-row"><div><h1>User Accounts</h1><p>View all registered users. You can suspend or reactivate accounts from here.</p></div></div><?php if ($message): ?><div style="background:#ecfdf3;border:1px solid #bbf7d0;color:#15803d;padding:12px 16px;border-radius:12px;margin-bottom:16px;"><?= e($message) ?></div><?php endif; ?><div class="table-card"><div class="table-card-header"><div><h2>All Users</h2></div><div class="table-search"><input type="text" placeholder="Search users…" oninput="filterTable('users-tbody', this.value)"/></div></div><table class="full-table"><thead><tr><th>Name &amp; Email</th><th>Phone</th><th>Role</th><th>Account Status</th><th>Actions</th></tr></thead><tbody id="users-tbody"><?php if ($users && $users->num_rows > 0): ?><?php while($u = $users->fetch_assoc()): ?><?php $name = trim($u['first_name'] . ' ' . $u['last_name']); $roleLabel = $u['role'] === 'renter' ? 'Farmer' : ($u['role'] === 'owner' ? 'Equipment Owner' : 'Admin'); ?><tr><td><div class="user-cell"><span><?= e($name) ?></span><span><?= e($u['email']) ?></span></div></td><td><?= e($u['phone_number']) ?></td><td><?= e($roleLabel) ?></td><td><span class="pill <?= e($u['status'] === 'suspended' ? 'suspended' : 'active') ?>"><?= e(ucfirst($u['status'])) ?></span></td><td><div class="action-btns"><?php if ($u['role'] === 'admin'): ?><span style="font-size:12px;color:var(--text-muted);">Protected</span><?php elseif ($u['status'] === 'suspended'): ?><form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>"><input type="hidden" name="new_status" value="active"><button class="icon-btn restore" type="submit">Reactivate</button></form><?php else: ?><form method="POST" style="display:inline;" onsubmit="return confirm('Suspend this account?');"><input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>"><input type="hidden" name="new_status" value="suspended"><button class="icon-btn danger" type="submit">Suspend</button></form><?php endif; ?></div></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="5">No users found.</td></tr><?php endif; ?></tbody></table></div></main><script>function filterTable(tbodyId, query){const q=query.toLowerCase();document.querySelectorAll('#'+tbodyId+' tr').forEach(row=>{row.style.display=row.textContent.toLowerCase().includes(q)?'':'none';});}</script>
</body>
</html>
