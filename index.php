<?php
session_start();
require_once './router.php';
require_once './db.php';

$pageTitle = 'Home';
$pageCSS   = ['assets/css/home.css'];   // <— page-specific CSS gets injected by header.php
require_once './header.php';
?>

<main class="min-vh-100 page-soft-bg">

  <!-- HERO -->
  <section class="hero-wrap position-relative overflow-hidden py-5 py-lg-6">
    <!-- floating blobs -->
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>

    <div class="container position-relative">
      <div class="row g-4 align-items-center">
        <div class="col-lg-6" data-reveal>
          <span class="badge rounded-pill text-bg-light border mb-3"></span>
          <h1 class="display-6 fw-bold lh-sm">Discover Your Perfect<br>Parlour Experience</h1>
          <p class="text-secondary mt-3">
            ParlourLink connects you with top-rated salons, spas, and barbershops near you.
            Book appointments effortlessly and enjoy exclusive offers.
          </p>
          <div class="d-flex gap-2 mt-3">
            <a href="#featured" class="btn btn-primary btn-soft ripple">Browse Services</a>
            <a href="#events" class="btn btn-outline-secondary ripple">View Events <i class="fa-solid fa-arrow-right ms-1"></i></a>
          </div>
        </div>

        <div class="col-lg-6" data-reveal style="--delay:120ms;">
          <div class="ratio ratio-16x9 card-soft overflow-hidden tilt">
            <img src="assets/images/hero-parlour.jpg"
                 onerror="this.src='https://images.unsplash.com/photo-1653821355736-0c2598d0a63e?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'"
                 class="w-100 h-100 object-fit-cover" alt="Parlour interior">
          </div>
        </div>
      </div>
    </div>

    <!-- soft wave -->
    <div class="hero-wave">
      <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
        <path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="currentColor"></path>
      </svg>
    </div>
  </section>

  <!-- Featured Parlours -->
  <section id="featured" class="py-5 bg-section">
    <div class="container">
      <div class="text-center mb-4" data-reveal>
        <h2 class="h3 fw-bold">Featured Parlours</h2>
        <p class="text-secondary">Our hand-picked selection of top-rated spots.</p>
      </div>

      <div class="row g-4">
        <?php
        $query = "SELECT e.id,e.name,e.price,e.event_type,u.name AS vendor_name,COALESCE(u.address,'Not specified') AS vendor_location,
                         COUNT(bi.id) AS total_bookings,COALESCE(AVG(f.rating),0) AS avg_rating,e.image
                  FROM booking_items bi
                  JOIN bookings b ON bi.booking_id=b.id
                  JOIN events e ON bi.event_id=e.id
                  JOIN users u ON e.vendor_id=u.id
                  LEFT JOIN feedback f ON b.id=f.order_id
                  WHERE b.status='confirmed'
                  GROUP BY e.id,e.name,e.price,e.event_type,u.name,u.address,e.image
                  ORDER BY total_bookings DESC LIMIT 6";
        $topEvents = $db->select($query);

        if (!$topEvents):
          echo '<div class="col-12 text-center text-secondary" data-reveal>No featured parlours yet.</div>';
        else:
          $i=0; foreach ($topEvents as $ev):
            $img = !empty($ev['image'])
                   ? 'Uploads/events/'.htmlspecialchars($ev['image'])
                   : 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?q=80&w=1200&auto=format&fit=crop';
        ?>
        <div class="col-sm-6 col-lg-4" data-reveal style="--delay:<?= $i*60 ?>ms;">
          <article class="card card-soft h-100 overflow-hidden hover-lift">
            <div class="position-relative">
              <img src="<?= $img ?>" class="card-img-top" style="height:200px;object-fit:cover" alt="">
              <span class="badge position-absolute top-0 start-0 m-3 rounded-pill bg-white text-secondary border">
                <?= htmlspecialchars($ev['event_type'] ?: 'Service') ?>
              </span>
            </div>
            <div class="card-body">
              <h5 class="card-title text-truncate mb-1"><?= htmlspecialchars($ev['name']) ?></h5>
              <div class="small text-secondary text-truncate mb-2">
                <i class="fa-regular fa-location-dot me-1"></i><?= htmlspecialchars($ev['vendor_location']) ?>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <div class="text-warning">
                  <i class="fa-solid fa-star me-1"></i><span class="small text-body"><?= number_format($ev['avg_rating'],1) ?></span>
                </div>
                <div class="fw-semibold"><?= number_format((float)$ev['price'],2) ?></div>
              </div>
            </div>
          </article>
        </div>
        <?php $i++; endforeach; endif; ?>
      </div>
    </div>
  </section>

  <!-- Upcoming Events -->
  <section id="events" class="py-5">
    <div class="container">
      <div class="text-center mb-4" data-reveal>
        <h2 class="h3 fw-bold">Upcoming Events</h2>
        <p class="text-secondary">Workshops, masterclasses, and beauty events.</p>
      </div>

      <div class="row g-4">
        <?php
        $q = "SELECT f.id,f.rating,f.comment,f.created_at,u.name AS customer_name,u.profile_image AS profile_picture,b.venue
              FROM feedback f JOIN users u ON f.customer_id=u.id JOIN bookings b ON f.order_id=b.id
              ORDER BY f.created_at DESC LIMIT 4";
        $feedbacks = $db->select($q);

        if (!$feedbacks):
          echo '<div class="col-12 text-center text-secondary" data-reveal>No upcoming events available yet.</div>';
        else:
          $j=0; foreach ($feedbacks as $row):
            $img = !empty($row['profile_picture'])
                   ? 'Uploads/profiles/'.htmlspecialchars($row['profile_picture'])
                   : 'https://images.unsplash.com/photo-1532712938310-34cb3982ef74?q=80&w=1200&auto=format&fit=crop';
            $title = !empty($row['comment']) ? $row['comment'] : 'Event highlight';
        ?>
        <div class="col-md-6" data-reveal style="--delay:<?= $j*80 ?>ms;">
          <article class="card card-soft h-100 overflow-hidden hover-lift">
            <img src="<?= $img ?>" class="w-100" style="height:220px;object-fit:cover" alt="">
            <div class="card-body">
              <h5 class="card-title mb-2"><?= htmlspecialchars($title) ?></h5>
              <div class="d-flex gap-3 small text-secondary">
                <span class="text-warning"><i class="fa-solid fa-star me-1"></i><?= (int)$row['rating'] ?>/5</span>
                <span class="text-truncate"><i class="fa-regular fa-location-dot me-1"></i><?= htmlspecialchars($row['venue'] ?: 'Venue TBA') ?></span>
              </div>
              <a href="#" class="btn btn-outline-secondary btn-sm ripple mt-3">Learn More <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
          </article>
        </div>
        <?php $j++; endforeach; endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require './footer.php'; ?>
