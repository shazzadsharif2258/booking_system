<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../functions.php';

// Process verification form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code']) && !empty($_POST['verification_code'])) {
    $verificationCode = sanitizeInput($_POST['verification_code']);
    $submittedToken = $_POST['verification_token'] ?? '';

    // Validate session token to prevent concurrent session issues
    if (!isset($_SESSION['verification_token']) || $submittedToken !== $_SESSION['verification_token']) {
        header('Location: login.php?message=' . urlencode('Invalid session. Please try logging in again.'));
        exit;
    }

    // Process account verification
    if (isset($_SESSION['user_id_for_verification'])) {
        $verifyResult = verifyUser($_SESSION['user_id_for_verification'], $verificationCode);
        
        if ($verifyResult['success']) {
            // Clean up session variables
            unset($_SESSION['user_id_for_verification']);
            unset($_SESSION['verification_token']);
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = $verifyResult['message'];
            $needsVerification = true;
        }
    } 
    // Process 2FA verification
    elseif (isset($_SESSION['user_id_for_2fa'])) {
        $verify2FAResult = verify2FA($_SESSION['user_id_for_2fa'], $verificationCode);
        
        if ($verify2FAResult['success']) {
            // Clean up session variables
            unset($_SESSION['user_id_for_2fa']);
            unset($_SESSION['verification_token']);
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = $verify2FAResult['message'];
            $needs2FA = true;
        }
    } 
    else {
        header('Location: login.php?message=' . urlencode('Verification session expired. Please try logging in again to resend the code.'));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account - Event Management System</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #e67e22;
            outline: none;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #222;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #000;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .form-footer a {
            color: #e67e22;
            text-decoration: none;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <h2>Verify Your Account</h2>
        </div>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            Please enter the verification code sent to your email.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="verification_token" value="<?php echo $_SESSION['verification_token'] ?? ''; ?>">
            <div class="form-group">
                <label for="verification_code">Verification Code</label>
                <input type="text" id="verification_code" name="verification_code" class="form-control" placeholder="Enter verification code" maxlength="6" required>
            </div>
            
            <button type="submit" class="btn">Verify</button>
            
            <div class="form-footer">
                <p><a href="login.php?reset=1">Start Over</a></p>
            </div>
        </form>
    </div>
</body>
</html>