<?php
session_start();
require_once 'db.php';
// إذا المستخدم مسجل دخول، حوله للرئيسية
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استقبال البيانات من الفورم
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $role_input   = $_POST['role'] ?? '';
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

    // في تصميمك اسم المزارع "farmer"، لكن في قاعدة البيانات لدينا اسمه "renter"
    $db_role = ($role_input === 'farmer') ? 'renter' : $role_input;

    if (!$first_name || !$last_name || !$email || !$phone_number || !$db_role || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // التحقق من أن الإيميل أو رقم الجوال غير مسجلين مسبقاً (معدل لـ mysqli)
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR phone_number = ?");
        $stmt_check->bind_param("ss", $email, $phone_number);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email or Phone Number is already registered. Please sign in.';
        } else {
            // تشفير كلمة المرور للحماية
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // إدخال المستخدم الجديد في قاعدة البيانات (معدل لـ mysqli)
            $stmt_insert = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, phone_number, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("ssssss", $first_name, $last_name, $email, $hashed_password, $phone_number, $db_role);
            
            try {
                $stmt_insert->execute();
                $_SESSION['user'] = [
                    'id'           => $conn->insert_id,
                    'user_id'      => $conn->insert_id,
                    'name'         => $first_name . ' ' . $last_name,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'email'        => $email,
                    'phone_number' => $phone_number,
                    'role'         => $db_role,
                    'status'       => 'active',
                ];
                if ($db_role === 'admin')     { header("Location: admin-dashboard.php"); }
                elseif ($db_role === 'owner') { header("Location: owner-dashboard.php"); }
                else                           { header("Location: farmer-dashboard.php"); }
                exit;
            } catch (Exception $e) {
                $error = 'An error occurred during registration. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>GreenRent — Create Account</title>
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
    .btn-outline {
      background: transparent;
      border: 1.5px solid var(--green-mid);
      color: var(--green-mid);
    }
    .btn-outline:hover { background: var(--green-pale); }
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
    .form-input.success { border-color: var(--green-light); }

    .form-select {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid rgba(82,183,136,.25);
      border-radius: 10px;
      background: var(--cream);
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--text-dark);
      outline: none;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235a7a62' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 36px;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-select:focus {
      border-color: var(--green-light);
      box-shadow: 0 0 0 3px rgba(82,183,136,.15);
    }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .field-error {
      font-size: 11.5px;
      color: #e74c3c;
      margin-top: 5px;
      display: none;
      align-items: center;
      gap: 4px;
    }
    .field-error.show { display: flex; }

    .field-hint {
      font-size: 11.5px;
      color: var(--green-mid);
      margin-top: 4px;
      display: none;
    }

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

    .password-strength { margin-top: 8px; }
    .strength-bar {
      height: 4px;
      border-radius: 2px;
      background: #e5e7eb;
      margin-bottom: 4px;
      overflow: hidden;
    }
    .strength-fill {
      height: 100%;
      border-radius: 2px;
      transition: width .3s, background .3s;
      width: 0%;
    }
    .strength-label { font-size: 11px; color: var(--text-muted); }

    .auth-submit-btn {
      width: 100%;
      padding: 13px;
      background: var(--green-mid);
      color: white;
      border: none;
      border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      transition: background .2s, transform .1s;
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .auth-submit-btn:hover { background: var(--green-deep); transform: translateY(-1px); }
    .auth-submit-btn:active { transform: translateY(0); }

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

    .role-info-box {
      background: var(--green-pale);
      border: 1px solid rgba(82,183,136,.3);
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 13px;
      color: var(--green-mid);
      margin-top: 8px;
      display: none;
    }
    .role-info-box.show { display: block; }

    @media (max-width: 600px) {
      .form-row { grid-template-columns: 1fr; }
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
      <a class="btn btn-outline" href="login.php">Log In</a>
    </div>
  </nav>
</header>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card-header">
      <span class="auth-card-header-icon">🌱</span>
      <h2>Create Your Account</h2>
      <p>Join GreenRent — Agricultural Equipment Marketplace</p>
    </div>
    <div class="auth-card-body">

      <?php if ($error): ?>
      <div class="alert alert-error show">
        <span>⚠️</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="alert show" style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534;">
        <span>✅</span>
        <span><?= htmlspecialchars($success) ?> <a href="login.php" style="color:#15803d; text-decoration:underline; font-weight:bold;">Sign in here</a></span>
      </div>
      <?php endif; ?>

      <div class="alert alert-error" id="reg-alert">
        <span>⚠️</span>
        <span id="reg-alert-msg">Please fix the errors below.</span>
      </div>

      <form method="POST" action="register.php" onsubmit="return validateAllFields(event)">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name <span>*</span></label>
              <input type="text" name="first_name" class="form-input" id="reg-fname" placeholder="e.g. Ali" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" oninput="validateField('reg-fname')" required/>
              <div class="field-error" id="err-reg-fname">⚠ First name is required</div>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name <span>*</span></label>
              <input type="text" name="last_name" class="form-input" id="reg-lname" placeholder="e.g. Alomar" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" oninput="validateField('reg-lname')" required/>
              <div class="field-error" id="err-reg-lname">⚠ Last name is required</div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address <span>*</span></label>
            <input type="email" name="email" class="form-input" id="reg-email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" oninput="validateEmail()" required/>
            <div class="field-error" id="err-reg-email">⚠ Enter a valid email address</div>
          </div>

          <div class="form-group">
            <label class="form-label">Phone Number <span>*</span></label>
            <input type="tel" name="phone_number" class="form-input" id="reg-phone" placeholder="05XXXXXXXX" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>" oninput="validatePhone()" maxlength="10" required/>
            <div class="field-error" id="err-reg-phone">⚠ Enter a valid Saudi phone number (05XXXXXXXX)</div>
            <div class="field-hint" id="hint-reg-phone">✓ Phone number format is valid</div>
          </div>

          <div class="form-group">
            <label class="form-label">Role <span>*</span></label>
            <select class="form-select" name="role" id="reg-role" onchange="onRoleChange()" required>
              <option value="">— Select your role —</option>
              <option value="farmer" <?= (isset($_POST['role']) && $_POST['role'] === 'farmer') ? 'selected' : '' ?>>🌾 Farmer</option>
              <option value="owner" <?= (isset($_POST['role']) && $_POST['role'] === 'owner') ? 'selected' : '' ?>>🏭 Equipment Owner</option>
              <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>⚙️ Admin</option>
            </select>
            <div class="field-error" id="err-reg-role">⚠ Please select a role</div>
            <div class="role-info-box" id="role-info"></div>
          </div>

          <div class="form-group">
            <label class="form-label">Password <span>*</span></label>
            <div class="password-wrap">
              <input type="password" name="password" class="form-input" id="reg-password" placeholder="Min 8 chars, a number & uppercase" oninput="validatePassword()" required/>
              <button type="button" class="password-toggle" onclick="togglePwd('reg-password', this)" aria-label="Toggle password visibility">
                <svg class="pwd-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="pwd-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <div class="password-strength" id="pwd-strength" style="display:none">
              <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
              <div class="strength-label" id="strength-label"></div>
            </div>
            <div class="field-error" id="err-reg-password">⚠ Password must be at least 8 characters, include a number and an uppercase letter (A-Z)</div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password <span>*</span></label>
            <div class="password-wrap">
              <input type="password" name="confirm_password" class="form-input" id="reg-confirm" placeholder="Repeat your password" oninput="validateConfirm()" required/>
              <button type="button" class="password-toggle" onclick="togglePwd('reg-confirm', this)" aria-label="Toggle password visibility">
                <svg class="pwd-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="pwd-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <div class="field-error" id="err-reg-confirm">⚠ Passwords do not match</div>
          </div>

          <button type="submit" class="auth-submit-btn">
            <span>🌿</span> Create Account
          </button>

      </form>

      <div class="auth-divider">
        Already have an account? <a href="login.php">Sign in here</a>
      </div>
    </div>
  </div>
</div>

<script>
  function setFieldState(id, isError, errId, show) {
    const el = document.getElementById(id);
    const err = document.getElementById(errId);
    if (isError) {
      el.classList.add('error');
      el.classList.remove('success');
      if (show) err.classList.add('show');
    } else {
      el.classList.remove('error');
      el.classList.add('success');
      err.classList.remove('show');
    }
  }

  function validateField(id) {
    const val = document.getElementById(id).value.trim();
    setFieldState(id, val.length === 0, 'err-' + id, true);
    return val.length > 0;
  }

  function validateEmail() {
    const val = document.getElementById('reg-email').value.trim();
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    setFieldState('reg-email', !ok, 'err-reg-email', true);
    return ok;
  }

  function validatePhone() {
    const val = document.getElementById('reg-phone').value.trim();
    const formatOk = /^05[0-9]{8}$/.test(val);
    const hint  = document.getElementById('hint-reg-phone');

    if (!formatOk) {
      setFieldState('reg-phone', true, 'err-reg-phone', true);
      hint.style.display = 'none';
      return false;
    }

    setFieldState('reg-phone', false, 'err-reg-phone', false);
    hint.style.display = 'block';
    return true;
  }

  function validatePassword() {
    const val = document.getElementById('reg-password').value;
    const hasMin   = val.length >= 8;
    const hasNum   = /\d/.test(val);
    const hasUpper = /[A-Z]/.test(val);
    const ok = hasMin && hasNum && hasUpper;
    setFieldState('reg-password', !ok, 'err-reg-password', true);

    const strengthEl = document.getElementById('pwd-strength');
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    if (val.length > 0) {
      strengthEl.style.display = 'block';
      let score = 0;
      if (val.length >= 8)        score++;
      if (/\d/.test(val))         score++;
      if (/[A-Z]/.test(val))      score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;
      const configs = [
        { w: '25%',  bg: '#ef4444', t: 'Weak' },
        { w: '50%',  bg: '#f59e0b', t: 'Fair' },
        { w: '75%',  bg: '#3b82f6', t: 'Good' },
        { w: '100%', bg: '#22c55e', t: 'Strong' },
      ];
      const c = configs[score - 1] || configs[0];
      fill.style.width      = c.w;
      fill.style.background = c.bg;
      label.textContent     = c.t;
    } else {
      strengthEl.style.display = 'none';
    }

    if (document.getElementById('reg-confirm').value) validateConfirm();
    return ok;
  }

  function validateConfirm() {
    const pwd  = document.getElementById('reg-password').value;
    const conf = document.getElementById('reg-confirm').value;
    const ok = pwd === conf && conf.length > 0;
    setFieldState('reg-confirm', !ok, 'err-reg-confirm', true);
    return ok;
  }

  function onRoleChange() {
    const role    = document.getElementById('reg-role').value;
    const infoBox = document.getElementById('role-info');
    const msgs = {
      farmer: '🌾 As a Farmer, you can search, filter, and reserve agricultural equipment for your needs.',
      owner:  '🏭 As an Equipment Owner, you can list your machinery, manage availability, and receive secure payments.',
      admin:  '⚙️ As an Admin, you can manage users, equipment listings, reservations, and platform reviews.'
    };
    if (role && msgs[role]) {
      infoBox.textContent = msgs[role];
      infoBox.classList.add('show');
      document.getElementById('reg-role').classList.remove('error');
      document.getElementById('err-reg-role').classList.remove('show');
    } else {
      infoBox.classList.remove('show');
    }
  }

  function togglePwd(id, btn) {
    const input     = document.getElementById(id);
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

  // الدالة التي تمنع إرسال الفورم للسيرفر إذا كانت هناك أخطاء
  function validateAllFields(event) {
    const fname = validateField('reg-fname');
    const lname = validateField('reg-lname');
    const email = validateEmail();
    const phone = validatePhone();
    const role  = document.getElementById('reg-role').value;
    const pwd   = validatePassword();
    const conf  = validateConfirm();

    let roleValid = true;
    if (!role) {
      document.getElementById('reg-role').classList.add('error');
      document.getElementById('err-reg-role').classList.add('show');
      roleValid = false;
    }

    const alertEl  = document.getElementById('reg-alert');
    const alertMsg = document.getElementById('reg-alert-msg');

    if (!fname || !lname || !email || !phone || !roleValid || !pwd || !conf) {
      event.preventDefault(); // إيقاف إرسال النموذج للسيرفر
      alertMsg.textContent = 'Please fill in all required fields correctly.';
      alertEl.classList.add('show');
      return false;
    }

    alertEl.classList.remove('show');
    return true; // السماح بالإرسال
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