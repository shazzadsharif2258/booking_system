<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor_comp/autoload.php';

function sendPHPMailerVerificationEmail($email, $name, $code) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sayebahmed1234@gmail.com';
        $mail->Password = 'tgzh copa aqwo cwzv';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('sayebahmed1234@gmail.com', 'Homemade Food Delivery');
        $mail->addAddress($email, $name ?: 'User');

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verification Code for AnyBooking';
        $mail->Body = "
            <h2>Verification Code</h2>
            <p>Dear " . ($name ?: 'User') . ",</p>
            <p>Your verification code is: <strong>$code</strong></p>
            <p>Please enter this code to complete your login or registration.</p>
            <p>If you did not request this code, please ignore this email.</p>
            <p>Best regards,<br>AnyBooking</p>
        ";
        $mail->AltBody = "Your verification code is: $code\n\nPlease enter this code to complete your login or registration.\nIf you did not request this code, please ignore this email.\n\nBest regards,\nHomemade Food Delivery Team";

        $mail->send();
        error_log("Verification email sent to $email with code $code");
        return true;
    } catch (Exception $e) {
        error_log("Failed to send verification email to $email. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>