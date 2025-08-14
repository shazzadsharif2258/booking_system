<?php
// /admin/index.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../router.php';
requireRole('admin');

// Fallback if functions.php doesn't have it
if (!function_exists('timeAgo')) {
  function timeAgo($datetime) {
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return $diff.'s ago';
    if ($diff < 3600) return floor($diff/60).'m ago';
    if ($diff < 86400) return floor($diff/3600).'h ago';
    return floor($diff/86400).'d ago';
  }
}

// --- metrics ---
$totBookings = (int)($db->selectOne("SELECT COUNT(*) c FROM bookings")['c'] ?? 0);
$activeUsers = (int)($db->selectOne("SELECT COUNT(*) c FROM users WHERE is_verified=1")['c'] ?? 0);
$upcoming    = (int)($db->selectOne("SELECT COUNT(*) c FROM events WHERE is_available=1")['c'] ?? 0);

// last 6 months (bookings/users)
$bookMonthly = $db->select("
  SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c
  FROM bookings GROUP BY m ORDER BY m DESC LIMIT 6
");
$userMonthly = $db->select("
  SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c
  FROM users GROUP BY m ORDER BY m DESC LIMIT 6
");

// pending vendors
$pending = $db->select("
  SELECT id,name,email,created_at
  FROM users
  WHERE user_type='vendor'
    AND (COALESCE(is_approved,0)=0 OR LOWER(COALESCE(status,''))<>'approved')
  ORDER BY created_at DESC
");

// approved vendors + counts
$approvedVendors = $db->select("
  SELECT u.id, u.name, u.email, u.created_at,
         COALESCE(ev.cnt,0)  AS events_count,
         COALESCE(pr.cnt,0)  AS promos_count
  FROM users u
  LEFT JOIN (SELECT vendor_id, COUNT(*) cnt FROM events GROUP BY vendor_id) ev
         ON ev.vendor_id = u.id
  LEFT JOIN (SELECT vendor_id, COUNT(*) cnt FROM promotions GROUP BY vendor_id) pr
         ON pr.vendor_id = u.id
  WHERE u.user_type='vendor'
    AND (COALESCE(u.is_approved,0)=1 OR LOWER(COALESCE(u.status,''))='approved')
  ORDER BY u.created_at DESC
");
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard · <?= htmlspecialchars(SITE_NAME) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --brand:#db2777; --brand-600:#be185d; --muted:#6b7280;
      --surface:#fff; --page:#faf7fb;
    }
    body{
      background:
        radial-gradient(900px 400px at 10% -10%, #ffe8f3 0%, transparent 60%),
        radial-gradient(900px 400px at 90% 110%, #f1f4ff 0%, transparent 60%),
        var(--page);
    }
    .navbar{backdrop-filter:saturate(120%) blur(4px)}
    .brand-dot{width:8px;height:8px;background:var(--brand);border-radius:50%}
    .title{font-family: ui-serif, Georgia, 'Times New Roman', serif}
    .metric{border:1px solid #f1e9f3;border-radius:18px;background:var(--surface);
      box-shadow:0 12px 28px rgba(219,39,119,.06); transition:.2s}
    .metric:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(219,39,119,.10)}
    .metric .big{font-weight:800;font-size:32px;color:var(--brand)}
    .card-soft{border:1px solid #f0e8f1;border-radius:18px;box-shadow:0 10px 28px rgba(25,28,38,.06)}
    .hover-lift{transition:transform .15s cubic-bezier(.2,.8,.2,1), box-shadow .2s}
    .hover-lift:hover{transform:translateY(-3px)}
    .btn-brand{--bs-btn-bg:var(--brand); --bs-btn-border-color:var(--brand);
      --bs-btn-hover-bg:var(--brand-600); --bs-btn-hover-border-color:var(--brand-600);
      border-radius:10px; box-shadow:0 8px 16px rgba(219,39,119,.25)}
    .link-soft{color:var(--muted);text-decoration:none}
    .link-soft:hover{color:var(--brand)}
    .badge-soft{background:#ffe7f1;color:var(--brand);border:1px solid #ffd3e6}
    .water{position:relative;overflow:hidden}
    .water:after{content:"";position:absolute;left:-20%;right:-20%;bottom:-1px;height:90px;color:#2f343a;
      background:currentColor;mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat; opacity:.12}
    .reveal{opacity:0;transform:translateY(6px);transition:all .35s ease-out}
    .reveal.reveal-in{opacity:1;transform:none}
    .table>:not(caption)>*>*{padding:.8rem .9rem}
    @media (max-width:576px){.metric .big{font-size:26px}}
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-2">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
      <span class="brand-dot"></span><span class="fw-semibold">System Admin</span>
    </a>
    <div class="d-flex align-items-center gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="../index.php">Site</a>
      <a class="btn btn-brand btn-sm" href="../logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container my-4 my-lg-5">
  <h1 class="h2 title text-center mb-1">Admin Dashboard</h1>
  <p class="text-center text-secondary mb-4">Overview of your Parlour &amp; Event management system.</p>

  <!-- Metrics -->
  <div class="row g-3 g-lg-4 mb-4">
    <div class="col-md-4">
      <div class="p-4 metric reveal">
        <div class="small text-secondary mb-1">Total Bookings</div>
        <div class="big"><?= number_format($totBookings) ?></div>
        <div class="small text-secondary mt-1"><i class="fa-solid fa-calendar-check me-1"></i>Up to date</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-4 metric reveal">
        <div class="small text-secondary mb-1">Active Users</div>
        <div class="big"><?= number_format($activeUsers) ?></div>
        <div class="small text-secondary mt-1"><i class="fa-solid fa-user me-1"></i>Verified accounts</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-4 metric reveal">
        <div class="small text-secondary mb-1">Upcoming Events</div>
        <div class="big"><?= number_format($upcoming) ?></div>
        <div class="small text-secondary mt-1"><i class="fa-solid fa-calendar-days me-1"></i>Open for booking</div>
      </div>
    </div>
  </div>

  <!-- Activity & Charts -->
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card-soft p-3 p-md-4 water reveal">
        <h5 class="mb-3">Recent Activity</h5>
        <ul class="list-group list-group-flush">
          <?php
          $acts = $db->select("SELECT id, created_at FROM bookings ORDER BY created_at DESC LIMIT 3");
          foreach ($acts as $a) {
            echo '<li class="list-group-item d-flex justify-content-between align-items-center">'
               . '<span><i class="fa-solid fa-file-invoice text-secondary me-2"></i>'
               . 'New booking #'.(int)$a['id'].'</span>'
               . '<small class="text-secondary">'.timeAgo($a['created_at']).'</small>'
               . '</li>';
          }
          ?>
        </ul>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3 p-md-4 reveal">
        <h5 class="mb-3">Monthly Bookings</h5>
        <canvas id="chartBookings" height="130"></canvas>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-5">
    <div class="col-lg-6">
      <div class="card-soft p-3 p-md-4 reveal">
        <h5 class="mb-3">User Growth</h5>
        <canvas id="chartUsers" height="130"></canvas>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card-soft p-3 p-md-4 reveal">
        <h5 class="mb-3">Pending Vendors</h5>
        <?php if (!$pending): ?>
          <div class="alert alert-light border">No vendors are awaiting approval.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr>
                <th>Vendor</th><th>Email</th><th>Requested</th><th class="text-end">Action</th>
              </tr></thead>
              <tbody>
              <?php foreach ($pending as $v): ?>
                <tr id="row-<?= (int)$v['id'] ?>">
                  <td class="fw-semibold"><?= htmlspecialchars($v['name']) ?></td>
                  <td class="text-secondary"><?= htmlspecialchars($v['email']) ?></td>
                  <td class="text-secondary small"><?= date('M j, Y', strtotime($v['created_at'])) ?></td>
                  <td class="text-end">
                    <button class="btn btn-brand btn-sm" data-approve="<?= (int)$v['id'] ?>">
                      <i class="fa-solid fa-check me-1"></i>Approve
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" data-reject="<?= (int)$v['id'] ?>">
                      <i class="fa-solid fa-xmark me-1"></i>Reject
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Approved Vendors -->
  <div class="row g-4 mb-5">
    <div class="col-12">
      <div class="card-soft p-3 p-md-4 reveal">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Approved Vendors</h5>
          <input class="form-control" id="vendorSearch" placeholder="Search vendor…" style="max-width:260px">
        </div>
        <?php if (!$approvedVendors): ?>
          <div class="alert alert-light border">No approved vendors yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle" id="vendorsTable">
              <thead>
                <tr>
                  <th>Vendor</th><th>Email</th><th>Since</th>
                  <th class="text-center">Events</th>
                  <th class="text-center">Promotions</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($approvedVendors as $v): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($v['name']) ?></td>
                    <td class="text-secondary"><?= htmlspecialchars($v['email']) ?></td>
                    <td class="text-secondary small"><?= date('M j, Y', strtotime($v['created_at'])) ?></td>
                    <td class="text-center"><span class="badge badge-soft"><?= (int)$v['events_count'] ?></span></td>
                    <td class="text-center"><span class="badge badge-soft"><?= (int)$v['promos_count'] ?></span></td>
                    <td class="text-end">
                      <button class="btn btn-outline-secondary btn-sm"
                              data-assets="<?= (int)$v['id'] ?>"
                              data-name="<?= htmlspecialchars($v['name']) ?>">
                        View Assets
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<section class="container mt-4 mt-lg-5">
  <h4 class="mb-3">Quick Navigation</h4>
  <div class="row g-3 g-lg-4">
    <div class="col-md-4">
      <a class="card-soft p-4 d-block hover-lift text-decoration-none" href="../vendor/dashboard.php">
        <div class="mb-2"><i class="fa-solid fa-calendar-check me-2 text-secondary"></i>
          <strong class="text-body">Parlour Admin</strong></div>
        <div class="small text-secondary">Manage parlour bookings, services and staff.</div>
        <div class="mt-2 small text-body">Open <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></div>
      </a>
    </div>
    <div class="col-md-4">
      <a class="card-soft p-4 d-block hover-lift text-decoration-none" href="../vendor/dashboard.php#events">
        <div class="mb-2"><i class="fa-solid fa-calendar-days me-2 text-secondary"></i>
          <strong class="text-body">Event Admin</strong></div>
        <div class="small text-secondary">Schedules, venues & assignments.</div>
        <div class="mt-2 small text-body">Open <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></div>
      </a>
    </div>
    <div class="col-md-4">
      <a class="card-soft p-4 d-block hover-lift text-decoration-none" href="../vendor/promotions.php">
        <div class="mb-2"><i class="fa-solid fa-bullhorn me-2 text-secondary"></i>
          <strong class="text-body">Promotions</strong></div>
        <div class="small text-secondary">Create and manage marketing campaigns.</div>
        <div class="mt-2 small text-body">Open <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></div>
      </a>
    </div>
  </div>
</section>

<footer class="border-top py-4 small text-secondary text-center">
  © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?> · <a class="link-soft" href="../privacy.php">Privacy</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  // reveal-on-scroll
  const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('reveal-in');io.unobserve(e.target)}}),{threshold:.12});
  document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

  // Charts
  const bookData = <?= json_encode(array_reverse(array_map(fn($r)=>[(string)$r['m'],(int)$r['c']], $bookMonthly ?? []))) ?>;
  const userData = <?= json_encode(array_reverse(array_map(fn($r)=>[(string)$r['m'],(int)$r['c']], $userMonthly ?? []))) ?>;

  new Chart(document.getElementById('chartBookings'), {
    type:'bar',
    data:{labels:bookData.map(x=>x[0]), datasets:[{label:'Bookings', data:bookData.map(x=>x[1])}]},
    options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
  });

  new Chart(document.getElementById('chartUsers'), {
    type:'line',
    data:{labels:userData.map(x=>x[0]), datasets:[{label:'Users', data:userData.map(x=>x[1]), tension:.3, fill:false}]},
    options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
  });

  // Approve / Reject
  async function postAction(action, id){
    const res = await fetch('api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action, id})
    });
    const data = await res.json();
    if(data.success && action==='approve_vendor'){
      const row = document.getElementById('row-'+id);
      if(row) row.remove();
    }
    if(data.success && action==='reject_vendor'){
      const row = document.getElementById('row-'+id);
      if(row) row.remove();
    }
    alert(data.message || (data.success?'Done':'Failed'));
  }
  document.addEventListener('click', (e)=>{
    const a = e.target.closest('[data-approve]'); if(a){ postAction('approve_vendor', a.dataset.approve); }
    const r = e.target.closest('[data-reject]');   if(r){ postAction('reject_vendor', r.dataset.reject); }
  });

  // quick search
  document.getElementById('vendorSearch')?.addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#vendorsTable tbody tr').forEach(tr => {
      tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  // load vendor assets (events + promos)
  async function loadAssets(id, name){
    const res = await fetch('api.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action:'vendor_assets', id})
    });
    const data = await res.json();

    const title = document.getElementById('assetsModalLabel');
    const evBox  = document.getElementById('assetsEvents');
    const prBox  = document.getElementById('assetsPromotions');

    title.textContent = `Vendor: ${name}`;

    if (!data.success) {
      evBox.textContent = prBox.textContent = (data.message || 'Failed to load assets.');
      return;
    }

    // EVENTS
    if (!data.events?.length){
      evBox.textContent = 'No events uploaded.';
    } else {
      evBox.innerHTML = `
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
              ${data.events.map(e => `
                <tr>
                  <td>${e.name ?? ''}</td>
                  <td class="text-secondary">${e.event_type ?? ''}</td>
                  <td>${+e.is_available ? '<span class="badge badge-soft">Available</span>' : 'Hidden'}</td>
                  <td class="text-secondary small">${new Date(e.created_at).toLocaleDateString()}</td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    }

    // PROMOTIONS
    if (!data.promotions?.length){
      prBox.textContent = 'No promotions created.';
    } else {
      prBox.innerHTML = `
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Title</th><th>Status</th><th>Start</th><th>End</th></tr></thead>
            <tbody>
              ${data.promotions.map(p => `
                <tr>
                  <td>${p.title ?? ''}</td>
                  <td>${(p.status ?? '').toString().toUpperCase()}</td>
                  <td class="text-secondary small">${p.start_date ? new Date(p.start_date).toLocaleDateString() : '-'}</td>
                  <td class="text-secondary small">${p.end_date ? new Date(p.end_date).toLocaleDateString() : '-'}</td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>`;
    }

    new bootstrap.Modal('#assetsModal').show();
  }
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-assets]');
    if (btn) loadAssets(btn.dataset.assets, btn.dataset.name);
  });
</script>

<!-- Assets modal -->
<div class="modal fade" id="assetsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assetsModalLabel">Vendor Assets</h5>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6 class="mb-2">Events</h6>
        <div id="assetsEvents" class="mb-3 small text-secondary">Loading…</div>
        <hr>
        <h6 class="mb-2">Promotions</h6>
        <div id="assetsPromotions" class="small text-secondary">Loading…</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-brand" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
