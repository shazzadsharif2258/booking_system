<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

if (!function_exists('timeAgo')) {
  function timeAgo($dt) {
    if (!$dt) return '';
    $t = is_numeric($dt) ? (int)$dt : @strtotime($dt);
    if (!$t) return '';
    $diff = time() - $t;
    if ($diff < 60) return $diff . 's ago';
    $diff = intdiv($diff, 60);  if ($diff < 60) return $diff . 'm ago';
    $diff = intdiv($diff, 60);  if ($diff < 24) return $diff . 'h ago';
    $diff = intdiv($diff, 24);  if ($diff < 30) return $diff . 'd ago';
    $diff = intdiv($diff, 30);  if ($diff < 12) return $diff . 'mo ago';
    return intdiv($diff, 12) . 'y ago';
  }
}

function getCategories() {
    global $db;
    try {
        $categories = $db->select("SELECT id, name FROM categories WHERE 1=1", []);
        return $categories ?: [];
    } catch (Exception $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        return [];
    }
}

function generateVerificationCode($length = 6) {
    return str_pad(mt_rand(0, 999999), $length, '0', STR_PAD_LEFT);
}
function activeNav($file){
  $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  return (substr($p, -strlen($file)) === $file) ? ' active' : '';
}
function uploadImage($file, $uploadDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxSize = 5 * 1024 * 1024) {
    if ($file['error'] !== 0) {
        return ['success' => false, 'message' => 'File upload error'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, and GIF images are allowed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Image size should not exceed 5MB'];
    }

    // Verify image content
    if (!getimagesize($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid image file'];
    }

    $fileName = uniqid() . '_' . sanitizeFileName(basename($file['name']));
    $uploadPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'fileName' => $fileName];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image'];
    }
}

function sanitizeFileName($filename) {
    // Remove dangerous characters and replace spaces with underscores
    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '', str_replace(' ', '_', $filename));
    return $filename;
}

function isStrongPassword($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one number'];
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one special character'];
    }
    return ['valid' => true, 'message' => 'Password is strong'];
}

function sendVerificationEmail($email, $name, $code) {
    return sendPHPMailerVerificationEmail($email, $name, $code);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    // Trim input and return empty string if null
    $input = trim($input ?? '');
    if ($input === '') {
        return '';
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && checkSessionTimeout();
}

function isUserType($type) {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === $type;
}

function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

function getUserData($userId) {
    global $db;
    return $db->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
}

function getEventsByVendor($vendorId) {
    global $db;
    return $db->select("SELECT e.*, u.name as vendor_name FROM events e JOIN users u ON e.vendor_id = u.id WHERE e.vendor_id = ? AND e.is_available = 1", [$vendorId]);
}

function getNearbyEvents($limit = 10) {
    global $db;
    return $db->select("SELECT e.*, u.name as vendor_name FROM events e JOIN users u ON e.vendor_id = u.id WHERE e.is_available = 1 ORDER BY RAND() LIMIT ?", [$limit]);
}

function getBookingDetails($bookingId) {
    global $db;
    $booking = $db->selectOne("SELECT b.*, u.name as customer_name FROM bookings b JOIN users u ON b.customer_id = u.id WHERE b.id = ?", [$bookingId]);
    if ($booking) {
        $booking['items'] = $db->select("SELECT bi.*, e.name as event_name FROM booking_items bi JOIN events e ON bi.event_id = e.id WHERE bi.booking_id = ?", [$bookingId]);
    }
    return $booking;
}

function getTopRatedEvents($limit = 5) {
    global $db;
    return $db->select(
        "SELECT e.*, u.name as vendor_name, AVG(f.rating) as avg_rating 
         FROM events e 
         JOIN users u ON e.vendor_id = u.id 
         LEFT JOIN feedback f ON e.id = f.event_id 
         WHERE e.is_available = 1 
         GROUP BY e.id 
         ORDER BY avg_rating DESC 
         LIMIT ?",
        [$limit]
    );
}

function getEventTypes() {
    return [
        'Birthday' => 'Birthday',
        'Wedding' => 'Wedding',
        'Corporate' => 'Corporate'
    ];
}

function formatPrice($price) {
    return CURRENCY_SYMBOL . number_format($price, 2);
}
?>