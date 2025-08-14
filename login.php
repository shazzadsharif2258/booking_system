
<?php
// login.php — unified login (customer + vendor) with verify/2FA step, animated UI
session_start();

$pageTitle = 'Login';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/router.php'; // redirectAfterLogin()

// Already signed in? Route to the right place.
if (isLoggedIn()) {
    $u = [
        'user_type' => $_SESSION['user_type'] ?? 'customer',
        'approved'  => (int)($_SESSION['approved'] ?? 1),
    ];
    redirectAfterLogin($u);
    exit;
}

$successMessage = $_GET['message'] ?? '';
$errors = [];
$info   = null;
$devCode = null;

// Helper: generate + email a code for (re)verification/2FA
function sendCodeFor(int $userId): ?string
{
    global $db;
    $user = $db->selectOne("SELECT email, name FROM users WHERE id = ?", [$userId]);
    if (!$user) return null;
    $code = generateVerificationCode();
    $db->update("UPDATE users SET verification_code = ? WHERE id = ?", [$code, $userId]);
    sendVerificationEmail($user['email'], $user['name'] ?? '', $code);
    return $code;
}

// Resend code
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    if (isset($_SESSION['user_id_for_verification'])) {
        $devCode = sendCodeFor((int)$_SESSION['user_id_for_verification']);
        $info = 'We’ve sent you a new verification code.';
    } elseif (isset($_SESSION['user_id_for_2fa'])) {
        $devCode = sendCodeFor((int)$_SESSION['user_id_for_2fa']);
        $info = 'We’ve sent you a new 2FA code.';
    }
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 2: code entry
    if (!empty($_POST['verification_code'])) {
        $code = sanitizeInput($_POST['verification_code']);

        if (isset($_SESSION['user_id_for_verification'])) {
            $uid = (int)$_SESSION['user_id_for_verification'];
            $res = verifyUser($uid, $code); // sets session on success
            if ($res['success']) {
                $row = $db->selectOne("SELECT user_type, is_approved, status FROM users WHERE id = ?", [$uid]);
                $_SESSION['approved'] = ((int)($row['is_approved'] ?? 0) === 1 || strtolower((string)($row['status'] ?? '')) === 'approved') ? 1 : 0;
                unset($_SESSION['user_id_for_verification']);
                redirectAfterLogin(['user_type' => $row['user_type'] ?? 'customer', 'approved' => (int)$_SESSION['approved']]);
            } else {
                $errors[] = $res['message'] ?? 'Verification failed.';
            }
        } elseif (isset($_SESSION['user_id_for_2fa'])) {
            $uid = (int)$_SESSION['user_id_for_2fa'];
            $res = verify2FA($uid, $code); // sets session on success
            if ($res['success']) {
                $row = $db->selectOne("SELECT user_type, is_approved, status FROM users WHERE id = ?", [$uid]);
                $_SESSION['approved'] = ((int)($row['is_approved'] ?? 0) === 1 || strtolower((string)($row['status'] ?? '')) === 'approved') ? 1 : 0;
                unset($_SESSION['user_id_for_2fa']);
                redirectAfterLogin(['user_type' => $row['user_type'] ?? 'customer', 'approved' => (int)$_SESSION['approved']]);
            } else {
                $errors[] = $res['message'] ?? 'Invalid 2FA code.';
            }
        } else {
            $errors[] = 'Your verification session has expired. Please sign in again.';
        }

    // Step 1: credentials
    } else {
        $identifier = sanitizeInput($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if ($identifier === '') $errors[] = 'Email or phone is required.';
        if ($password === '')   $errors[] = 'Password is required.';

        if (!$errors) {
            $user = $db->selectOne(
                "SELECT id, email, phone, name, password, is_verified, user_type
                   FROM users
                  WHERE email = ? OR phone = ?
                  LIMIT 1",
                [$identifier, $identifier]
            );

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid credentials.';
            } else {
                if (!(int)$user['is_verified']) {
                    $_SESSION['user_id_for_verification'] = (int)$user['id'];
                    $devCode = sendCodeFor((int)$user['id']);
                    $info = 'Account not verified. Enter the code we sent to your email.';
                } else {
                    $_SESSION['user_id_for_2fa'] = (int)$user['id'];
                    $devCode = sendCodeFor((int)$user['id']);
                    $info = 'We emailed you a 6-digit code. Enter it to continue.';
                }
            }
        }
    }
}

// Reset flow
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    unset($_SESSION['user_id_for_verification'], $_SESSION['user_id_for_2fa']);
    header('Location: login.php'); exit;
}

$stage = (isset($_SESSION['user_id_for_verification']) || isset($_SESSION['user_id_for_2fa'])) ? 'code' : 'login';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= htmlspecialchars(SITE_NAME) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

<style>
  :root{
    --brand:#db2777; --brand-600:#be185d; --ink:#1f2937; --muted:#6b7280;
  }
  /* Animated background: soft blobs + gradient */
  body{
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:
      radial-gradient(900px 400px at 10% -10%, #ffe8f3 0%, transparent 60%),
      radial-gradient(900px 400px at 90% 110%, #e6f0ff 0%, transparent 60%),
      linear-gradient(135deg,#f7f7fb 0%,#f1f5ff 100%);
    overflow-x:hidden;
  }
  .blob, .blob2{
    position:fixed; width:520px; height:520px; border-radius:50%;
    filter:blur(60px); opacity:.35; z-index:0; pointer-events:none;
    animation: float 16s ease-in-out infinite;
  }
  .blob{ background:#ff9ec5; top:-140px; left:-120px; }
  .blob2{ background:#a0b4ff; bottom:-160px; right:-140px; animation-delay:-6s; }

  @keyframes float{
    0%,100% { transform:translateY(0) translateX(0) scale(1) }
    50%     { transform:translateY(20px) translateX(10px) scale(1.03) }
  }

  /* Card wrapper + subtle 3D tilt */
  .auth-wrap{ position:relative; z-index:1; }
  .auth-card{
    width:min(940px, 92vw); border:0; border-radius:22px; overflow:hidden;
    box-shadow:0 30px 70px rgba(31,41,55,.14);
    transform-style:preserve-3d; will-change:transform;
  }

  /* Left graphic panel */
  .art{
    background: radial-gradient(800px 260px at 50% -60%, #ffd4e7 0%, transparent 60%), #fff;
    position:relative;
  }
  .art-wave{
    position:absolute; left:-15%; right:-15%; bottom:-1px; height:110px; color:#2f343a; opacity:.10;
    -webkit-mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
            mask:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
    background:currentColor;
  }
  .brand-badge{ width:84px; height:84px; border-radius:50%; background:#fff; box-shadow:0 12px 28px rgba(0,0,0,.08) }

  /* Right form panel */
  .pane{ background:#fff }
  .reveal{ opacity:0; transform:translateY(12px); transition:all .5s cubic-bezier(.16,.84,.44,1)}
  .reveal.in{ opacity:1; transform:none }
  .shake{ animation:shake .35s ease-in-out }
  @keyframes shake{
    0%{transform:translateX(0)}25%{transform:translateX(-6px)}50%{transform:translateX(6px)}75%{transform:translateX(-4px)}100%{transform:translateX(0)}
  }

  .form-control{
    height:52px; border-radius:12px; border:1px solid #e9e3ee; padding-left:46px;
  }
  .form-control:focus{
    border-color:#f4c1d9; box-shadow:0 0 0 4px rgba(219,39,119,.08);
  }
  .inp-ico{ position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#8b8f9a; }

  .btn-brand{
    --bs-btn-bg:var(--brand); --bs-btn-border-color:var(--brand);
    --bs-btn-hover-bg:var(--brand-600); --bs-btn-hover-border-color:var(--brand-600);
    height:52px; border-radius:12px; box-shadow:0 10px 20px rgba(219,39,119,.25)
  }
  .btn-ghost{ height:52px; border-radius:12px }

  .small-links a{ color:#6b7280; text-decoration:none }
  .small-links a:hover{ color:var(--brand) }

  /* code inputs */
  .code-input{ letter-spacing:.4em; text-align:center; font-size:1.15rem; }

  @media (max-width: 991.98px){
    .art{ display:none }
  }
</style>
</head>
<body>
<div class="blob"></div><div class="blob2"></div>

<div class="auth-wrap reveal">
  <div class="card auth-card">
    <div class="row g-0">
      <!-- Art / brand panel -->
      <div class="col-lg-5 art p-4 d-flex flex-column justify-content-between">
        <div>
          <a href="<?= app_url('index.php') ?>" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-4">
            <div class="brand-badge d-flex align-items-center justify-content-center">
              <i class="fa-solid fa-spa fa-2x text-danger"></i>
            </div>
            <div>
              <div class="fw-semibold fs-5 text-dark"><?= htmlspecialchars(SITE_NAME) ?></div>
              <div class="small text-secondary">Beauty & Event Experiences</div>
            </div>
          </a>

          <div class="mt-5">
            <h2 class="fw-semibold">Welcome back 👋</h2>
            <p class="text-secondary mb-0">Sign in to manage bookings, vendors and events. Your session is secured with email verification.</p>
          </div>
        </div>

        <div class="art-wave"></div>
      </div>

      <!-- Form pane -->
      <div class="col-lg-7 pane p-4 p-lg-5">
        <?php if (!empty($successMessage)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($info)): ?>
          <div class="alert alert-info d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($info) ?></span>
            <a class="small text-decoration-none" href="login.php?resend=1">Resend code</a>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger shake">
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($stage === 'login'): ?>
        <!-- Sign in -->
        <h3 class="mb-3">Sign in</h3>
        <p class="text-secondary mb-4">Use your email or phone and password.</p>

        <form method="POST" novalidate id="loginForm">
          <div class="mb-3 position-relative">
            <i class="fa-regular fa-envelope inp-ico"></i>
            <input type="text" class="form-control" name="identifier" placeholder="Email or Phone" required>
          </div>
          <div class="mb-3 position-relative">
            <i class="fa-solid fa-lock inp-ico"></i>
            <input type="password" class="form-control" name="password" id="pwd" placeholder="Password" required>
            <button type="button" class="btn btn-sm position-absolute top-50 end-0 translate-middle-y me-2 text-secondary" id="togglePwd" aria-label="Show password" style="background:transparent">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3 small-links">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember">
              <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="<?= app_url('forgot-password.php') ?>">Forgot password?</a>
          </div>

          <button class="btn btn-brand w-100 mb-3">Sign In</button>

          <div class="row g-2">
            <div class="col-6"><button type="button" class="btn btn-ghost w-100 border"><i class="fab fa-google me-2 text-danger"></i>Google</button></div>
            <div class="col-6"><button type="button" class="btn btn-ghost w-100 border"><i class="fab fa-facebook me-2 text-primary"></i>Facebook</button></div>
          </div>

          <div class="text-center mt-4 small-links">
            <p class="mb-1">New here? <a href="<?= app_url('register.php?type=customer') ?>" class="fw-semibold">Sign up as Customer</a></p>
            <p class="mb-0"><a href="<?= app_url('register.php?type=vendor') ?>" class="fw-semibold">Apply as Vendor</a> · <a href="<?= app_url('about.php') ?>" class="fw-semibold">About</a></p>
          </div>
        </form>

        <?php else: ?>
        <!-- Code verification -->
        <h3 class="mb-3">Verify your code</h3>
        <p class="text-secondary mb-4">We sent a 6-digit code to your email. Enter it below to continue.</p>

        <form method="POST" novalidate id="codeForm">
          <div class="mb-3 position-relative">
            <i class="fa-solid fa-key inp-ico"></i>
            <input type="text" name="verification_code" maxlength="6" class="form-control code-input" placeholder="••••••" inputmode="numeric" required>
          </div>

          <button class="btn btn-brand w-100 mb-3">Verify</button>

          <?php if ($devCode): ?>
            <div class="alert alert-warning small mb-0">
              <strong>Dev:</strong> <?= htmlspecialchars($devCode) ?>
            </div>
          <?php endif; ?>

          <div class="text-center mt-3 small-links">
            <a href="login.php?resend=1">Resend code</a> ·
            <a href="login.php?reset=1">Start over</a>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// entrance reveal
window.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.reveal').forEach(el => requestAnimationFrame(()=> el.classList.add('in')));
});

// card tilt (gentle)
const card = document.querySelector('.auth-card');
if(card){
  let rAF;
  card.addEventListener('mousemove', e=>{
    cancelAnimationFrame(rAF);
    rAF = requestAnimationFrame(()=>{
      const b = card.getBoundingClientRect();
      const x = (e.clientX - b.left) / b.width  - .5;
      const y = (e.clientY - b.top ) / b.height - .5;
      card.style.transform = `rotateX(${(-y*4)}deg) rotateY(${(x*6)}deg)`;
    });
  });
  ['mouseleave','blur'].forEach(ev=>card.addEventListener(ev,()=>{ card.style.transform='rotateX(0) rotateY(0)'; }));
}

// password toggle
const t = document.getElementById('togglePwd');
if(t){
  t.addEventListener('click', ()=>{
    const p = document.getElementById('pwd');
    const is = p.type === 'password';
    p.type = is ? 'text' : 'password';
    t.innerHTML = is ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
  });
}
</script>
</body>
</html>
