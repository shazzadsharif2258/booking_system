<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../router.php';
requireApprovedVendor();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Promotions · <?= htmlspecialchars(SITE_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <style>
    .promo-hero{background:
      radial-gradient(800px 400px at 10% -10%, #ffe3f1 0%, transparent 60%),
      radial-gradient(800px 400px at 90% 110%, #f0f4ff 0%, transparent 60%), #fff;}
    .card-soft:hover{transform: translateY(-4px); box-shadow:0 16px 36px rgba(25,28,38,.10)}
    .ink{position:relative; overflow:hidden}
    .ink:after{content:"";position:absolute;border-radius:50%;transform:scale(0);opacity:.25;background:currentColor}
    .ink:active:after{left:var(--x);top:var(--y);width:10px;height:10px;animation:ink .6s ease-out}
    @keyframes ink{to{transform:scale(18);opacity:0}}
  </style>
</head>
<body class="bg-body">
  <header class="border-bottom bg-white">
    <nav class="navbar container py-2">
      <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
        <img src="../assets/images/logo.png" height="28"><span class="fw-semibold">ParlourLink</span>
      </a>
      <div class="ms-auto"><a class="btn btn-outline-secondary me-2" href="event-management.php">Dashboard</a><a class="btn btn-primary" href="../logout.php">Logout</a></div>
    </nav>
  </header>

  <main class="py-5 promo-hero">
    <div class="container">
      <div class="row g-4 align-items-center">
        <div class="col-lg-6">
          <h1 class="display-6 fw-bold">Unlock Growth with<br>Strategic Promotions</h1>
          <p class="text-secondary mt-2">Create compelling offers to attract new customers and reward loyal ones.</p>
          <a class="btn btn-primary ink" id="openForm">Create New Promotion</a>
        </div>
        <div class="col-lg-6">
          <div class="ratio ratio-16x9 card-soft overflow-hidden">
            <img src="../assets/images/hero-parlour.jpg" class="w-100 h-100 object-fit-cover" alt="">
          </div>
        </div>
      </div>

      <div class="card card-soft mt-5">
        <div class="card-body">
          <h5 class="fw-semibold mb-3">Create New Promotion</h5>
          <form class="row g-3" id="promoForm">
            <div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Discount Type</label>
              <select name="type" class="form-select" required>
                <option value="percent">% Percent</option><option value="amount">Fixed amount</option>
              </select></div>
            <div class="col-md-3"><label class="form-label">Value</label><input name="value" type="number" step="0.01" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="col-md-3"><label class="form-label">Start Date</label><input name="start" type="date" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">End Date</label><input name="end" type="date" class="form-control"></div>
            <div class="col-12"><button class="btn btn-primary ink">Create Promotion</button></div>
          </form>
        </div>
      </div>

      <h5 class="fw-semibold mt-5 mb-3">Current & Past Promotions</h5>
      <div class="row g-4" id="promoGrid">
        <div class="col-md-6 col-lg-4"><div class="card card-soft h-100">
          <img src="../assets/images/hero-parlour.jpg" class="card-img-top" style="height:160px;object-fit:cover">
          <div class="card-body">
            <h6 class="fw-semibold mb-1">Sample Promotion</h6>
            <div class="small text-secondary">Status: <span class="text-success">Active</span></div>
          </div>
          <div class="card-footer bg-white d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm ink">Edit</button>
            <button class="btn btn-outline-danger btn-sm ink">Delete</button>
          </div>
        </div></div>
      </div>
    </div>
  </main>

  <script>
    // little ripple helper for .ink elements
    document.addEventListener('click', (e)=>{
      const b=e.target.closest('.ink'); if(!b) return;
      const r=b.getBoundingClientRect(); b.style.setProperty('--x', (e.clientX-r.left)+'px'); b.style.setProperty('--y',(e.clientY-r.top)+'px');
    });
    // TODO: wire promoForm with your DB when ready
    document.getElementById('promoForm')?.addEventListener('submit', e=>{
      e.preventDefault();
      alert('Demo: hook this to your promotions table/backend.');
    });
  </script>
</body>
</html>
