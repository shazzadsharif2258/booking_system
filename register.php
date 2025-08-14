<?php
// register.php — unified Customer/Vendor registration with classy UI
session_start();

$pageTitle = 'Create Account';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $userType = ($_POST['user_type'] ?? 'customer') === 'vendor' ? 'vendor' : 'customer';

  $name     = sanitizeInput($_POST['name'] ?? '');
  $email    = sanitizeInput($_POST['email'] ?? '');
  $phone    = sanitizeInput($_POST['phone'] ?? '');
  $address  = sanitizeInput($_POST['address'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($name === '')    $errors[] = 'Name is required.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if ($address === '') $errors[] = 'Address is required.';
  if ($phone === '' || !preg_match('/^0\d{10}$/', $phone)) $errors[] = 'Phone must start with 0 and be 11 digits.';

  $strength = isStrongPassword($password);
  if (!$strength['valid']) $errors[] = $strength['message'];

  // Optional profile image
  $profileImage = null;
  if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    $uploadBase = $uploadDirs['profiles'] ?? (__DIR__ . '/uploads/profiles/');
    if (!is_dir($uploadBase)) @mkdir($uploadBase, 0755, true);

    $up = uploadImage($_FILES['profile_image'], rtrim($uploadBase, '/').'/');
    if ($up['success']) {
      $profileImage = $up['fileName'];
    } else {
      $errors[] = $up['message'];
    }
  }

  if (!$errors) {
    $res = registerUser($name, $email, $phone, $address, $password, $userType, $profileImage);
    if ($res['success']) {
      $_SESSION['user_id_for_verification'] = (int)$res['user_id'];
      $successMessage = 'Registration successful! Check your email for the verification code.';
      header('Location: login.php?message=' . urlencode($successMessage));
      exit;
    } else {
      $errors[] = $res['message'] ?? 'Registration failed.';
      if ($profileImage && !empty($uploadBase) && file_exists(rtrim($uploadBase,'/').'/'.$profileImage)) {
        @unlink(rtrim($uploadBase,'/').'/'.$profileImage);
      }
    }
  }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- If you already use assets/css/style.css, you can remove the .btn-primary override below -->
  <style>
    :root{
      --primary:#db2777; --primary-rgb:219,39,119; --ink:#313244; --muted:#6b7280;
    }
    body{
      min-height:100vh;
      background:
        radial-gradient(1200px 600px at 10% -10%, #f7eaf2 0%, transparent 60%),
        radial-gradient(1000px 500px at 90% 110%, #eef4ff 0%, transparent 55%),
        #f7f8fb;
      display:flex; align-items:center; padding:20px;
    }
    .auth-card{
      border-radius:20px; overflow:hidden; border:none;
      box-shadow:0 15px 35px rgba(0,0,0,.08);
      animation:cardIn .55s cubic-bezier(.2,.75,.25,1) both;
    }
    @keyframes cardIn{from{opacity:0;transform:translateY(8px) scale(.985)} to{opacity:1;transform:translateY(0) scale(1)}}
    .brand-header{
      background:linear-gradient(135deg, var(--primary) 0%, #ec4899 100%);
      color:#fff; padding:32px 24px; text-align:center;
    }
    .brand-logo{
      width:86px;height:86px;border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center;
      margin:-60px auto 16px; box-shadow:0 8px 20px rgba(0,0,0,.12);
    }
    .form-section{ background:#fff; padding:32px 26px; }
    @media (min-width:992px){ .form-section{ padding:42px; } }

    .form-control{
      height:48px; border-radius:10px; border:1px solid #e7e7ee;
      transition:box-shadow .2s,border-color .2s;
    }
    .form-control:focus{ border-color:var(--primary); box-shadow:0 0 0 .25rem rgba(var(--primary-rgb),.15); }

    /* Primary button in brand pink (works even without global style.css) */
    .btn-primary{
      --bs-btn-color:#fff;
      --bs-btn-bg:var(--primary);
      --bs-btn-border-color:var(--primary);
      --bs-btn-hover-bg:#be185d;
      --bs-btn-hover-border-color:#be185d;
      --bs-btn-active-bg:#9d174d;
      --bs-btn-active-border-color:#9d174d;
      border-radius:10px;
      box-shadow:0 10px 18px rgba(var(--primary-rgb), .22);
    }

    .role-toggle .btn{
      border-radius:10px; padding:.55rem .9rem; font-weight:600;
    }
    .role-toggle .btn-check:checked + .btn{
      background:rgba(var(--primary-rgb), .08); border-color:var(--primary); color:var(--primary);
      box-shadow:inset 0 0 0 1px var(--primary);
    }
    .strength{
      height:6px; border-radius:6px; background:#e9ecef; overflow:hidden;
    }
    .strength-bar{
      height:100%; width:0%; background:#ef4444; transition:width .25s ease, background .25s ease;
    }

    /* Tiny sticky header */
    .mini-head{
      position:fixed; top:8px; left:12px; right:12px; z-index:1030; pointer-events:none;
    }
    .mini-head a{
      pointer-events:auto; display:inline-flex; align-items:center; gap:.5rem; text-decoration:none;
      background:rgba(255,255,255,.85); backdrop-filter:saturate(140%) blur(6px);
      padding:.35rem .6rem; border-radius:999px; box-shadow:0 6px 16px rgba(0,0,0,.08);
    }
  </style>
</head>
<body>

  <!-- Logo → Home -->
  <div class="mini-head">
    <a href="index.php" class="shadow-sm">
      <img src="assets/images/logo.png" alt="ParlourLink" height="22">
      <span class="small text-dark-emphasis">Home</span>
    </a>
  </div>

  <div class="container" style="max-width: 1040px;">
    <div class="card auth-card">
      <div class="row g-0">
        <!-- Brand / Visual -->
        <div class="col-lg-5 d-none d-lg-block">
          <div class="brand-header h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="brand-logo">
                <i class="fas fa-spa fa-2x text-primary" style="color:var(--primary)"></i>
              </div>
              <h2 class="fw-bold mb-1">Join ParlourLink</h2>
              <div class="text-white-50">Beauty · Wellness · Events</div>
            </div>
            <div class="px-3 pb-3">
              <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm" style="background:#fff">
                <img src="assets/images/hero-parlour.jpg"
                     onerror="this.src='https://images.unsplash.com/photo-1653821355168-144695e5c0e6?q=80&w=1171&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'"
                     class="w-100 h-100 object-fit-cover" alt="">
              </div>
            </div>
          </div>
        </div>

        <!-- Form -->
        <div class="col-lg-7">
          <div class="form-section">
            <h1 class="h4 fw-bold mb-1">Create your account</h1>
            <div class="text-secondary small mb-4">Choose your role and fill in your details.</div>

            <?php if ($errors): ?>
              <div class="alert alert-danger">
                <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" novalidate>
              <div class="mb-3 role-toggle">
                <label class="form-label">I am a</label>
                <div class="d-flex gap-2">
                  <input type="radio" class="btn-check" name="user_type" id="roleCustomer" value="customer" checked>
                  <label class="btn btn-outline-secondary" for="roleCustomer"><i class="fa-regular fa-user me-1"></i> Customer</label>

                  <input type="radio" class="btn-check" name="user_type" id="roleVendor" value="vendor">
                  <label class="btn btn-outline-secondary" for="roleVendor"><i class="fa-solid fa-briefcase me-1"></i> Vendor</label>
                </div>
              </div>

              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Full name</label>
                  <input name="name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Phone (11 digits, starts with 0)</label>
                  <input name="phone" class="form-control" placeholder="01XXXXXXXXX" pattern="^0\d{10}$" required>
                </div>

                <div class="col-12">
                  <label class="form-label">Address</label>
                  <input name="address" class="form-control" placeholder="Street, City" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Password</label>
                  <input type="password" name="password" id="password" class="form-control" placeholder="Strong password" required>
                  <div class="strength mt-2"><div id="strengthBar" class="strength-bar"></div></div>
                  <div class="form-text">Min 8 chars including upper, lower, number & special.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Profile picture (optional)</label>
                  <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
                  <div class="mt-2 d-flex align-items-center gap-2">
                    <img id="preview" src="" alt="" class="rounded-circle d-none" style="width:48px;height:48px;object-fit:cover;border:2px solid #eee">
                    <small class="text-secondary">JPG/PNG/GIF up to 5MB.</small>
                  </div>
                </div>
              </div>

              <button class="btn btn-primary w-100 mt-4">Create account</button>
            </form>

            <div class="text-center small text-secondary mt-4">
              Already have an account? <a href="login.php" class="text-decoration-none">Sign in</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Password strength meter (very lightweight)
    const pwd = document.getElementById('password');
    const bar = document.getElementById('strengthBar');
    if (pwd && bar){
      pwd.addEventListener('input', () => {
        const v = pwd.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[a-z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        const pct = [0,20,40,70,85,100][score];
        bar.style.width = pct + '%';
        bar.style.background = ['#ef4444','#ef4444','#f59e0b','#16a34a','#16a34a','#16a34a'][score];
      });
    }

    // Image preview
    const input = document.getElementById('profile_image');
    const img   = document.getElementById('preview');
    if (input && img){
      input.addEventListener('change', () => {
        if (input.files && input.files[0]){
          const r = new FileReader();
          r.onload = e => { img.src = e.target.result; img.classList.remove('d-none'); };
          r.readAsDataURL(input.files[0]);
        } else {
          img.classList.add('d-none');
        }
      });
    }
  </script>
</body>
</html>
