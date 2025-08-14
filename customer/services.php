<?php
// /customer/services.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';

// ✅ Public page – no login required to view
$isCustomer = isLoggedIn() && isUserType('customer');

$PLACEHOLDER = app_url('assets/images/placeholder.jpg');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Services & Events · <?= htmlspecialchars(SITE_NAME) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
  :root{
    --brand:#db2777; --brand-600:#be185d; --ink:#1f2937; --muted:#6b7280;
    --card-border:#f1e9f3; --card-shadow:0 12px 28px rgba(219,39,119,.06);
  }
  body{background:#fff;}
  .brand-dot{width:10px;height:10px;border-radius:50%;background:var(--brand)}
  .btn-brand{
    --bs-btn-bg:var(--brand);--bs-btn-border-color:var(--brand);
    --bs-btn-hover-bg:var(--brand-600);--bs-btn-hover-border-color:var(--brand-600);
    box-shadow:0 8px 16px rgba(219,39,119,.2)
  }
  .waterband{position:relative;background:#fff;padding:28px 0 50px}
  .waterband:after{
    content:"";position:absolute;left:-20%;right:-20%;bottom:-1px;height:94px;color:#2f343a;background:currentColor;opacity:.12;
    -webkit-mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
            mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
  }
  .card-soft{
    border:1px solid var(--card-border);border-radius:16px;background:#fff;box-shadow:var(--card-shadow);
    transition:transform .16s ease, box-shadow .22s ease
  }
  .card-soft:hover{transform:translateY(-4px);box-shadow:0 18px 36px rgba(219,39,119,.12)}
  .card-img-wrap{height:170px;overflow:hidden;border-top-left-radius:16px;border-top-right-radius:16px}
  .card-img{width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.2,.8,.2,1)}
  .card-soft:hover .card-img{transform:scale(1.05)}
  .badge-soft{background:#ffe7f1;color:#111;border:1px solid #ffd3e6}
  .muted{color:var(--muted)}
  .icon-tiny{font-size:.9rem}
</style>
</head>
<body>

<!-- Top bar -->
<nav class="navbar bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= app_url('index.php') ?>">
      <span class="brand-dot"></span>
      <span class="fw-semibold"><?= htmlspecialchars(SITE_NAME) ?></span>
    </a>
    <ul class="nav">
      <li class="nav-item"><a class="nav-link text-dark" href="<?= app_url('index.php') ?>">Home</a></li>
      <li class="nav-item"><a class="nav-link active text-dark fw-semibold" href="#">Services</a></li>
      <?php if ($isCustomer): ?>
        <li class="nav-item"><a class="nav-link text-dark" href="<?= app_url('customer/profile.php') ?>">Profile</a></li>
      <?php else: ?>
        <li class="nav-item"><a class="nav-link text-dark" href="<?= app_url('login.php') ?>">Login</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<!-- Title + subtle water wave -->
<section class="waterband">
  <div class="container">
    <h1 class="h4 fw-semibold mb-0">Our Services & Events</h1>
  </div>
</section>

<!-- Filters -->
<div class="border-bottom py-3">
  <div class="container">
    <div class="row g-2 align-items-center">
      <div class="col-6 col-md-3"><select id="filterType" class="form-select"><option value="">Service Type</option></select></div>
      <div class="col-6 col-md-3"><select id="filterLocation" class="form-select"><option value="">Location</option></select></div>
      <div class="col-12 col-md-3 d-flex align-items-center gap-2">
        <div class="small muted">Price:</div>
        <input type="range" id="filterPrice" class="form-range" min="0" max="500" value="200" step="10" style="width:130px">
        <div class="small fw-semibold" id="priceLabel">$0–$200</div>
      </div>
      <div class="col-12 col-md-3">
        <select id="filterSort" class="form-select">
          <option value="popular">Sort By: Popular</option>
          <option value="price_asc">Price: Low → High</option>
          <option value="price_desc">Price: High → Low</option>
          <option value="rating_desc">Highest Rated</option>
        </select>
      </div>
    </div>
  </div>
</div>

<!-- Grid -->
<main class="container py-4 py-lg-5">
  <div id="grid" class="row g-3 g-md-4"></div>
  <div id="empty" class="text-center text-secondary py-5 d-none">No services match your filters.</div>
  <div id="loading" class="text-center py-5"><div class="spinner-border text-secondary"></div></div>
</main>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="detailsTitle" class="modal-title">Service details</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="ratio ratio-16x9 mb-3"><img id="detailsImg" src="" alt="" class="w-100 h-100 object-fit-cover rounded"></div>
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="mb-2"><span class="badge badge-soft" id="detailsType">Event</span></div>
            <p class="mb-2" id="detailsDesc" style="white-space:pre-line"></p>
            <div class="d-flex flex-wrap gap-2 small">
              <span class="badge text-bg-light border"><i class="fa-regular fa-users me-1"></i><span id="detailsCapacity"></span> guests</span>
              <span class="badge text-bg-light border"><i class="fa-regular fa-sparkles me-1"></i><span id="detailsServices"></span></span>
              <span class="badge text-bg-light border"><i class="fa-regular fa-circle-check me-1"></i><span id="detailsAvail"></span></span>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="p-3 border rounded">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-regular fa-building icon-tiny"></i>
                <div>
                  <div class="fw-semibold" id="vendorName"></div>
                  <div class="small text-secondary" id="vendorAddress"></div>
                </div>
              </div>
              <div class="small">
                <div class="mb-1"><i class="fa-regular fa-envelope me-2"></i><span id="vendorEmail"></span></div>
                <div class="mb-2"><i class="fa-regular fa-phone me-2"></i><span id="vendorPhone"></span></div>
                <div><i class="fa-regular fa-star text-warning me-2"></i><span id="detailsRating"></span></div>
              </div>
              <hr>
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-secondary small">Starting at</div>
                  <div class="h5 mb-0" id="detailsPrice"><?= CURRENCY_SYMBOL ?>0</div>
                </div>
                <button class="btn btn-brand" id="btnBook" data-id="">Book Now</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- Login required modal -->
<div class="modal fade" id="loginAsk" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Please sign in</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      You need to be logged in as a customer to book. You can still browse and view details.
    </div>
    <div class="modal-footer">
      <a href="<?= app_url('login.php') ?>" class="btn btn-brand">Login</a>
      <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Continue browsing</button>
    </div>
  </div></div>
</div>

<footer class="border-top py-4 small text-center text-secondary">
  © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const IS_CUSTOMER = <?= $isCustomer ? 'true' : 'false' ?>;
const LOGIN_URL   = '<?= app_url('login.php') ?>';

const $grid   = document.getElementById('grid');
const $empty  = document.getElementById('empty');
const $loading= document.getElementById('loading');

const $type = document.getElementById('filterType');
const $loc  = document.getElementById('filterLocation');
const $price= document.getElementById('filterPrice');
const $priceLabel = document.getElementById('priceLabel');
const $sort = document.getElementById('filterSort');

$price.addEventListener('input', () => $priceLabel.textContent = `$0–$${$price.value}`);

function cardHtml(x){
  const img = x.image_url || '<?= $PLACEHOLDER ?>';
  const price = (x.base_cost ? Number(x.base_cost).toFixed(0) : '0');
  const rating = (x.avg_rating ? Number(x.avg_rating).toFixed(1) : '4.5');
  const reviews = (x.reviews || 0);
  return `
  <div class="col-12 col-sm-6 col-lg-4">
    <article class="card-soft h-100">
      <div class="card-img-wrap">
        <img src="${img}" class="card-img" alt="">
      </div>
      <div class="p-3">
        <h6 class="mb-1 line-clamp-1">${x.name ?? 'Service'}</h6>
        <div class="small mb-2"><i class="fa-regular fa-shop me-1"></i>${x.vendor_name ?? ''}</div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="small"><span class="fw-semibold">$${price}</span></div>
          <div class="small text-secondary"><i class="fa-regular fa-star text-warning me-1"></i>${rating} <span class="opacity-75">(${reviews} reviews)</span></div>
        </div>
      </div>
      <div class="p-3 pt-0">
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary flex-fill" data-details="${x.id}">Details</button>
          <button class="btn btn-sm btn-brand flex-fill" data-book="${x.id}">Book Now</button>
        </div>
      </div>
    </article>
  </div>`;
}

async function fetchList(){
  $loading.classList.remove('d-none');
  $grid.innerHTML='';
  $empty.classList.add('d-none');

  const params = new URLSearchParams({
    action: 'list',
    type: $type.value,
    location: $loc.value,
    max: $price.value,
    sort: $sort.value
  });

  const res = await fetch('services_api.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params });
  const data = await res.json();

  $loading.classList.add('d-none');

  if(!data.success || !data.items?.length){
    $empty.classList.remove('d-none'); return;
  }

  if($type.options.length===1 && data.facets?.types){
    data.facets.types.forEach(t=>{ const o=document.createElement('option'); o.value=t; o.textContent=t; $type.appendChild(o); });
  }
  if($loc.options.length===1 && data.facets?.locations){
    data.facets.locations.forEach(l=>{ const o=document.createElement('option'); o.value=l; o.textContent=l; $loc.appendChild(o); });
  }

  const frag = document.createDocumentFragment();
  data.items.forEach(x=>{ const div=document.createElement('div'); div.innerHTML=cardHtml(x); frag.appendChild(div.firstElementChild); });
  $grid.appendChild(frag);
}

async function openDetails(id){
  const res = await fetch('services_api.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'detail', id}) });
  const d = await res.json();
  if(!d.success){ alert(d.message||'Failed'); return; }

  const x = d.item;
  document.getElementById('detailsTitle').textContent = x.name || 'Service';
  document.getElementById('detailsImg').src = x.image_url || '<?= $PLACEHOLDER ?>';
  document.getElementById('detailsType').textContent = x.event_type || 'Event';
  document.getElementById('detailsDesc').textContent = x.description || '';
  document.getElementById('detailsCapacity').textContent = x.capacity || 0;
  document.getElementById('detailsServices').textContent = x.services && x.services.length ? x.services.join(', ') : '—';
  document.getElementById('detailsAvail').textContent = x.is_available ? 'Available' : 'Not available';
  document.getElementById('vendorName').textContent = x.vendor_name || '';
  document.getElementById('vendorAddress').textContent = x.vendor_address || '';
  document.getElementById('vendorEmail').textContent = x.vendor_email || '';
  document.getElementById('vendorPhone').textContent = x.vendor_phone || '';
  document.getElementById('detailsPrice').textContent = '<?= CURRENCY_SYMBOL ?>' + (x.base_cost ? Number(x.base_cost).toFixed(2) : '0.00');
  document.getElementById('detailsRating').textContent = (x.avg_rating ? Number(x.avg_rating).toFixed(1) : '4.5') + ` (${x.reviews||0} reviews)`;

  const btn = document.getElementById('btnBook');
  btn.dataset.id = id;

  new bootstrap.Modal('#detailsModal').show();
}

function promptLogin(){
  new bootstrap.Modal('#loginAsk').show();
}

async function bookNow(id){
  if(!IS_CUSTOMER){ promptLogin(); return; }
  const res = await fetch('services_api.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({action:'add_to_cart', id})
  });
  const d = await res.json();
  if(!d.success && (d.message||'').toLowerCase().includes('sign in')) { promptLogin(); return; }
  alert(d.message || (d.success ? 'Added to cart' : 'Failed'));
}

// interactions
['change','input'].forEach(ev=>{
  document.getElementById('filterType').addEventListener(ev, fetchList);
  document.getElementById('filterLocation').addEventListener(ev, fetchList);
  document.getElementById('filterPrice').addEventListener(ev, fetchList);
  document.getElementById('filterSort').addEventListener(ev, fetchList);
});

document.addEventListener('click', (e)=>{
  const det = e.target.closest('[data-details]'); if(det){ openDetails(det.dataset.details); }
  const book = e.target.closest('[data-book]');   if(book){ bookNow(book.dataset.book); }
  if(e.target.id==='btnBook'){ bookNow(e.target.dataset.id); }
});

// first load
fetchList();
</script>
</body>
</html>
