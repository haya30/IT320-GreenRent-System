<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reservation Page — GreenRent</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet"/>
  <link href="pages.css" rel="stylesheet"/>
  <style>
    /* ── Logo fix ── */
    .gr-logo img {
      height: 42px;
      width: auto;
      border-radius: 0;
      background: transparent;
    }
    .footer-logo img {
      height: 48px;
      width: auto;
      display: block;
      border-radius: 10%;
    }

    /* ── Reservation-specific ── */
    .res-hero {
      background: linear-gradient(135deg, var(--green-deep) 0%, var(--green-mid) 55%, #3a8a5f 100%);
      padding: 36px 0;
      position: relative;
      overflow: hidden;
    }
    .res-hero::before {
      content: '';
      position: absolute;
      top: -40px; right: -40px;
      width: 280px; height: 280px;
      border-radius: 50%;
      background: rgba(255,255,255,.04);
      pointer-events: none;
    }
    .res-hero-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 32px;
      position: relative;
      z-index: 1;
    }
    .res-hero-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.18);
      border-radius: 20px;
      padding: 5px 14px;
      font-size: 12px;
      font-weight: 600;
      color: rgba(255,255,255,.85);
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .res-hero h1 {
      font-family: 'DM Serif Display', serif;
      font-size: 32px;
      color: white;
      margin-bottom: 8px;
    }
    .res-hero p {
      font-size: 14.5px;
      color: rgba(255,255,255,.68);
    }

    /* ── Steps indicator ── */
    .steps-bar {
      background: var(--white);
      border-bottom: 1px solid rgba(82,183,136,.15);
    }
    .steps-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 32px;
      display: flex;
      align-items: center;
      height: 56px;
      gap: 8px;
    }
    .step {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13.5px;
      font-weight: 500;
      color: var(--text-muted);
    }
    .step.active { color: var(--green-mid); font-weight: 700; }
    .step.done { color: var(--green-light); }
    .step-num {
      width: 26px; height: 26px;
      border-radius: 50%;
      background: #e8f5e9;
      border: 2px solid rgba(82,183,136,.25);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px;
      font-weight: 700;
      color: var(--text-muted);
    }
    .step.active .step-num {
      background: var(--green-mid);
      border-color: var(--green-mid);
      color: white;
    }
    .step.done .step-num {
      background: var(--green-light);
      border-color: var(--green-light);
      color: white;
    }
    .step-divider {
      flex: 1;
      max-width: 60px;
      height: 1px;
      background: rgba(82,183,136,.20);
    }

    /* ── Page layout ── */
    .res-wrap {
      max-width: 1200px;
      margin: 0 auto;
      padding: 36px 32px;
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 28px;
      align-items: start;
    }

    /* ── Form sections ── */
    .form-card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
      margin-bottom: 20px;
    }
    .form-card-header {
      padding: 18px 24px;
      border-bottom: 1px solid rgba(82,183,136,.12);
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .form-card-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: var(--green-pale);
      display: flex; align-items: center; justify-content: center;
      color: var(--green-mid);
      flex-shrink: 0;
    }
    .form-card-title {
      font-weight: 700;
      font-size: 15.5px;
      color: var(--green-deep);
    }
    .form-card-sub {
      font-size: 12.5px;
      color: var(--text-muted);
      margin-top: 2px;
    }
    .form-card-body {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
    .form-row.three { grid-template-columns: 1fr 1fr 1fr; }

    /* Duration display */
    .duration-pill {
      background: linear-gradient(135deg, var(--green-pale), #c8f0d4);
      border: 1px solid rgba(82,183,136,.25);
      border-radius: 10px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .duration-icon {
      width: 38px; height: 38px;
      border-radius: 10px;
      background: var(--green-mid);
      display: flex; align-items: center; justify-content: center;
      color: white;
      flex-shrink: 0;
    }
    .duration-text .val {
      font-weight: 700;
      font-size: 16px;
      color: var(--green-deep);
    }
    .duration-text .lbl {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 2px;
    }

    /* Payment section */
    .card-input-wrap {
      position: relative;
    }
    .card-icons {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      display: flex;
      gap: 4px;
    }
    .card-icon {
      width: 32px; height: 20px;
      background: #eee;
      border-radius: 3px;
      display: flex; align-items: center; justify-content: center;
      font-size: 8px;
      font-weight: 700;
      letter-spacing: .3px;
    }
    .card-icon.visa { background: #1a1f71; color: white; }
    .card-icon.mc { background: #eb001b; color: white; }

    /* ── Booking summary (right col) ── */
    .summary-card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
      overflow: hidden;
      position: sticky;
      top: 88px;
    }
    .summary-header {
      background: linear-gradient(135deg, var(--green-deep), #2a5a3f);
      padding: 20px 22px;
    }
    .summary-title {
      font-family: 'DM Serif Display', serif;
      font-size: 18px;
      color: white;
      margin-bottom: 16px;
    }
    .summary-equip {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .summary-equip-img {
      width: 52px; height: 52px;
      border-radius: 10px;
      background: rgba(255,255,255,.15);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .summary-equip-img svg { color: rgba(255,255,255,.7); }
    .summary-equip-name {
      font-weight: 700;
      font-size: 14.5px;
      color: white;
    }
    .summary-equip-meta {
      font-size: 12px;
      color: rgba(255,255,255,.65);
      margin-top: 3px;
    }

    .summary-body { padding: 20px 22px; }

    .sum-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      font-size: 13.5px;
      border-bottom: 1px solid rgba(82,183,136,.08);
    }
    .sum-row:last-child { border-bottom: none; }
    .sum-row .key { color: var(--text-muted); }
    .sum-row .val { font-weight: 600; color: var(--text-dark); }
    .sum-row.total-row {
      margin-top: 8px;
      padding-top: 14px;
      border-top: 2px solid rgba(82,183,136,.18);
      border-bottom: none;
    }
    .sum-row.total-row .key {
      font-weight: 700;
      font-size: 15px;
      color: var(--green-deep);
    }
    .sum-row.total-row .val {
      font-family: 'DM Serif Display', serif;
      font-size: 22px;
      color: var(--green-mid);
    }

    /* Trust indicators */
    .trust-block {
      background: var(--cream);
      border-top: 1px solid rgba(82,183,136,.12);
      padding: 16px 22px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .trust-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12.5px;
      color: var(--text-muted);
    }
    .trust-item svg { color: var(--green-mid); flex-shrink: 0; }

    /* ── Success state ── */
    .success-screen {
      display: none;
      text-align: center;
      padding: 60px 32px;
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
    }
    .success-screen.show { display: block; }
    .success-icon {
      width: 80px; height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #d8f3dc, #b7e4c7);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
      animation: pop .4s ease;
    }
    @keyframes pop {
      0% { transform: scale(.5); opacity: 0; }
      80% { transform: scale(1.08); }
      100% { transform: scale(1); opacity: 1; }
    }
    .success-icon svg { color: var(--green-mid); }
    .success-screen h2 {
      font-family: 'DM Serif Display', serif;
      font-size: 28px;
      color: var(--green-deep);
      margin-bottom: 10px;
    }
    .success-screen p {
      font-size: 14.5px;
      color: var(--text-muted);
      max-width: 420px;
      margin: 0 auto 24px;
      line-height: 1.65;
    }
    .success-ref {
      background: var(--green-pale);
      border-radius: 10px;
      padding: 14px 20px;
      display: inline-block;
      font-weight: 700;
      font-size: 14px;
      color: var(--green-deep);
      margin-bottom: 24px;
      letter-spacing: .5px;
    }
    .success-details {
      background: var(--cream);
      border-radius: 12px;
      padding: 18px;
      text-align: left;
      max-width: 400px;
      margin: 0 auto 28px;
    }
    .success-detail-row {
      display: flex;
      justify-content: space-between;
      font-size: 13.5px;
      padding: 6px 0;
      border-bottom: 1px solid rgba(82,183,136,.10);
    }
    .success-detail-row:last-child { border-bottom: none; }
    .success-detail-row .k { color: var(--text-muted); }
    .success-detail-row .v { font-weight: 600; color: var(--text-dark); }

    @media (max-width: 900px) {
      .res-wrap { grid-template-columns: 1fr; }
      .summary-card { position: static; }
      .form-row { grid-template-columns: 1fr; }
      .form-row.three { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ══════════════ HEADER ══════════════ -->
<header class="gr-header">
  <nav class="gr-nav">
    <a href="farmer-dashboard.html" class="gr-logo">
      <img src="logo.png" alt="GreenRent Logo" />
      <div class="gr-logo-text">
        <span>GreenRent</span>
        <span>Agricultural Equipment</span>
      </div>
    </a>
    <ul class="gr-navlinks">
      <li><a href="farmer-dashboard.html">Farmer Dashboard</a></li>
      <li><a href="my-reservations.html">My Reservations</a></li>
      <li><a href="farmer-profile.html">Profile</a></li>
      <li><a href="login.html">Logout</a></li>
    </ul>
    <div class="gr-search">
      <svg class="gr-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21" stroke-linecap="round"/>
      </svg>
      <input type="text" placeholder="Search equipment…"/>
    </div>
    <div class="gr-nav-actions">
      <div class="farmer-badge">
        <div class="avatar">AH</div>
        Ahmed H.
      </div>
    </div>
  </nav>
</header>

<!-- ══════════════ HERO ══════════════ -->
<section class="res-hero">
  <div class="res-hero-inner">
    <div class="breadcrumb" style="margin-bottom:14px;">
      <a href="farmer-dashboard.html" style="color:rgba(255,255,255,.7);">Dashboard</a>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <a href="equipment-details.html" style="color:rgba(255,255,255,.7);">Equipment Details</a>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      <span style="color:white;">Reservation Page</span>
    </div>
    <div class="res-hero-tag">📋 Reservation Page</div>
    <h1>Complete Your Reservation</h1>
    <p>Review your booking, select rental dates, and enter payment details to confirm your reservation.</p>
  </div>
</section>

<!-- ══════════════ STEPS ══════════════ -->
<div class="steps-bar">
  <div class="steps-inner">
    <div class="step done">
      <div class="step-num">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      Browse Equipment
    </div>
    <div class="step-divider"></div>
    <div class="step done">
      <div class="step-num">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      View Details
    </div>
    <div class="step-divider"></div>
    <div class="step active">
      <div class="step-num">3</div>
      Reservation
    </div>
    <div class="step-divider"></div>
    <div class="step">
      <div class="step-num">4</div>
      Confirmation
    </div>
  </div>
</div>

<!-- ══════════════ MAIN CONTENT ══════════════ -->
<div class="res-wrap">

  <!-- ── LEFT: Forms ── -->
  <div id="formsCol">

    <!-- SECTION 1: Rental Dates -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="form-card-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
          </svg>
        </div>
        <div>
          <div class="form-card-title">Rental Dates</div>
          <div class="form-card-sub">Select your start and end dates (minimum 2 days)</div>
        </div>
      </div>
      <div class="form-card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date *</label>
            <input type="date" class="form-control" id="startDate" required/>
            <span class="error-msg" id="startDateErr">Please select a start date</span>
          </div>
          <div class="form-group">
            <label class="form-label">End Date *</label>
            <input type="date" class="form-control" id="endDate" required/>
            <span class="error-msg" id="endDateErr">End date must be after start date (min 2 days)</span>
          </div>
        </div>

        <div class="duration-pill" id="durationPill">
          <div class="duration-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/>
            </svg>
          </div>
          <div class="duration-text">
            <div class="val" id="durationVal">3 days rental</div>
            <div class="lbl" id="durationLbl">Apr 10 – Apr 13 · SAR 1,050 subtotal</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Special Notes or Requirements (Optional)</label>
          <textarea class="form-control" rows="3" placeholder="e.g. Delivery to south field gate, preferred morning start time, specific attachments needed…" style="resize:vertical;"></textarea>
        </div>
      </div>
    </div>

    <!-- SECTION 2: Contact Info -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="form-card-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </div>
        <div>
          <div class="form-card-title">Farmer Information</div>
          <div class="form-card-sub">Confirm your contact details for this booking</div>
        </div>
      </div>
      <div class="form-card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-control" id="firstName" value="Ahmed" placeholder="First name" required/>
            <span class="error-msg" id="firstNameErr">First name is required</span>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-control" id="lastName" value="Al-Harbi" placeholder="Last name" required/>
            <span class="error-msg" id="lastNameErr">Last name is required</span>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Phone Number *</label>
            <input type="tel" class="form-control" id="phone" value="+966 55 123 4567" placeholder="+966 5X XXX XXXX" required/>
            <span class="error-msg" id="phoneErr">Valid phone number required</span>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" class="form-control" id="email" value="ahmed.harbi@example.com" placeholder="your@email.com" required/>
            <span class="error-msg" id="emailErr">Valid email is required</span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Farm / Delivery Location *</label>
          <input type="text" class="form-control" id="location" placeholder="e.g. Farm Al-Rawabi, Diriyah District, Riyadh" required/>
          <span class="error-msg" id="locationErr">Delivery location is required</span>
        </div>
      </div>
    </div>

    <!-- SECTION 3: Payment -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="form-card-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
          </svg>
        </div>
        <div>
          <div class="form-card-title">Payment Information</div>
          <div class="form-card-sub">Secured by 256-bit SSL encryption · PCI DSS compliant</div>
        </div>
      </div>
      <div class="form-card-body">
        <div class="form-group">
          <label class="form-label">Cardholder Name *</label>
          <input type="text" class="form-control" id="cardName" placeholder="Name as it appears on card" required/>
          <span class="error-msg" id="cardNameErr">Cardholder name is required</span>
        </div>
        <div class="form-group">
          <label class="form-label">Card Number *</label>
          <div class="card-input-wrap">
            <input type="text" class="form-control" id="cardNumber" placeholder="1234  5678  9012  3456" maxlength="19" required style="padding-right:100px;"/>
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
            <input type="text" class="form-control" id="expiry" placeholder="MM / YY" maxlength="7" required/>
            <span class="error-msg" id="expiryErr">Enter valid expiry (MM/YY)</span>
          </div>
          <div class="form-group">
            <label class="form-label">CVV *</label>
            <input type="text" class="form-control" id="cvv" placeholder="123" maxlength="4" required/>
            <span class="error-msg" id="cvvErr">Enter CVV</span>
          </div>
        </div>

        <div style="background:rgba(82,183,136,.08);border:1px solid rgba(82,183,136,.20);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text-muted);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--green-mid)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          Your payment details are encrypted and never stored on our servers. SAR 500 damage deposit will be held and released upon return inspection.
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <button class="btn btn-solid btn-lg" id="confirmBtn" onclick="submitReservation()" style="flex:1;justify-content:center;min-width:200px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Confirm Reservation
      </button>
      <a href="equipment-details.html" class="btn btn-outline btn-lg" style="justify-content:center;">
        ← Back to Details
      </a>
      <a href="farmer-dashboard.html" class="btn btn-sm" style="color:var(--text-muted);background:none;margin-left:auto;">
        Cancel
      </a>
    </div>

  </div><!-- /formsCol -->

  <!-- ── RIGHT: Summary ── -->
  <div>
    <div class="summary-card">
      <div class="summary-header">
        <div class="summary-title">Booking Summary</div>
        <div class="summary-equip">
          <div class="summary-equip-img">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="2" y="10" width="14" height="8" rx="2"/><circle cx="6" cy="18" r="2"/><circle cx="14" cy="18" r="2"/><path d="M16 12h4l2 4H16"/><circle cx="20" cy="18" r="2"/>
            </svg>
          </div>
          <div>
            <div class="summary-equip-name">John Deere 6M Series Tractor</div>
            <div class="summary-equip-meta">Tractor · Riyadh · Operator Included</div>
          </div>
        </div>
      </div>
      <div class="summary-body">
        <div class="sum-row">
          <span class="key">Daily Rate</span>
          <span class="val">SAR 350 / day</span>
        </div>
        <div class="sum-row">
          <span class="key">Rental Period</span>
          <span class="val" id="sumDays">3 days</span>
        </div>
        <div class="sum-row">
          <span class="key">Start Date</span>
          <span class="val" id="sumStart">Apr 10, 2026</span>
        </div>
        <div class="sum-row">
          <span class="key">End Date</span>
          <span class="val" id="sumEnd">Apr 13, 2026</span>
        </div>
        <div class="sum-row">
          <span class="key">Subtotal</span>
          <span class="val" id="sumSubtotal">SAR 1,050</span>
        </div>
        <div class="sum-row">
          <span class="key">Service Fee</span>
          <span class="val">SAR 85</span>
        </div>
        <div class="sum-row">
          <span class="key">Damage Deposit (refundable)</span>
          <span class="val">SAR 500</span>
        </div>
        <div class="sum-row total-row">
          <span class="key">Total Due</span>
          <span class="val" id="sumTotal">SAR 1,635</span>
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
        <div class="trust-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.63A2 2 0 012 .99h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          24/7 customer support available during rental
        </div>
      </div>
    </div>
  </div>

</div><!-- /res-wrap -->

<!-- ══════════════ SUCCESS SCREEN ══════════════ -->
<div style="max-width:780px;margin:0 auto;padding:0 32px 60px;" id="successWrap" style="display:none;">
  <div class="success-screen" id="successScreen">
    <div class="success-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <h2>Reservation Confirmed! 🌾</h2>
    <p>Your equipment has been successfully reserved. You will receive a confirmation SMS and email shortly. The owner will contact you within 2 hours to coordinate delivery details.</p>
    <div class="success-ref">Booking Reference: GR-2026-04847</div>
    <div class="success-details">
      <div class="success-detail-row">
        <span class="k">Equipment</span>
        <span class="v">John Deere 6M Tractor</span>
      </div>
      <div class="success-detail-row">
        <span class="k">Rental Period</span>
        <span class="v" id="successDates">Apr 10 – Apr 13, 2026</span>
      </div>
      <div class="success-detail-row">
        <span class="k">Duration</span>
        <span class="v" id="successDuration">3 days</span>
      </div>
      <div class="success-detail-row">
        <span class="k">Total Charged</span>
        <span class="v" id="successTotal">SAR 1,635</span>
      </div>
      <div class="success-detail-row">
        <span class="k">Status</span>
        <span class="v" style="color:var(--green-mid);">✓ Confirmed</span>
      </div>
    </div>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
      <a href="farmer-dashboard.html" class="btn btn-solid btn-lg">Back to Dashboard</a>
      <a href="#" class="btn btn-outline btn-lg">View My Reservations</a>
    </div>
  </div>
</div>

<!-- ══════════════ FOOTER ══════════════ -->
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
  const PRICE_PER_DAY = 350;
  const SERVICE_FEE = 85;
  const DEPOSIT = 500;

  // ── Set default dates ──
  const today = new Date();
  const startDefault = new Date(today); startDefault.setDate(today.getDate() + 2);
  const endDefault = new Date(today); endDefault.setDate(today.getDate() + 5);
  document.getElementById('startDate').value = startDefault.toISOString().split('T')[0];
  document.getElementById('endDate').value = endDefault.toISOString().split('T')[0];
  updateSummary();

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

  // ── Date change → update summary ──
  document.getElementById('startDate').addEventListener('change', updateSummary);
  document.getElementById('endDate').addEventListener('change', updateSummary);

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
    const total = subtotal + SERVICE_FEE + DEPOSIT;

    document.getElementById('durationVal').textContent = days + ' day' + (days > 1 ? 's' : '') + ' rental';
    document.getElementById('durationLbl').textContent = formatDate(s) + ' – ' + formatDate(e) + ' · SAR ' + subtotal.toLocaleString() + ' subtotal';

    document.getElementById('sumDays').textContent = days + ' days';
    document.getElementById('sumStart').textContent = formatDate(s);
    document.getElementById('sumEnd').textContent = formatDate(e);
    document.getElementById('sumSubtotal').textContent = 'SAR ' + subtotal.toLocaleString();
    document.getElementById('sumTotal').textContent = 'SAR ' + total.toLocaleString();

    window._resData = { days, s, e, total };
  }

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
    const s = new Date(sVal), e = new Date(eVal);
    const days = Math.ceil((e - s) / 86400000);

    let valid = true;
    if (!validateField('startDate','startDateErr', v => v !== '')) valid = false;
    if (!validateField('endDate','endDateErr', v => v !== '' && days >= 2)) valid = false;
    if (!validateField('firstName','firstNameErr', v => v.trim().length > 0)) valid = false;
    if (!validateField('lastName','lastNameErr', v => v.trim().length > 0)) valid = false;
    if (!validateField('phone','phoneErr', v => v.trim().length >= 9)) valid = false;
    if (!validateField('email','emailErr', v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))) valid = false;
    if (!validateField('location','locationErr', v => v.trim().length > 0)) valid = false;
    if (!validateField('cardName','cardNameErr', v => v.trim().length > 0)) valid = false;
    if (!validateField('cardNumber','cardNumberErr', v => v.replace(/\s/g,'').length === 16)) valid = false;
    if (!validateField('expiry','expiryErr', v => /^\d{2}\s*\/\s*\d{2}$/.test(v))) valid = false;
    if (!validateField('cvv','cvvErr', v => /^\d{3,4}$/.test(v))) valid = false;

    if (!valid) {
      document.querySelector('.form-control.error')?.scrollIntoView({behavior:'smooth', block:'center'});
      return;
    }

    const btn = document.getElementById('confirmBtn');
    btn.textContent = 'Processing…';
    btn.disabled = true;
    btn.style.opacity = '.7';

    setTimeout(() => {
      if (window._resData) {
        const {days, s, e, total} = window._resData;
        document.getElementById('successDates').textContent = formatDate(s) + ' – ' + formatDate(e);
        document.getElementById('successDuration').textContent = days + ' days';
        document.getElementById('successTotal').textContent = 'SAR ' + total.toLocaleString();
      }

      document.querySelector('.res-wrap').style.display = 'none';
      const sw = document.getElementById('successWrap');
      sw.style.display = 'block';
      document.getElementById('successScreen').classList.add('show');
      sw.scrollIntoView({behavior:'smooth', block:'start'});
    }, 1500);
  }
</script>
</body>
</html>