<?php
// router.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/* always build absolute URLs */
function go(string $path): void {
  if (function_exists('app_url')) {
    header('Location: ' . app_url($path));
  } else {
    // fallback (shouldn’t hit if config.php is loaded)
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header('Location: ' . ($base === '/' ? '' : $base) . '/' . ltrim($path, '/'));
  }
  exit;
}

function isVendorApproved(int $userId): bool {
  global $db;
  $row = $db->selectOne("SELECT is_approved, status FROM users WHERE id = ?", [$userId]);
  return (int)($row['is_approved'] ?? 0) === 1 || strtolower((string)($row['status'] ?? '')) === 'approved';
}

/* where to land after login */
function redirectAfterLogin(array $ctx = []) {
  $type = $ctx['user_type'] ?? ($_SESSION['user_type'] ?? 'customer');

  if ($type === 'admin') {
    go('admin/index.php');
  }

  if ($type === 'vendor') {
    $uid = $_SESSION['user_id'] ?? null;
    $approved = isset($ctx['approved']) ? (int)$ctx['approved'] : ($uid ? (int)isVendorApproved($uid) : 0);
    // ✅ correct filename and correct folder
    $approved ? go('vendor/dashboard.php') : go('vendor/pending.php');
  }

  go('customer/dashboard.php');
}


/* guards */
function requireAuth(): void {
  if (empty($_SESSION['user_id'])) {
    go('login.php?message=' . urlencode('Please sign in to continue.'));
  }
}
function requireRole(string $role): void {
  requireAuth();
  if (($_SESSION['user_type'] ?? '') !== $role) {
    go('login.php?message=' . urlencode('You do not have access to that page.'));
  }
}
function requireApprovedVendor(): void {
  requireRole('vendor');
  if (!isVendorApproved((int)$_SESSION['user_id'])) {
    go('vendor/pending.php');
  }
}
