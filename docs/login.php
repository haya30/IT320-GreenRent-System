<?php
session_start();
require_once 'db.php';

// إذا مسجل دخول، روّح للداشبورد
if (isset($_SESSION['user'])) {
    $r = $_SESSION['user']['role'];
    if ($r === 'admin')       header("Location: admin-dashboard.php");
    elseif ($r === 'owner')   header("Location: owner-dashboard.php");
    else                       header("Location: farmer-dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $type     = $_POST['login_type']    ?? 'user'; // user | admin

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && password_verify($password, $row['password'])) {
            if ($row['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact the admin.';
            } elseif ($row['status'] === 'inactive') {
                $error = 'Your account is inactive.';
            } elseif ($type === 'admin' && $row['role'] !== 'admin') {
                $error = 'No admin account found with these credentials.';
            } elseif ($type === 'user' && $row['role'] === 'admin') {
                $error = 'Please use the Admin Sign In button.';
            } else {
                $_SESSION['user'] = [
                    'id'           => $row['user_id'],
                    'user_id'      => $row['user_id'],
                    'name'         => $row['first_name'] . ' ' . $row['last_name'],
                    'first_name'   => $row['first_name'],
                    'last_name'    => $row['last_name'],
                    'email'        => $row['email'],
                    'phone_number' => $row['phone_number'],
                    'role'         => $row['role'],
                    'status'       => $row['status'],
                ];
                if ($row['role'] === 'admin')     header("Location: admin-dashboard.php");
                elseif ($row['role'] === 'owner') header("Location: owner-dashboard.php");
                else                              header("Location: farmer-dashboard.php");
                exit; 
            }
        } else {
            $error = 'Incorrect email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent — Sign In</title>
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
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: #eef5ee;
      color: var(--text-dark);
      min-height: 100vh;
    }

    /* ── HEADER ── */
    .gr-header {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--white);
      border-bottom: 1px solid rgba(82,183,136,.20);
      box-shadow: var(--shadow-sm);
    }
    .gr-nav {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 32px;
      height: 68px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 32px;
    }
    .gr-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      flex-shrink: 0;
    }
    .gr-logo-icon { width: 150px; height: auto; }
    .gr-logo-text { line-height: 1; }
    .gr-logo-text span:first-child {
      display: block;
      font-family: 'DM Serif Display', serif;
      font-size: 22px;
      color: var(--green-deep);
      letter-spacing: -.3px;
    }
    .gr-logo-text span:last-child {
      display: block;
      font-size: 10.5px;
      color: var(--text-muted);
      font-weight: 500;
      letter-spacing: .8px;
      text-transform: uppercase;
    }
    .gr-nav-actions { display: flex; align-items: center; gap: 10px; }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 600;
      font-size: 13.5px;
      border-radius: 10px;
      padding: 9px 18px;
      cursor: pointer;
      border: none;
      transition: all .18s;
      text-decoration: none;
    }
    .btn-solid {
      background: var(--green-mid);
      color: var(--white);
      box-shadow: 0 2px 8px rgba(45,106,79,.30);
    }
    .btn-solid:hover { background: var(--green-deep); transform: translateY(-1px); }

    /* ── AUTH LAYOUT ── */
    .auth-wrap {
      min-height: calc(100vh - 68px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 16px;
      background: #eef5ee;
    }
    .auth-card {
      background: var(--white);
      border-radius: 24px;
      box-shadow: 0 8px 40px rgba(26,60,43,.12);
      width: 100%;
      max-width: 480px;
      overflow: hidden;
    }
    .auth-card-header {
      background: linear-gradient(135deg, var(--green-deep), var(--green-mid));
      padding: 32px 36px 28px;
      color: white;
    }
    .auth-card-header-icon { font-size: 36px; margin-bottom: 12px; display: block; }
    .auth-card-header h2 {
      font-family: 'DM Serif Display', serif;
      font-size: 26px;
      margin-bottom: 6px;
    }
    .auth-card-header p { font-size: 13.5px; opacity: .75; }
    .auth-card-body { padding: 32px 36px; }

    /* ── FORM ── */
    .form-group { margin-bottom: 20px; }
    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 6px;
    }
    .form-label span { color: #e74c3c; }
    .form-input {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid rgba(82,183,136,.25);
      border-radius: 10px;
      background: var(--cream);
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--text-dark);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-input:focus {
      border-color: var(--green-light);
      box-shadow: 0 0 0 3px rgba(82,183,136,.15);
    }
    .form-input.error {
      border-color: #e74c3c;
      box-shadow: 0 0 0 3px rgba(231,76,60,.10);
    }

    .field-error {
      font-size: 11.5px;
      color: #e74c3c;
      margin-top: 5px;
      display: none;
      align-items: center;
      gap: 4px;
    }
    .field-error.show { display: flex; }

    .password-wrap { position: relative; }
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-muted);
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color .2s;
    }
    .password-toggle:hover { color: var(--green-mid); }
    .pwd-eye-open { display: block; }
    .pwd-eye-closed { display: none; }

    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 20px;
      display: none;
      align-items: center;
      gap: 8px;
    }
    .alert.show { display: flex; }
    .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

    /* ── LOGIN BUTTONS ── */
    .login-btn-group {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 4px;
    }
    .login-main-btn {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 18px;
      border-radius: 14px;
      border: 2px solid transparent;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
      text-align: left;
    }
    .login-btn-icon { font-size: 26px; flex-shrink: 0; }
    .login-btn-label { display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .login-btn-title { font-weight: 700; font-size: 14.5px; }
    .login-btn-sub { font-size: 12px; opacity: .7; }
    .login-btn-user {
      background: var(--green-pale);
      border-color: var(--green-light);
      color: var(--green-deep);
    }
    .login-btn-user:hover {
      background: #c3ebca;
      border-color: var(--green-mid);
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(45,106,79,.2);
    }
    .login-btn-admin {
      background: #fff8f0;
      border-color: #f59e0b;
      color: #78350f;
    }
    .login-btn-admin:hover {
      background: #fef3c7;
      border-color: #d97706;
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(245,158,11,.2);
    }

    .auth-divider {
      text-align: center;
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 20px;
    }
    .auth-divider a {
      color: var(--green-mid);
      font-weight: 600;
      text-decoration: none;
    }
    .auth-divider a:hover { text-decoration: underline; }

    @media (max-width: 600px) {
      .auth-card-body { padding: 24px 20px; }
      .auth-card-header { padding: 24px 20px 20px; }
      .gr-nav { padding: 0 16px; }
    }

    /* ── FOOTER ── */
    footer { background: var(--green-deep); color: rgba(255,255,255,.85); margin-top: 80px; }
    .footer-wave { display: block; width: 100%; height: 50px; }
    .footer-main { max-width: 1200px; margin: 0 auto; padding: 48px 32px 36px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 20px; }
    .footer-logo img { height: 48px; width: auto; display: block; border-radius: 10%; }
    .footer-tagline { font-size: 13.5px; line-height: 1.7; color: rgba(255,255,255,.60); max-width: 480px; }
    .footer-social { display: flex; gap: 10px; }
    .footer-social a { width: 36px; height: 36px; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.75); text-decoration: none; transition: background .2s, border-color .2s; }
    .footer-social a:hover { background: var(--green-light); border-color: var(--green-light); color: var(--white); }
    .footer-badges { border-top: 1px solid rgba(255,255,255,.10); max-width: 1200px; margin: 0 auto; padding: 18px 32px; display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .f-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; padding: 5px 12px; font-size: 11.5px; color: rgba(255,255,255,.65); }
    .f-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green-light); }
    .footer-bottom { border-top: 1px solid rgba(255,255,255,.10); }
    .footer-bottom-inner { max-width: 1200px; margin: 0 auto; padding: 16px 32px; text-align: center; font-size: 12.5px; color: rgba(255,255,255,.40); }
  </style>
</head>
<body>

<header class="gr-header">
  <nav class="gr-nav">
    <a class="gr-logo" href="index.php">
      <img src="logo.png" alt="Logo" class="gr-logo-icon">
      <div class="gr-logo-text">
        <span>GreenRent</span>
        <span>Agricultural Equipment</span>
      </div>
    </a>
    <div class="gr-nav-actions">
      <a class="btn btn-solid" href="register.php">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 2a5 5 0 105 5 5 5 0 00-5-5zM2 20a10 10 0 0120 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        Register
      </a>
    </div>
  </nav>
</header>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card-header">
      <span class="auth-card-header-icon">🔑</span>
      <h2>Welcome Back</h2>
      <p>Sign in to your GreenRent account</p>
    </div>
    <div class="auth-card-body">

      <?php if ($error): ?>
      <div class="alert alert-error show">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <div class="alert alert-error" id="login-alert">
        <span>⚠️</span>
        <span id="login-alert-msg">Please fill in all required fields correctly.</span>
      </div>

      <form id="loginForm" method="POST" action="login.php">
        <input type="hidden" name="login_type" id="login_type" value="user">

        <div class="form-group">
          <label class="form-label">Email Address <span>*</span></label>
          <input type="email" name="email" class="form-input" id="login-email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" oninput="clearAlert()"/>
          <div class="field-error" id="err-login-email">⚠ Enter a valid email address</div>
        </div>

        <div class="form-group">
          <label class="form-label">Password <span>*</span></label>
          <div class="password-wrap">
            <input type="password" name="password" class="form-input" id="login-password" placeholder="Your password" oninput="clearAlert()"/>
            <button type="button" class="password-toggle" onclick="togglePwd()" aria-label="Toggle password visibility">
              <svg class="pwd-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="pwd-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <div class="field-error" id="err-login-password">⚠ Password is required</div>
        </div>

        <div class="login-btn-group">
          <button type="button" class="login-main-btn login-btn-user" onclick="submitLogin('user')">
            <span class="login-btn-icon">👤</span>
            <span class="login-btn-label">
              <span class="login-btn-title">Sign In as User</span>
              <span class="login-btn-sub">Farmer or Equipment Owner</span>
            </span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <button type="button" class="login-main-btn login-btn-admin" onclick="submitLogin('admin')">
            <span class="login-btn-icon">⚙️</span>
            <span class="login-btn-label">
              <span class="login-btn-title">Sign In as Admin</span>
              <span class="login-btn-sub">Platform Management</span>
            </span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </form>

      <div class="auth-divider" style="margin-top:20px">
        Don't have an account? <a href="register.php">Register here</a>
      </div>
    </div>
  </div>
</div>

<script>
  function clearAlert() {
    document.getElementById('login-alert').classList.remove('show');
    document.getElementById('login-email').classList.remove('error');
    document.getElementById('login-password').classList.remove('error');
    document.getElementById('err-login-email').classList.remove('show');
    document.getElementById('err-login-password').classList.remove('show');
  }

  function togglePwd() {
    const input     = document.getElementById('login-password');
    const btn       = input.nextElementSibling;
    const eyeOpen   = btn.querySelector('.pwd-eye-open');
    const eyeClosed = btn.querySelector('.pwd-eye-closed');
    if (input.type === 'password') {
      input.type = 'text';
      eyeOpen.style.display   = 'none';
      eyeClosed.style.display = 'block';
    } else {
      input.type = 'password';
      eyeOpen.style.display   = 'block';
      eyeClosed.style.display = 'none';
    }
  }

  // دالة الإرسال المعدلة لتربط بين الـ JS والـ PHP
  function submitLogin(type) {
    const emailEl  = document.getElementById('login-email');
    const pwdEl    = document.getElementById('login-password');
    const emailVal = emailEl.value.trim().toLowerCase();
    const pwdVal   = pwdEl.value;

    let hasError = false;
    
    // التحقق من صيغة الإيميل
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
      emailEl.classList.add('error');
      document.getElementById('err-login-email').classList.add('show');
      hasError = true;
    }
    
    // التحقق من كتابة الباسورد
    if (!pwdVal) {
      pwdEl.classList.add('error');
      document.getElementById('err-login-password').classList.add('show');
      hasError = true;
    }
    
    // إذا كان هناك خطأ، أوقف العملية واعرض رسالة التنبيه الحمراء
    if (hasError) {
      document.getElementById('login-alert').classList.add('show');
      return;
    }

    // إذا كانت البيانات سليمة مبدئياً:
    // 1. عيّن نوع الدخول (مستخدم أو آدمن) في الحقل المخفي
    document.getElementById('login_type').value = type;
    
    // 2. أرسل الفورم للسيرفر (PHP) ليكمل عملية التحقق من قاعدة البيانات
    document.getElementById('loginForm').submit();
  }
</script>
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

</body>
</html>