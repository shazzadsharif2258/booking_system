<?php
// /customer/profile.php
session_start();
$pageTitle = 'Customer Profile';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn() || !isUserType('customer')) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$user   = getUserData($userId) ?: [];

// paths/urls come from config.php
$FS_PROFILES  = rtrim($uploadDirs['profiles'], '/\\') . DIRECTORY_SEPARATOR;
$URL_PROFILES = rtrim($uploadUrls['profiles'], '/') . '/';
$URL_ASSETS   = rtrim(app_url('assets/images/'), '/') . '/';

if (!is_dir($FS_PROFILES)) {
    @mkdir($FS_PROFILES, 0755, true);
}

/* ---------- Handle update ---------- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitizeInput($_POST['name'] ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $phone    = sanitizeInput($_POST['phone'] ?? '');
    $address  = sanitizeInput($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '')  $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    elseif (!preg_match('/^0\d{10}$/', $phone)) $errors[] = 'Phone must start with 0 and contain 11 digits.';
    if ($address === '') $errors[] = 'Address is required.';

    // Image upload (optional)
    $newImage = $user['profile_image'] ?? '';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['profile_image']['tmp_name'];
        $type = @mime_content_type($tmp);
        $ok   = in_array($type, ['image/jpeg', 'image/png', 'image/gif'], true);
        $size = (int)$_FILES['profile_image']['size'];

        if (!$ok)              $errors[] = 'Only JPG, PNG, or GIF images are allowed.';
        elseif ($size > 5 * 1024 * 1024) $errors[] = 'Image must be 5MB or smaller.';
        else {
            $ext   = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $fname = 'pf_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $FS_PROFILES . $fname)) {
                $newImage = $fname; // store only filename in users.profile_picture
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (!$errors) {
        $sql    = "UPDATE users SET name=?, email=?, phone=?, address=?";
        $params = [$name, $email, $phone, $address];

        if ($password !== '') {
            $check = isStrongPassword($password);
            if (!$check['valid']) {
                $errors[] = $check['message'];
            } else {
                $sql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
        }
        if (($user['profile_image'] ?? '') !== $newImage) {
            $sql .= ", profile_image=?";
            $params[] = $newImage;
        }

        $sql .= " WHERE id=?";
        $params[] = $userId;

        if (!$errors) {
            if ($db->update($sql, $params) !== false) {
                $_SESSION['user_name']      = $name;
                $_SESSION['user_location']  = $address;
                if ($newImage) $_SESSION['profile_image'] = $newImage;
                $_SESSION['flash_message']  = 'Profile updated successfully.';
                header('Location: profile.php');
                exit;
            } else {
                $errors[] = 'Failed to update profile.';
            }
        }
    }
}

/* ---------- Avatar URL ---------- */
$avatarUrl = $URL_ASSETS . 'placeholder.jpg';


if (!empty($user['profile_image']) && is_file($FS_PROFILES . $user['profile_image'])) {
    $avatarUrl = $URL_PROFILES . rawurlencode($user['profile_image']);
}

/* ---------- Booking history (simple) ---------- */
$bookings = $db->select("
    SELECT b.id, b.total_cost, b.status, b.event_date, b.created_at, e.name AS event_name
    FROM bookings b
    LEFT JOIN booking_items bi ON bi.booking_id=b.id
    LEFT JOIN events e ON e.id=bi.event_id
    WHERE b.customer_id=?
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 10
", [$userId]) ?: [];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> · <?= htmlspecialchars(SITE_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #db2777;
            --brand-600: #be185d;
            --muted: #6b7280;
        }

        body {
            background: #fff;
        }

        .brand-dot {
            width: 10px;
            height: 10px;
            background: var(--brand);
            border-radius: 50%
        }

        .btn-brand {
            --bs-btn-bg: var(--brand);
            --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: var(--brand-600);
            --bs-btn-hover-border-color: var(--brand-600)
        }

        .sidebar-card {
            border: 1px solid #f1e9f3;
            border-radius: 16px
        }

        .pane-card {
            border: 1px solid #efe5ef;
            border-radius: 16px
        }

        .nav-pills .nav-link {
            color: #111;
            border-radius: 10px
        }

        .nav-pills .nav-link.active {
            background: #fde7f1;
            color: #111;
            border: 1px solid #ffd3e6
        }

        .waterband {
            position: relative;
            padding: 34px 0 50px;
            background: linear-gradient(180deg, #ffffff 0%, #fff3f8 100%)
        }

        .waterband:after {
            content: "";
            position: absolute;
            left: -20%;
            right: -20%;
            bottom: -1px;
            height: 90px;
            color: #2f343a;
            background: currentColor;
            opacity: .10;
            -webkit-mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
            mask: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120"><path d="M0,64 C320,160 1120,0 1440,80 L1440,120 L0,120 Z" fill="white"/></svg>') center/100% 100% no-repeat;
        }

        .card-hover {
            transition: transform .15s ease, box-shadow .25s ease
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(0, 0, 0, .08)
        }

        .muted {
            color: var(--muted)
        }
    </style>
</head>

<body>

    <!-- Top bar -->
    <nav class="navbar bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
                <span class="brand-dot"></span>
                <span class="fw-semibold"><?= htmlspecialchars(SITE_NAME) ?></span>
            </a>
            <ul class="nav">
                <li class="nav-item"><a class="nav-link text-dark" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="../customer/dashboard.php">Services</a></li>
                <li class="nav-item"><a class="nav-link active text-dark fw-semibold" href="profile.php">Profile</a></li>
            </ul>
        </div>
    </nav>

    <!-- Title + wave -->
    <section class="waterband">
        <div class="container">
            <h1 class="display-6 fw-semibold">Your Profile</h1>
        </div>
    </section>

    <main class="container pb-5" style="margin-top:-24px;">
        <?php if (!empty($_SESSION['flash_message'])): ?>
            <div class="alert alert-success shadow-sm">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['flash_message']);
                                                                unset($_SESSION['flash_message']); ?>
            </div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left: profile summary + tabs -->
            <div class="col-lg-4">
                <div class="sidebar-card p-3 p-lg-4 bg-white card-hover">
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" class="rounded-circle object-fit-cover mb-3" style="width:96px;height:96px;border:3px solid #f3e8ef" alt="Avatar">
                        <div class="fw-semibold"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                        <div class="small muted"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    </div>

                    <div class="nav nav-pills flex-column gap-2" role="tablist">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-info" type="button" role="tab">
                            Personal Information
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-history" type="button" role="tab">
                            Booking History
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-settings" type="button" role="tab">
                            Settings
                        </button>
                    </div>

                    <a href="../logout.php" class="btn btn-outline-secondary w-100 mt-3">Logout</a>
                </div>
            </div>

            <!-- Right: panes -->
            <div class="col-lg-8">
                <div class="tab-content">
                    <!-- Personal Information -->
                    <div class="tab-pane fade show active" id="pane-info" role="tabpanel">
                        <div class="pane-card bg-white p-3 p-lg-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Personal Information</h5>
                                <button class="btn btn-sm btn-brand" data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="fa-regular fa-pen-to-square me-1"></i>Edit
                                </button>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="small muted">Full Name</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="small muted">Email Address</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="small muted">Phone Number</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($user['phone'] ?? '—') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="small muted">Address</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($user['address'] ?? '—') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking History -->
                    <div class="tab-pane fade" id="pane-history" role="tabpanel">
                        <div class="pane-card bg-white p-3 p-lg-4">
                            <h5 class="mb-3">Booking History</h5>
                            <?php if (!$bookings): ?>
                                <div class="alert alert-light border">No bookings yet.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Event</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bookings as $b): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?= (int)$b['id'] ?></td>
                                                    <td><?= htmlspecialchars($b['event_name'] ?? '—') ?></td>
                                                    <td><span class="badge" style="background:#ffe7f1;color:#000;border:1px solid #ffd3e6">
                                                            <?= htmlspecialchars(ucfirst($b['status'])) ?></span></td>
                                                    <td class="text-secondary small">
                                                        <?= $b['event_date'] ? date('d M Y', strtotime($b['event_date'])) : date('d M Y', strtotime($b['created_at'])) ?>
                                                    </td>
                                                    <td class="text-end fw-semibold"><?= CURRENCY_SYMBOL . number_format((float)$b['total_cost'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="tab-pane fade" id="pane-settings" role="tabpanel">
                        <div class="pane-card bg-white p-3 p-lg-4">
                            <h5 class="mb-3">Settings</h5>
                            <p class="text-secondary mb-0">More preferences coming soon.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" class="rounded-circle object-fit-cover" style="width:96px;height:96px;border:3px solid #f3e8ef" alt="">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input class="form-control" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                        <div class="form-text">Must start with 0 and be 11 digits.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input class="form-control" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-secondary">(optional)</span></label>
                        <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Profile Image</label>
                        <input type="file" class="form-control" name="profile_image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-brand" type="submit">Save Changes</button>
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="border-top py-4 small text-center text-secondary">
        © <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>