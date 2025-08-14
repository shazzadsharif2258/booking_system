<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../db.php';

// Ensure the user is authenticated and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(403);
    echo 'Unauthorized access';
    exit;
}

// Hardcode Stripe secret key (for testing only)
$stripeSecretKey = 'sk_test_51RM8NP2MRj4ABXCGDeOUhgxYTS0wDJrGEPJmogpTBqLuYkvNpgyvkEhBw4Rz20fu1AcnCxJs0CKurL3MI9w8HMGn00ZgkfB2HA';
if (!$stripeSecretKey) {
    die("Stripe secret key not set.");
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Initialize variables
$bookingId = null;
$errorMessage = null;

// Check for Stripe Checkout Session ID
$sessionId = $_GET['session_id'] ?? null;
if ($sessionId) {
    try {
        $checkoutSession = \Stripe\Checkout\Session::retrieve($sessionId);

        if ($checkoutSession->payment_status !== 'paid') {
            $_SESSION['payment_error'] = 'Payment not completed.';
            header('Location: make_payment.php?error=' . urlencode('Payment not completed.'));
            exit;
        }

        $customerId = $checkoutSession->metadata->customer_id;
        $venue = $checkoutSession->metadata->venue;
        $eventId = $checkoutSession->metadata->event_id;
        $eventDate = $checkoutSession->metadata->event_date;
        $price = (float)$checkoutSession->metadata->price;

        if ($customerId != $_SESSION['user_id']) {
            $_SESSION['payment_error'] = 'Customer ID mismatch.';
            header('Location: make_payment.php?error=' . urlencode('Customer ID mismatch.'));
            exit;
        }

        if (!$eventId) {
            $_SESSION['payment_error'] = 'Invalid event.';
            header('Location: make_payment.php?error=' . urlencode('Invalid event.'));
            exit;
        }

        $db->begin_transaction();

        try {
            $bookingInsertQuery = "INSERT INTO bookings (customer_id, total_cost, status, venue, event_date, created_at) 
                                  VALUES (?, ?, 'Pending', ?, ?, NOW())";
            $bookingId = $db->insert($bookingInsertQuery, [$customerId, $price, $venue, $eventDate]);

            if ($bookingId === false) {
                throw new Exception("Failed to insert booking.");
            }

            $bookingItemInsertQuery = "INSERT INTO booking_items (booking_id, event_id) VALUES (?, ?)";
            $result = $db->insert($bookingItemInsertQuery, [$bookingId, $eventId]);
            if ($result === false) {
                throw new Exception("Failed to insert booking item.");
            }

            $paymentQuery = "INSERT INTO payments (booking_id, payment_method, transaction_id, created_at) 
                            VALUES (?, 'stripe', ?, NOW())";
            $result = $db->insert($paymentQuery, [$bookingId, $sessionId]);
            if ($result === false) {
                throw new Exception("Failed to insert payment.");
            }

            $db->delete("DELETE FROM cart WHERE customer_id = ? AND event_id = ?", [$customerId, $eventId]);

            $db->commit();

            $_SESSION['order_success'] = 'Your booking has been placed successfully.';
            $_SESSION['order_id'] = $bookingId;
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['payment_error'] = 'Error processing payment: ' . $e->getMessage();
            header('Location: make_payment.php?error=' . urlencode('Error processing payment: ' . $e->getMessage()));
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['payment_error'] = 'Error retrieving payment details: ' . $e->getMessage();
        header('Location: make_payment.php?error=' . urlencode('Error retrieving payment details: ' . $e->getMessage()));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - Event Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        h2 {
            font-size: 1.8em;
            color: #2e7d32;
            margin-bottom: 20px;
            font-weight: 600;
        }

        p {
            font-size: 1.1em;
            margin-bottom: 20px;
            color: #333;
        }

        .error {
            color: #d32f2f;
            font-size: 1em;
            margin-bottom: 20px;
        }

        a {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(45deg, #2e7d32, #66bb6a);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        a:hover {
            background: linear-gradient(45deg, #66bb6a, #2e7d32);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 15px;
            }

            .container {
                padding: 20px;
            }

            h2 {
                font-size: 1.5em;
            }

            p {
                font-size: 1em;
            }

            a {
                padding: 8px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['order_success'])): ?>
            <h2>Payment Successful!</h2>
            <?php if ($bookingId): ?>
                <p>Your booking (ID: <?php echo htmlspecialchars($bookingId); ?>) has been placed successfully.</p>
            <?php else: ?>
                <p>Your booking has been placed successfully.</p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($_SESSION['order_success']); ?></p>
            <a href="dashboard.php">Back to Dashboard</a>
            <?php unset($_SESSION['order_success'], $_SESSION['order_id']); ?>
        <?php elseif ($errorMessage): ?>
            <h2>Payment Error</h2>
            <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
            <a href="make_payment.php">Try Again</a>
        <?php else: ?>
            <h2>Payment Success</h2>
            <p>Your booking has been placed successfully.</p>
            <a href="dashboard.php">Back to Dashboard</a>
            <?php unset($_SESSION['order_success'], $_SESSION['order_id']); ?>
        <?php endif; ?>
    </div>
</body>
</html>