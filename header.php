<?php
// header.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (isLoggedIn()) {
  checkSessionTimeout();
}

$pageTitle = $pageTitle ?? SITE_NAME;
$pageCSS   = $pageCSS   ?? [];                 // per-page CSS (optional)
$BASE      = rtrim(defined('APP_BASE') ? APP_BASE : '', '/');
$VER       = defined('ASSET_VER') ? ASSET_VER : '1.0.0';

// Build avatar path safely (Uploads/profiles/<file> or absolute URL)
$avatar = $_SESSION['profile_image'] ?? '';
if (!$avatar) {
  $avatar = $BASE . '/assets/images/placeholder.jpg';
} else {
  if (!preg_match('#^https?://#i', $avatar)) {
    // Prefix if user stored only filename
    $prefix = (preg_match('#^(Uploads/|assets/)#', $avatar)) ? '' : 'Uploads/profiles/';
    $avatar = $BASE . '/' . ltrim($prefix . $avatar, '/');
  }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle . ' - ' . SITE_NAME) ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- Global theme (after Bootstrap) -->
  <link href="<?= $BASE ?>/assets/css/style.css?v=<?= $VER ?>" rel="stylesheet">
  <?php foreach ($pageCSS as $css): ?>
    <link href="<?= $BASE . '/' . ltrim($css, '/') ?>?v=<?= $VER ?>" rel="stylesheet">
  <?php endforeach; ?>
</head>

<body class="bg-body">

  <header class="border-bottom bg-white sticky-top">
    <nav class="navbar navbar-expand-lg container py-2">
      <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $BASE ?>/index.php">
        <img src="<?= $BASE ?>/assets/images/logo.png" alt="ParlourLink" height="28">
        <span class="fw-semibold text-body-emphasis">Event</span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div id="navMain" class="collapse navbar-collapse">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link<?= activeNav('index.php'); ?>" href="<?= $BASE ?>/index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link<?= activeNav('services.php'); ?>" href="<?= $BASE ?>/customer/services.php">Services</a></li>
          <?php if (isLoggedIn() && isUserType('vendor')): ?>
            <li class="nav-item"><a class="nav-link<?= activeNav('dashboard.php'); ?>" href="<?= $BASE ?>/vendor/dashboard.php">Vendor Dashboard</a></li>
            <li class="nav-item"><a class="nav-link<?= activeNav('promotions.php'); ?>" href="<?= $BASE ?>/vendor/promotions.php">Promotions</a></li>
          <?php elseif (isLoggedIn() && isUserType('admin')): ?>
            <li class="nav-item"><a class="nav-link" href="<?= $BASE ?>/admin/index.php">Admin</a></li>

          <?php elseif (isLoggedIn() && isUserType('customer')): ?>
            <li class="nav-item"><a class="nav-link<?= activeNav('dashboard.php'); ?>" href="<?= $BASE ?>/customer/dashboard.php">My Bookings</a></li>
          <?php endif; ?>
        </ul>

        <div class="d-flex align-items-center gap-3">
          <?php if (isLoggedIn()): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?= htmlspecialchars($avatar) ?>" class="rounded-circle me-2" width="32" height="32" style="object-fit:cover" alt="">
                <span class="small text-body"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item" href="<?= $BASE ?>/<?= isUserType('customer') ? 'customer/profile.php' : 'vendor/verify.php' ?>">
                    Profile
                  </a>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item text-danger" href="<?= $BASE ?>/logout.php">Logout</a></li>
              </ul>
            </div>
          <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= $BASE ?>/login.php">Login</a>
            <a class="btn btn-primary" href="<?= $BASE ?>/register.php">Sign up</a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </header>

  <div class="container pt-3">
    <?php if (function_exists('displayFlashMessage')) displayFlashMessage(); ?>
  </div>