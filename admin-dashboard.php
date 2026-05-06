<?php require_once 'admin-auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Water Billing – Admin Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  :root {
    --deep:    #0a1628;
    --ocean:   #0d2f5e;
    --tide:    #1a5f7a;
    --aqua:    #22a8d4;
    --foam:    #7dd4ef;
    --accent:  #00e5ff;
    --pearl:   #e8f4f8;
    --white:   #ffffff;
    --warn:    #ff6b6b;
    --success: #4dd9ac;
    --sidebar-w: 240px;
    --header-h: 62px;
    --card-bg: rgba(13,23,43,0.95);
    --glass:   rgba(255,255,255,0.04);
    --border:  rgba(34,168,212,0.18);
    --text:    rgba(255,255,255,0.85);
    --muted:   rgba(255,255,255,0.4);
  }
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'DM Sans',sans-serif; background:var(--deep); color:var(--text); min-height:100vh; display:flex; overflow:hidden; }

  /* ===== ANIMATED WATER BG ===== */
  .water-bg {
    position:fixed; inset:0; z-index:0;
    background:linear-gradient(180deg,#040e1e 0%,#0a1f3d 50%,#0d3a5c 100%);
    overflow:hidden;
  }
  .bubble { position:absolute; border-radius:50%; background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.3),rgba(34,168,212,0.05)); border:1px solid rgba(255,255,255,0.12); animation:rise linear infinite; }
  .bubble:nth-child(1){width:5px;height:5px;left:5%;bottom:-10px;animation-duration:12s;animation-delay:0s;}
  .bubble:nth-child(2){width:8px;height:8px;left:20%;bottom:-10px;animation-duration:9s;animation-delay:3s;}
  .bubble:nth-child(3){width:4px;height:4px;left:35%;bottom:-10px;animation-duration:14s;animation-delay:1s;}
  .bubble:nth-child(4){width:7px;height:7px;left:55%;bottom:-10px;animation-duration:11s;animation-delay:5s;}
  .bubble:nth-child(5){width:5px;height:5px;left:72%;bottom:-10px;animation-duration:10s;animation-delay:2s;}
  .bubble:nth-child(6){width:9px;height:9px;left:88%;bottom:-10px;animation-duration:8s;animation-delay:4s;}
  @keyframes rise{0%{transform:translateY(0) translateX(0);opacity:0;}10%{opacity:0.5;}90%{opacity:0.2;}100%{transform:translateY(-100vh) translateX(10px);opacity:0;}}
  .shimmer{position:absolute;width:1px;height:80px;background:linear-gradient(to bottom,transparent,rgba(0,229,255,0.25),transparent);animation:shimDrift ease-in-out infinite;}
  .shimmer:nth-child(7){left:12%;top:20%;animation-duration:7s;animation-delay:0s;}
  .shimmer:nth-child(8){left:60%;top:10%;animation-duration:9s;animation-delay:2s;}
  .shimmer:nth-child(9){left:85%;top:45%;animation-duration:8s;animation-delay:1s;}
  @keyframes shimDrift{0%,100%{opacity:0.15;transform:translateY(0);}50%{opacity:0.5;transform:translateY(15px);}}
  .waves{position:absolute;bottom:0;left:0;width:100%;}
  .wave{position:absolute;bottom:0;left:0;width:200%;border-radius:40% 60% 40% 60%/30% 30% 70% 70%;}
  .wave1{background:rgba(13,47,94,0.45);height:120px;animation:waveAnim 10s ease-in-out infinite;}
  .wave2{background:rgba(34,168,212,0.08);height:80px;animation:waveAnim 14s ease-in-out infinite reverse;}
  @keyframes waveAnim{0%,100%{transform:translateX(-25%);}50%{transform:translateX(0%);}}

  /* ===== SIDEBAR ===== */
  .sidebar {
    position:fixed; top:0; left:0; bottom:0;
    width:var(--sidebar-w); z-index:100;
    background:rgba(8,16,32,0.96);
    border-right:1px solid var(--border);
    backdrop-filter:blur(20px);
    display:flex; flex-direction:column;
    padding: 0 0 20px;
  }
  .sb-logo {
    padding:20px 20px 16px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
  }
  .sb-logo-icon {
    width:38px;height:38px;
    background:linear-gradient(135deg,var(--aqua),var(--accent));
    border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 16px rgba(34,168,212,0.35);
    flex-shrink:0;
  }
  .sb-logo-icon svg{width:20px;height:20px;fill:white;}
  .sb-brand { font-family:'Playfair Display',serif; font-size:1.15rem; color:white; }
  .sb-brand span { color:var(--aqua); }

  .sb-section { padding:16px 12px 4px; }
  .sb-section-label { font-size:0.65rem; letter-spacing:1.5px; text-transform:uppercase; color:var(--muted); padding:0 8px; margin-bottom:6px; }

  .sb-item {
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;
    border-radius:10px;
    color:var(--muted);
    font-size:0.875rem;font-weight:500;
    cursor:pointer;
    transition:all 0.18s;
    text-decoration:none;
    margin-bottom:2px;
  }
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

  /* ===== MAIN ===== */
  .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;position:relative;z-index:10;}

  .topbar{
    position:sticky;top:0;z-index:50;
    height:var(--header-h);
    background:rgba(8,16,32,0.9);
    border-bottom:1px solid var(--border);
    backdrop-filter:blur(16px);
    display:flex;align-items:center;justify-content:space-between;
    padding:0 28px;
  }
  .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:white;}
  .topbar-right{display:flex;align-items:center;gap:12px;}
  .topbar-date{font-size:0.78rem;color:var(--muted);}
  .notif-btn{width:34px;height:34px;border-radius:9px;background:var(--glass);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:color 0.2s;}
  .notif-btn:hover{color:var(--aqua);}
  .notif-btn svg{width:18px;height:18px;}

  .content{padding:28px;flex:1;}

  /* ===== STATS CARDS ===== */
  .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
  .stat-card{
    background:var(--card-bg);border:1px solid var(--border);
    border-radius:16px;padding:20px;
    position:relative;overflow:hidden;
    transition:transform 0.2s,box-shadow 0.2s;
  }
  .stat-card:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,0.3);}
  .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--aqua),var(--accent));}
  .stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;}
  .stat-icon svg{width:20px;height:20px;}
  .stat-icon.blue{background:rgba(34,168,212,0.15);color:var(--aqua);}
  .stat-icon.green{background:rgba(77,217,172,0.15);color:var(--success);}
  .stat-icon.red{background:rgba(255,107,107,0.15);color:var(--warn);}
  .stat-icon.teal{background:rgba(0,229,255,0.12);color:var(--accent);}
  .stat-val{font-family:'Playfair Display',serif;font-size:1.8rem;color:white;font-weight:700;}
  .stat-label{font-size:0.75rem;color:var(--muted);margin-top:4px;}
  .stat-badge{display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;padding:2px 8px;border-radius:20px;margin-top:8px;}
  .stat-badge.up{background:rgba(77,217,172,0.12);color:var(--success);}
  .stat-badge.dn{background:rgba(255,107,107,0.12);color:var(--warn);}

  /* ===== CHARTS ROW ===== */
  .charts-row{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px;}
  .chart-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .chart-card-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:4px;}
  .chart-card-sub{font-size:0.75rem;color:var(--muted);margin-bottom:18px;}
  .chart-wrap{position:relative;}

  /* ===== RECENT BILLS TABLE ===== */
  .table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .table-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
  .table-title{font-size:0.9rem;font-weight:600;color:white;}
  .btn-sm{padding:7px 14px;border-radius:8px;border:none;font-family:'DM Sans',sans-serif;font-size:0.78rem;font-weight:500;cursor:pointer;transition:all 0.18s;}
  .btn-aqua{background:linear-gradient(135deg,var(--aqua),var(--accent));color:white;box-shadow:0 3px 10px rgba(34,168,212,0.3);}
  .btn-aqua:hover{box-shadow:0 5px 16px rgba(34,168,212,0.45);}
  table{width:100%;border-collapse:collapse;}
  th{font-size:0.7rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);}
  td{padding:12px 12px;font-size:0.845rem;color:var(--text);border-bottom:1px solid rgba(255,255,255,0.04);}
  tr:last-child td{border-bottom:none;}
  tr:hover td{background:rgba(255,255,255,0.02);}
  .badge-status{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:500;}
  .badge-status.paid{background:rgba(77,217,172,0.12);color:var(--success);}
  .badge-status.paid::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--success);}
  .badge-status.unpaid{background:rgba(255,107,107,0.12);color:var(--warn);}
  .badge-status.unpaid::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--warn);}
  .action-btns{display:flex;gap:6px;}
  .btn-icon{width:28px;height:28px;border:1px solid var(--border);border-radius:6px;background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:all 0.15s;}
  .btn-icon:hover{border-color:var(--aqua);color:var(--aqua);}
  .btn-icon svg{width:13px;height:13px;}
</style>
</head>
<body>

<div class="water-bg">
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
  <div class="shimmer"></div><div class="shimmer"></div><div class="shimmer"></div>
  <div class="waves"><div class="wave wave1"></div><div class="wave wave2"></div></div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon">
      <svg viewBox="0 0 24 24"><path d="M12 2c-5.33 4-8 8-8 11a8 8 0 0 0 16 0c0-3-2.67-7-8-11z"/></svg>
    </div>
    <div class="sb-brand">Water<span> Billing</span></div>
  </div>

  <div class="sb-section">
    <div class="sb-section-label">Main</div>
    <a class="sb-item active" href="admin-dashboard.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      Dashboard
    </a>
  </div>

  <div class="sb-section">
    <div class="sb-section-label">Management</div>
    <a class="sb-item" href="admin-users.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      Manage Users
    </a>
    <a class="sb-item" href="admin-bills.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
      Manage Bills
    </a>
  </div>

  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar">A</div>
      <div>
        <div class="sb-uname">Administrator</div>
        <div class="sb-role">Super Admin</div>
      </div>
    </div>
    <a class="logout-btn" href="logout.php">⇠ Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div class="topbar-title">Dashboard Overview</div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbar-date"></div>
      <div class="notif-btn">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div>
        <div class="stat-val"></div>
        <div class="stat-label">Total Users</div>
        <div class="stat-badge up">↑ 12 this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg></div>
        <div class="stat-val"></div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-badge up">↑ 8.3% vs last month</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon teal"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg></div>
        <div class="stat-val"></div>
        <div class="stat-label">Total Bills Issued</div>
        <div class="stat-badge up">↑ 23 this month</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>
        <div class="stat-val">47</div>
        <div class="stat-label">Unpaid Bills</div>
        <div class="stat-badge dn">↑ 5 overdue</div>
      </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-row">
      <div class="chart-card">
        <div class="chart-card-title">Monthly Revenue Collection</div>
        <div class="chart-card-sub">Billing performance over the past 6 months</div>
        <div class="chart-wrap" style="height:220px;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Bill Status</div>
        <div class="chart-card-sub">Paid vs Unpaid distribution</div>
        <div class="chart-wrap" style="height:220px;">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>

    <!-- RECENT BILLS -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">Recent Billing Records</div>
        <a href="admin-bills.php" class="btn-sm btn-aqua">View All Bills</a>
      </div>
      <table>
        <thead>
          <tr>
            <th>User ID</th><th>Username</th><th>Bill ID</th>
            <th>Month/Year</th><th>Usage (m³)</th><th>Amount</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="bills-tbody"></tbody>
      </table>
    </div>

  </div>
</main>

<script>
window.addEventListener('pageshow', function(event) {
  if (event.persisted) {
    window.location.reload();
  }
});

// Date
document.getElementById('topbar-date').textContent = new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

// Revenue Chart
const rCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(rCtx, {
  type: 'bar',
  data: {
    labels: ['Nov','Dec','Jan','Feb','Mar','Apr'],
    datasets: [{
      label: 'Revenue (₱)',
      data: [32400,38900,29700,41200,44800,48320],
      backgroundColor: 'rgba(34,168,212,0.25)',
      borderColor: '#22a8d4',
      borderWidth: 2,
      borderRadius: 6,
      hoverBackgroundColor: 'rgba(0,229,255,0.4)'
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'rgba(255,255,255,0.45)',font:{size:11}} },
      y:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'rgba(255,255,255,0.45)',font:{size:11}, callback:v=>'₱'+v.toLocaleString()} }
    }
  }
});

// Status Chart
const sCtx = document.getElementById('statusChart').getContext('2d');
new Chart(sCtx, {
  type: 'doughnut',
  data: {
    labels: ['Paid','Unpaid'],
    datasets: [{
      data: [295,47],
      backgroundColor: ['rgba(77,217,172,0.7)','rgba(255,107,107,0.7)'],
      borderColor: ['#4dd9ac','#ff6b6b'],
      borderWidth: 2,
      hoverOffset: 6
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{
      legend:{ position:'bottom', labels:{color:'rgba(255,255,255,0.6)',font:{size:11},padding:14} }
    },
    cutout:'68%'
  }
});

</script>
</body>
</html>
