<?php
// /customer/dashboard.php  (Bootstrap version)
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../router.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';

requireRole('customer');

$userId            = (int)($_SESSION['user_id'] ?? 0);
$customerName      = $_SESSION['user_name'] ?? 'Customer';
$customerLocation  = $_SESSION['user_location'] ?? '';
$profileImage      = !empty($_SESSION['profile_image']) ? '../Uploads/profiles/' . $_SESSION['profile_image'] : '../assets/images/placeholder.jpg';
$flashSuccess      = $_SESSION['order_success'] ?? '';
unset($_SESSION['order_success']);

$eventTypes = ['Birthday','Wedding','Corporate'];

// categories
try { $categories = getCategories() ?: []; } catch(Throwable $e){ $categories = []; }

// bookings
try {
  $bookings = $db->select("
    SELECT b.id, b.total_cost, b.status, b.venue, b.event_date, b.created_at,
           COALESCE(p.payment_method,'') payment_method, COALESCE(p.transaction_id,'') transaction_id
      FROM bookings b
      LEFT JOIN payments p ON p.booking_id=b.id
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC
  ",[$userId]) ?: [];
} catch(Throwable $e){ $bookings = []; }

// booking items
$bookingItems = [];
if ($bookings) {
  $ids = array_column($bookings,'id');
  $place = implode(',', array_fill(0,count($ids),'?'));
  try {
    $rows = $db->select("
      SELECT bi.booking_id, bi.service_type, bi.service_cost, e.name event_name, e.image event_image
        FROM booking_items bi
        JOIN events e ON e.id=bi.event_id
       WHERE bi.booking_id IN ($place)
    ", $ids) ?: [];
    foreach($rows as $r){ $bookingItems[(int)$r['booking_id']][] = $r; }
  } catch(Throwable $e){}
}

// food orders (optional)
try {
  $orders = $db->select("
    SELECT id,total_amount,status,delivery_address,created_at
      FROM orders WHERE customer_id=? ORDER BY created_at DESC
  ",[$userId]) ?: [];
} catch(Throwable $e){ $orders = []; }

// order items
$orderItems = [];
if ($orders) {
  $oIds = array_column($orders,'id');
  $place = implode(',', array_fill(0,count($oIds),'?'));
  try{
    $rows = $db->select("
      SELECT oi.order_id, oi.quantity,
             COALESCE(fi.name,e.name) name,
             CASE WHEN oi.food_item_id IS NOT NULL THEN 'food' ELSE 'event' END item_type
        FROM order_items oi
        LEFT JOIN food_items fi ON fi.id=oi.food_item_id
        LEFT JOIN events e ON e.id=oi.event_id
       WHERE oi.order_id IN ($place)
    ", $oIds) ?: [];
    foreach($rows as $r){ $orderItems[(int)$r['order_id']][] = $r; }
  } catch(Throwable $e){}
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Customer Dashboard · <?= htmlspecialchars(SITE_NAME) ?></title>

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --brand:#db2777; --brand-600:#be185d;
      --ink:#2b2d42; --muted:#6b7280; --surface:#fff; --page:#ffffff;
    }
    body{background:var(--page);}
    .navbar-brand-dot{width:8px;height:8px;background:var(--brand);border-radius:50%}
    .btn-brand{
      --bs-btn-bg:var(--brand);--bs-btn-border-color:var(--brand);
      --bs-btn-hover-bg:var(--brand-600);--bs-btn-hover-border-color:var(--brand-600);
      box-shadow:0 8px 16px rgba(219,39,119,.18);
    }
    .badge-soft{background:#ffe7f1;color:var(--brand);border:1px solid #ffd3e6}
    .card-hover{transition:transform .15s ease, box-shadow .2s ease}
    .card-hover:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(0,0,0,.08)}
    /* water band */
    .waterband{position:relative;background:linear-gradient(180deg,#fff 0%,#ffe8f3 100%)}
    .waterband:after{
      content:"";position:absolute;left:-20%;right:-20%;bottom:-1px;height:90px;color:#2f343a;background:currentColor;opacity:.12;
      -webkit-mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
              mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
    }
    .form-label small{color:#6b7280}
    .thumb img{height:180px;object-fit:cover;transition:transform .5s ease}
    .thumb:hover img{transform:scale(1.03)}
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
      <span class="navbar-brand-dot"></span><span class="fw-semibold"><?= htmlspecialchars(SITE_NAME) ?></span>
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <div class="d-none d-md-flex align-items-center gap-2">
        <img src="<?= htmlspecialchars($profileImage) ?>" class="rounded-circle object-fit-cover" style="width:36px;height:36px;border:2px solid #eee" alt="Profile">
        <div class="small">
          <div class="fw-semibold"><?= htmlspecialchars($customerName) ?></div>
          <div class="text-secondary"><?= htmlspecialchars($customerLocation ?: '') ?></div>
        </div>
      </div>
      <a href="../logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="waterband py-4">
  <div class="container">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="flex-grow-1">
        <h1 class="h3 mb-1">Welcome back, <span class="text-nowrap" style="color:var(--brand)"><?= htmlspecialchars($customerName) ?></span></h1>
        <div class="text-secondary">Discover events & dishes near <?= htmlspecialchars($customerLocation ?: 'you') ?>.</div>
      </div>
      <?php if ($flashSuccess): ?>
        <div class="alert alert-success shadow-sm mb-0"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($flashSuccess) ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- FILTERS -->
<section class="py-4">
  <div class="container">
    <div class="row g-3">
      <div class="col-12 col-md-6 col-xl">
        <label class="form-label">Service Type <small>(events)</small></label>
        <select id="eventTypeSelect" class="form-select">
          <option value="">All</option>
          <?php foreach($eventTypes as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-6 col-xl">
        <label class="form-label">Location</label>
        <input id="locationInput" class="form-control" placeholder="City / Area">
      </div>
      <div class="col-12 col-md-6 col-xl">
        <label class="form-label">Max Price</label>
        <input id="priceRange" type="range" min="0" max="500" value="250" class="form-range">
        <div class="small text-secondary">Up to: <span id="priceValue">$250</span></div>
      </div>
      <div class="col-12 col-md-6 col-xl">
        <label class="form-label">Food Category</label>
        <select id="categorySelect" class="form-select">
          <option value="">All</option>
          <?php foreach($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-6 col-xl">
        <label class="form-label">Sort By</label>
        <select id="sortSelect" class="form-select">
          <option value="popular">Most Popular</option>
          <option value="price_asc">Price: Low to High</option>
          <option value="price_desc">Price: High to Low</option>
          <option value="rating_desc">Highest Rated</option>
        </select>
      </div>
    </div>
  </div>
</section>

<!-- DISCOVER GRID -->
<section class="pb-4">
  <div class="container">
    <div id="itemsGrid" class="row g-4"></div>
    <div id="emptyState" class="text-center text-secondary py-5 d-none">No items available.</div>
  </div>
</section>

<!-- EVENT BOOKINGS -->
<section class="py-4">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h4 mb-0">Your Event Bookings</h2>
    </div>

    <?php if(empty($bookings)): ?>
      <div class="alert alert-info">You don’t have any bookings yet.</div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach($bookings as $b): 
          $items = $bookingItems[(int)$b['id']] ?? [];
          $firstImg = $items[0]['event_image'] ?? '';
          $imgPath = $firstImg ? '../Uploads/events/'.$firstImg : '../assets/images/placeholder.jpg';
        ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
          <div class="card card-hover h-100">
            <div class="thumb overflow-hidden">
              <img src="<?= htmlspecialchars($imgPath) ?>" class="card-img-top" alt="">
            </div>
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">#<?= (int)$b['id'] ?></div>
                <span class="badge badge-soft"><?= htmlspecialchars(ucfirst($b['status'])) ?></span>
              </div>
              <div class="small text-secondary mt-1"><?= date('d M Y, H:i', strtotime($b['created_at'])) ?></div>

              <div class="mt-2 small"><span class="text-secondary">Venue:</span> <span class="fw-medium"><?= htmlspecialchars($b['venue'] ?: 'N/A') ?></span></div>
              <div class="small"><span class="text-secondary">Event:</span> <span class="fw-medium"><?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) : 'N/A' ?></span></div>

              <?php if($items): ?>
              <div class="mt-2 small">
                <div class="text-secondary">Items</div>
                <ul class="ps-3 mb-0">
                  <?php foreach($items as $it): ?>
                    <li><?= htmlspecialchars($it['event_name']) ?> <span class="text-secondary">(<?= htmlspecialchars($it['service_type']) ?>)</span></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="fw-semibold"><?= CURRENCY_SYMBOL . number_format((float)$b['total_cost'],2) ?></div>
                <div class="small text-secondary text-end">
                  <?= htmlspecialchars($b['payment_method'] ?: '—') ?>
                  <?= $b['transaction_id'] ? ' · '.htmlspecialchars($b['transaction_id']) : '' ?>
                </div>
              </div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
              <a class="btn btn-brand w-100 btn-sm" href="booking-details.php?id=<?= (int)$b['id'] ?>">
                View Details
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- FOOD ORDERS -->
<section class="py-4 pb-5">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h4 mb-0">Your Food Orders</h2>
    </div>

    <?php if(empty($orders)): ?>
      <div class="alert alert-info">No food orders yet.</div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach($orders as $o): $its = $orderItems[(int)$o['id']] ?? []; ?>
        <div class="col-md-6 col-xl-4">
          <div class="card card-hover h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-semibold">Order #<?= (int)$o['id'] ?></div>
                  <div class="small text-secondary"><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></div>
                </div>
                <span class="badge badge-soft"><?= htmlspecialchars(ucfirst($o['status'])) ?></span>
              </div>
              <?php if($its): ?>
                <div class="small mt-2">
                  <div class="text-secondary">Items</div>
                  <ul class="ps-3 mb-0">
                    <?php foreach($its as $it): ?>
                      <li><?= htmlspecialchars($it['name']) ?> <span class="text-secondary">(x<?= (int)$it['quantity'] ?>)</span></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <div class="small mt-2">
                <span class="text-secondary">Delivery:</span> <span class="fw-medium"><?= htmlspecialchars($o['delivery_address'] ?: 'N/A') ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="fw-semibold"><?= CURRENCY_SYMBOL . number_format((float)$o['total_amount'],2) ?></div>
                <a href="order-details.php?id=<?= (int)$o['id'] ?>" class="link-secondary text-decoration-none">Details</a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- FEEDBACK MODAL (optional) -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="feedbackForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Provide Feedback</h5>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="order_id" id="feedbackOrderId">
        <div class="mb-3">
          <label class="form-label">Rating</label>
          <select name="rating" class="form-select" required>
            <option value="5">5 – Excellent</option><option value="4">4 – Good</option>
            <option value="3">3 – Okay</option><option value="2">2 – Poor</option>
            <option value="1">1 – Bad</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Comment</label>
          <textarea name="comment" rows="4" class="form-control" placeholder="Your feedback..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-brand" type="submit">Submit</button>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Close</button>
      </div>
    </form>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // price slider label
  const priceRange=document.getElementById('priceRange'), priceValue=document.getElementById('priceValue');
  priceRange?.addEventListener('input', e=> priceValue.textContent = '$'+e.target.value);

  // build one card (Bootstrap)
  function cardTpl(obj, type){
    const price = type==='food' ? parseFloat(obj.price||0) : parseFloat(obj.base_cost||0);
    const rating = obj.avg_rating ? Number(obj.avg_rating).toFixed(1) :
                   (obj.rating ? Number(obj.rating).toFixed(1) : '4.5');
    const location = obj.location || obj.vendor_location || 'N/A';
    const vendor   = obj.chef_name || obj.vendor_name || '';
    const img = (type==='food'
      ? (obj.image ? '../Uploads/dishes/'+obj.image : 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?q=80&w=1200&auto=format&fit=crop')
      : (obj.image ? '../Uploads/events/'+obj.image : 'https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?q=80&w=1200&auto=format&fit=crop'));

    return `
      <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card h-100 card-hover">
          <div class="thumb overflow-hidden"><img src="${img}" class="card-img-top" alt=""></div>
          <div class="card-body">
            <h6 class="card-title text-truncate mb-1">${(obj.name || 'Service')}</h6>
            <div class="small text-secondary mb-1">${vendor}</div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="badge badge-soft"><i class="fa-solid fa-star me-1"></i>${rating}</span>
              <span class="fw-semibold"><?= CURRENCY_SYMBOL ?>${price.toFixed(0)}</span>
            </div>
            <div class="small text-secondary mt-1"><i class="fa-solid fa-location-dot me-1"></i>${location}</div>
          </div>
          <div class="card-footer bg-white border-0 pt-0">
            <button class="btn btn-brand w-100 btn-sm"
              onclick="addToCart('${type}', ${obj.id}, ${type==='food' ? obj.chef_id : obj.vendor_id})">
              Book Now
            </button>
          </div>
        </div>
      </div>`;
  }

  function applySort(list, key){
    if(key==='price_asc')  list.sort((a,b)=> (a._price)-(b._price));
    if(key==='price_desc') list.sort((a,b)=> (b._price)-(a._price));
    if(key==='rating_desc')list.sort((a,b)=> (b._rating)-(a._rating));
    return list;
  }

  function loadDiscover(){
    const categoryId = document.getElementById('categorySelect').value;
    const eventType  = document.getElementById('eventTypeSelect').value;
    const location   = document.getElementById('locationInput').value;
    const maxPrice   = document.getElementById('priceRange').value;
    const sortBy     = document.getElementById('sortSelect').value;

    fetch(`fetch_items.php?category_id=${categoryId}&event_type=${eventType}&location=${encodeURIComponent(location)}&max=${maxPrice}`)
      .then(r=>r.json())
      .then(data=>{
        const grid=document.getElementById('itemsGrid');
        const empty=document.getElementById('emptyState');
        grid.innerHTML='';

        let foods  = (data.foods||[]).map(x=>({...x,_price:+x.price||0,      _rating:+(x.avg_rating||x.rating||4.5)}));
        let events = (data.events||[]).map(x=>({...x,_price:+x.base_cost||0, _rating:+(x.avg_rating||x.rating||4.5)}));

        foods  = foods.filter(x=>x._price<=maxPrice);
        events = events.filter(x=>x._price<=maxPrice);

        foods  = applySort(foods,sortBy);
        events = applySort(events,sortBy);

        if(foods.length===0 && events.length===0){ empty.classList.remove('d-none'); return; }
        empty.classList.add('d-none');

        foods.forEach(f => grid.insertAdjacentHTML('beforeend', cardTpl(f,'food')));
        events.forEach(e => grid.insertAdjacentHTML('beforeend', cardTpl(e,'event')));
      })
      .catch(()=>{ document.getElementById('itemsGrid').innerHTML=''; document.getElementById('emptyState').classList.remove('d-none'); });
  }

  function addToCart(itemType, itemId, providerId){
    const data = {
      item_type:itemType,
      food_id:  itemType==='food' ? itemId : null,
      event_id: itemType==='event'? itemId : null,
      chef_id:  itemType==='food' ? providerId : null,
      vendor_id:itemType==='event'? providerId : null
    };
    fetch('add-to-cart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
      .then(r=>r.json()).then(d=>alert(d.message)).catch(()=>alert('Error adding item to cart.'));
  }

  // filters
  ['change','input'].forEach(ev=>{
    document.getElementById('categorySelect').addEventListener(ev, loadDiscover);
    document.getElementById('eventTypeSelect').addEventListener(ev, loadDiscover);
    document.getElementById('locationInput').addEventListener(ev, loadDiscover);
    document.getElementById('priceRange').addEventListener(ev, loadDiscover);
    document.getElementById('sortSelect').addEventListener(ev, loadDiscover);
  });
  document.addEventListener('DOMContentLoaded', loadDiscover);

  // feedback submit (optional)
  document.getElementById('feedbackForm')?.addEventListener('submit', e=>{
    e.preventDefault();
    const fd=new FormData(e.target); fd.append('action','submit_feedback');
    fetch('feedback.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(d=>{
        if(d.status==='success'){ bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide(); alert(d.message); }
        else alert(d.message||'Error');
      }).catch(()=>alert('Error submitting feedback.'));
  });
</script>
</body>
</html>
<?php require '../footer.php'; ?>
