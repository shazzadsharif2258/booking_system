<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../auth.php';
require_once '../functions.php';
require_once '../router.php';       // ← use our redirect helpers

// Auth/role/approval
requireApprovedVendor();

// Vendor id
$vendor_id = (int)($_SESSION['user_id'] ?? 0);

// Ensure uploads dir exists
if (!file_exists($uploadDirs['events'])) {
  @mkdir($uploadDirs['events'], 0755, true);
}

// Fetch vendor avatar (note: column is profile_image)
$vendor_profile   = $db->selectOne("SELECT profile_image FROM users WHERE id = ?", [$vendor_id]);
$profile_picture  = ($vendor_profile && !empty($vendor_profile['profile_image']))
                    ? '../Uploads/profiles/' . $vendor_profile['profile_image']
                    : '../assets/images/placeholder.jpg';

/* ------------------------ AJAX ENDPOINTS (unchanged) ----------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
  $search = $_GET['search'] ?? '';
  $event_type = $_GET['event_type'] ?? '';
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $itemsPerPage = 6; $offset = ($page - 1) * $itemsPerPage;

  $query = "SELECT * FROM events WHERE vendor_id = ?";
  $params = [$vendor_id];
  if ($search)     { $query .= " AND name LIKE ?";      $params[] = "%$search%"; }
  if ($event_type) { $query .= " AND event_type = ?";   $params[] = $event_type; }

  $totalQuery  = "SELECT COUNT(*) as total FROM events WHERE vendor_id = ?";
  $totalParams = [$vendor_id];
  if ($search)     { $totalQuery .= " AND name LIKE ?";     $totalParams[] = "%$search%"; }
  if ($event_type) { $totalQuery .= " AND event_type = ?";  $totalParams[] = $event_type; }

  try {
    $totalResult = $db->selectOne($totalQuery, $totalParams);
    $totalItems  = (int)($totalResult['total'] ?? 0);
    $totalPages  = max(1, (int)ceil($totalItems / $itemsPerPage));

    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage; $params[] = $offset;

    $events = $db->select($query, $params) ?: [];
    echo json_encode(['events' => $events, 'totalPages' => $totalPages, 'currentPage' => $page]);
  } catch (PDOException $e) {
    error_log("Error fetching events: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error fetching events: ' . $e->getMessage()]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'booking_history') {
  try {
    $q = "SELECT b.id, b.customer_id, b.total_cost, b.event_date, b.venue, b.created_at,
                 bi.event_id, bi.service_type, bi.service_cost,
                 e.name AS event_name, e.image AS event_image,
                 COALESCE(u.name, 'Unknown Customer') AS customer_name,
                 p.payment_method, p.transaction_id
          FROM bookings b
          JOIN booking_items bi ON b.id = bi.booking_id
          JOIN events e ON bi.event_id = e.id
          LEFT JOIN users u ON b.customer_id = u.id
          JOIN payments p ON b.id = p.booking_id
          WHERE e.vendor_id = ? AND b.status = 'confirmed'
          ORDER BY b.created_at DESC";
    $bookings = $db->select($q, [$vendor_id]) ?: [];
    echo json_encode(['bookings' => $bookings]);
  } catch (PDOException $e) {
    error_log("Error fetching booking history: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error fetching bookings: ' . $e->getMessage()]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_feedback') {
  try {
    $q = "SELECT f.id, f.booking_id, f.rating, f.comment, f.created_at,
                 COALESCE(u.name, 'Unknown Customer') AS customer_name,
                 GROUP_CONCAT(COALESCE(e.name,'Unknown Event')) AS event_names
          FROM feedback f
          LEFT JOIN users u ON f.customer_id = u.id
          LEFT JOIN booking_items bi ON f.booking_id = bi.booking_id
          LEFT JOIN events e ON bi.event_id = e.id
          WHERE e.vendor_id = ?
          GROUP BY f.id
          ORDER BY f.created_at DESC";
    $feedback = $db->select($q, [$vendor_id]) ?: [];
    echo json_encode(['status' => 'success','message' => 'Feedback retrieved successfully','feedback' => $feedback]);
  } catch (PDOException $e) {
    error_log("Error fetching feedback: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error','message' => 'Server error fetching feedback: ' . $e->getMessage()]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
  try {
    $event = $db->selectOne("SELECT * FROM events WHERE id = ? AND vendor_id = ?", [$_GET['id'], $vendor_id]);
    if ($event) echo json_encode($event);
    else { http_response_code(404); echo json_encode(['error' => 'Event not found or unauthorized']); }
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error fetching event: ' . $e->getMessage()]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  error_log("POST data: " . print_r($_POST, true));
  error_log("FILES data: " . print_r($_FILES, true));

  if (!isLoggedIn()) { echo json_encode(['status'=>'error','message'=>'Session expired. Please log in again.']); exit; }

  if (($_POST['action'] ?? '') === 'delete') {
    try {
      $ev = $db->selectOne("SELECT image FROM events WHERE id = ? AND vendor_id = ?", [$_POST['id'], $vendor_id]);
      if ($ev && $ev['image'] && file_exists($uploadDirs['events'] . $ev['image'])) @unlink($uploadDirs['events'] . $ev['image']);
      $ok = $db->delete("DELETE FROM events WHERE id = ? AND vendor_id = ?", [$_POST['id'], $vendor_id]);
      echo json_encode($ok !== false ? ['status'=>'success','message'=>'Event deleted successfully']
                                     : ['status'=>'error','message'=>'Failed to delete event']);
    } catch (PDOException $e) {
      echo json_encode(['status'=>'error','message'=>'Server error deleting event: '.$e->getMessage()]);
    }
    exit;
  }

  $id          = $_POST['id'] ?? '';
  $name        = sanitizeInput($_POST['name'] ?? '');
  $desc        = sanitizeInput($_POST['description'] ?? '');
  $event_type  = sanitizeInput($_POST['event_type'] ?? '');
  $base_cost   = filter_var($_POST['base_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
  $capacity    = filter_var($_POST['capacity'] ?? 0, FILTER_VALIDATE_INT);
  $services    = isset($_POST['services']) ? (array)$_POST['services'] : [];
  $is_available= filter_var($_POST['is_available'] ?? 0, FILTER_VALIDATE_INT);
  $imageName   = null;

  if ($name==='')                                        { echo json_encode(['status'=>'error','message'=>'Event name is required']); exit; }
  if ($base_cost===false || $base_cost<=0)               { echo json_encode(['status'=>'error','message'=>'Base cost must be greater than 0']); exit; }
  if ($capacity===false || $capacity<=0)                 { echo json_encode(['status'=>'error','message'=>'Capacity must be greater than 0']); exit; }
  if (!in_array($event_type, ['Birthday','Wedding','Corporate'])) { echo json_encode(['status'=>'error','message'=>'Invalid event type']); exit; }

  $valid_services = ['Decoration','Parlour','Music Party','Silent Environment','AC'];
  if ($event_type==='Corporate' && (in_array('Parlour',$services)||in_array('Music Party',$services))) {
    echo json_encode(['status'=>'error','message'=>'Parlour and Music Party are not available for Corporate events']); exit;
  }
  if (in_array($event_type, ['Birthday','Wedding']) && in_array('Silent Environment',$services)) {
    echo json_encode(['status'=>'error','message'=>'Silent Environment is only available for Corporate events']); exit;
  }
  foreach ($services as $s) if (!in_array($s,$valid_services)) { echo json_encode(['status'=>'error','message'=>"Invalid service: ".htmlspecialchars($s)]); exit; }

  if (isset($_FILES['image']) && $_FILES['image']['error']===UPLOAD_ERR_OK) {
    $up = uploadImage($_FILES['image'], $uploadDirs['events']);
    if ($up['success']) $imageName = $up['fileName']; else { echo json_encode(['status'=>'error','message'=>'Image upload failed: '.$up['message']]); exit; }
  }

  try {
    if ($id) {
      $ev = $db->selectOne("SELECT id,image FROM events WHERE id = ? AND vendor_id = ?", [$id,$vendor_id]);
      if (!$ev) { echo json_encode(['status'=>'error','message'=>'Unauthorized or not found']); exit; }

      $sql = "UPDATE events SET name=?, description=?, event_type=?, base_cost=?, capacity=?, services=?, is_available=?";
      $p   = [$name,$desc,$event_type,$base_cost,$capacity,json_encode($services),$is_available];

      if ($imageName) {
        if ($ev['image'] && file_exists($uploadDirs['events'].$ev['image'])) @unlink($uploadDirs['events'].$ev['image']);
        $sql .= ", image=?"; $p[] = $imageName;
      }
      $sql .= " WHERE id=? AND vendor_id=?"; $p[]=$id; $p[]=$vendor_id;

      $ok = $db->update($sql,$p);
      echo json_encode($ok!==false?['status'=>'success','message'=>'Event updated successfully']
                                  :['status'=>'error','message'=>'Failed to update event']);
    } else {
      $sql = "INSERT INTO events (vendor_id,name,description,event_type,base_cost,capacity,services,is_available,image,created_at)
              VALUES (?,?,?,?,?,?,?,?,?,NOW())";
      $ok  = $db->insert($sql, [$vendor_id,$name,$desc,$event_type,$base_cost,$capacity,json_encode($services),$is_available,$imageName]);
      echo json_encode($ok!==false?['status'=>'success','message'=>'Event added successfully']
                                  :['status'=>'error','message'=>'Failed to add event']);
    }
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Server error saving event: '.$e->getMessage()]);
  }
  exit;
}

$event_types = ['Birthday','Wedding','Corporate'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vendor Dashboard · <?= htmlspecialchars(SITE_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --brand:#db2777;            /* primary pink */
      --brand-600:#be185d;
      --ink:#303446;
      --muted:#6b7280;
      --panel:#ffffff;
      --bg:#f7f8fb;
      --ring:0 0 0 .25rem rgba(219,39,119,.18);
    }

    body{
      background:
        radial-gradient(1200px 600px at 10% -10%, #ffe5f2 0%, transparent 60%),
        radial-gradient(1000px 500px at 90% 110%, #eef2ff 0%, transparent 55%),
        var(--bg);
      min-height:100vh;
      color:#2b2f38;
    }

    /* Header / hero */
    .dash-hero{
      position:relative;
      padding:28px 0 16px;
      background:transparent;
    }
    .dash-hero .wrap{
      background: var(--panel);
      border:1px solid #f0f0f3;
      border-radius:18px;
      box-shadow:0 10px 30px rgba(25,28,38,.06);
      padding:20px 22px;
      display:flex; align-items:center; gap:16px; justify-content:space-between;
    }
    .dash-hero .title{
      font-size:26px; font-weight:700;
    }
    .wave{
      position:relative; height:46px; margin-top:24px; color:#2f3441;
    }
    .wave svg{ position:absolute; inset:0; width:100%; height:100%; }

    /* Tabs */
    .tabs{ display:flex; gap:10px; margin:22px 0 16px; flex-wrap:wrap; }
    .tab{
      padding:10px 16px; border-radius:999px; background:#fff;
      border:1px solid #eee; color:#444; cursor:pointer;
      transition: all .25s ease; position:relative; overflow:hidden;
    }
    .tab:hover{ transform:translateY(-1px); box-shadow:0 8px 20px rgba(25,28,38,.08) }
    .tab.active{ background:var(--brand); color:#fff; border-color:var(--brand) }

    /* Search/filter bar */
    .search-filter .form-control, .search-filter .form-select{
      border-radius:10px; border:1px solid #e7e7ee; padding:.6rem .85rem;
    }
    .search-filter .form-control:focus, .form-select:focus{ box-shadow:var(--ring); border-color:var(--brand) }

    /* Cards */
    .card-soft{
      border:1px solid #f0f0f3; border-radius:18px;
      box-shadow:0 10px 28px rgba(25,28,38,.06); background:#fff;
    }
    .hover-lift{ transition:transform .2s ease, box-shadow .2s ease }
    .hover-lift:hover{ transform:translateY(-4px); box-shadow:0 16px 36px rgba(25,28,38,.10) }

    .event-grid,.booking-grid,.feedback-grid{
      display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
      gap:20px;
    }
    .event-card,.booking-card,.feedback-card{ overflow:hidden }
    .event-image,.booking-image{ height:160px; overflow:hidden }
    .event-image img,.booking-image img{ width:100%; height:100%; object-fit:cover }

    /* Buttons */
    .btn-primary{
      background:var(--brand) !important; border-color:var(--brand) !important;
      border-radius:10px; box-shadow:0 8px 16px rgba(219,39,119,.25);
    }
    .btn-primary:hover{ background:var(--brand-600)!important; border-color:var(--brand-600)!important;
      transform:translateY(-1px); box-shadow:0 12px 22px rgba(219,39,119,.30) }
    .btn-outline-secondary{ border-radius:10px }

    /* Pagination */
    .pagination button{
      border:1px solid #e7e7ee; border-radius:10px; background:#fff; padding:.5rem .8rem;
    }
    .pagination button:hover:not(:disabled){ background:var(--brand); color:#fff; border-color:var(--brand) }
    .pagination button:disabled{ background:#f6f6fa; color:#999 }

    /* Inputs */
    .form-control,.form-select{ border:1px solid #e7e7ee; border-radius:10px }
    .form-control:focus,.form-select:focus{ border-color:var(--brand); box-shadow:var(--ring) }

    /* Reveal in */
    [data-reveal]{ opacity:0; transform:translateY(6px); transition:all .45s ease }
    .reveal-in{ opacity:1; transform:none }

    /* Ripple (ink) */
    .ink{ position:relative; overflow:hidden }
    .ink::after{
      content:""; position:absolute; left:var(--x); top:var(--y); width:10px; height:10px;
      background:rgba(0,0,0,.12); border-radius:50%; transform:scale(0); opacity:.35;
      pointer-events:none;
    }
    .ink:active::after{ animation:ink .6s ease-out forwards }
    @keyframes ink{ to{ transform:scale(18); opacity:0 } }

    /* Modal */
    .modal-backdrop{ background:rgba(15,18,30,.45) }
    .modal-content{ border-radius:16px }
  </style>
</head>
<body>

  <!-- HERO -->
  <section class="dash-hero">
    <div class="container">
      <div class="wrap card-soft" data-reveal>
        <div class="d-flex align-items-center gap-3">
          <img src="<?= htmlspecialchars($profile_picture) ?>" width="48" height="48" class="rounded-circle" style="object-fit:cover" alt="">
          <div>
            <div class="title">Vendor Dashboard</div>
            <div class="small text-secondary">Manage events, bookings, and feedback.</div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="../index.php" class="btn btn-outline-secondary ink">Home</a>
          <a href="../logout.php" class="btn btn-primary ink">Logout</a>
        </div>
      </div>

      <!-- Water wave -->
      <div class="wave">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="currentColor" opacity=".08"></path></svg>
      </div>
    </div>
  </section>

  <main class="pb-5">
    <div class="container">

      <!-- Tabs -->
      <div class="tabs" data-reveal>
        <div class="tab active ink" onclick="showTab('events')">Events</div>
        <div class="tab ink" onclick="showTab('bookings')">Bookings</div>
        <div class="tab ink" onclick="showTab('feedback')">Feedback</div>
      </div>

      <!-- Events -->
      <div id="events" class="tab-content">
        <div class="card-soft p-3 p-md-4 mb-3" data-reveal>
          <div class="row g-2 align-items-center search-filter">
            <div class="col-md"><input type="text" id="searchInput" class="form-control" placeholder="Search events..."></div>
            <div class="col-md-3">
              <select id="eventTypeFilter" class="form-select">
                <option value="">All Event Types</option>
                <?php foreach ($event_types as $type): ?>
                  <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-auto">
              <button class="btn btn-primary ink" onclick="openModal()">Add New Event</button>
            </div>
          </div>
        </div>

        <div id="eventGrid" class="event-grid"></div>
        <div id="pagination" class="pagination mt-3"></div>
      </div>

      <!-- Bookings -->
      <div id="bookings" class="tab-content" style="display:none;">
        <div id="bookingGrid" class="booking-grid"></div>
      </div>

      <!-- Feedback -->
      <div id="feedback" class="tab-content" style="display:none;">
        <div id="feedbackGrid" class="feedback-grid"></div>
      </div>

    </div>
  </main>

  <!-- Modal -->
  <div id="eventModal" class="modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content card-soft">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="modalTitle">Add New Event</h5>
          <button type="button" class="btn-close" onclick="closeModal()"></button>
        </div>
        <div class="modal-body">
          <form id="eventForm" enctype="multipart/form-data">
            <input type="hidden" id="eventId" name="id">
            <div class="mb-3">
              <label class="form-label">Event Name</label>
              <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea id="description" name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Event Type</label>
                <select id="event_type" name="event_type" class="form-select" required onchange="updateServiceOptions()">
                  <option value="">Select Event Type</option>
                  <?php foreach ($event_types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Base Cost (<?= htmlspecialchars(CURRENCY_SYMBOL) ?>)</label>
                <input type="number" id="base_cost" name="base_cost" step="0.01" min="0" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Capacity (Guests)</label>
                <input type="number" id="capacity" name="capacity" min="1" class="form-control" required>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label d-block">Services</label>
              <div id="services" class="d-flex flex-wrap gap-3 small">
                <label><input type="checkbox" name="services[]" value="Decoration"> Decoration</label>
                <label><input type="checkbox" name="services[]" value="Parlour"> Parlour</label>
                <label><input type="checkbox" name="services[]" value="Music Party"> Music Party</label>
                <label><input type="checkbox" name="services[]" value="Silent Environment"> Silent Environment</label>
                <label><input type="checkbox" name="services[]" value="AC"> Air Conditioning</label>
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Event Image</label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Availability</label>
                <select id="is_available" name="is_available" class="form-select">
                  <option value="1">Available</option>
                  <option value="0">Not Available</option>
                </select>
              </div>
            </div>
            <div class="mt-4 d-grid gap-2">
              <button class="btn btn-primary ink" type="submit">Save Event</button>
              <button class="btn btn-outline-secondary ink" type="button" onclick="closeModal()">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* Ripple */
    document.addEventListener('click', (e)=>{
      const b = e.target.closest('.ink'); if(!b) return;
      const r = b.getBoundingClientRect();
      b.style.setProperty('--x', (e.clientX - r.left)+'px');
      b.style.setProperty('--y', (e.clientY - r.top)+'px');
    });

    /* Reveal on scroll */
    (function(){
      const io = new IntersectionObserver((entries)=>{
        entries.forEach(en=>{ if(en.isIntersecting){ en.target.classList.add('reveal-in'); io.unobserve(en.target); } });
      }, {threshold:.12});
      document.querySelectorAll('[data-reveal]').forEach(el=>io.observe(el));
    })();

    /* Tabs */
    function showTab(tab){
      document.querySelectorAll('.tab-content').forEach(c=>c.style.display='none');
      document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
      document.getElementById(tab).style.display='block';
      document.querySelector(`.tab[onclick="showTab('${tab}')"]`).classList.add('active');
      if (tab==='events') loadEvents();
      else if (tab==='bookings') loadBookings();
      else if (tab==='feedback') loadFeedback();
    }

    function updateServiceOptions(){
      const t = document.getElementById('event_type').value;
      const boxes = document.querySelectorAll('#services input[type="checkbox"]');
      boxes.forEach(cb=>{
        const lab = cb.parentElement;
        if (t==='Corporate' && (cb.value==='Parlour' || cb.value==='Music Party')) { cb.checked=false; lab.style.display='none'; }
        else if ((t==='Birthday' || t==='Wedding') && cb.value==='Silent Environment') { cb.checked=false; lab.style.display='none'; }
        else { lab.style.display='block'; }
      });
    }

    /* Data loads (same endpoints you already had) */
    let currentPage=1;

    function loadEvents(){
      const q = document.getElementById('searchInput').value;
      const typ = document.getElementById('eventTypeFilter').value;
      fetch(`dashboard.php?action=list&search=${encodeURIComponent(q)}&event_type=${encodeURIComponent(typ)}&page=${currentPage}`)
        .then(r=>r.json())
        .then(data=>{
          const grid = document.getElementById('eventGrid'); grid.innerHTML='';
          if (data.error){ grid.innerHTML = `<div class="text-danger">${data.error}</div>`; return; }
          if (!data.events.length){ grid.innerHTML = '<div class="text-success text-center">No events found.</div>'; }
          data.events.forEach(ev=>{
            const services = (ev.services?JSON.parse(ev.services):[]).join(', ');
            const card = document.createElement('div');
            card.className = 'card-soft hover-lift event-card';
            card.innerHTML = `
              <div class="event-image"><img src="${ev.image ? '../Uploads/events/'+ev.image : '../assets/images/placeholder.jpg'}" alt=""></div>
              <div class="p-3">
                <h6 class="mb-1 fw-semibold">${ev.name}</h6>
                <div class="small text-secondary">Type: ${ev.event_type}</div>
                <div class="small text-secondary">Cost: <?= htmlspecialchars(CURRENCY_SYMBOL) ?>${parseFloat(ev.base_cost).toFixed(2)}</div>
                <div class="small text-secondary">Capacity: ${ev.capacity} guests</div>
                <div class="small text-secondary">Services: ${services || 'None'}</div>
                <div class="small mt-1">${ev.is_available ? '<span class="text-success">Available</span>' : '<span class="text-danger">Not Available</span>'}</div>
              </div>
              <div class="p-3 pt-0 d-flex gap-2">
                <button class="btn btn-primary btn-sm ink" onclick="editEvent(${ev.id})">Edit</button>
                <button class="btn btn-outline-secondary btn-sm ink" onclick="deleteEvent(${ev.id})">Delete</button>
              </div>`;
            grid.appendChild(card);
          });

          const pag = document.getElementById('pagination'); pag.innerHTML='';
          if (data.totalPages>1){
            const prev = document.createElement('button'); prev.textContent='Previous'; prev.disabled = currentPage===1;
            prev.onclick = ()=>{ if(currentPage>1){ currentPage--; loadEvents(); } };
            pag.appendChild(prev);

            for (let i=1;i<=data.totalPages;i++){
              const b = document.createElement('button'); b.textContent=i; b.disabled = (i===currentPage);
              b.onclick=()=>{ currentPage=i; loadEvents(); }; pag.appendChild(b);
            }

            const next = document.createElement('button'); next.textContent='Next'; next.disabled = currentPage===data.totalPages;
            next.onclick = ()=>{ if(currentPage<data.totalPages){ currentPage++; loadEvents(); } };
            pag.appendChild(next);
          }
        })
        .catch(err=>{
          document.getElementById('eventGrid').innerHTML = `<div class="text-danger">Failed to load events: ${err.message}</div>`;
        });
    }

    function loadBookings(){
      fetch('dashboard.php?action=booking_history').then(r=>r.json()).then(data=>{
        const grid = document.getElementById('bookingGrid'); grid.innerHTML='';
        if (data.error){ grid.innerHTML = `<div class="text-danger">${data.error}</div>`; return; }
        if (!data.bookings.length){ grid.innerHTML = '<div class="text-success text-center">No bookings found.</div>'; return; }
        const byId={};
        data.bookings.forEach(b=>{
          (byId[b.id]??=( {...b,items:[]} )).items.push({
            event_name:b.event_name, service_type:b.service_type, service_cost:b.service_cost, event_image:b.event_image
          });
        });
        Object.values(byId).forEach(b=>{
          const img = b.items[0]?.event_image ? '../Uploads/events/'+b.items[0].event_image : '../assets/images/placeholder.jpg';
          const items = b.items.map(i=>`${i.event_name} (${i.service_type}) - <?= htmlspecialchars(CURRENCY_SYMBOL) ?>${parseFloat(i.service_cost).toFixed(2)}`).join('<br>');
          const card = document.createElement('div');
          card.className='card-soft hover-lift booking-card';
          card.innerHTML = `
            <div class="booking-image"><img src="${img}" alt=""></div>
            <div class="p-3">
              <h6 class="fw-semibold mb-1">Booking #${b.id}</h6>
              <div class="small text-secondary">Customer: ${b.customer_name}</div>
              <div class="small text-secondary">Total: <?= htmlspecialchars(CURRENCY_SYMBOL) ?>${parseFloat(b.total_cost).toFixed(2)}</div>
              <div class="small text-secondary">Venue: ${b.venue}</div>
              <div class="small text-secondary">Event Date: ${new Date(b.event_date).toLocaleDateString()}</div>
              <div class="small text-secondary">Payment: ${b.payment_method} (${b.transaction_id})</div>
              <div class="small mt-2">${items}</div>
            </div>`;
          grid.appendChild(card);
        });
      }).catch(err=>{
        document.getElementById('bookingGrid').innerHTML = `<div class="text-danger">Failed to load bookings: ${err.message}</div>`;
      });
    }

    function loadFeedback(){
      fetch('dashboard.php?action=fetch_feedback').then(r=>r.json()).then(data=>{
        const grid = document.getElementById('feedbackGrid'); grid.innerHTML='';
        if (data.status==='error'){ grid.innerHTML = `<div class="text-danger">${data.message}</div>`; return; }
        if (!data.feedback.length){ grid.innerHTML = '<div class="text-success text-center">No feedback found.</div>'; return; }
        data.feedback.forEach(f=>{
          const card = document.createElement('div');
          card.className='card-soft hover-lift feedback-card';
          card.innerHTML = `
            <div class="p-3">
              <h6 class="fw-semibold mb-1">Booking #${f.booking_id}</h6>
              <div class="small text-secondary">Customer: ${f.customer_name}</div>
              <div class="small text-secondary">Rating: ${'★'.repeat(f.rating)}${'☆'.repeat(5-f.rating)}</div>
              <div class="small text-secondary">Comment: ${f.comment || 'No comment'}</div>
              <div class="small text-secondary">Events: ${f.event_names}</div>
              <div class="small text-secondary">Date: ${new Date(f.created_at).toLocaleString()}</div>
            </div>`;
          grid.appendChild(card);
        });
      }).catch(err=>{
        document.getElementById('feedbackGrid').innerHTML = `<div class="text-danger">Failed to load feedback: ${err.message}</div>`;
      });
    }

    /* Modal controls */
    function openModal(){
      document.getElementById('eventForm').reset();
      document.getElementById('eventId').value='';
      document.getElementById('modalTitle').textContent='Add New Event';
      updateServiceOptions();
      document.getElementById('eventModal').style.display='block';
      document.body.classList.add('modal-open');
      addBackdrop();
    }
    function closeModal(){
      document.getElementById('eventModal').style.display='none';
      document.body.classList.remove('modal-open');
      removeBackdrop();
    }
    function addBackdrop(){
      const b = document.createElement('div'); b.className='modal-backdrop fade show'; document.body.appendChild(b);
    }
    function removeBackdrop(){
      document.querySelectorAll('.modal-backdrop').forEach(b=>b.remove());
    }

    function editEvent(id){
      fetch(`dashboard.php?id=${id}`).then(r=>r.json()).then(d=>{
        if (d.error){ alert(d.error); return; }
        document.getElementById('eventId').value=d.id;
        document.getElementById('name').value=d.name;
        document.getElementById('description').value=d.description||'';
        document.getElementById('event_type').value=d.event_type;
        document.getElementById('base_cost').value=d.base_cost;
        document.getElementById('capacity').value=d.capacity;
        document.getElementById('is_available').value=d.is_available;
        const sv = d.services?JSON.parse(d.services):[];
        document.querySelectorAll('#services input[type="checkbox"]').forEach(c=>c.checked=sv.includes(c.value));
        document.getElementById('modalTitle').textContent='Edit Event';
        updateServiceOptions(); openModal();
      }).catch(err=>alert('Failed to load event: '+err.message));
    }

    function deleteEvent(id){
      if(!confirm('Delete this event?')) return;
      fetch('dashboard.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=delete&id=${id}`
      }).then(r=>r.json()).then(res=>{
        alert(res.message); if(res.status==='success') loadEvents();
      }).catch(err=>alert('Failed to delete: '+err.message));
    }

    document.getElementById('eventForm').addEventListener('submit', function(e){
      e.preventDefault();
      const fd = new FormData(this);
      fetch('dashboard.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(res=>{
          alert(res.message);
          if(res.status==='success'){ closeModal(); loadEvents(); }
        }).catch(err=>alert('Failed to save: '+err.message));
    });

    document.getElementById('searchInput').addEventListener('input', ()=>{ currentPage=1; loadEvents(); });
    document.getElementById('eventTypeFilter').addEventListener('change', ()=>{ currentPage=1; loadEvents(); });

    // Initial load
    loadEvents();
  </script>
</body>
</html>
