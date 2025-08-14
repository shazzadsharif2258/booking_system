<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$customerId = $_SESSION['user_id'];

// Fetch cart items
$cartItems = $db->select(
    "SELECT c.*, e.name AS event_name, e.image AS event_image, e.price, u.name AS vendor_name
     FROM cart c
     JOIN events e ON c.event_id = e.id
     JOIN users u ON c.vendor_id = u.id
     WHERE c.customer_id = ?",
    [$customerId]
);

$totalCost = 0;
$eventIds = [];
if ($cartItems) {
    foreach ($cartItems as $item) {
        $totalCost += $item['price'] * $item['quantity'];
        $eventIds[] = $item['event_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Event Management System</title>
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
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        h2 {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }

        .checkout-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .checkout-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
        }

        .checkout-item-details {
            flex: 1;
        }

        .checkout-item-details h3 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 10px;
        }

        .checkout-item-details p {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .total-cost {
            font-size: 1.3em;
            font-weight: 600;
            color: #e67e22;
            text-align: right;
            margin-top: 20px;
        }

        form {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-size: 1em;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="date"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            width: 100%;
        }

        button {
            padding: 12px;
            background: #e67e22;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover {
            background: #d35400;
        }

        .empty-cart {
            text-align: center;
            font-size: 1.2em;
            color: #666;
        }

        @media (max-width: 768px) {
            .checkout-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .checkout-item img {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Checkout</h2>
        <?php if (!empty($cartItems)): ?>
            <?php foreach ($cartItems as $item): ?>
                <div class="checkout-item">
                    <img src="../Uploads/events/<?php echo htmlspecialchars($item['event_image'] ?: 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['event_name']); ?>">
                    <div class="checkout-item-details">
                        <h3><?php echo htmlspecialchars($item['event_name']); ?></h3>
                        <p><strong>Vendor:</strong> <?php echo htmlspecialchars($item['vendor_name']); ?></p>
                        <p><strong>Price:</strong> ৳<?php echo number_format($item['price'], 2); ?></p>
                        <p><strong>Quantity:</strong> <?php echo $item['quantity']; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="total-cost">Total Cost: ৳<?php echo number_format($totalCost, 2); ?></div>
            <form action="make_payment.php" method="POST">
                <input type="hidden" name="event_ids" value="<?php echo htmlspecialchars(json_encode($eventIds)); ?>">
                <input type="hidden" name="total_cost" value="<?php echo htmlspecialchars($totalCost); ?>">
                <label>Event Date:
                    <input type="date" name="event_date" required>
                </label>
                <label>Venue:
                    <input type="text" name="venue" value="<?php echo isset($_SESSION['user_address']) ? htmlspecialchars($_SESSION['user_address']) : ''; ?>" required>
                </label>
                <button type="submit">Proceed to Payment</button>
            </form>
        <?php else: ?>
            <p class="empty-cart">Your cart is empty.</p>
        <?php endif; ?>
    </div>
</body>
</html>