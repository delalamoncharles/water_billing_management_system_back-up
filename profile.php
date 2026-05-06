<?php require_once 'user-auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AquaBill – Profile Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<?php
$profilePhone = '';
$profileDefaults = [
  'username' => $user_username,
  'fullName' => $user_name,
  'email' => $user_email,
  'phone' => $profilePhone,
  'address' => $user_address,
  'barangay' => $user_barangay,
  'initial' => $user_initial,
];

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
  .bubble{position:absolute;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.3),rgba(34,168,212,0.06));border:1px solid rgba(255,255,255,0.12);animation:rise linear infinite;}
  .bubble:nth-child(1){width:6px;height:6px;left:8%;bottom:-10px;animation-duration:10s;}
  .bubble:nth-child(2){width:4px;height:4px;left:25%;bottom:-10px;animation-duration:12s;animation-delay:2s;}
  .bubble:nth-child(3){width:9px;height:9px;left:50%;bottom:-10px;animation-duration:9s;animation-delay:1s;}
  .bubble:nth-child(4){width:5px;height:5px;left:75%;bottom:-10px;animation-duration:14s;animation-delay:4s;}
  .bubble:nth-child(5){width:7px;height:7px;left:90%;bottom:-10px;animation-duration:8s;animation-delay:3s;}
  @keyframes rise{0%{transform:translateY(0) translateX(0);opacity:0;}10%{opacity:0.6;}90%{opacity:0.15;}100%{transform:translateY(-100vh) translateX(8px);opacity:0;}}
  .waves{position:absolute;bottom:0;left:0;width:100%;}
  .wave{position:absolute;bottom:0;left:0;width:200%;border-radius:40% 60% 40% 60%/30% 30% 70% 70%;}
  .wave1{background:rgba(13,47,94,0.42);height:110px;animation:waveAnim 10s ease-in-out infinite;}
  .wave2{background:rgba(34,168,212,0.07);height:75px;animation:waveAnim 13s ease-in-out infinite reverse;}
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
  .sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#22a8d4,#00e5ff);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;flex-shrink:0;}
  .sb-uname{font-size:0.82rem;font-weight:600;color:white;}
  .sb-role{font-size:0.7rem;color:var(--muted);}
  .logout-btn{display:block;margin-top:8px;padding:9px;text-align:center;background:rgba(255,107,107,0.1);border:1px solid rgba(255,107,107,0.2);border-radius:9px;color:#ff9f9f;font-size:0.8rem;cursor:pointer;transition:all 0.2s;text-decoration:none;}
  .logout-btn:hover{background:rgba(255,107,107,0.18);}

  .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;position:relative;z-index:10;}
  .topbar{position:sticky;top:0;z-index:50;height:62px;background:rgba(8,16,32,0.9);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);display:flex;align-items:center;justify-content:space-between;padding:0 28px;}
  .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:white;}
  .topbar-sub{font-size:0.78rem;color:var(--muted);}
  .content{padding:28px;flex:1;}

  .profile-grid{display:grid;grid-template-columns:1fr 2fr;gap:18px;}
  .card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .card h2{font-size:1.05rem;margin-bottom:14px;color:white;}
  .info-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
  .field{display:flex;flex-direction:column;margin-bottom:14px;}
  .field label{font-size:0.78rem;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.08em;}
  .field input,.field textarea{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:12px 14px;color:white;font-family:'DM Sans',sans-serif;font-size:0.92rem;outline:none;}
  .field textarea{min-height:110px;resize:vertical;}
  .field input:focus,.field textarea:focus{border-color:var(--aqua);}
  .avatar-card{text-align:center;}
  .avatar-preview{width:112px;height:112px;margin:0 auto 14px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:white;background:linear-gradient(135deg,rgba(34,168,212,0.22),rgba(0,229,255,0.16));border:1px solid rgba(255,255,255,0.12);overflow:hidden;}
  .avatar-preview img{width:100%;height:100%;object-fit:cover;}
  .upload-label{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:999px;border:1px solid rgba(255,255,255,0.12);color:var(--foam);background:rgba(255,255,255,0.04);cursor:pointer;transition:all 0.18s;}
  .upload-label:hover{background:rgba(255,255,255,0.08);border-color:var(--aqua);}
  .upload-label svg{width:18px;height:18px;}
  .upload-input{display:none;}
  .button-row{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
  .btn-primary{padding:12px 22px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--aqua),var(--accent));color:#071022;font-size:0.95rem;font-weight:700;cursor:pointer;transition:all 0.18s;}
  .btn-primary:hover{transform:translateY(-1px);}
  .btn-secondary{padding:12px 22px;border-radius:12px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.04);color:var(--foam);cursor:pointer;transition:all 0.18s;}
  .btn-secondary:hover{border-color:var(--aqua);color:white;}
  .settings-list{display:grid;gap:10px;}
  .setting-item{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-radius:14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);}
  .setting-label{display:flex;flex-direction:column;gap:4px;}
  .setting-label span:first-child{font-size:0.9rem;color:white;font-weight:600;}
  .setting-label span:last-child{font-size:0.78rem;color:var(--muted);}
  .toggle-switch{position:relative;width:44px;height:24px;border-radius:999px;background:rgba(255,255,255,0.12);cursor:pointer;}
  .toggle-switch::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:white;transition:all 0.18s;}
  .toggle-switch.active{background:linear-gradient(135deg,var(--aqua),var(--accent));}
  .toggle-switch.active::after{left:23px;}
  .note{font-size:0.82rem;color:var(--muted);margin-top:8px;}
</style>
</head>
<body>

<div class="water-bg">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div>
  <div class="waves"><div class="wave wave1"></div><div class="wave wave2"></div></div>
</div>

<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon"><svg viewBox="0 0 24 24"><path d="M12 2c-5.33 4-8 8-8 11a8 8 0 0 0 16 0c0-3-2.67-7-8-11z"/></svg></div>
    <div class="sb-brand">Water<span> Billing</span></div>
  </div>
  <div class="sb-section">
    <div class="sb-section-label">Menu</div>
    <a class="sb-item" href="user-dashboard.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>My Dashboard
    </a>
    <a class="sb-item" href="user-bills.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>My Bills
    </a>
    <a class="sb-item active" href="profile.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Profile
    </a>
  </div>
  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar" id="sidebarAvatar"><?php echo htmlspecialchars($user_initial); ?></div>
      <div><div class="sb-uname" id="sidebarUsername"><?php echo htmlspecialchars($user_name); ?></div><div class="sb-role">Account Holder</div></div>
    </div>
    <a class="logout-btn" href="logout.php">⇠ Sign Out</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">Profile Settings</div>
      <div class="topbar-sub">Review your account and service information.</div>
    </div>
    <div class="topbar-sub" id="profile-date"></div>
  </div>
  <div class="content">

    <div class="profile-grid" style="grid-template-columns:1fr;">
      <div class="card">
        <h2>Account Information</h2>
        <div class="info-row">
          <div class="field">
            <label>Username</label>
            <input type="text" id="inputUsername" value="<?php echo htmlspecialchars($user_username); ?>">
          </div>
          <div class="field">
            <label>Full Name</label>
            <input type="text" id="inputFullName" value="<?php echo htmlspecialchars($user_name); ?>">
          </div>
        </div>
        <div class="info-row">
          <div class="field">
            <label>Email Address</label>
            <input type="email" id="inputEmail" value="<?php echo htmlspecialchars($user_email); ?>">
          </div>
          <div class="field">
            <label>Phone</label>
            <input type="text" id="inputPhone" value="<?php echo htmlspecialchars($profilePhone); ?>" placeholder="Add your phone number">
          </div>
        </div>
        <div class="field">
          <label>Address</label>
          <textarea id="inputAddress"><?php echo htmlspecialchars($user_address); ?></textarea>
        </div>
          <div class="field">
            <label>Account Status</label>
            <input type="text" value="Active water billing account" readonly>
        </div>
        <div class="button-row">
          <button class="btn-primary" onclick="saveProfile()">Save Changes</button>
          <button class="btn-secondary" onclick="resetProfile()">Reset</button>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
window.addEventListener('pageshow', function(event) {
  if (event.persisted) {
    window.location.reload();
  }
});

document.getElementById('profile-date').textContent = new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
const sidebarAvatar = document.getElementById('sidebarAvatar');
const sidebarUsername = document.getElementById('sidebarUsername');
const profileDefaults = <?php echo json_encode($profileDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function getInitial(name) {
  const cleanName = name.trim();
  return cleanName ? cleanName.charAt(0).toUpperCase() : profileDefaults.initial;
}

function saveProfile() {
  const username = document.getElementById('inputUsername').value.trim();
  const fullname = document.getElementById('inputFullName').value.trim();
  const email = document.getElementById('inputEmail').value.trim();
  if (!username || !fullname || !email) {
    return alert('Please complete the required profile fields.');
  }
  sidebarUsername.textContent = fullname;
  sidebarAvatar.textContent = getInitial(fullname);
  alert('Profile saved successfully.');
}

function resetProfile() {
  document.getElementById('inputUsername').value = profileDefaults.username;
  document.getElementById('inputFullName').value = profileDefaults.fullName;
  document.getElementById('inputEmail').value = profileDefaults.email;
  document.getElementById('inputPhone').value = profileDefaults.phone;
  document.getElementById('inputAddress').value = profileDefaults.address;
  document.getElementById('inputBarangay').value = profileDefaults.barangay;
  sidebarUsername.textContent = profileDefaults.fullName;
  sidebarAvatar.innerHTML = profileDefaults.initial;
}
</script>
</body>
</html>
