<?php require_once 'user-auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AquaBill – My Bills</title>
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
$paid_bills = count(array_filter($bills, fn($b) => $b['status'] === 'paid'));
$unpaid_bills = $total_bills - $paid_bills;
$total_due = array_sum(array_column(array_filter($bills, fn($b) => $b['status'] === 'unpaid'), 'amount'));
$billing_years = array_values(array_unique(array_map(fn($b) => (int) $b['billing_year'], $bills)));
rsort($billing_years, SORT_NUMERIC);

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
  .bubble{position:absolute;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.28),transparent);border:1px solid rgba(255,255,255,0.1);animation:rise linear infinite;}
  .bubble:nth-child(1){width:5px;height:5px;left:12%;bottom:-10px;animation-duration:10s;}
  .bubble:nth-child(2){width:8px;height:8px;left:30%;bottom:-10px;animation-duration:13s;animation-delay:2s;}
  .bubble:nth-child(3){width:4px;height:4px;left:55%;bottom:-10px;animation-duration:9s;animation-delay:1s;}
  .bubble:nth-child(4){width:7px;height:7px;left:78%;bottom:-10px;animation-duration:11s;animation-delay:4s;}
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
  .sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#22a8d4,#00e5ff);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;flex-shrink:0;}
  .sb-uname{font-size:0.82rem;font-weight:600;color:white;}
  .sb-role{font-size:0.7rem;color:var(--muted);}
  .logout-btn{display:block;margin-top:8px;padding:9px;text-align:center;background:rgba(255,107,107,0.1);border:1px solid rgba(255,107,107,0.2);border-radius:9px;color:#ff9f9f;font-size:0.8rem;cursor:pointer;transition:all 0.2s;text-decoration:none;}
  .logout-btn:hover{background:rgba(255,107,107,0.18);}

  .main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;position:relative;z-index:10;}
  .topbar{position:sticky;top:0;z-index:50;height:62px;background:rgba(8,16,32,0.9);border-bottom:1px solid var(--border);backdrop-filter:blur(16px);display:flex;align-items:center;padding:0 28px;}
  .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:white;}
  .content{padding:28px;flex:1;}

  /* Summary cards */
  .summary-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
  .sum-card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:16px 18px;position:relative;overflow:hidden;}
  .sum-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--aqua),var(--accent));}
  .sum-label{font-size:0.72rem;color:var(--muted);margin-bottom:6px;}
  .sum-val{font-family:'Playfair Display',serif;font-size:1.5rem;color:white;}
  .sum-val.green{color:var(--success);}
  .sum-val.red{color:var(--warn);}
  .sum-val.aqua{color:var(--aqua);}

  /* Chart */
  .chart-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;margin-bottom:22px;}
  .chart-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:3px;}
  .chart-sub{font-size:0.75rem;color:var(--muted);margin-bottom:16px;}

  /* Filter */
  .filter-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
  .filter-bar label{font-size:0.75rem;color:var(--muted);white-space:nowrap;}
  .filter-input{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:9px;padding:8px 12px;color:white;font-family:'DM Sans',sans-serif;font-size:0.83rem;outline:none;transition:border-color 0.2s;}
  .filter-input::placeholder{color:rgba(255,255,255,0.25);}
  .filter-input:focus{border-color:var(--aqua);}
  .filter-input option{background:#0d2f5e;}
  .btn-reset{padding:8px 16px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:0.82rem;cursor:pointer;transition:all 0.18s;}
  .btn-reset:hover{border-color:var(--aqua);color:var(--foam);}
  .result-count{font-size:0.78rem;color:var(--muted);margin-left:auto;}

  /* Table */
  .table-card{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:22px;}
  .table-title{font-size:0.9rem;font-weight:600;color:white;margin-bottom:16px;}
  table{width:100%;border-collapse:collapse;}
  th{font-size:0.68rem;letter-spacing:1px;text-transform:uppercase;color:var(--muted);padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);}
  td{padding:13px 12px;font-size:0.845rem;color:var(--text);border-bottom:1px solid rgba(255,255,255,0.04);}
  tr:last-child td{border-bottom:none;}
  tr:hover td{background:rgba(255,255,255,0.02);}
  .badge-status{display:inline-flex;align-items:center;gap:5px;padding:3px 11px;border-radius:20px;font-size:0.71rem;font-weight:500;}
  .badge-status.paid{background:rgba(77,217,172,0.12);color:var(--success);}
  .badge-status.paid::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--success);}
  .badge-status.unpaid{background:rgba(255,107,107,0.12);color:var(--warn);}
  .badge-status.unpaid::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--warn);}
  .no-results{text-align:center;padding:40px;color:var(--muted);font-size:0.875rem;}
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
    <div class="sb-section-label">Menu</div>
    <a class="sb-item" href="user-dashboard.php">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>My Dashboard
    </a>
    <a class="sb-item active" href="user-bills.php">
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
    <div class="topbar-title">My Bills</div>
  </div>
  <div class="content">

    <!-- SUMMARY -->
    <div class="summary-row">
      <div class="sum-card">
        <div class="sum-label">TOTAL BILLS</div>
        <div class="sum-val" id="s-total"><?php echo $total_bills; ?></div>
      </div>
      <div class="sum-card">
        <div class="sum-label">PAID</div>
        <div class="sum-val green" id="s-paid"><?php echo $paid_bills; ?></div>
      </div>
      <div class="sum-card">
        <div class="sum-label">UNPAID</div>
        <div class="sum-val red" id="s-unpaid"><?php echo $unpaid_bills; ?></div>
      </div>
      <div class="sum-card">
        <div class="sum-label">TOTAL AMOUNT DUE</div>
        <div class="sum-val aqua" id="s-due">₱<?php echo number_format($total_due, 2); ?></div>
      </div>
    </div>

    <!-- CHART -->
    <div class="chart-card">
      <div class="chart-title">Billing History – Usage & Amount</div>
      <div class="chart-sub">Bar chart of monthly water usage and corresponding bill amounts</div>
      <div style="height:220px;position:relative;">
        <canvas id="billChart"></canvas>
      </div>
    </div>

    <!-- FILTER -->
    <div class="filter-bar">
      <label>SEARCH:</label>
      <select class="filter-input" id="f-month" onchange="filterBills()">
        <option value="">All Months</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
      <select class="filter-input" id="f-year" onchange="filterBills()">
        <option value="">All Years</option>
        <?php foreach ($billing_years as $billing_year): ?>
          <option value="<?php echo $billing_year; ?>"><?php echo $billing_year; ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-input" id="f-status" onchange="filterBills()">
        <option value="">All Status</option>
        <option value="paid">Paid</option>
        <option value="unpaid">Unpaid</option>
      </select>
      <button class="btn-reset" onclick="resetFilters()">Reset</button>
      <div class="result-count" id="result-count"></div>
    </div>

    <!-- TABLE -->
    <div class="table-card">
      <div class="table-title">Billing History</div>
      <table>
        <thead>
          <tr>
            <th>#</th><th>Month</th><th>Year</th>
            <th>Usage (m³)</th><th>Amount</th><th>Status</th>
          </tr>
        </thead>
        <tbody id="bills-tbody">
          <?php foreach ($bills as $index => $bill): ?>
            <tr>
              <td><?php echo $bill['id']; ?></td>
              <td><?php echo date('F', mktime(0, 0, 0, $bill['billing_month'], 1)); ?></td>
              <td><?php echo $bill['billing_year']; ?></td>
              <td><?php echo $bill['usage_m3']; ?> m³</td>
              <td>₱<?php echo number_format($bill['amount'], 2); ?></td>
              <td><span class="badge-status <?php echo $bill['status']; ?>"><?php echo ucfirst($bill['status']); ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($bills)): ?>
            <tr><td colspan="6" style="text-align: center; color: rgba(255,255,255,0.5);">No bills yet.</td></tr>
          <?php endif; ?>
        </tbody>
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

const bills = <?php echo json_encode(array_map(function($b) {
  return [
    'id' => (int) $b['id'],
    'month' => date('F', mktime(0, 0, 0, $b['billing_month'], 1)),
    'year' => (int) $b['billing_year'],
    'usage' => (float) $b['usage_m3'],
    'amount' => (float) $b['amount'],
    'status' => $b['status']
  ];
}, $bills)); ?>;

// Chart
const ctx = document.getElementById('billChart').getContext('2d');
const chartBills = bills.slice(0,6).reverse();
new Chart(ctx,{
  type:'bar',
  data:{
    labels:chartBills.map(b=>b.month.slice(0,3)+' '+b.year),
    datasets:[
      {label:'Usage (m³)',data:chartBills.map(b=>b.usage),backgroundColor:'rgba(34,168,212,0.3)',borderColor:'#22a8d4',borderWidth:2,borderRadius:5,yAxisID:'y'},
      {label:'Amount (₱)',data:chartBills.map(b=>b.amount),backgroundColor:'rgba(77,217,172,0.2)',borderColor:'#4dd9ac',borderWidth:2,borderRadius:5,yAxisID:'y1'},
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{labels:{color:'rgba(255,255,255,0.55)',font:{size:11}}}},
    scales:{
      x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'rgba(255,255,255,0.45)',font:{size:11}}},
      y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'rgba(255,255,255,0.45)',font:{size:11}},position:'left',title:{display:true,text:'m³',color:'rgba(255,255,255,0.3)',font:{size:10}}},
      y1:{grid:{display:false},ticks:{color:'rgba(255,255,255,0.45)',font:{size:11},callback:v=>'₱'+v},position:'right',title:{display:true,text:'₱',color:'rgba(255,255,255,0.3)',font:{size:10}}}
    }
  }
});

function filterBills() {
  const m = document.getElementById('f-month').value;
  const y = document.getElementById('f-year').value;
  const s = document.getElementById('f-status').value;
  const filtered = bills.filter(b=>
    (!m || b.month===m) &&
    (!y || b.year===+y) &&
    (!s || b.status===s)
  );
  renderTable(filtered);
}

function renderTable(list) {
  const tb = document.getElementById('bills-tbody');
  document.getElementById('result-count').textContent = list.length + ' record(s) found';
  if (list.length===0) { tb.innerHTML=`<tr><td colspan="6"><div class="no-results">💧 No billing records found for the selected filters.</div></td></tr>`; return; }
  tb.innerHTML = '';
  list.forEach((b,i)=>{
    tb.innerHTML += `<tr>
      <td>${i+1}</td>
      <td>${b.month}</td>
      <td>${b.year}</td>
      <td>${b.usage} m³</td>
      <td>₱${b.amount.toFixed(2)}</td>
      <td><span class="badge-status ${b.status}">${b.status.charAt(0).toUpperCase()+b.status.slice(1)}</span></td>
    </tr>`;
  });
}

function resetFilters() {
  document.getElementById('f-month').value='';
  document.getElementById('f-year').value='';
  document.getElementById('f-status').value='';
  filterBills();
}

filterBills();
</script>
</body>
</html>
