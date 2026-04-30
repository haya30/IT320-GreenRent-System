<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'owner') {
    header("Location: login.php");
    exit;
}

$owner_id = $_SESSION['user']['user_id'];
$user = $_SESSION['user'];

$initials = strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
$short_name = htmlspecialchars($user['first_name'] . ' ' . mb_substr($user['last_name'], 0, 1) . '.');

$message = "";
$messageType = "success";

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? "success";
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$editData = [
    "equipment_id" => "",
    "equipment_name" => "",
    "type" => "",
    "description" => "",
    "condition" => "",
    "price_per_day" => "",
    "location" => "",
    "availability_status" => "available",
    "operator_included" => 0,
    "image_url" => ""
];

if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);

    $stmt = $conn->prepare("SELECT * FROM equipment WHERE equipment_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $edit_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $editData = $result->fetch_assoc();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $equipment_id = $_POST["equipment_id"] ?? "";
    $name = trim($_POST["equipment_name"] ?? "");
    $type = trim($_POST["type"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $condition = trim($_POST["condition"] ?? "");
    $price = floatval($_POST["price_per_day"] ?? 0);
    $location = trim($_POST["location"] ?? "");
    $status = $_POST["availability_status"] ?? "available";
    $operator = intval($_POST["operator_included"] ?? 0);

    $image_url = $editData["image_url"] ?? "";

    // If user clicked Clear and saved without choosing a new image
    if (isset($_POST["remove_image"]) && $_POST["remove_image"] === "1") {
        $image_url = "";
    }

    if ($name === "" || $type === "" || $description === "" || $condition === "" || $price <= 0 || $location === "") {
        $message = "Please fill in all required fields correctly.";
        $messageType = "error";
    }

    if ($messageType !== "error" && isset($_FILES["equipment_image"]) && $_FILES["equipment_image"]["error"] === 0) {
        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ["jpg", "jpeg", "png", "gif", "webp"];
        $fileName = $_FILES["equipment_image"]["name"];
        $fileTmp = $_FILES["equipment_image"]["tmp_name"];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowedTypes)) {
            $newFileName = time() . "_" . uniqid() . "." . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                $image_url = $targetPath;
            }
        } else {
            $message = "Invalid image type. Please upload JPG, PNG, GIF, or WEBP.";
            $messageType = "error";
        }
    }

    if ($messageType !== "error") {
        if (!empty($equipment_id)) {
            $equipment_id = intval($equipment_id);

            $stmt = $conn->prepare("
                UPDATE equipment
                SET equipment_name = ?,
                    type = ?,
                    description = ?,
                    `condition` = ?,
                    price_per_day = ?,
                    location = ?,
                    availability_status = ?,
                    operator_included = ?,
                    image_url = ?
                WHERE equipment_id = ? AND owner_id = ?
            ");

            $stmt->bind_param(
                "ssssdssisii",
                $name,
                $type,
                $description,
                $condition,
                $price,
                $location,
                $status,
                $operator,
                $image_url,
                $equipment_id,
                $owner_id
            );

            if ($stmt->execute()) {
                $message = "Equipment updated successfully.";
                $messageType = "success";

                $stmt = $conn->prepare("SELECT * FROM equipment WHERE equipment_id = ? AND owner_id = ?");
                $stmt->bind_param("ii", $equipment_id, $owner_id);
                $stmt->execute();
                $editData = $stmt->get_result()->fetch_assoc();
            } else {
                $message = "Error updating equipment: " . $conn->error;
                $messageType = "error";
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO equipment 
                (owner_id, equipment_name, type, description, `condition`, price_per_day, location, availability_status, operator_included, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssdssis",
                $owner_id,
                $name,
                $type,
                $description,
                $condition,
                $price,
                $location,
                $status,
                $operator,
                $image_url
            );

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Equipment added successfully.";
                $_SESSION['flash_type'] = "success";
                header("Location: add-edit-equipment.php");
                exit;
            } else {
                $message = "Error adding equipment: " . $conn->error;
                $messageType = "error";
            }
        }
    }
}

$equipmentList = [];

$stmt = $conn->prepare("SELECT equipment_id, equipment_name FROM equipment WHERE owner_id = ? ORDER BY equipment_id DESC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $equipmentList[] = $row;
}

$isEditMode = !empty($editData["equipment_id"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add / Edit Equipment | GreenRent</title>

  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>

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
      --shadow-md:   0 6px 24px rgba(26,60,43,.16);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: #eef5ee;
      color: var(--text-dark);
      min-height: 100vh;
      margin: 0;
    }

    a { text-decoration: none; }

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
      gap: 24px;
    }

    .gr-logo {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .gr-logo img { height: 40px; width: auto; }

    .gr-logo-text span:first-child {
      display: block;
      font-family: 'DM Serif Display', serif;
      font-size: 21px;
      color: var(--green-deep);
      line-height: 1;
    }

    .gr-logo-text span:last-child {
      display: block;
      font-size: 10px;
      color: var(--text-muted);
      font-weight: 500;
      letter-spacing: .8px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    .gr-navlinks {
      display: flex;
      gap: 24px;
      list-style: none;
      margin: 0 auto;
    }

    .gr-navlinks a {
      color: var(--text-muted);
      font-weight: 500;
      font-size: 14.5px;
      transition: color 0.2s;
    }

    .gr-navlinks a:hover,
    .gr-navlinks a.active {
      color: var(--green-deep);
      font-weight: 700;
    }

    .gr-nav-actions {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .farmer-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13.5px;
      font-weight: 600;
      color: var(--text-dark);
      background: var(--cream);
      padding: 4px 14px 4px 4px;
      border-radius: 30px;
      border: 1px solid rgba(82,183,136,.2);
    }

    .farmer-badge .avatar {
      width: 30px;
      height: 30px;
      background: var(--green-mid);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    .btn,
    .btn-solid,
    .btn-outline,
    .btn-soft {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 11px 18px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      border: none;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
    }

    .btn-solid {
      background: #2d6a4f;
      color: white;
      box-shadow: 0 2px 8px rgba(45,106,79,.25);
    }

    .btn-solid:hover {
      background: #1a3c2b;
      transform: translateY(-1px);
    }

    .btn-outline {
      background: white;
      color: #2d6a4f;
      border: 1.5px solid #2d6a4f;
    }

    .btn-outline:hover { background: var(--green-pale); }

    .btn-soft {
      background: rgba(255,255,255,.14);
      color: white;
      border: 1px solid rgba(255,255,255,.25);
    }

    .btn-logout {
      background: transparent;
      border: 1.5px solid #e74c3c;
      color: #e74c3c;
      padding: 8px 16px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 13.5px;
      transition: .2s;
    }

    .btn-logout:hover { background: #fef2f2; }

    .equip-page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 36px 32px;
    }

    .equip-hero {
      background: linear-gradient(135deg, #1a3c2b, #2d6a4f);
      border-radius: 22px;
      padding: 34px 32px;
      color: white;
      display: flex;
      justify-content: space-between;
      gap: 24px;
      margin-bottom: 28px;
    }

    .equip-badge {
      display: inline-block;
      background: rgba(255,255,255,.15);
      border-radius: 20px;
      padding: 7px 14px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 14px;
      text-transform: uppercase;
    }

    .equip-hero h1 {
      font-family: 'DM Serif Display', serif;
      font-size: 38px;
      margin: 0 0 10px;
    }

    .equip-hero p {
      max-width: 680px;
      line-height: 1.7;
      color: rgba(255,255,255,.85);
    }

    .equip-layout {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 24px;
    }

    .form-panel,
    .tips-panel,
    .preview-box {
      background: white;
      border: 1px solid rgba(82,183,136,.18);
      border-radius: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,.06);
      overflow: hidden;
    }

    .panel-head {
      padding: 22px 24px;
      border-bottom: 1px solid rgba(82,183,136,.15);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .panel-head h2 {
      font-family: 'DM Serif Display', serif;
      color: #1a3c2b;
      margin: 0;
    }

    .panel-head span {
      color: #6b7280;
      font-size: 13px;
    }

    .form-body,
    .tips-body { padding: 24px; }

    .message {
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 18px;
      font-weight: 700;
    }

    .message.success {
      background: #d8f3dc;
      border: 1px solid #52b788;
      color: #1b4332;
    }

    .message.error {
      background: #fee2e2;
      border: 1px solid #ef4444;
      color: #991b1b;
    }

    .mode-note {
      background: #f8fcf8;
      border: 1px solid rgba(82,183,136,.18);
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 18px;
      color: #6b7280;
      line-height: 1.7;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-group.full { grid-column: 1 / -1; }

    .form-label {
      font-size: 13px;
      font-weight: 700;
    }

    .form-label span { color: #dc2626; }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid rgba(82,183,136,.30);
      border-radius: 12px;
      background: #fbfdfb;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
    }

    .form-input.invalid,
    .form-select.invalid,
    .form-textarea.invalid {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    }

    .form-textarea {
      min-height: 130px;
      resize: vertical;
      line-height: 1.6;
    }

    .current-image {
      margin-top: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 13px;
      color: #6b7280;
    }

    .current-image img {
      width: 72px;
      height: 72px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid #d1fae5;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 24px;
      padding-top: 22px;
      border-top: 1px solid rgba(82,183,136,.15);
      flex-wrap: wrap;
    }

    .info-card {
      background: #f8fcf8;
      border: 1px solid rgba(82,183,136,.16);
      border-radius: 16px;
      padding: 18px;
      margin-bottom: 16px;
    }

    .info-card h3 {
      color: #1a3c2b;
      margin: 0 0 8px;
    }

    .info-card p,
    .info-card ul {
      color: #6b7280;
      font-size: 13px;
      line-height: 1.7;
    }

    .preview-box { margin-top: 26px; }

    .preview-card {
      padding: 22px;
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 22px;
    }

    .preview-image {
      min-height: 180px;
      border-radius: 16px;
      background: linear-gradient(135deg, #d8f3dc, #b7e4c7);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 46px;
      overflow: hidden;
    }

    .preview-image img {
      width: 100%;
      height: 100%;
      min-height: 180px;
      object-fit: cover;
    }

    .preview-content h3 {
      font-size: 24px;
      color: #1a3c2b;
      margin: 0 0 10px;
    }

    .preview-meta {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    .meta-badge {
      background: #e9f7ec;
      border-radius: 999px;
      padding: 6px 12px;
      font-size: 12px;
      font-weight: 700;
      color: #2d6a4f;
    }

    .price-preview {
      font-size: 26px;
      font-family: 'DM Serif Display', serif;
      color: #1a3c2b;
      margin-top: 14px;
    }

    footer {
      background: var(--green-deep);
      color: white;
      margin-top: 40px;
    }

    .footer-wave {
      display: block;
      width: 100%;
      height: 50px;
    }

    .footer-main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 28px 32px 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
    }

    .footer-logo img { height: 44px; }

    .footer-tagline {
      font-size: 13px;
      color: rgba(255,255,255,.6);
      text-align: center;
      max-width: 440px;
    }

    .footer-social {
      display: flex;
      gap: 8px;
    }

    .footer-social a {
      width: 36px;
      height: 36px;
      background: rgba(255,255,255,.10);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255,255,255,.75);
      text-decoration: none;
      transition: background .2s;
    }

    .footer-social a:hover { background: var(--green-light); }

    .footer-badges {
      border-top: 1px solid rgba(255,255,255,.10);
      max-width: 1200px;
      margin: 0 auto;
      padding: 16px 32px;
      display: flex;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .f-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 20px;
      padding: 5px 12px;
      font-size: 11.5px;
      color: rgba(255,255,255,.65);
    }

    .f-badge-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--green-light);
    }

    .footer-bottom {
      border-top: 1px solid rgba(255,255,255,.10);
    }

    .footer-bottom-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 14px 32px;
      text-align: center;
      font-size: 12.5px;
      color: rgba(255,255,255,.40);
    }

    @media (max-width: 900px) {
      .equip-layout,
      .preview-card {
        grid-template-columns: 1fr;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .equip-hero {
        flex-direction: column;
      }
    }
  </style>
</head>

<body>

<header class="gr-header">
  <nav class="gr-nav">
    <a href="index.php" class="gr-logo">
      <img src="logo.png" alt="GreenRent Logo" />
      <div class="gr-logo-text">
        <span>GreenRent</span>
        <span>Agricultural Equipment</span>
      </div>
    </a>

    <ul class="gr-navlinks">
      <li><a href="owner-dashboard.php" class="active">Owner Dashboard</a></li>
      <li><a href="add-edit-equipment.php">Manage Equipment</a></li>
      <li><a href="view-reservations.php">Reservations</a></li>
      <li><a href="owner-profile.php">My Profile</a></li>
    </ul>

    <div class="gr-nav-actions">
      <div class="farmer-badge" title="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
        <div class="avatar"><?= $initials ?></div>
        <?= $short_name ?>
      </div>
      <a href="logout.php" class="btn-logout">Logout</a>
    </div>
  </nav>
</header>

<main class="equip-page">

  <section class="equip-hero">
    <div>
      <div class="equip-badge"><?php echo $isEditMode ? "Edit Mode" : "Add Mode"; ?></div>
      <h1><?php echo $isEditMode ? "Edit equipment listing" : "Add a new equipment listing"; ?></h1>
      <p>
        <?php echo $isEditMode
          ? "Update the selected equipment listing and save the changes to the database."
          : "Create a professional equipment listing with all required details so farmers can view and rent it.";
        ?>
      </p>
    </div>

    <div>
      <a href="owner-dashboard.php" class="btn btn-soft">Back to Dashboard</a>
      <a href="view-reservations.php" class="btn btn-solid">View Reservations</a>
    </div>
  </section>

  <section class="equip-layout">

    <div class="form-panel">
      <div class="panel-head">
        <h2><?php echo $isEditMode ? "Edit Equipment" : "Add New Equipment"; ?></h2>
        <span><?php echo $isEditMode ? "You are modifying an existing listing" : "You are creating a new listing"; ?></span>
      </div>

      <div class="form-body">

        <?php if (!empty($message)): ?>
          <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="mode-note">
          <strong>Tip:</strong>
          To edit an existing equipment item, select it from the list below.
        </div>

        <div class="form-group full" style="margin-bottom:18px;">
          <label class="form-label">Choose Equipment to Edit</label>
          <select class="form-select" onchange="if(this.value) window.location.href='add-edit-equipment.php?edit_id=' + this.value;">
            <option value="">Select equipment</option>
            <?php foreach ($equipmentList as $item): ?>
              <option value="<?php echo $item['equipment_id']; ?>" <?php if ($editData['equipment_id'] == $item['equipment_id']) echo "selected"; ?>>
                <?php echo htmlspecialchars($item['equipment_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <form id="equipmentForm" method="POST" action="" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($editData['equipment_id']); ?>">
          <input type="hidden" name="remove_image" id="removeImage" value="0">

          <div class="form-grid">

            <div class="form-group">
              <label class="form-label">Equipment Name <span>*</span></label>
              <input type="text" class="form-input required-field" id="equipmentName" name="equipment_name"
                value="<?php echo htmlspecialchars($editData['equipment_name']); ?>"
                placeholder="e.g. John Deere Tractor" required />
            </div>

            <div class="form-group">
              <label class="form-label">Category <span>*</span></label>
              <select class="form-select required-field" id="equipmentCategory" name="type" required>
                <option value="">Select category</option>
                <?php
                $types = ["Tractor", "Harvester", "Irrigation Equipment", "Transport Equipment", "Other"];
                foreach ($types as $typeOption):
                ?>
                  <option value="<?php echo $typeOption; ?>" <?php if ($editData['type'] === $typeOption) echo "selected"; ?>>
                    <?php echo $typeOption; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Price per Day (SAR) <span>*</span></label>
              <input type="number" step="0.01" min="1" class="form-input required-field" id="equipmentPrice" name="price_per_day"
                value="<?php echo htmlspecialchars($editData['price_per_day']); ?>"
                placeholder="e.g. 850" required />
            </div>

            <div class="form-group">
              <label class="form-label">Location <span>*</span></label>
              <input type="text" class="form-input required-field" id="equipmentLocation" name="location"
                value="<?php echo htmlspecialchars($editData['location']); ?>"
                placeholder="e.g. Riyadh" required />
            </div>

            <div class="form-group">
              <label class="form-label">Availability Status <span>*</span></label>
              <select class="form-select required-field" id="equipmentStatus" name="availability_status" required>
                <option value="available" <?php if($editData['availability_status'] == "available") echo "selected"; ?>>
                  Available
                </option>
                <option value="unavailable" <?php if($editData['availability_status'] == "unavailable") echo "selected"; ?>>
                  Unavailable
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Equipment Condition <span>*</span></label>
              <select class="form-select required-field" id="equipmentCondition" name="condition" required>
                <option value="">Select condition</option>
                <?php
                $conditions = ["Excellent", "Good", "Used"];
                foreach ($conditions as $conditionOption):
                ?>
                  <option value="<?php echo $conditionOption; ?>" <?php if ($editData['condition'] === $conditionOption) echo "selected"; ?>>
                    <?php echo $conditionOption; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Operator Included</label>
              <select class="form-select" name="operator_included">
                <option value="1" <?php if($editData['operator_included']==1) echo "selected"; ?>>Yes</option>
                <option value="0" <?php if($editData['operator_included']==0) echo "selected"; ?>>No</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Equipment Image</label>
              <input type="file" class="form-input" id="equipmentImage" name="equipment_image" accept="image/*" />

              <?php if (!empty($editData['image_url'])): ?>
                <div class="current-image">
                  <img src="<?php echo htmlspecialchars($editData['image_url']); ?>" alt="Current Equipment Image">
                  <span>Current image</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="form-group full">
              <label class="form-label">Short Description <span>*</span></label>
              <textarea class="form-textarea required-field" id="equipmentDescription" name="description" required><?php echo htmlspecialchars($editData['description']); ?></textarea>
            </div>

          </div>

          <div class="form-actions">
            <a href="add-edit-equipment.php" class="btn btn-outline">Add New</a>
            <button type="button" class="btn btn-outline" id="clearBtn">Clear</button>
            <button type="submit" class="btn btn-solid">
              <?php echo $isEditMode ? "Save Changes" : "Add Equipment"; ?>
            </button>
          </div>
        </form>

      </div>
    </div>

    <aside class="tips-panel">
      <div class="panel-head">
        <h2>Listing Tips</h2>
        <span>Make your listing clearer</span>
      </div>

      <div class="tips-body">
        <div class="info-card">
          <h3>Write a clear title</h3>
          <p>Use a direct equipment name so users can quickly understand what the listing offers.</p>
        </div>

        <div class="info-card">
          <h3>Add the correct location</h3>
          <p>Make sure the location is accurate so farmers know where the equipment is available.</p>
        </div>

        <div class="info-card">
          <h3>Set a clear daily price</h3>
          <p>Show the rental price per day in SAR to avoid confusion during booking.</p>
        </div>

        <div class="info-card">
          <h3>Recommended fields</h3>
          <ul>
            <li>Clear equipment name</li>
            <li>Category and location</li>
            <li>Availability status</li>
            <li>Short and useful description</li>
          </ul>
        </div>
      </div>
    </aside>

  </section>

  <section class="preview-box">
    <div class="panel-head">
      <h2>Listing Preview</h2>
      <span>How the equipment may appear to users</span>
    </div>

    <div class="preview-card">
      <div class="preview-image" id="previewImage">
        <?php if (!empty($editData['image_url'])): ?>
          <img src="<?php echo htmlspecialchars($editData['image_url']); ?>" alt="Equipment Image">
        <?php else: ?>
          🚜
        <?php endif; ?>
      </div>

      <div class="preview-content">
        <h3 id="previewName">Equipment Name</h3>

        <div class="preview-meta">
          <span class="meta-badge" id="previewCategory">Category</span>
          <span class="meta-badge" id="previewLocation">Location</span>
          <span class="meta-badge" id="previewStatus">Status</span>
        </div>

        <p id="previewDescription">Your equipment description will appear here.</p>

        <div class="price-preview">
          <span id="previewPrice">0</span> SAR <span style="font-size:14px;">/ day</span>
        </div>
      </div>
    </div>
  </section>

</main>

<footer>
  <svg class="footer-wave" viewBox="0 0 1440 50" preserveAspectRatio="none">
    <path d="M0,0 C360,50 1080,0 1440,40 L1440,0 Z" fill="#eef5ee"/>
  </svg>

  <div class="footer-main">
    <div class="footer-logo">
      <img src="logo.png" alt="GreenRent Logo" />
    </div>

    <p class="footer-tagline">
      A trusted platform connecting farmers and equipment owners across Riyadh.
    </p>
  </div>

  <div class="footer-badges">
    <span class="f-badge"><span class="f-badge-dot"></span> Verified Equipment</span>
    <span class="f-badge"><span class="f-badge-dot"></span> Secure Payments</span>
    <span class="f-badge"><span class="f-badge-dot"></span> Riyadh — Saudi Arabia</span>
  </div>

  <div class="footer-bottom">
    <div class="footer-bottom-inner">
      © 2026 GreenRent. All rights reserved.
    </div>
  </div>
</footer>

<script>
const equipmentForm = document.getElementById("equipmentForm");
var existingImageUrl = "<?php echo htmlspecialchars($editData['image_url'] ?? ''); ?>";

// ================= VALIDATION =================
function validateForm() {
  let isValid = true;

  const oldMsg = document.getElementById("clientMessage");
  if (oldMsg) oldMsg.remove();

  document.querySelectorAll(".required-field").forEach(function(field) {
    if (!field.value.trim()) {
      field.classList.add("invalid");
      isValid = false;
    } else {
      field.classList.remove("invalid");
    }
  });

  const priceField = document.getElementById("equipmentPrice");
  if (priceField && parseFloat(priceField.value) <= 0) {
    priceField.classList.add("invalid");
    isValid = false;
  }

  if (!isValid) {
    showClientMessage("Please fill in all required fields correctly before submitting.", "error");
  }

  return isValid;
}

// ================= SHOW MESSAGE =================
function showClientMessage(text, type) {
  let msg = document.getElementById("clientMessage");

  if (!msg) {
    msg = document.createElement("div");
    msg.id = "clientMessage";
    equipmentForm.parentNode.insertBefore(msg, equipmentForm);
  }

  msg.className = "message " + type;
  msg.textContent = text;
  msg.scrollIntoView({ behavior: "smooth", block: "center" });
}

// ================= SUBMIT =================
equipmentForm.addEventListener("submit", function(e) {
  if (!validateForm()) {
    e.preventDefault();
  }
});

// ================= PREVIEW =================
function updatePreview() {
  const name     = document.getElementById("equipmentName").value;
  const category = document.getElementById("equipmentCategory").value;
  const price    = document.getElementById("equipmentPrice").value;
  const location = document.getElementById("equipmentLocation").value;
  const desc     = document.getElementById("equipmentDescription").value;
  const status   = document.getElementById("equipmentStatus").value;

  document.getElementById("previewName").textContent        = name     || "Equipment Name";
  document.getElementById("previewCategory").textContent    = category || "Category";
  document.getElementById("previewLocation").textContent    = location || "Location";
  document.getElementById("previewStatus").textContent      = status   || "Status";
  document.getElementById("previewDescription").textContent = desc     || "Your equipment description will appear here.";
  document.getElementById("previewPrice").textContent       = (price && parseFloat(price) > 0) ? price : "0";
}

["equipmentName", "equipmentCategory", "equipmentPrice", "equipmentLocation", "equipmentDescription", "equipmentStatus"].forEach(function(id) {
  var el = document.getElementById(id);
  if (el) {
    el.addEventListener("input", updatePreview);
    el.addEventListener("change", updatePreview);
  }
});

// ================= IMAGE PREVIEW =================
document.getElementById("equipmentImage").addEventListener("change", function(e) {
  document.getElementById("removeImage").value = "0";

  var file = e.target.files[0];
  var previewDiv = document.getElementById("previewImage");

  if (file) {
    var reader = new FileReader();
    reader.onload = function(event) {
      previewDiv.innerHTML = '<img src="' + event.target.result + '" alt="Preview" style="width:100%;height:100%;min-height:180px;object-fit:cover;">';
    };
    reader.readAsDataURL(file);
  }
});

// ================= CLEAR BUTTON =================
document.getElementById("clearBtn").addEventListener("click", function() {
  document.querySelectorAll(".required-field").forEach(function(field) {
    field.value = "";
    field.classList.remove("invalid");
  });

  var operatorSelect = document.querySelector("[name='operator_included']");
  if (operatorSelect) operatorSelect.value = "0";

  var fileInput = document.getElementById("equipmentImage");
  if (fileInput) fileInput.value = "";

  var oldMsg = document.getElementById("clientMessage");
  if (oldMsg) oldMsg.remove();

  var previewDiv = document.getElementById("previewImage");
  previewDiv.innerHTML = "🚜";
  existingImageUrl = "";

  document.getElementById("removeImage").value = "1";

  updatePreview();
});

updatePreview();
</script>

</body>
</html>