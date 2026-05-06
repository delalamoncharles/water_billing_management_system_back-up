<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Water Billing Management System – Manage Bills</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<?php
require_once 'admin-auth.php';

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

// Keep barangay synced from address format: Street, Barangay, City/Municipality.
$conn->query("
  UPDATE users
  SET barangay = TRIM(
    CASE
      WHEN address LIKE '%,%' THEN SUBSTRING_INDEX(SUBSTRING_INDEX(address, ',', 2), ',', -1)
      ELSE SUBSTRING_INDEX(address, ',', 1)
    END
  )
  WHERE role = 'user'
    AND NULLIF(address, '') IS NOT NULL
");

$conn->query("
  UPDATE bills b
  JOIN users u ON u.id = b.user_id
  SET b.barangay = u.barangay
  WHERE NULLIF(u.barangay, '') IS NOT NULL
");

// Fetch active user accounts only
$users = [];
$result = $conn->query("
  SELECT
    id,
    full_name,
    email,
    COALESCE(NULLIF(barangay, ''), 'Not set') AS barangay
  FROM users
  WHERE role = 'user' AND is_active = 1
  ORDER BY full_name
");
while ($row = $result->fetch_assoc()) {
  $users[] = $row;
}

$barangays = array_values(array_unique(array_filter(array_map(function($user) {
  return trim($user['barangay'] ?? '');
}, $users), function($barangay) {
  return $barangay !== '' && strtolower($barangay) !== 'not set';
})));
sort($barangays, SORT_NATURAL | SORT_FLAG_CASE);

// Fetch billing records for user accounts only
$bills = [];
$result = $conn->query("
  SELECT
    b.id,
    b.user_id,
    COALESCE(NULLIF(u.barangay, ''), NULLIF(b.barangay, ''), 'Not set') AS barangay,
    b.billing_month,
    b.billing_year,
    b.usage_m3,
    b.amount,
    b.status,
    b.created_at,
    u.full_name
  FROM bills b
  JOIN users u ON b.user_id = u.id
  WHERE u.role = 'user'
  ORDER BY b.created_at DESC
");
while ($row = $result->fetch_assoc()) {
  $bills[] = $row;
}

// Handle POST for adding bill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bill'])) {

  $user_id = (int)$_POST['user_id'];
  $month = (int)$_POST['month'];
  $year = (int)$_POST['year'];
  $usage = (float)$_POST['usage'];

  $amount = max($usage * 20, 100);

  // GET USER + BARANGAY
  $stmt = $conn->prepare("
    SELECT barangay 
    FROM users 
    WHERE id = ? AND role = 'user' AND is_active = 1
  ");

  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if (!$user) {
    die("User does not exist or is inactive.");
  }

  $barangay = $user['barangay'];

  // INSERT BILL INCLUDING BARANGAY
  $stmt = $conn->prepare("
    INSERT INTO bills (user_id, barangay, billing_month, billing_year, usage_m3, amount)
    VALUES (?, ?, ?, ?, ?, ?)
  ");

  $stmt->bind_param("isiiid", $user_id, $barangay, $month, $year, $usage, $amount);

  $stmt->execute();

  header('Location: admin-bills.php');
  exit;
}

// Handle POST for editing an existing bill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bill'])) {
  $bill_id = (int)$_POST['bill_id'];
  $month = (int)$_POST['month'];
  $year = (int)$_POST['year'];
  $usage = (float)$_POST['usage'];
  $status = $_POST['status'] === 'paid' ? 'paid' : 'unpaid';
  $amount = max($usage * 20, 100);

  if ($bill_id <= 0 || $month < 1 || $month > 12 || $year < 2020 || $year > 2099 || $usage < 0) {
    die("Invalid bill details.");
  }

  $stmt = $conn->prepare("
    UPDATE bills b
    JOIN users u ON b.user_id = u.id
    SET b.billing_month = ?,
        b.billing_year = ?,
        b.usage_m3 = ?,
        b.amount = ?,
        b.status = ?,
        b.paid_at = CASE WHEN ? = 'paid' THEN COALESCE(b.paid_at, NOW()) ELSE NULL END
    WHERE b.id = ? AND u.role = 'user'
  ");
  $stmt->bind_param("iiddssi", $month, $year, $usage, $amount, $status, $status, $bill_id);
  if (!$stmt->execute()) {
    die("Unable to add bill: " . $stmt->error);
  }

  header('Location: admin-bills.php');
  exit;
}

$billData = [];
foreach ($bills as $bill) {
  $billData[(int)$bill['id']] = [
    'id' => (int)$bill['id'],
    'user' => $bill['full_name'],
    'month' => (int)$bill['billing_month'],
    'monthName' => date('F', mktime(0, 0, 0, (int)$bill['billing_month'], 1)),
    'year' => (int)$bill['billing_year'],
    'usage' => (float)$bill['usage_m3'],
    'amount' => (float)$bill['amount'],
    'status' => $bill['status']
  ];
}

$conn->close();
?>
<style>
  :root {
    --deep:#0a1628;--aqua:#22a8d4;--foam:#7dd4ef;--accent:#00e5ff;
    --warn:#ff6b6b;--success:#4dd9ac;--sidebar-w:240px;
    --card-bg:rgba(13,23,43,0.95);--glass:rgba(255,255,255,0.04);
    --border:rgba(34,168,212,0.18);--text:rgba(255,255,255,0.85);--muted:rgba(255,255,255,0.4);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'DM Sans',sans-serif;background:var(--deep);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden;}
  .water-bg{position:fixed;inset:0;z-index:0;background:linear-gradient(180deg,#040e1e 0%,#0a1f3d 50%,#0d3a5c 100%);overflow:hidden;}
  .bubble{position:absolute;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.25),transparent);border:1px solid rgba(255,255,255,0.1);animation:rise linear infinite;}
  .bubble:nth-child(1){width:5px;height:5px;left:10%;bottom:-10px;animation-duration:11s;}
  .bubble:nth-child(2){width:7px;height:7px;left:35%;bottom:-10px;animation-duration:9s;animation-delay:3s;}
  .bubble:nth-child(3){width:4px;height:4px;left:65%;bottom:-10px;animation-duration:13s;animation-delay:1s;}
  .bubble:nth-child(4){width:8px;height:8px;left:85%;bottom:-10px;animation-duration:8s;animation-delay:4s;}
  @keyframes rise{0%{transform:translateY(0);opacity:0;}10%{opacity:0.5;}90%{opacity:0.1;}100%{transform:translateY(-100vh);opacity:0;}}
  .waves{position:absolute;bottom:0;left:0;width:100%;}
  .wave{position:absolute;bottom:0;left:0;width:200%;border-radius:40% 60% 40% 60%/30% 30% 70% 70%;}
  .wave1{background:rgba(13,47,94,0.4);height:100px;animation:waveAnim 10s ease-in-out infinite;}
  .wave2{background:rgba(34,168,212,0.06);height:70px;animation:waveAnim 14s ease-in-out infinite reverse;}
  @keyframes waveAnim{0%,100%{transform:translateX(-25%);}50%{transform:translateX(0%);}}

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

  .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;position:relative;z-index:10;}
  .topbar{position:sticky;top:0;z-index:50;height:62px;background:rgba(8,16,32,0.9);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);display:flex;align-items:center;justify-content:space-between;padding:0 28px;}
  .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:white;}
  .content{padding:28px;flex:1;}

  .rate-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;gap:16px;font-size:0.82rem;}
  .rate-bar strong{color:var(--aqua);}
  .rate-bar span{color:var(--muted);}

  .add-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;margin-bottom:20px;}
  .add-card-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:16px;}
  .form-grid{display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr 1fr auto;gap:12px;align-items:end;}
  .f-group label{display:block;font-size:0.7rem;letter-spacing:0.5px;color:var(--muted);margin-bottom:5px;}
  .f-group input,.f-group select{width:100%;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:10px 12px;color:white;font-family:'DM Sans',sans-serif;font-size:0.84rem;outline:none;transition:border-color 0.2s;}
  .f-group select option{background:#0d2f5e;}
  .f-group input::placeholder{color:rgba(255,255,255,0.25);}
  .f-group input:focus,.f-group select:focus{border-color:var(--aqua);}
  .computed-amt{background:rgba(34,168,212,0.1);border:1px solid rgba(34,168,212,0.2);border-radius:9px;padding:10px 12px;color:var(--aqua);font-weight:600;font-size:0.9rem;min-width:110px;}
  .btn-primary{padding:10px 20px;background:linear-gradient(135deg,var(--aqua),var(--accent));border:none;border-radius:9px;color:white;font-family:'DM Sans',sans-serif;font-size:0.875rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:all 0.2s;box-shadow:0 4px 14px rgba(34,168,212,0.3);}
  .btn-primary:hover{box-shadow:0 6px 20px rgba(34,168,212,0.45);}

  .table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .table-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap;}
  .table-title{font-size:0.9rem;font-weight:600;color:white;}
  .filters{display:flex;gap:8px;align-items:center;}
  .filter-input{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:8px 12px;color:white;font-family:'DM Sans',sans-serif;font-size:0.82rem;outline:none;transition:border-color 0.2s;}
  select.filter-input option{background:#0d2f5e;color:white;}
  select.filter-input option:checked{background:#22a8d4;color:#071022;}
  .filter-input::placeholder{color:rgba(255,255,255,0.25);}
  .filter-input:focus{border-color:var(--aqua);}
  table{width:100%;border-collapse:collapse;}
  th{font-size:0.67rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);}
  td{padding:11px 10px;font-size:0.83rem;color:var(--text);border-bottom:1px solid rgba(255,255,255,0.04);}
  tr:last-child td{border-bottom:none;}
  tr:hover td{background:rgba(255,255,255,0.02);}
  .badge-status{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:0.7rem;font-weight:500;}
  .badge-status.paid{background:rgba(77,217,172,0.12);color:var(--success);}
  .badge-status.paid::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--success);}
  .badge-status.unpaid{background:rgba(255,107,107,0.12);color:var(--warn);}
  .badge-status.unpaid::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--warn);}
  .action-btns{display:flex;gap:5px;}
  .btn-icon{width:28px;height:28px;border:1px solid var(--border);border-radius:6px;background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:all 0.15s;}
  .btn-icon:hover{border-color:var(--aqua);color:var(--aqua);}
  .btn-icon.danger:hover{border-color:var(--warn);color:var(--warn);}
  .btn-icon.green:hover{border-color:var(--success);color:var(--success);}
  .btn-icon svg{width:13px;height:13px;}
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
  .modal-overlay.open{display:flex;}
  .modal{background:rgba(13,23,43,0.98);border:1px solid var(--border);border-radius:18px;padding:28px;width:420px;max-width:90vw;}
  .modal-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:white;margin-bottom:20px;}
  .modal-field{margin-bottom:14px;}
  .modal-field label{display:block;font-size:0.7rem;letter-spacing:0.5px;color:var(--muted);margin-bottom:5px;}
  .modal-field input,.modal-field select{width:100%;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:10px 12px;color:white;font-family:'DM Sans',sans-serif;font-size:0.84rem;outline:none;transition:border-color 0.2s;}
  .modal-field select option{background:#0d2f5e;}
  .modal-field input:focus,.modal-field select:focus{border-color:var(--aqua);}
  .modal-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .modal-btns{display:flex;gap:10px;margin-top:20px;}
  .btn-cancel{flex:1;padding:10px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:0.875rem;cursor:pointer;}
  .btn-save{flex:1;padding:10px;background:linear-gradient(135deg,var(--aqua),var(--accent));border:none;border-radius:9px;color:white;font-family:'DM Sans',sans-serif;font-size:0.875rem;font-weight:600;cursor:pointer;}
</style>
</head>
<body>

<div class="water-bg">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="waves"><div class="wave wave1"></div><div class="wave wave2"></div></div>
</div>

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
    <a class="sb-item" href="admin-users.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>Manage Users
    </a>
    <a class="sb-item active" href="admin-bills.php">
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

<main class="main">
  <div class="topbar">
    <div class="topbar-title">Manage Bills</div>
  </div>
  <div class="content">

    <div class="rate-bar">
      💧 Current Rate: <strong>₱20.00 per m³</strong>
      <span>|</span>
      <span>Minimum charge (0–5 m³): <strong style="color:var(--foam)">₱100.00</strong></span>
      <span>|</span>
      <span>Amount = Usage × ₱20.00 (min ₱100)</span>
    </div>

    <!-- ADD BILL -->
    <div class="add-card">
      <div class="add-card-title">➕ Add New Bill</div>
      <form method="post">
        <input type="hidden" name="add_bill" value="1">
        <div class="form-grid">
          <div class="f-group">
            <label>BARANGAY</label>
            <select id="b-barangay" onchange="filterUsersByBarangay()" required>
              <option value="">-- Select Barangay --</option>
              <?php foreach ($barangays as $barangay): ?>
                <option value="<?php echo htmlspecialchars($barangay, ENT_QUOTES); ?>"><?php echo htmlspecialchars($barangay); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="f-group">
            <label>SELECT USER</label>
            <select name="user_id" id="b-user" onchange="updateBillBarangay()" required disabled>
              <option value="">-- Select Barangay First --</option>
              <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['id']; ?>" data-barangay="<?php echo htmlspecialchars($user['barangay'], ENT_QUOTES); ?>">
                  <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="f-group">
            <label>MONTH</label>
            <select name="month" required>
              <option value="1">January</option><option value="2">February</option><option value="3">March</option>
              <option value="4">April</option><option value="5">May</option><option value="6">June</option>
              <option value="7">July</option><option value="8">August</option><option value="9">September</option>
              <option value="10">October</option><option value="11">November</option><option value="12">December</option>
            </select>
          </div>
          <div class="f-group">
            <label>YEAR</label>
            <input type="number" name="year" value="2025" min="2020" max="2099" required>
          </div>
          <div class="f-group">
            <label>USAGE (m³)</label>
            <input type="number" name="usage" placeholder="0" min="0" step="0.1" required>
          </div>
          <div><button type="submit" class="btn-primary">Add Bill</button></div>
        </div>
      </form>
    </div>

    <!-- TABLE -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">All Billing Records</div>
        <div class="filters">
          <input class="filter-input" type="text" placeholder="Search user…" id="f-user" oninput="filterBills()">
          <select class="filter-input" id="f-status" onchange="filterBills()">
            <option value="">All Status</option>
            <option value="paid">Paid</option>
            <option value="unpaid">Unpaid</option>
          </select>
          <select class="filter-input" id="f-month" onchange="filterBills()">
            <option value="">All Months</option>
            <option>January</option><option>February</option><option>March</option>
            <option>April</option><option>May</option><option>June</option>
            <option>July</option><option>August</option><option>September</option>
            <option>October</option><option>November</option><option>December</option>
          </select>
        </div>
      </div>
      <table>
  <thead>
  <tr>
    <th>User ID</th>
    <th>Username</th>
    <th>Barangay</th>
    <th>Bill ID</th>
    <th>Billing Month</th>
    <th>Usage (m³)</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Actions</th>
  </tr>
</thead>
        <tbody id="bills-tbody">
          <?php foreach ($bills as $bill): ?>
            <tr data-user="<?php echo htmlspecialchars(strtolower($bill['full_name'] . ' ' . $bill['barangay'] . ' ' . $bill['id']), ENT_QUOTES); ?>"
                data-status="<?php echo htmlspecialchars($bill['status'], ENT_QUOTES); ?>"
                data-month="<?php echo date('F', mktime(0, 0, 0, $bill['billing_month'], 1)); ?>">
              <td><?php echo $bill['user_id']; ?></td>
              <td><?php echo htmlspecialchars($bill['full_name']); ?></td>
              <td><?php echo htmlspecialchars($bill['barangay']); ?></td>
              <td><?php echo $bill['id']; ?></td>
              <td><?php echo date('F Y', mktime(0, 0, 0, $bill['billing_month'], 1, $bill['billing_year'])); ?></td>
              <td><?php echo $bill['usage_m3']; ?> m³</td>
              <td>₱<?php echo number_format($bill['amount'], 2); ?></td>
              <td><span class="badge-status <?php echo $bill['status']; ?>"><?php echo ucfirst($bill['status']); ?></span></td>
              <td class="action-btns">
                <button type="button" class="btn-icon" onclick="editBill(<?php echo $bill['id']; ?>)" title="Edit bill"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>
                <div class="btn-icon danger" onclick="deleteBill(<?php echo $bill['id']; ?>)"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal">
    <div class="modal-title">Edit Bill</div>
    <input type="hidden" id="edit-bid">
    <div class="modal-row">
      <div class="modal-field"><label>MONTH</label>
        <select id="e-month">
          <option>January</option><option>February</option><option>March</option>
          <option>April</option><option>May</option><option>June</option>
          <option>July</option><option>August</option><option>September</option>
          <option>October</option><option>November</option><option>December</option>
        </select>
      </div>
      <div class="modal-field"><label>YEAR</label><input type="number" id="e-year"></div>
    </div>
    <div class="modal-row">
      <div class="modal-field"><label>USAGE (m³)</label><input type="number" id="e-usage" oninput="calcEditAmount()"></div>
      <div class="modal-field"><label>AMOUNT</label><input type="text" id="e-amount" readonly style="color:var(--aqua);"></div>
    </div>
    <div class="modal-field"><label>STATUS</label>
      <select id="e-status"><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal('edit-modal')">Cancel</button>
      <button class="btn-save" onclick="saveEdit()">Save Changes</button>
    </div>
  </div>
</div>

<script>
window.addEventListener('pageshow', function(event) {
  if (event.persisted) {
    window.location.reload();
  }
});

const RATE = 20; const MIN_CHARGE = 100;
function compute(usage) { return Math.max(usage * RATE, MIN_CHARGE); }

let bills = <?php echo json_encode(array_values($billData)); ?>;
const userSelectPlaceholder = '-- Select User --';

function calcAmount() {
  const u = parseFloat(document.getElementById('b-usage').value)||0;
  document.getElementById('b-amount').textContent = '₱'+compute(u).toFixed(2);
}
function calcEditAmount() {
  const u = parseFloat(document.getElementById('e-usage').value)||0;
  document.getElementById('e-amount').value = '₱'+compute(u).toFixed(2);
}

function updateBillBarangay() {
  const select = document.getElementById('b-user');
  const barangay = select.options[select.selectedIndex]?.dataset.barangay || '';
  const barangaySelect = document.getElementById('b-barangay');
  if (barangay && barangaySelect.value !== barangay) {
    barangaySelect.value = barangay;
    filterUsersByBarangay(false);
    select.value = [...select.options].find(option => option.dataset.barangay === barangay && option.value === select.value)?.value || '';
  }
}

function filterUsersByBarangay(resetUser = true) {
  const barangay = document.getElementById('b-barangay').value;
  const userSelect = document.getElementById('b-user');
  let hasVisibleUsers = false;

  userSelect.disabled = !barangay;
  userSelect.options[0].textContent = barangay ? userSelectPlaceholder : '-- Select Barangay First --';

  [...userSelect.options].forEach((option, index) => {
    if (index === 0) return;
    const matches = option.dataset.barangay === barangay;
    option.hidden = !matches;
    option.disabled = !matches;
    hasVisibleUsers = hasVisibleUsers || matches;
  });

  if (resetUser) {
    userSelect.value = '';
  }

  if (barangay && !hasVisibleUsers) {
    userSelect.options[0].textContent = 'No users in this barangay';
  }
}

function renderBills(list) {
  const tb = document.getElementById('bills-tbody');
  tb.innerHTML = '';
  list.forEach(b=>{
    tb.innerHTML += `<tr>
      <td>${b.uid}</td><td>${b.user}</td><td>${b.id}</td>
      <td>${b.month} ${b.year}</td><td>${b.usage} m³</td>
      <td>₱${b.amount.toFixed(2)}</td>
      <td>
        <span class="badge-status ${b.status}" style="cursor:pointer" onclick="toggleStatus(${b.id})" title="Click to toggle">
          ${b.status.charAt(0).toUpperCase()+b.status.slice(1)}
        </span>
      </td>
      <td><div class="action-btns">
        <button class="btn-icon" onclick="openEdit(${b.id})" title="Edit">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
        </button>
        <button class="btn-icon green" onclick="toggleStatus(${b.id})" title="Mark Paid/Unpaid">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </button>
        <button class="btn-icon danger" onclick="deleteBill(${b.id})" title="Delete">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
        </button>
      </div></td>
    </tr>`;
  });
}

function filterBills() {
  const u = document.getElementById('f-user').value.toLowerCase();
  const s = document.getElementById('f-status').value;
  const m = document.getElementById('f-month').value;
  document.querySelectorAll('#bills-tbody tr').forEach(row => {
    const matches =
      (!u || row.dataset.user.includes(u)) &&
      (!s || row.dataset.status === s) &&
      (!m || row.dataset.month === m);
    row.style.display = matches ? '' : 'none';
  });
}

function addBill() {
  const uid = +document.getElementById('b-user').value;
  const month = document.getElementById('b-month').value;
  const year = +document.getElementById('b-year').value;
  const usage = parseFloat(document.getElementById('b-usage').value)||0;
  if (!uid) return alert('Please select a user.');
  if (!usage) return alert('Please enter usage.');
  const amount = compute(usage);
  bills.unshift({id:nextId++,uid,user:userNames[uid],month,year,usage,amount,status:'unpaid'});
  renderBills(bills);
  document.getElementById('b-usage').value='';
  document.getElementById('b-amount').textContent='₱0.00';
}

function toggleStatus(id) {
  const b = bills.find(x=>x.id===id);
  b.status = b.status==='paid'?'unpaid':'paid';
  filterBills();
}

function deleteBill(id) {
  if (!confirm('Delete this bill?')) return;
  bills = bills.filter(x=>x.id!==id);
  filterBills();
}

function openEdit(id) {
  const b = bills.find(x=>x.id===id);
  if (!b) return alert('Bill not found.');
  document.getElementById('edit-bid').value=id;
  document.getElementById('e-month').selectedIndex=b.month - 1;
  document.getElementById('e-year').value=b.year;
  document.getElementById('e-usage').value=b.usage;
  document.getElementById('e-amount').value='₱'+b.amount.toFixed(2);
  document.getElementById('e-status').value=b.status;
  document.getElementById('edit-modal').classList.add('open');
}
function editBill(id) { openEdit(id); }
function saveEdit() {
  const id=+document.getElementById('edit-bid').value;
  const form = document.createElement('form');
  form.method = 'post';
  const fields = {
    edit_bill: '1',
    bill_id: id,
    month: document.getElementById('e-month').selectedIndex + 1,
    year: document.getElementById('e-year').value,
    usage: document.getElementById('e-usage').value,
    status: document.getElementById('e-status').value
  };
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
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');}));

</script>
</body>
</html>
