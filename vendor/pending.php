<?php
require_once '../config.php';
require_once '../auth.php';
require_once '../router.php';
require_once '../functions.php';

if (!isLoggedIn() || !isUserType('vendor')) {
  header('Location: ' . app_url('login.php')); exit;
}
// If they somehow get approved while here, bounce them in
if (isVendorApproved((int)($_SESSION['user_id'] ?? 0))) {
  header('Location: ' . app_url('event-management.php')); exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Approval · ParlourLink</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --brand:#db2777 }
    body{min-height:100vh;display:grid;place-items:center;background:#f7f8fb}
    .card{border-radius:18px;border:1px solid #f0f0f3;box-shadow:0 10px 28px rgba(25,28,38,.06)}
    .btn-primary{background:var(--brand);border-color:var(--brand);border-radius:10px}
  </style>
</head>
<body>
  <div class="container" style="max-width:560px">
    <div class="card p-4 text-center">
      <h1 class="h4 fw-bold mb-2">Your Vendor Account Is Pending</h1>
      <p class="text-secondary mb-4">Thanks for applying. We’ll email you as soon as an admin approves your account.</p>
      <div class="d-flex gap-2 justify-content-center">
        <a class="btn btn-outline-secondary" href="<?= app_url('index.php') ?>">Back to Home</a>
        <a class="btn btn-primary" href="<?= app_url('logout.php') ?>">Logout</a>
      </div>
    </div>
  </div>
</body>
</html>
