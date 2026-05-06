<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Water Billing Management System – Login</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<?php
session_start();
$active_tab = 'login';
$login_email_value = '';

if (isset($_SESSION['login_flash'])) {
  $login_message = $_SESSION['login_flash']['message'] ?? '';
  $login_type = $_SESSION['login_flash']['type'] ?? 'success';
  $login_email_value = $_SESSION['login_flash']['email'] ?? '';
  unset($_SESSION['login_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Database connection
  $conn = new mysqli('localhost', 'root', '', 'billing_system');
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  function ensureColumn($conn, $table, $column, $definition) {
    $stmt = $conn->prepare("
      SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    if (!$exists) {
      $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
  }

  ensureColumn($conn, 'users', 'barangay', "VARCHAR(100) NOT NULL DEFAULT '' AFTER address");
  ensureColumn($conn, 'bills', 'barangay', "VARCHAR(100) NOT NULL DEFAULT '' AFTER user_id");

  $usernameColumnCheck = $conn->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'username'
  ");
  $hasUsername = $usernameColumnCheck && $usernameColumnCheck->num_rows > 0;
  if ($usernameColumnCheck instanceof mysqli_result) {
    $usernameColumnCheck->free();
  }

  // Ensure default admin exists
  $defaultAdminEmail = 'admin@gmail.com';
  $defaultAdminPassword = 'admin123';
  $defaultAdminName = 'System Administrator';
  $defaultAdminAddress = 'AquaBill Office, Main St.';
  $defaultAdminRole = 'admin';
  $defaultAdminHash = password_hash($defaultAdminPassword, PASSWORD_BCRYPT);

  $adminCheck = $conn->prepare("SELECT id, role, is_active FROM users WHERE email = ?");
  $adminCheck->bind_param("s", $defaultAdminEmail);
  $adminCheck->execute();
  $adminResult = $adminCheck->get_result();

  if ($adminResult && $adminResult->num_rows === 1) {
    $adminRow = $adminResult->fetch_assoc();
    if ($adminRow['role'] !== 'admin' || $adminRow['is_active'] != 1) {
      $updateAdmin = $conn->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE email = ?");
      $updateAdmin->bind_param("s", $defaultAdminEmail);
      $updateAdmin->execute();
      $updateAdmin->close();
    }
  } else {
    if ($hasUsername) {
      $insertAdmin = $conn->prepare("INSERT INTO users (full_name, username, email, address, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
      $adminUsername = 'admin';
      $insertAdmin->bind_param("ssssss", $defaultAdminName, $adminUsername, $defaultAdminEmail, $defaultAdminAddress, $defaultAdminHash, $defaultAdminRole);
    } else {
      $insertAdmin = $conn->prepare("INSERT INTO users (full_name, email, address, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
      $insertAdmin->bind_param("sssss", $defaultAdminName, $defaultAdminEmail, $defaultAdminAddress, $defaultAdminHash, $defaultAdminRole);
    }
    $insertAdmin->execute();
    $insertAdmin->close();
  }

  $adminCheck->close();

  $action = $_POST['action'] ?? '';

  if ($action === 'register') {
    $active_tab = 'register';
    $first = trim($_POST['first'] ?? '');
    $last = trim($_POST['last'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $errors = [];
    if (!$first || !$last || !$email || !$address || !$barangay || !$password || !$confirm) {
      $errors[] = 'All fields are required.';
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Invalid email format.';
    }
    if (strlen($password) < 6) {
      $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
      $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
      $full_name = $first . ' ' . $last;
      $hashed_password = password_hash($password, PASSWORD_BCRYPT);

      $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
      if (!$emailCheck) {
        $reg_message = 'Unable to validate your account right now. Please try again.';
        $reg_type = 'error';
      } else {
        $emailCheck->bind_param("s", $email);
        $emailCheck->execute();
        $existingUser = $emailCheck->get_result();
        $emailExists = $existingUser && $existingUser->num_rows > 0;
        if ($existingUser instanceof mysqli_result) {
          $existingUser->free();
        }
        $emailCheck->close();

        if ($emailExists) {
          $reg_message = 'An account with this email already exists.';
          $reg_type = 'error';
        } else {
          if ($hasUsername) {
            $usernameBase = strtolower(preg_replace('/[^a-z0-9]+/i', '', strstr($email, '@', true) ?: 'user'));
            if ($usernameBase === '') {
              $usernameBase = 'user';
            }
            $username = substr($usernameBase, 0, 20) . substr(md5($email), 0, 6);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, address, barangay, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 'user', 1)");
            if ($stmt) {
              $stmt->bind_param("ssssss", $full_name, $username, $email, $address, $barangay, $hashed_password);
            }
          } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, address, barangay, password, role, is_active) VALUES (?, ?, ?, ?, ?, 'user', 1)");
            if ($stmt) {
              $stmt->bind_param("sssss", $full_name, $email, $address, $barangay, $hashed_password);
            }
          }

          if (!$stmt) {
            $reg_message = 'Unable to create the account right now. Please try again.';
            $reg_type = 'error';
          } elseif ($stmt->execute()) {
            $_SESSION['login_flash'] = [
              'message' => 'Account created successfully! You can now log in.',
              'type' => 'success',
              'email' => $email,
            ];

            if ($stmt) {
              $stmt->close();
            }
            $conn->close();

            if (!headers_sent()) {
              header('Location: login.php');
            } else {
              echo "<script>window.location.href='login.php';</script>";
            }
            exit;
          } else {
            $reg_message = $stmt->errno === 1062
              ? 'An account with this email already exists.'
              : 'Unable to create the account right now. Please try again.';
            $reg_type = 'error';
          }

          if ($stmt) {
            $stmt->close();
          }
        }
      }
    } else {
      $reg_message = implode('<br>', $errors);
      $reg_type = 'error';
    }
  } elseif ($action === 'login') {
    $active_tab = 'login';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_email_value = $email;

    if (!$email || !$password) {
      $login_message = 'Please fill in all fields.';
      $login_type = 'error';
    } else {
      $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ? AND is_active = 1");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
          session_regenerate_id(true);
          $_SESSION['user_id'] = $user['id'];
          $_SESSION['user_name'] = $user['full_name'];
          $_SESSION['user_role'] = $user['role'];
          if ($user['role'] === 'admin') {
            header('Location: admin-dashboard.php');
          } else {
            header('Location: user-dashboard.php');
          }
          exit;
        } else {
          $login_message = 'Invalid email or password.';
          $login_type = 'error';
        }
      } else {
        $login_message = 'Invalid email or password.';
        $login_type = 'error';
      }
      $stmt->close();
    }
  }
  $conn->close();
}
?>
<style>
  :root {
    --deep: #0a1628;
    --ocean: #0d2f5e;
    --tide: #1a5f7a;
    --aqua: #22a8d4;
    --foam: #7dd4ef;
    --pearl: #e8f4f8;
    --white: #ffffff;
    --accent: #00e5ff;
    --warn: #ff6b6b;
    --card-bg: rgba(10, 22, 40, 0.82);
    --glass: rgba(255,255,255,0.06);
    --border: rgba(34,168,212,0.25);
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--deep);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }

  /* ===== ANIMATED WATER BACKGROUND ===== */
  .water-bg {
    position: fixed; inset: 0; z-index: 0;
    background: linear-gradient(180deg, #040e1e 0%, #0a1f3d 40%, #0d3a5c 70%, #0e4f72 100%);
    overflow: hidden;
  }

  /* Caustic light ripples */
  .caustic {
    position: absolute; inset: 0;
    background:
      radial-gradient(ellipse 80px 40px at 20% 30%, rgba(34,168,212,0.12) 0%, transparent 70%),
      radial-gradient(ellipse 60px 30px at 70% 60%, rgba(0,229,255,0.10) 0%, transparent 70%),
      radial-gradient(ellipse 100px 50px at 50% 80%, rgba(34,168,212,0.08) 0%, transparent 70%);
    animation: causticShift 8s ease-in-out infinite alternate;
  }
  @keyframes causticShift {
    0%   { transform: translate(0,0) scale(1); opacity: 0.7; }
    50%  { transform: translate(20px,-10px) scale(1.05); opacity: 1; }
    100% { transform: translate(-15px,15px) scale(0.97); opacity: 0.8; }
  }

  /* Bubble particles */
  .bubbles { position: absolute; inset: 0; }
  .bubble {
    position: absolute;
    border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.45), rgba(34,168,212,0.1));
    border: 1px solid rgba(255,255,255,0.18);
    animation: rise linear infinite;
  }
  .bubble:nth-child(1)  { width:8px;  height:8px;  left:8%;   bottom:-20px; animation-duration:9s;  animation-delay:0s; }
  .bubble:nth-child(2)  { width:5px;  height:5px;  left:18%;  bottom:-20px; animation-duration:12s; animation-delay:2s; }
  .bubble:nth-child(3)  { width:12px; height:12px; left:30%;  bottom:-20px; animation-duration:8s;  animation-delay:1s; }
  .bubble:nth-child(4)  { width:4px;  height:4px;  left:45%;  bottom:-20px; animation-duration:14s; animation-delay:3s; }
  .bubble:nth-child(5)  { width:9px;  height:9px;  left:58%;  bottom:-20px; animation-duration:10s; animation-delay:0.5s; }
  .bubble:nth-child(6)  { width:6px;  height:6px;  left:70%;  bottom:-20px; animation-duration:11s; animation-delay:4s; }
  .bubble:nth-child(7)  { width:14px; height:14px; left:82%;  bottom:-20px; animation-duration:7s;  animation-delay:1.5s; }
  .bubble:nth-child(8)  { width:3px;  height:3px;  left:92%;  bottom:-20px; animation-duration:13s; animation-delay:2.5s; }
  .bubble:nth-child(9)  { width:7px;  height:7px;  left:25%;  bottom:-20px; animation-duration:9.5s; animation-delay:5s; }
  .bubble:nth-child(10) { width:10px; height:10px; left:65%;  bottom:-20px; animation-duration:8.5s; animation-delay:3.5s; }
  .bubble:nth-child(11) { width:5px;  height:5px;  left:50%;  bottom:-20px; animation-duration:15s; animation-delay:6s; }
  .bubble:nth-child(12) { width:8px;  height:8px;  left:38%;  bottom:-20px; animation-duration:11s; animation-delay:1s; }

  @keyframes rise {
    0%   { transform: translateY(0) translateX(0) scale(1);    opacity: 0; }
    10%  { opacity: 0.7; }
    50%  { transform: translateY(-50vh) translateX(15px) scale(1.05); }
    90%  { opacity: 0.4; }
    100% { transform: translateY(-110vh) translateX(-10px) scale(0.8); opacity: 0; }
  }

  /* Wave layers */
  .waves { position: absolute; bottom: 0; left: 0; width: 100%; }
  .wave {
    position: absolute; bottom: 0; left: 0;
    width: 200%; height: 160px;
    border-radius: 40% 60% 40% 60% / 30% 30% 70% 70%;
  }
  .wave1 {
    background: rgba(13,47,94,0.55);
    animation: waveAnim 10s ease-in-out infinite;
  }
  .wave2 {
    background: rgba(26,95,122,0.35);
    animation: waveAnim 14s ease-in-out infinite reverse;
    height: 130px;
  }
  .wave3 {
    background: rgba(34,168,212,0.12);
    animation: waveAnim 18s ease-in-out infinite;
    height: 100px;
  }
  @keyframes waveAnim {
    0%,100% { transform: translateX(-25%) scaleY(1); }
    50%      { transform: translateX(0%)   scaleY(1.08); }
  }

  /* Shimmer streaks */
  .shimmer {
    position: absolute;
    width: 2px; height: 120px;
    background: linear-gradient(to bottom, transparent, rgba(0,229,255,0.3), transparent);
    border-radius: 2px;
    animation: shimmerDrift ease-in-out infinite;
  }
  .shimmer:nth-child(1) { left:15%; top:10%; animation-duration:6s;  animation-delay:0s; }
  .shimmer:nth-child(2) { left:40%; top:25%; animation-duration:8s;  animation-delay:2s; }
  .shimmer:nth-child(3) { left:68%; top:5%;  animation-duration:7s;  animation-delay:1s; }
  .shimmer:nth-child(4) { left:85%; top:40%; animation-duration:9s;  animation-delay:3s; }
  @keyframes shimmerDrift {
    0%,100% { opacity:0.2; transform:translateY(0) rotate(-5deg); }
    50%      { opacity:0.7; transform:translateY(20px) rotate(5deg); }
  }

  /* ===== CARD ===== */
  .card-wrapper {
    position: relative; z-index: 10;
    width: 100%; max-width: 440px;
    padding: 20px;
  }

  .logo-area {
    text-align: center;
    margin-bottom: 28px;
  }
  .logo-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, var(--aqua), var(--accent));
    border-radius: 20px;
    display: inline-flex; align-items: center; justify-content: center;
    margin-bottom: 12px;
    box-shadow: 0 0 30px rgba(34,168,212,0.4), 0 0 60px rgba(0,229,255,0.15);
    animation: logoPulse 3s ease-in-out infinite;
  }
  @keyframes logoPulse {
    0%,100% { box-shadow: 0 0 30px rgba(34,168,212,0.4), 0 0 60px rgba(0,229,255,0.15); }
    50%      { box-shadow: 0 0 45px rgba(34,168,212,0.6), 0 0 90px rgba(0,229,255,0.25); }
  }
  .logo-icon svg { width:32px; height:32px; fill:white; }
  .logo-title {
    font-family: 'Playfair Display', serif;
    font-size: 2rem; font-weight: 700;
    color: var(--white);
    letter-spacing: -0.5px;
  }
  .logo-title span { color: var(--aqua); }
  .logo-sub {
    font-size: 0.78rem; color: rgba(255,255,255,0.45);
    letter-spacing: 2px; text-transform: uppercase;
    margin-top: 2px;
  }

  .card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 36px 32px;
    backdrop-filter: blur(20px);
    box-shadow: 0 24px 80px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.06);
  }

  .tab-bar {
    display: flex; gap: 4px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px; padding: 4px;
    margin-bottom: 28px;
  }
  .tab {
    flex: 1; padding: 9px;
    border: none; background: transparent;
    color: rgba(255,255,255,0.45);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 500;
    border-radius: 9px; cursor: pointer;
    transition: all 0.25s;
  }
  .tab.active {
    background: linear-gradient(135deg, var(--aqua), var(--accent));
    color: white;
    box-shadow: 0 4px 14px rgba(34,168,212,0.35);
  }

  .form-section { display: none; }
  .form-section.active { display: block; }

  .field { margin-bottom: 18px; }
  .field label {
    display: block;
    font-size: 0.78rem; font-weight: 500;
    color: rgba(255,255,255,0.55);
    letter-spacing: 0.5px;
    margin-bottom: 7px;
  }
  .field input, .field select {
    width: 100%;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 12px 14px;
    color: white;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }
  .field input::placeholder { color: rgba(255,255,255,0.25); }
  .field input:focus {
    border-color: var(--aqua);
    box-shadow: 0 0 0 3px rgba(34,168,212,0.15);
    background: rgba(255,255,255,0.09);
  }
  .field input.error { border-color: var(--warn); }
  .password-field { position: relative; }
  .password-field input { padding-right: 72px; }
  .password-toggle {
  position: absolute;
  top: 50%;
  right: 12px;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--foam);
  font-size: 1.1rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.password-toggle:hover {
  color: white;
  transform: translateY(-50%) scale(1.1);
}

  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

  .btn-primary {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--aqua) 0%, var(--accent) 100%);
    border: none; border-radius: 11px;
    color: white; font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem; font-weight: 600;
    cursor: pointer; letter-spacing: 0.3px;
    transition: all 0.2s;
    box-shadow: 0 6px 20px rgba(34,168,212,0.35);
    margin-top: 8px;
    position: relative; overflow: hidden;
  }
  .btn-primary::after {
    content:''; position:absolute; inset:0;
    background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
    opacity:0; transition:opacity 0.2s;
  }
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(34,168,212,0.45); }
  .btn-primary:hover::after { opacity:1; }
  .btn-primary:active { transform: translateY(0); }

  .divider {
    text-align: center; position: relative;
    margin: 20px 0; color: rgba(255,255,255,0.2);
    font-size: 0.78rem;
  }
  .divider::before, .divider::after {
    content:''; position:absolute; top:50%;
    width: calc(50% - 24px); height: 1px;
    background: rgba(255,255,255,0.08);
  }
  .divider::before { left:0; }
  .divider::after { right:0; }

  .alert {
    padding: 10px 14px; border-radius: 9px;
    font-size: 0.82rem; margin-bottom: 16px;
    display: none;
  }
  .alert.error { background: rgba(255,107,107,0.15); border: 1px solid rgba(255,107,107,0.3); color: #ff9f9f; display:block; }
  .alert.success { background: rgba(34,168,212,0.15); border: 1px solid rgba(34,168,212,0.3); color: var(--foam); display:block; }

  .badge-demo {
    display: flex; gap: 8px; margin-top: 20px;
    padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.07);
  }
  .badge {
    flex:1; background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 9px; padding: 10px;
    font-size: 0.72rem;
  }
  .badge strong { display:block; color: var(--aqua); font-size:0.78rem; margin-bottom:3px; }
  .badge span { color: rgba(255,255,255,0.4); }
</style>
</head>
<body>

<div class="water-bg">
  <div class="caustic"></div>
  <div class="shimmer"></div>
  <div class="shimmer"></div>
  <div class="shimmer"></div>
  <div class="shimmer"></div>
  <div class="bubbles">
    <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  </div>
  <div class="waves">
    <div class="wave wave1"></div>
    <div class="wave wave2"></div>
    <div class="wave wave3"></div>
  </div>
</div>

<div class="card-wrapper">
  <div class="logo-area">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/><path d="M12 1a11 11 0 1 0 0 22A11 11 0 0 0 12 1zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
    </div>
    <div class="logo-sub">Water Billing Management System</div>
  </div>

  <div class="card">
    <div class="tab-bar">
      <button class="tab <?php echo $active_tab === 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">Sign In</button>
      <button class="tab <?php echo $active_tab === 'register' ? 'active' : ''; ?>" onclick="switchTab('register')">Register</button>
    </div>

    <!-- LOGIN FORM -->
    <div class="form-section <?php echo $active_tab === 'login' ? 'active' : ''; ?>" id="login-section">
      <div id="login-alert" class="alert <?php echo $login_type ?? 'error'; ?>" style="display: <?php echo isset($login_message) ? 'block' : 'none'; ?>;"><?php echo $login_message ?? ''; ?></div>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="field">
          <label>EMAIL</label>
          <input type="email" name="email" id="login-user" placeholder="Enter your email" value="<?php echo htmlspecialchars($login_email_value ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="field">
          <label>PASSWORD</label>
          <div class="password-field">
            <input type="password" name="password" id="login-pass" placeholder="••••••••" required>
            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">👁</button>
          </div>
        </div>
        <button type="submit" class="btn-primary">Sign In</button>
      </form>
    </div>
    <div class="form-section <?php echo $active_tab === 'register' ? 'active' : ''; ?>" id="register-section">
      <div id="reg-alert" class="alert <?php echo $reg_type ?? 'error'; ?>" style="display: <?php echo isset($reg_message) ? 'block' : 'none'; ?>;"><?php echo $reg_message ?? ''; ?></div>
      <form method="post">
        <input type="hidden" name="action" value="register">
        <div class="field-row">
          <div class="field">
            <label>FIRST NAME</label>
            <input type="text" name="first" id="reg-first" placeholder="Enter your first name" required>
          </div>
          <div class="field">
            <label>LAST NAME</label>
            <input type="text" name="last" id="reg-last" placeholder="Enter your last name" required>
          </div>
        </div>
        <div class="field">
          <label>EMAIL</label>
          <input type="email" name="email" id="reg-email" placeholder="your@email.com" required>
        </div>
        <div class="field">
          <label>ADDRESS</label>
          <input type="text" name="address" id="reg-addr" placeholder="Street, Barangay, City/Municipality" required>
        </div>
        <div class="field-row">
          <div class="field">
            <label>PASSWORD</label>
            <div class="password-field">
              <input type="password" name="password" id="reg-pass" placeholder="••••••••" required>
              <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">👁</button>
            </div>
          </div>
          <div class="field">
            <label>CONFIRM</label>
            <div class="password-field">
              <input type="password" name="confirm" id="reg-confirm" placeholder="••••••••" required>
              <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">👁s</button>
            </div>
          </div>
        </div>
        <button type="submit" class="btn-primary">Create Account</button>
      </form>
    </div>
  </div>
</div>

<script>
function setPasswordVisibility(input, button, show) {
  input.type = show ? 'text' : 'password';
  button.setAttribute(
    'aria-label',
    show ? 'Hide password' : 'Show password'
  );
}

function initPasswordToggles() {
  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const field = button.closest('.password-field');
    const input = field ? field.querySelector('input') : null;

    if (!input) return;

    // Prevent duplicate event listeners
    if (button.dataset.bound === 'true') return;
    button.dataset.bound = 'true';

    // Show while hovering
    button.addEventListener('mouseenter', () => {
      setPasswordVisibility(input, button, true);
    });

    // Hide when mouse leaves
    button.addEventListener('mouseleave', () => {
      setPasswordVisibility(input, button, false);
    });

    // Show while mouse button is held down
    button.addEventListener('mousedown', () => {
      setPasswordVisibility(input, button, true);
    });

    // Hide when released
    button.addEventListener('mouseup', () => {
      setPasswordVisibility(input, button, false);
    });

    // For touch devices
    button.addEventListener('touchstart', () => {
      setPasswordVisibility(input, button, true);
    });

    button.addEventListener('touchend', () => {
      setPasswordVisibility(input, button, false);
    });
  });
}

function switchTab(tab) {
  document.querySelectorAll('.tab').forEach((t, i) => {
    t.classList.toggle(
      'active',
      (i === 0 && tab === 'login') ||
      (i === 1 && tab === 'register')
    );
  });

  document
    .getElementById('login-section')
    .classList.toggle('active', tab === 'login');

  document
    .getElementById('register-section')
    .classList.toggle('active', tab === 'register');
}

document.addEventListener('DOMContentLoaded', initPasswordToggles);
</script>
