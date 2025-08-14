<?php
$pageTitle = 'Verify Account';
$includeAuth = true;

require_once '../auth.php';

// Check if user ID is set
if (!isset($_SESSION['user_id_for_verification'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id_for_verification'];
$email = $_SESSION['registration_email'] ?? '';

// Handle resend code request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {
    $user = getUserData($userId);
    if ($user) {
        $newCode = generateVerificationCode();
        $db->update("UPDATE users SET verification_code = ? WHERE id = ?", [$newCode, $userId]);
        sendVerificationEmail($user['email'], $user['name'], $newCode);
        echo json_encode(['success' => true, 'message' => 'New code sent to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// Process verification form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $code = sanitizeInput($_POST['verification_code']);
    
    // Validate code
    if (empty($code)) {
        $errors[] = 'Verification code is required';
    } elseif (strlen($code) !== 6) {
        $errors[] = 'Verification code must be 6 digits';
    }
    
    if (empty($errors)) {
        // Verify user
        $result = verifyUser($userId, $code);
        
        if ($result['success']) {
            // Clear session variables
            unset($_SESSION['user_id_for_verification']);
            unset($_SESSION['registration_email']);
            
            // Set success message
            $_SESSION['flash_message'] = $result['message'];
            $_SESSION['flash_type'] = 'success';
            
            // Redirect to login page
            header('Location: login.php');
            exit;
        } else {
            $errors[] = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="verification-container">
        <div class="logo">
            <a href="../index.php">
                <img src="../assets/images/logo.png" alt="<?php echo SITE_NAME; ?>">
            </a>
        </div>
        
        <h2>Verify Your Account</h2>
        <p>We've sent a 6-digit verification code to <strong><?php echo $email; ?></strong>. Please enter the code below to verify your account.</p>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form id="verification-form" method="POST" action="">
            <div class="verification-code">
                <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            </div>
            
            <input type="hidden" id="verification-code" name="verification_code">
            
            <button type="submit" class="btn btn-block">Verify Account</button>
        </form>
        
        <div class="form-footer">
            <p>Didn't receive the code? <a href="#" id="resend-code" data-user-id="<?php echo $userId; ?>">Resend Code</a></p>
            <p><a href="login.php">Back to Login</a></p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.verification-code input');
            const hiddenInput = document.getElementById('verification-code');
            
            inputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    if (this.value.length === 1 && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    updateHiddenInput();
                });
                
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        inputs[index - 1].focus();
                    }
                });
            });
            
            function updateHiddenInput() {
                const code = Array.from(inputs).map(input => input.value).join('');
                hiddenInput.value = code;
            }
            
            // Handle resend code
            document.getElementById('resend-code').addEventListener('click', function(e) {
                e.preventDefault();
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=resend'
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                })
                .catch(err => {
                    console.error('Error resending code:', err);
                    alert('Error resending code. Please try again.');
                });
            });
        });
    </script>
</body>
</html>