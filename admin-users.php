<?php
if (!ob_get_level()) {
  ob_start();
}
require_once 'admin-auth.php';

$usersData = [];
$nextUserId = 1;

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

function makeUsername($conn, $email) {
  $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', strstr($email, '@', true) ?: 'user'));
  $base = $base !== '' ? substr($base, 0, 20) : 'user';
  $username = $base;
  $suffix = 1;
  $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
  do {
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($exists) {
      $username = $base . $suffix++;
    }
  } while ($exists);
  $stmt->close();
  return $username;
}

function deriveBarangayFromAddress($address) {
  $parts = array_values(array_filter(array_map('trim', explode(',', $address)), function($part) {
    return $part !== '';
  }));

  if (count($parts) >= 2) {
    return $parts[1];
  }

  return $parts[0] ?? '';
}

ensureColumn($conn, 'users', 'barangay', "VARCHAR(100) NOT NULL DEFAULT '' AFTER address");
ensureColumn($conn, 'bills', 'barangay', "VARCHAR(100) NOT NULL DEFAULT '' AFTER user_id");

$conn->query("
  UPDATE users
  SET barangay = TRIM(
    CASE
      WHEN address LIKE '%,%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(address, ',', 2), ',', -1)
      ELSE SUBSTRING_INDEX(address, ',', 1)
    END
  )
  WHERE role = 'user'
    AND NULLIF(barangay, '') IS NULL
    AND NULLIF(address, '') IS NOT NULL
");

$conn->query("
  UPDATE bills b
  JOIN users u ON u.id = b.user_id
  SET b.barangay = u.barangay
  WHERE NULLIF(b.barangay, '') IS NULL
    AND NULLIF(u.barangay, '') IS NOT NULL
");

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add_user') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $barangay = deriveBarangayFromAddress($address);

    if ($name === '' || $email === '' || $password === '' || $address === '') {
      die('All user fields are required.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    if ($hasUsername) {
      $username = makeUsername($conn, $email);
      $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, address, barangay, password, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 'user', 1)");
      $stmt->bind_param("ssssss", $name, $username, $email, $address, $barangay, $hash);
    } else {
      $stmt = $conn->prepare("INSERT INTO users (full_name, email, address, barangay, password, role, is_active) VALUES (?, ?, ?, ?, ?, 'user', 1)");
      $stmt->bind_param("sssss", $name, $email, $address, $barangay, $hash);
    }
    if (!$stmt->execute()) {
      die('Unable to add user: ' . $stmt->error);
    }
    header('Location: admin-users.php');
    exit;
  }

  if ($action === 'edit_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $barangay = deriveBarangayFromAddress($address);

    if ($id <= 0 || $name === '' || $email === '' || $address === '') {
      die('All edit fields are required.');
    }

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, address = ?, barangay = ? WHERE id = ? AND role = 'user'");
    $stmt->bind_param("ssssi", $name, $email, $address, $barangay, $id);
    if (!$stmt->execute()) {
      die('Unable to update user: ' . $stmt->error);
    }

    $billStmt = $conn->prepare("UPDATE bills SET barangay = ? WHERE user_id = ?");
    $billStmt->bind_param("si", $barangay, $id);
    $billStmt->execute();

    header('Location: admin-users.php');
    exit;
  }

  if ($action === 'toggle_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ? AND role = 'user'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: admin-users.php');
    exit;
  }

  if ($action === 'change_password') {
    $id = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6) {
      die('Password must be at least 6 characters.');
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'user'");
    $stmt->bind_param("si", $hash, $id);
    $stmt->execute();
    header('Location: admin-users.php');
    exit;
  }

  if ($action === 'delete_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: admin-users.php');
    exit;
  }
}

$usersResult = $conn->query("
  SELECT
    u.id,
    u.full_name,
    u.email,
    u.address,
    u.barangay,
    u.is_active,
    COUNT(b.id) AS bill_count
  FROM users u
  LEFT JOIN bills b ON b.user_id = u.id
  WHERE u.role = 'user'
  GROUP BY u.id, u.full_name, u.email, u.address, u.barangay, u.is_active
  ORDER BY u.id ASC
");

if ($usersResult) {
  while ($row = $usersResult->fetch_assoc()) {
    $userId = (int) $row['id'];
    $usersData[] = [
      'id' => $userId,
      'name' => $row['full_name'],
      'email' => $row['email'],
      'address' => $row['address'],
      'barangay' => $row['barangay'],
      'active' => (bool) $row['is_active'],
      'billCount' => (int) $row['bill_count'],
    ];
    $nextUserId = max($nextUserId, $userId + 1);
  }
  $usersResult->free();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Water Billing Management System – Manage Users</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --deep:#0a1628;--ocean:#0d2f5e;--tide:#1a5f7a;--aqua:#22a8d4;--foam:#7dd4ef;--accent:#00e5ff;
    --white:#ffffff;--warn:#ff6b6b;--success:#4dd9ac;
    --sidebar-w:240px;--card-bg:rgba(13,23,43,0.95);--glass:rgba(255,255,255,0.04);
    --border:rgba(34,168,212,0.18);--text:rgba(255,255,255,0.85);--muted:rgba(255,255,255,0.4);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'DM Sans',sans-serif;background:var(--deep);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}
  .water-bg{position:fixed;inset:0;z-index:0;background:linear-gradient(180deg,#040e1e 0%,#0a1f3d 50%,#0d3a5c 100%);overflow:hidden;}
  .bubble{position:absolute;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.25),transparent);border:1px solid rgba(255,255,255,0.1);animation:rise linear infinite;}
  .bubble:nth-child(1){width:5px;height:5px;left:10%;bottom:-10px;animation-duration:11s;}
  .bubble:nth-child(2){width:7px;height:7px;left:30%;bottom:-10px;animation-duration:9s;animation-delay:2s;}
  .bubble:nth-child(3){width:4px;height:4px;left:60%;bottom:-10px;animation-duration:13s;animation-delay:1s;}
  .bubble:nth-child(4){width:8px;height:8px;left:80%;bottom:-10px;animation-duration:8s;animation-delay:4s;}
  @keyframes rise{0%{transform:translateY(0);opacity:0;}10%{opacity:0.5;}90%{opacity:0.1;}100%{transform:translateY(-100vh);opacity:0;}}
  .waves{position:absolute;bottom:0;left:0;width:100%;}
  .wave{position:absolute;bottom:0;left:0;width:200%;border-radius:40% 60% 40% 60%/30% 30% 70% 70%;}
  .wave1{background:rgba(13,47,94,0.4);height:100px;animation:waveAnim 10s ease-in-out infinite;}
  .wave2{background:rgba(34,168,212,0.06);height:70px;animation:waveAnim 14s ease-in-out infinite reverse;}
  @keyframes waveAnim{0%,100%{transform:translateX(-25%);}50%{transform:translateX(0%);}}

  /* Sidebar */
  .sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);z-index:100;background:rgba(8,16,32,0.96);border-right:1px solid var(--border);backdrop-filter:blur(20px);display:flex;flex-direction:column;padding:0 0 20px;}
  .sb-logo{padding:20px 20px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
  .sb-logo-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--aqua),var(--accent));border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 16px rgba(34,168,212,0.35);}
  .sb-logo-icon svg{width:20px;height:20px;fill:white;}
  .sb-brand{font-family:'Playfair Display',serif;font-size:1.15rem;color:white;}
  .sb-brand span{color:var(--aqua);}
  .sb-section{padding:16px 12px 4px;}
  .sb-section-label{font-size:0.65rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);padding:0 8px;margin-bottom:6px;}
  .sb-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:var(--muted);font-size:0.875rem;font-weight:500;cursor:pointer;transition:all 0.18s;text-decoration:none;margin-bottom:2px;}
  .sb-item svg{width:18px;height:18px;flex-shrink:0;}
  .sb-item:hover{background:var(--glass);color:var(--foam);}
  .sb-item.active{background:linear-gradient(90deg,rgba(34,168,212,0.15),transparent);color:var(--aqua);border-left:2px solid var(--aqua);padding-left:10px;}
  .sb-bottom{margin-top:auto;padding:0 12px;}
  .sb-user{display:flex;align-items:center;gap:10px;padding:12px;border-radius:10px;background:var(--glass);border:1px solid var(--border);}
  .sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--aqua),var(--accent));display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;flex-shrink:0;}
  .sb-uname{font-size:0.82rem;font-weight:600;color:white;}
  .sb-role{font-size:0.7rem;color:var(--muted);}
  .logout-btn{display:block;margin-top:8px;padding:9px;text-align:center;background:rgba(255,107,107,0.1);border:1px solid rgba(255,107,107,0.2);border-radius:9px;color:#ff9f9f;font-size:0.8rem;cursor:pointer;transition:all 0.2s;text-decoration:none;}
  .logout-btn:hover{background:rgba(255,107,107,0.18);}

  /* Main */
  .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;position:relative;z-index:10;}
  .topbar{position:sticky;top:0;z-index:50;height:62px;background:rgba(8,16,32,0.9);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);display:flex;align-items:center;justify-content:space-between;padding:0 28px;}
  .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:white;}
  .content{padding:28px;flex:1;}

  /* ADD USER FORM */
  .add-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;margin-bottom:22px;}
  .add-card-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
  .add-card-title svg{width:16px;height:16px;color:var(--aqua);}
  .form-grid{display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px;align-items:end;}
  .f-group label{display:block;font-size:0.7rem;letter-spacing:0.5px;color:var(--muted);margin-bottom:5px;}
  .f-group input,.f-group select{width:100%;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:10px 12px;color:white;font-family:'DM Sans',sans-serif;font-size:0.84rem;outline:none;transition:border-color 0.2s;}
  .f-group input::placeholder{color:rgba(255,255,255,0.25);}
  .f-group input:focus{border-color:var(--aqua);box-shadow:0 0 0 2px rgba(34,168,212,0.12);}
  .password-field{position:relative;}
  .password-field input{padding-right:68px;}
  .password-toggle {
  position: absolute;
  top: 50%;
  right: 12px;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: var(--foam);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  transition: all 0.2s ease;
}

.password-toggle:hover {
  color: white;
  transform: translateY(-50%) scale(1.1);
}
  .btn-primary{padding:10px 20px;background:linear-gradient(135deg,var(--aqua),var(--accent));border:none;border-radius:9px;color:white;font-family:'DM Sans',sans-serif;font-size:0.875rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:all 0.2s;box-shadow:0 4px 14px rgba(34,168,212,0.3);}
  .btn-primary:hover{box-shadow:0 6px 20px rgba(34,168,212,0.45);}

  /* TABLE CARD */
  .table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .table-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;}
  .table-title{font-size:0.9rem;font-weight:600;color:white;}
  .search-box{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:8px 14px;color:white;font-family:'DM Sans',sans-serif;font-size:0.84rem;outline:none;width:220px;transition:border-color 0.2s;}
  .search-box::placeholder{color:rgba(255,255,255,0.25);}
  .search-box:focus{border-color:var(--aqua);}
  table{width:100%;border-collapse:collapse;}
  th{font-size:0.68rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);}
  td{padding:11px 12px;font-size:0.84rem;color:var(--text);border-bottom:1px solid rgba(255,255,255,0.04);}
  tr:last-child td{border-bottom:none;}
  tr:hover td{background:rgba(255,255,255,0.02);}
  .badge-status{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.7rem;font-weight:500;}
  .badge-status.active{background:rgba(77,217,172,0.12);color:var(--success);}
  .badge-status.active::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--success);}
  .badge-status.inactive{background:rgba(255,107,107,0.12);color:var(--warn);}
  .badge-status.inactive::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--warn);}
  .action-btns{display:flex;gap:5px;}
  .btn-icon{width:29px;height:29px;border:1px solid var(--border);border-radius:6px;background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:all 0.15s;}
  .btn-icon:hover{border-color:var(--aqua);color:var(--aqua);}
  .btn-icon.danger:hover{border-color:var(--warn);color:var(--warn);}
  .btn-icon.green:hover{border-color:var(--success);color:var(--success);}
  .btn-icon svg{width:13px;height:13px;}
  .toggle-sw{width:36px;height:20px;border-radius:20px;border:none;cursor:pointer;transition:background 0.2s;position:relative;flex-shrink:0;}
  .toggle-sw::after{content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:white;transition:transform 0.2s;}
  .toggle-sw.on{background:var(--success);}
  .toggle-sw.on::after{transform:translateX(16px);}
  .toggle-sw.off{background:rgba(255,255,255,0.2);}

  /* MODAL */
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
  .modal-overlay.open{display:flex;}
  .modal{background:rgba(13,23,43,0.98);border:1px solid var(--border);border-radius:18px;padding:28px;width:400px;max-width:90vw;}
  .modal-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:white;margin-bottom:20px;}
  .modal-field{margin-bottom:14px;}
  .modal-field label{display:block;font-size:0.7rem;letter-spacing:0.5px;color:var(--muted);margin-bottom:5px;}
  .modal-field input{width:100%;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:10px 12px;color:white;font-family:'DM Sans',sans-serif;font-size:0.84rem;outline:none;transition:border-color 0.2s;}
  .modal-field input::placeholder{color:rgba(255,255,255,0.25);}
  .modal-field input:focus{border-color:var(--aqua);}
  .modal-btns{display:flex;gap:10px;margin-top:20px;}
  .btn-cancel{flex:1;padding:10px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:0.875rem;cursor:pointer;transition:all 0.2s;}
  .btn-cancel:hover{background:rgba(255,255,255,0.1);}
  .btn-save{flex:1;padding:10px;background:linear-gradient(135deg,var(--aqua),var(--accent));border:none;border-radius:9px;color:white;font-family:'DM Sans',sans-serif;font-size:0.875rem;font-weight:600;cursor:pointer;}
</style>
</head>
<body>

<div class="water-bg">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="waves"><div class="wave wave1"></div><div class="wave wave2"></div></div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon"><svg viewBox="0 0 24 24"><path d="M12 2c-5.33 4-8 8-8 11a8 8 0 0 0 16 0c0-3-2.67-7-8-11z"/></svg></div>
    <div class="sb-brand">Water<span> Billing</span></div>
  </div>
  <div class="sb-section">
    <div class="sb-section-label">Main</div>
    <a class="sb-item" href="admin-dashboard.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Dashboard
    </a>
  </div>
  <div class="sb-section">
    <div class="sb-section-label">Management</div>
    <a class="sb-item active" href="admin-users.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>Manage Users
    </a>
    <a class="sb-item" href="admin-bills.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>Manage Bills
    </a>
  </div>
  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar">A</div>
      <div><div class="sb-uname">Administrator</div><div class="sb-role">Super Admin</div></div>
    </div>
    <a class="logout-btn" href="logout.php">⇠ Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div class="topbar-title">Manage Users</div>
  </div>
  <div class="content">

    <!-- ADD USER -->
    <div class="add-card">
      <div class="add-card-title">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        Add New User
      </div>
      <form method="post" class="form-grid" onsubmit="document.getElementById('n-pass-hidden').value=document.getElementById('n-pass').value">
        <input type="hidden" name="action" value="add_user">
        <input type="hidden" name="password" id="n-pass-hidden">
        <div class="f-group"><label>FULL NAME</label><input type="text" name="full_name" id="n-name" placeholder="Juan" required></div>
        <div class="f-group"><label>EMAIL</label><input type="email" name="email" id="n-email" placeholder="juan@email.com" required></div>
        <div class="f-group">
          <label>PASSWORD</label>
          <div class="password-field">
            <input type="password" id="n-pass" placeholder="••••••••">
            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">
  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>
    <circle cx="12" cy="12" r="3"/>
  </svg>
</button>
          </div>
        </div>
        <div class="f-group"><label>ADDRESS</label><input type="text" name="address" id="n-addr" placeholder="Street, Barangay, City/Municipality" required></div>
        <div><button type="submit" class="btn-primary">+ Add User</button></div>
      </form>
      <div style="margin-top:10px;font-size:0.8rem;color:var(--muted);"></div>
    </div>

    <!-- USERS TABLE -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">All Registered Users</div>
        <input class="search-box" type="text" placeholder="🔍 Search users…" oninput="filterUsers(this.value)">
      </div>
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Full Name</th><th>Email</th>
            <th>Bills</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="users-tbody"></tbody>
      </table>
    </div>

  </div>
</main>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-title">Edit User</div>
    <input type="hidden" id="edit-id">
    <div class="modal-field"><label>FULL NAME</label><input type="text" id="edit-name" placeholder="Full Name"></div>
    <div class="modal-field"><label>EMAIL</label><input type="email" id="edit-email" placeholder="Email"></div>
    <div class="modal-field"><label>ADDRESS</label><input type="text" id="edit-addr" placeholder="Street, Barangay, City/Municipality"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('edit-modal')">Cancel</button>
      <button class="btn-save" onclick="saveEdit()">Save Changes</button>
    </div>
  </div>
</div>

<!-- PASSWORD MODAL -->
<div class="modal-overlay" id="pw-modal">
  <div class="modal">
    <div class="modal-title">Change Password</div>
    <input type="hidden" id="pw-id">
    <div class="modal-field">
      <label>NEW PASSWORD</label>
      <div class="password-field">
        <input type="password" id="pw-new" placeholder="••••••••">
      <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">👁</button>
    </div>
    <div class="modal-field">
      <label>CONFIRM PASSWORD</label>
      <div class="password-field">
        <input type="password" id="pw-confirm" placeholder="••••••••">
        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">👁</button>
      </div>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('pw-modal')">Cancel</button>
      <button class="btn-save" onclick="savePw()">Update Password</button>
    </div>
  </div>
</div>

<script>
window.addEventListener('pageshow', function(event) {
  if (event.persisted) {
    window.location.reload();
  }
});

let users = <?php echo json_encode($usersData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
let nextId = <?php echo $nextUserId; ?>;

function setPasswordVisibility(input, button, show) {
  input.type = show ? 'text' : 'password';

  const icon = button.querySelector('svg');
  if (icon) {
    icon.style.opacity = show ? '1' : '0.7';
    icon.style.transform = show ? 'scale(1.1)' : 'scale(1)';
  }

  button.setAttribute(
    'aria-label',
    show ? 'Hide password' : 'Show password'
  );
}

function resetPasswordField(input) {
  if (!input) return;

  const field = input.closest('.password-field');
  const button = field ? field.querySelector('[data-password-toggle]') : null;

  if (!button) {
    input.type = 'password';
    return;
  }

  setPasswordVisibility(input, button, false);
}

function initPasswordToggles(scope = document) {
  scope.querySelectorAll('[data-password-toggle]').forEach((button) => {
    if (button.dataset.toggleBound === 'true') return;

    button.dataset.toggleBound = 'true';

    const field = button.closest('.password-field');
    const input = field ? field.querySelector('input') : null;

    if (!input) return;

    // SHOW when mouse touches eye button
    button.addEventListener('mouseenter', () => {
      setPasswordVisibility(input, button, true);
    });

    // HIDE when mouse leaves eye button
    button.addEventListener('mouseleave', () => {
      setPasswordVisibility(input, button, false);
    });
  });
}

function renderUsers(list) {
  const tbody = document.getElementById('users-tbody');
  tbody.innerHTML = '';

  list.forEach(u => {
    const billCount = Number(u.billCount || 0);
    const billLabel = billCount === 1 ? '1 bill' : `${billCount} bills`;

    tbody.innerHTML += `<tr id="row-${u.id}">
      <td>${u.id}</td>
      <td>${u.name}</td>
      <td>${u.email}</td>
      <td>${billLabel}</td>
      <td><span class="badge-status ${u.active ? 'active' : 'inactive'}">${u.active ? 'Active' : 'Inactive'}</span></td>
      <td><div class="action-btns">
        <button class="btn-icon" title="Edit" onclick="openEdit(${u.id})">✏</button>
        <button class="btn-icon green" title="Change Password" onclick="openPw(${u.id})">🔒</button>
        <button class="toggle-sw ${u.active ? 'on' : 'off'}" onclick="toggleUser(${u.id})"></button>
        <button class="btn-icon danger" title="Delete" onclick="deleteUser(${u.id})">🗑</button>
      </div></td>
    </tr>`;
  });
}

function postAction(fields) {
  const form = document.createElement('form');
  form.method = 'post';
  Object.entries(fields).forEach(([name, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}

function addUser() {
  const name = document.getElementById('n-name').value.trim();
  const email = document.getElementById('n-email').value.trim();
  const passInput = document.getElementById('n-pass');
  const pass = passInput.value;
  const addr = document.getElementById('n-addr').value.trim();

  if (!name || !email || !pass || !addr) {
    return alert('All fields required.');
  }

  users.push({
    id: nextId++,
    name,
    email,
    password: pass,
    address: addr,
    active: true,
    billCount: 0
  });

  renderUsers(users);

  ['n-name', 'n-email', 'n-pass', 'n-addr']
    .forEach(id => document.getElementById(id).value = '');

  resetPasswordField(passInput);
}

function filterUsers(q) {
  const filtered = users.filter(u =>
    u.name.toLowerCase().includes(q.toLowerCase()) ||
    u.email.toLowerCase().includes(q.toLowerCase()) ||
    (u.address || '').toLowerCase().includes(q.toLowerCase())
  );

  renderUsers(filtered);
}

function openEdit(id) {
  const u = users.find(x => x.id === id);

  document.getElementById('edit-id').value = id;
  document.getElementById('edit-name').value = u.name;
  document.getElementById('edit-email').value = u.email;
  document.getElementById('edit-addr').value = u.address;
  document.getElementById('edit-modal').classList.add('open');
}

function saveEdit() {
  const id = +document.getElementById('edit-id').value;
  postAction({
    action: 'edit_user',
    user_id: id,
    full_name: document.getElementById('edit-name').value,
    email: document.getElementById('edit-email').value,
    address: document.getElementById('edit-addr').value
  });
}

function openPw(id) {
  document.getElementById('pw-id').value = id;

  const newPasswordInput = document.getElementById('pw-new');
  const confirmPasswordInput = document.getElementById('pw-confirm');

  newPasswordInput.value = '';
  confirmPasswordInput.value = '';

  resetPasswordField(newPasswordInput);
  resetPasswordField(confirmPasswordInput);

  document.getElementById('pw-modal').classList.add('open');
}

function savePw() {
  const np = document.getElementById('pw-new').value;
  const cp = document.getElementById('pw-confirm').value;

  if (np.length < 6) {
    return alert('Password must be at least 6 characters.');
  }

  if (np !== cp) {
    return alert('Passwords do not match.');
  }

  alert('Password updated successfully!');
  postAction({
    action: 'change_password',
    user_id: document.getElementById('pw-id').value,
    password: np
  });
}

function toggleUser(id) {
  postAction({action: 'toggle_user', user_id: id});
}

function deleteUser(id) {
  if (!confirm('Delete this user?')) return;
  postAction({action: 'delete_user', user_id: id});
}

function closeModal(id) {
  const modal = document.getElementById(id);

  if (!modal) return;

  modal.querySelectorAll('.password-field input')
    .forEach(resetPasswordField);

  modal.classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', function (e) {
    if (e.target === this) closeModal(this.id);
  })
);

initPasswordToggles();
renderUsers(users);
</script>
