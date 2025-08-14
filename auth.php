<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    session_set_cookie_params(SESSION_TIMEOUT);
    session_start();
}

function registerUser($name, $email, $phone, $address, $password, $userType, $profileImage = null) {
    global $db;
    $name = sanitizeInput($name);
    $email = sanitizeInput($email);
    $phone = sanitizeInput($phone);
    $address = sanitizeInput($address);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }

    // Validate phone
    if (!preg_match('/^0\d{10}$/', $phone)) {
        return ['success' => false, 'message' => 'Phone number must start with 0 and contain exactly 11 digits'];
    }

    // Validate password strength
    $passwordCheck = isStrongPassword($password);
    if (!$passwordCheck['valid']) {
        return ['success' => false, 'message' => $passwordCheck['message']];
    }

    // Validate user type
    if (!in_array($userType, ['customer', 'vendor', 'admin'])) {
        return ['success' => false, 'message' => 'Invalid user type'];
    }

    // Check if email already exists
    $existingUser = $db->selectOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existingUser) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

    // Generate verification code
    $verificationCode = generateVerificationCode();

    // Insert user into database
    $query = "INSERT INTO users (name, email, phone, address, password, user_type, profile_image, verification_code, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $params = [$name, $email, $phone, $address, $hashedPassword, $userType, $profileImage, $verificationCode];
    $success = $db->insert($query, $params);

    if ($success) {
        $userId = $db->selectOne("SELECT id FROM users WHERE email = ?", [$email])['id'];
        // Send verification email
        if (sendVerificationEmail($email, $name, $verificationCode)) {
            return [
                'success' => true,
                'user_id' => $userId,
                'verification_code' => $verificationCode // For development
            ];
        } else {
            // Rollback: delete user if email sending fails
            $db->delete("DELETE FROM users WHERE id = ?", [$userId]);
            return ['success' => false, 'message' => 'Failed to send verification email'];
        }
    }

    return ['success' => false, 'message' => 'Registration failed'];
}

function verifyUser($userId, $code) {
    global $db;

    $user = $db->selectOne("SELECT id, verification_code FROM users WHERE id = ? AND is_verified = 0", [$userId]);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid user or already verified'];
    }

    if ($user['verification_code'] !== $code) {
        return ['success' => false, 'message' => 'Invalid verification code'];
    }

    // Mark user as verified
    $updated = $db->update("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?", [$userId]);
    if ($updated) {
        // Fetch user data to set session variables
        $userData = $db->selectOne("SELECT id, name, email, user_type, profile_image, address FROM users WHERE id = ?", [$userId]);
        if ($userData) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['user_name'] = $userData['name'];
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['user_type'] = $userData['user_type'];
            $_SESSION['profile_image'] = $userData['profile_image'];
            $_SESSION['user_location'] = $userData['address'];
            $_SESSION['last_activity'] = time();
            return ['success' => true, 'message' => 'Account verified successfully', 'user_id' => $userId];
        }
    }

    return ['success' => false, 'message' => 'Verification failed'];
}

function loginUser($email, $password) {
    global $db;

    $user = $db->selectOne("SELECT id, password, is_verified, user_type, name, email, profile_image, address FROM users WHERE email = ?", [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    if (!$user['is_verified']) {
        // Resend verification code if not verified
        $newCode = generateVerificationCode();
        $db->update("UPDATE users SET verification_code = ? WHERE id = ?", [$newCode, $user['id']]);
        sendVerificationEmail($email, $user['name'], $newCode);
        return [
            'success' => false,
            'needs_verification' => true,
            'user_id' => $user['id'],
            'verification_code' => $newCode // For development
        ];
    }

    // Generate 2FA code
    $twoFactorCode = generateVerificationCode();
    $db->update("UPDATE users SET verification_code = ? WHERE id = ?", [$twoFactorCode, $user['id']]);
    sendVerificationEmail($email, $user['name'], $twoFactorCode);

    session_regenerate_id(true); // Prevent session fixation

    return [
        'success' => true,
        'needs_2fa' => true,
        'user_id' => $user['id'],
        'verification_code' => $twoFactorCode // For development
    ];
}

function verify2FA($userId, $code) {
    global $db;

    $user = $db->selectOne("SELECT id, verification_code, user_type, name, email, profile_image, address FROM users WHERE id = ? AND is_verified = 1", [$userId]);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid user'];
    }

    if ($user['verification_code'] !== $code) {
        return ['success' => false, 'message' => 'Invalid 2FA code'];
    }

    // Clear 2FA code
    $db->update("UPDATE users SET verification_code = NULL WHERE id = ?", [$userId]);

    // Set session variables
    session_regenerate_id(true); // Prevent session fixation
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['profile_image'] = $user['profile_image'];
    $_SESSION['user_location'] = $user['address'];
    $_SESSION['last_activity'] = time();

    return ['success' => true, 'message' => '2FA verified successfully', 'user_id' => $userId];
}

function logoutUser() {
    session_unset();
    session_destroy();
    session_start();
}

function checkSessionTimeout() {
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }

    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        logoutUser();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

function resetPassword($email) {
    global $db;

    $user = $db->selectOne("SELECT id FROM users WHERE email = ? AND is_verified = 1", [$email]);
    if (!$user) {
        return ['success' => false, 'message' => 'Email not found or not verified'];
    }

    $resetCode = generateVerificationCode();
    $db->update("UPDATE users SET verification_code = ? WHERE id = ?", [$resetCode, $user['id']]);
    sendVerificationEmail($email, '', $resetCode);

    return ['success' => true, 'message' => 'Password reset code sent to your email', 'user_id' => $user['id'], 'reset_code' => $resetCode];
}

function updatePassword($userId, $code, $newPassword) {
    global $db;

    $user = $db->selectOne("SELECT verification_code FROM users WHERE id = ?", [$userId]);
    if (!$user || $user['verification_code'] !== $code) {
        return ['success' => false, 'message' => 'Invalid reset code'];
    }

    $passwordCheck = isStrongPassword($newPassword);
    if (!$passwordCheck['valid']) {
        return ['success' => false, 'message' => $passwordCheck['message']];
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    $updated = $db->update("UPDATE users SET password = ?, verification_code = NULL WHERE id = ?", [$hashedPassword, $userId]);

    if ($updated) {
        return ['success' => true, 'message' => 'Password updated successfully'];
    }

    return ['success' => false, 'message' => 'Failed to update password'];
}
?>