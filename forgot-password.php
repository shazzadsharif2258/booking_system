<?php
// Enable error reporting for debugging (disable display to avoid non-JSON output)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
error_log("Starting forgot-password.php");

// Ensure JSON content type
header('Content-Type: application/json');

// Start session
session_start();

// Load dependencies
try {
    require_once 'includes/config.php';
    require_once 'includes/functions.php';
    error_log("Dependencies loaded");

    // Check database connection
    if (!isset($db)) {
        error_log("Database connection not initialized");
        throw new Exception('Database connection not initialized');
    }
    if (!method_exists($db, 'selectOne') || !method_exists($db, 'update')) {
        error_log("Database object missing required methods");
        throw new Exception('Database object missing required methods');
    }
    error_log("Database connection verified");

    // Password validation function
    function validatePassword($password) {
        // Check minimum length
        if (strlen($password) < 8) {
            return "Password must be at least 8 characters long";
        }
        // Check for at least one uppercase letter
        if (!preg_match("/[A-Z]/", $password)) {
            return "Password must contain at least one uppercase letter";
        }
        // Check for at least one lowercase letter
        if (!preg_match("/[a-z]/", $password)) {
            return "Password must contain at least one lowercase letter";
        }
        // Check for at least one number
        if (!preg_match("/[0-9]/", $password)) {
            return "Password must contain at least one number";
        }
        // Check for at least one special character
        if (!preg_match("/[!@#$%^&*()_+\-=\[\]{};:'\",.<>?]/", $password)) {
            return "Password must contain at least one special character (e.g., !@#$%^&*)";
        }
        return true;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log("Invalid request method: {$_SERVER['REQUEST_METHOD']}");
        throw new Exception('Invalid request method');
    }

    error_log("Reading POST data");
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON input: " . json_last_error_msg());
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    error_log("POST data parsed: " . json_encode($data));

    $action = $data['action'] ?? '';
    $user_type = $data['user_type'] ?? '';

    if ($action === 'check_email') {
        $email = sanitizeInput($data['email'] ?? '');
        error_log("Processing check_email for email: $email, user_type: $user_type");

        if (empty($email)) {
            throw new Exception('Email is required');
        }

        $user = $db->selectOne("SELECT * FROM users WHERE email = ? AND user_type = ?", [$email, $user_type]);
        if (!$user) {
            error_log("Email not found: $email for user_type: $user_type");
            throw new Exception('Email not found for this user type');
        }
        error_log("User found for email: $email");

        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_user_type'] = $user_type;
        error_log("Session set, responding with success");

        echo json_encode(['status' => 'success', 'message' => 'Email verified. Redirecting to set new password']);
        exit;
    }

    if ($action === 'reset_password') {
        $newPassword = $data['new_password'] ?? '';
        error_log("Processing reset_password");

        if (empty($newPassword)) {
            throw new Exception('New password is required');
        }

        // Validate password strength
        $passwordValidation = validatePassword($newPassword);
        if ($passwordValidation !== true) {
            throw new Exception($passwordValidation);
        }

        if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_type'])) {
            error_log("Session expired");
            throw new Exception('Session expired. Please start over');
        }

        $email = $_SESSION['reset_email'];
        $user_type = $_SESSION['reset_user_type'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        error_log("Hashed new password for email: $email");

        $db->update(
            "UPDATE users SET password = ? WHERE email = ? AND user_type = ?",
            [$hashedPassword, $email, $user_type]
        );
        error_log("Password updated for email: $email");

        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_user_type']);
        error_log("Session cleared, responding with success");
        echo json_encode(['status' => 'success', 'message' => 'Password reset successfully. You can now log in']);
        exit;
    }

    error_log("Invalid action: $action");
    throw new Exception('Invalid action');
} catch (Exception $e) {
    error_log("Error in forgot-password.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
} catch (Throwable $t) {
    error_log("Fatal error in forgot-password.php: " . $t->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $t->getMessage()]);
    exit;
}
?>