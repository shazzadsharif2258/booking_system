<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .password-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            color: #666;
            margin-bottom: 5px;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #f4a261; /* Orange border */
            border-radius: 5px;
            font-size: 1em;
            outline: none;
            box-sizing: border-box;
        }
        #passwordFeedback {
            display: block;
            font-size: 0.9em;
            margin-top: 5px;
            transition: color 0.3s ease;
        }
        .requirements {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }
        button {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        #setPasswordBtn {
            background-color: #333;
            color: #fff;
        }
        #cancelBtn {
            background-color: #ccc;
            color: #333;
        }
        #setPasswordBtn:hover {
            background-color: #555;
        }
        #cancelBtn:hover {
            background-color: #bbb;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <h2>SET NEW PASSWORD</h2>
        <form id="resetPasswordForm" onsubmit="submitForm(event)">
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input type="password" id="newPassword" name="new_password" required>
                <span id="passwordFeedback"></span>
                <div class="requirements">
                    Password must:
                    <ul>
                        <li>Be at least 8 characters long</li>
                        <li>Contain at least one uppercase letter</li>
                        <li>Contain at least one lowercase letter</li>
                        <li>Contain at least one number</li>
                        <li>Contain at least one special character (e.g., !@#$%^&*)</li>
                    </ul>
                </div>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <input type="password" id="confirmPassword" name="confirm_password" required>
                <span id="confirmFeedback"></span>
            </div>
            <button type="submit" id="setPasswordBtn">Set Password</button>
            <button type="button" id="cancelBtn" onclick="window.location.href='login.php'">Cancel</button>
        </form>
    </div>

    <script>
        // Real-time password strength validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('newPassword');
            const passwordFeedback = document.getElementById('passwordFeedback');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const confirmFeedback = document.getElementById('confirmFeedback');

            if (newPasswordInput && passwordFeedback && confirmPasswordInput && confirmFeedback) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    let feedback = '';
                    let isStrong = false;

                    // Check password strength criteria
                    const hasUpperCase = /[A-Z]/.test(password);
                    const hasLowerCase = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};:'",.<>?]/.test(password);
                    const isLongEnough = password.length >= 8;

                    if (password.length === 0) {
                        feedback = '';
                    } else if (isLongEnough && hasUpperCase && hasLowerCase && hasNumber && hasSpecial) {
                        feedback = 'Strong password';
                        isStrong = true;
                    } else {
                        feedback = 'Weak password';
                        if (!isLongEnough) feedback += ' (Minimum 8 characters)';
                        if (!hasUpperCase) feedback += ' (Need uppercase)';
                        if (!hasLowerCase) feedback += ' (Need lowercase)';
                        if (!hasNumber) feedback += ' (Need number)';
                        if (!hasSpecial) feedback += ' (Need special character)';
                    }

                    // Update feedback text and color
                    passwordFeedback.textContent = feedback;
                    passwordFeedback.style.color = isStrong ? '#2e7d32' : '#dc3545'; // Green for strong, red for weak
                });

                // Confirm password matching validation
                confirmPasswordInput.addEventListener('input', function() {
                    const confirmPassword = this.value;
                    const newPassword = newPasswordInput.value;
                    let feedback = '';

                    if (confirmPassword.length === 0) {
                        feedback = '';
                    } else if (confirmPassword === newPassword) {
                        feedback = 'Passwords match';
                        confirmFeedback.style.color = '#2e7d32'; // Green
                    } else {
                        feedback = 'Passwords do not match';
                        confirmFeedback.style.color = '#dc3545'; // Red
                    }

                    confirmFeedback.textContent = feedback;
                });
            }
        });

        // AJAX submission function
        function submitForm(event) {
            event.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }

            fetch('forgot-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reset_password',
                    new_password: newPassword,
                    user_type: 'customer' // Adjust user_type as needed
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    window.location.href = 'login.php'; // Redirect on success
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>