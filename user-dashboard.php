<?php require_once 'user-auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Water Billing Management System – My Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php
// Fetch user's bills
$bills = [];
$result = $conn->query("SELECT * FROM bills WHERE user_id = $user_id ORDER BY billing_year DESC, billing_month DESC");
while ($row = $result->fetch_assoc()) {
  $bills[] = $row;
}

$total_bills = count($bills);
$paid_bills = count(array_filter($bills, function($b) { return $b['status'] === 'paid'; }));
$unpaid_bills = $total_bills - $paid_bills;

// Prepare chart data
$chart_labels = [];
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
  $month = date('M', strtotime("-$i months"));
  $year = date('Y', strtotime("-$i months"));
  $month_num = date('n', strtotime("-$i months"));
  $usage = 0;
  foreach ($bills as $bill) {
    if ($bill['billing_year'] == $year && $bill['billing_month'] == $month_num) {
      $usage = $bill['usage_m3'];
      break;
    }
  }
  $chart_labels[] = $month;
  $chart_data[] = $usage;
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
  .bubble{position:absolute;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.3),rgba(34,168,212,0.06));border:1px solid rgba(255,255,255,0.12);animation:rise linear infinite;}
  .bubble:nth-child(1){width:6px;height:6px;left:8%;bottom:-10px;animation-duration:10s;}
  .bubble:nth-child(2){width:4px;height:4px;left:25%;bottom:-10px;animation-duration:12s;animation-delay:2s;}
  .bubble:nth-child(3){width:9px;height:9px;left:50%;bottom:-10px;animation-duration:9s;animation-delay:1s;}
  .bubble:nth-child(4){width:5px;height:5px;left:75%;bottom:-10px;animation-duration:14s;animation-delay:4s;}
  .bubble:nth-child(5){width:7px;height:7px;left:90%;bottom:-10px;animation-duration:8s;animation-delay:3s;}
  @keyframes rise{0%{transform:translateY(0) translateX(0);opacity:0;}10%{opacity:0.6;}90%{opacity:0.15;}100%{transform:translateY(-100vh) translateX(8px);opacity:0;}}
  .shimmer{position:absolute;width:1px;height:90px;background:linear-gradient(to bottom,transparent,rgba(0,229,255,0.28),transparent);animation:shimDrift ease-in-out infinite;}
  .shimmer:nth-child(6){left:20%;top:15%;animation-duration:7s;}
  .shimmer:nth-child(7){left:55%;top:30%;animation-duration:9s;animation-delay:2s;}
  .shimmer:nth-child(8){left:80%;top:8%;animation-duration:8s;animation-delay:1s;}
  @keyframes shimDrift{0%,100%{opacity:0.15;transform:translateY(0);}50%{opacity:0.55;transform:translateY(18px);}}
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

  .welcome-banner{background:linear-gradient(135deg,rgba(34,168,212,0.15),rgba(0,229,255,0.08));border:1px solid rgba(34,168,212,0.2);border-radius:16px;padding:22px 26px;margin-bottom:22px;display:flex;align-items:center;justify-content:space-between;}
  .wb-text h2{font-family:'Playfair Display',serif;font-size:1.4rem;color:white;margin-bottom:4px;}
  .wb-text p{font-size:0.84rem;color:var(--muted);}
  .wb-drop{width:56px;height:56px;background:linear-gradient(135deg,rgba(34,168,212,0.3),rgba(0,229,255,0.15));border:1px solid rgba(34,168,212,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;animation:dropFloat 3s ease-in-out infinite;}
  @keyframes dropFloat{0%,100%{transform:translateY(0);}50%{transform:translateY(-6px);}}

  .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;}
  .stat-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:20px;position:relative;overflow:hidden;}
  .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--aqua),var(--accent));}
  .stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;}
  .stat-icon svg{width:20px;height:20px;}
  .stat-icon.blue{background:rgba(34,168,212,0.15);color:var(--aqua);}
  .stat-icon.green{background:rgba(77,217,172,0.15);color:var(--success);}
  .stat-icon.red{background:rgba(255,107,107,0.15);color:var(--warn);}
  .stat-val{font-family:'Playfair Display',serif;font-size:1.7rem;color:white;font-weight:700;}
  .stat-label{font-size:0.75rem;color:var(--muted);margin-top:4px;}

  .charts-row{display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:22px;}
  .chart-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .chart-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:3px;}
  .chart-sub{font-size:0.75rem;color:var(--muted);margin-bottom:16px;}

  .latest-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .latest-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:14px;}
  .bill-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.05);}
  .bill-item:last-child{border-bottom:none;}
  .bill-month{font-size:0.85rem;font-weight:500;color:white;}
  .bill-usage{font-size:0.75rem;color:var(--muted);margin-top:2px;}
  .bill-right{text-align:right;}
  .bill-amount{font-size:0.9rem;font-weight:600;color:var(--aqua);}
  .badge-status{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:0.68rem;font-weight:500;margin-top:3px;}
  .badge-status.paid{background:rgba(77,217,172,0.12);color:var(--success);}
  .badge-status.paid::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--success);}
  .badge-status.unpaid{background:rgba(255,107,107,0.12);color:var(--warn);}
  .badge-status.unpaid::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--warn);}
</style>
</head>
<body>

<div class="water-bg">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div>
  <div class="shimmer"></div><div class="shimmer"></div><div class="shimmer"></div>
  <div class="waves"><div class="wave wave1"></div><div class="wave wave2"></div></div>
</div>

<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon"><svg viewBox="0 0 24 24"><path d="M12 2c-5.33 4-8 8-8 11a8 8 0 0 0 16 0c0-3-2.67-7-8-11z"/></svg></div>
    <div class="sb-brand">Water<span> Billing</span></div>
  </div>
  <div class="sb-section">
    <div class="sb-section-label">Menu</div>
    <a class="sb-item active" href="user-dashboard.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>My Dashboard
    </a>
    <a class="sb-item" href="user-bills.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>My Bills
    </a>
    <a class="sb-item" href="profile.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Profile
    </a>
  </div>
  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar"><?php echo htmlspecialchars($user_initial); ?></div>
      <div><div class="sb-uname"><?php echo htmlspecialchars($user_name); ?></div><div class="sb-role">Account Holder</div></div>
    </div>
    <a class="logout-btn" href="logout.php">⇠ Sign Out</a>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div class="topbar-title">My Dashboard</div>
    <div class="topbar-sub" id="topbar-date"></div>
  </div>
  <div class="content">

    <div class="welcome-banner">
      <div class="wb-text">
        <h2>Good day, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
        <p>Here's a summary of your water billing account.</p>
      </div>
      <div class="wb-drop">💧</div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2z"/></svg></div>
        <div class="stat-val"><?php echo $total_bills; ?></div>
        <div class="stat-label">Total Bills</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></div>
        <div class="stat-val"><?php echo $paid_bills; ?></div>
        <div class="stat-label">Paid Bills</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>
        <div class="stat-val"><?php echo $unpaid_bills; ?></div>
        <div class="stat-label">Unpaid Bills</div>
      </div>
    </div>

    <div class="charts-row">
      <div class="chart-card">
        <div class="chart-title">Monthly Water Usage</div>
        <div class="chart-sub">Your consumption trend over 6 months (m³)</div>
        <div style="height:210px;position:relative;">
          <canvas id="usageChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Latest Bills</div>
        <div class="chart-sub">Most recent billing records</div>
        <div id="latest-list">
          <?php
          $latest_bills = array_slice($bills, 0, 4); // Show latest 4
          if (empty($latest_bills)) {
            echo '<p style="color: rgba(255,255,255,0.5); text-align: center; padding: 20px;">No bills yet.</p>';
          } else {
            foreach ($latest_bills as $bill) {
              $month_name = date('F', mktime(0, 0, 0, $bill['billing_month'], 1));
              $status = $bill['status'];
              echo '<div class="bill-item">
                <div><div class="bill-month">' . $month_name . ' ' . $bill['billing_year'] . '</div><div class="bill-usage">' . $bill['usage_m3'] . ' m³ used</div></div>
                <div class="bill-right">
                  <div class="bill-amount">₱' . number_format($bill['amount'], 2) . '</div>
                  <div><span class="badge-status ' . $status . '">' . ucfirst($status) . '</span></div>
                </div>
              </div>';
            }
          }
          ?>
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

document.getElementById('topbar-date').textContent = new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

// Usage Chart
const ctx = document.getElementById('usageChart').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($chart_labels); ?>,
    datasets: [{
      label: 'Usage (m³)',
      data: <?php echo json_encode($chart_data); ?>,
      borderColor: '#22a8d4',
      backgroundColor: 'rgba(34,168,212,0.12)',
      borderWidth: 2.5,
      pointBackgroundColor: '#22a8d4',
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
      pointRadius: 5,
      tension: 0.4,
      fill: true,
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'rgba(255,255,255,0.45)',font:{size:11}} },
      y:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'rgba(255,255,255,0.45)',font:{size:11}}, beginAtZero:true }
    }
  }
});

// Latest Bills - now handled in PHP

</script>
</body>
</html>
