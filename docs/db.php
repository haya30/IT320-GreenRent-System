<?php
// ══════════════════════════════════════════
//  GreenRent — Database Connection & Helpers
// ══════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'greenrent_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ── Session Helpers ──────────────────────

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin($redirect = 'login.php') {
    if (!isset($_SESSION['user'])) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole($role, $redirect = 'index.php') {
    requireLogin('login.php');
    if ($_SESSION['user']['role'] !== $role) {
        header("Location: $redirect");
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}
?>