<?php
$pageTitle = 'Two-Factor Authentication';
$includeAuth = true;

require_once '../auth.php';

// Check if user ID is set
if (!isset($_SESSION['user_id_for_2fa'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id_for_2fa'];
$email = $_SESSION['user_email_for_2fa'] ?? '';

// Handle resend code request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {
    $result = resetPassword($email); // Using resetPassword to generate a new code
    if ($result['success']) {
        $_SESSION['user_id_for_2fa'] = $result['user_id'];
        echo json_encode(['success' => true, 'message' => 'New code sent to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
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
        // Verify 2FA
        $result = verify2FA($userId, $code);
        
        if ($result['success']) {
            // Clear session variables
            unset($_SESSION['user_id_for_2fa']);
            unset($_SESSION['user_email_for_2fa']);
            
            // Redirect to dashboard
            header('Location: dashboard.php');
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
        
        <h2>Two-Factor Authentication</h2>
        <p>We've sent a 6-digit verification code to <strong><?php echo $email; ?></strong>. Please enter the code below to complete your login.</p>
        
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
            
            <button type="submit" class="btn btn-block">Verify and Login</button>
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