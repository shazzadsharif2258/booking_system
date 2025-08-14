<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    die("Unauthorized access");
}

// Fetch POST data from cart.php
$itemType = $_POST['item_type'] ?? null;
$foodId = $_POST['food_id'] ?? null;
$eventId = $_POST['event_id'] ?? null;
$chefId = $_POST['chef_id'] ?? null;
$vendorId = $_POST['vendor_id'] ?? null;
$foodName = $_POST['food_name'] ?? '';
$eventName = $_POST['event_name'] ?? '';
$foodImage = $_POST['food_image'] ?? '';
$eventImage = $_POST['event_image'] ?? '';
$price = (float)($_POST['price'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$deliveryAddress = $_POST['delivery_address'] ?? '';

// Calculate total price
$totalPrice = $price * $quantity;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Your Order - Homemade Food Delivery</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), 
                        url('https://images.unsplash.com/photo-1504674900247-087ca5f5c2f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            line-height: 1.6;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            animation: fadeIn 1s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        h2 {
            font-size: 2.5em;
            color: #2e7d32;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .order-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            margin-bottom: 30px;
            animation: slideUp 0.8s ease-in-out;
        }

        .order-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #eee;
        }

        .order-details p {
            font-size: 1.1em;
            margin-bottom: 10px;
            color: #555;
        }

        .order-details p strong {
            color: #333;
        }

        .total-price {
            font-size: 1.3em;
            font-weight: 600;
            color: #2e7d32;
        }

        form {
            margin-top: 20px;
        }

        label {
            display: block;
            font-size: 1em;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            margin-bottom: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 5px rgba(46, 125, 50, 0.3);
            outline: none;
        }

        button {
            background: linear-gradient(45deg, #2e7d32, #66bb6a);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1.1em;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            animation: pulse 2s infinite;
        }

        button:hover {
            background: linear-gradient(45deg, #66bb6a, #2e7d32);
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 15px;
            }

            .order-container {
                padding: 20px;
            }

            h2 {
                font-size: 2em;
            }

            .order-image {
                height: 150px;
            }

            button {
                padding: 10px 20px;
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <h2>Review Your Order</h2>

    <div class="order-container">
        <img src="<?php echo $itemType === 'event' ? ($eventImage ? '../Uploads/events/' . htmlspecialchars($eventImage) : '../assets/images/placeholder.jpg') : '../Uploads/dishes/' . htmlspecialchars($foodImage); ?>" alt="<?php echo htmlspecialchars($itemType === 'event' ? $eventName : $foodName); ?>" class="order-image">

        <div class="order-details">
            <p><strong>Item:</strong> <?php echo htmlspecialchars($itemType === 'event' ? $eventName : $foodName); ?></p>
            <p><strong><?php echo $itemType === 'event' ? 'Vendor' : 'Chef'; ?>:</strong> <?php echo htmlspecialchars($itemType === 'event' ? ($db->selectOne("SELECT name FROM users WHERE id = ?", [$vendorId])['name'] ?? 'Unknown') : ($db->selectOne("SELECT name FROM users WHERE id = ?", [$chefId])['name'] ?? 'Unknown')); ?></p>
            <p><strong>Price per Unit:</strong> <?php echo number_format($price, 2); ?></p>
            <p><strong>Quantity:</strong> <?php echo htmlspecialchars($quantity); ?></p>
            <p class="total-price"><strong>Total Price:</strong> <?php echo number_format($totalPrice, 2); ?></p>
            <?php if ($itemType === 'event'): ?>
                <?php
                $event = $db->selectOne("SELECT event_type, capacity, services FROM events WHERE id = ?", [$eventId]);
                if ($event):
                ?>
                    <p><strong>Event Type:</strong> <?php echo htmlspecialchars($event['event_type']); ?></p>
                    <p><strong>Capacity:</strong> <?php echo htmlspecialchars($event['capacity']); ?></p>
                    <p><strong>Services:</strong> <?php echo htmlspecialchars(implode(', ', json_decode($event['services'], true) ?? [])); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <form action="make_payment.php" method="POST">
        <input type="hidden" name="item_type" value="<?php echo htmlspecialchars($itemType); ?>">
        <input type="hidden" name="food_id" value="<?php echo htmlspecialchars($foodId); ?>">
        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($eventId); ?>">
        <input type="hidden" name="chef_id" value="<?php echo htmlspecialchars($chefId); ?>">
        <input type="hidden" name="vendor_id" value="<?php echo htmlspecialchars($vendorId); ?>">
        <input type="hidden" name="food_name" value="<?php echo htmlspecialchars($foodName); ?>">
        <input type="hidden" name="event_name" value="<?php echo htmlspecialchars($eventName); ?>">
        <input type="hidden" name="price" value="<?php echo htmlspecialchars($price); ?>">
        <input type="hidden" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>">
        <input type="hidden" name="total_price" value="<?php echo htmlspecialchars($totalPrice); ?>">

        <label>Delivery Address:<br>
            <input type="text" name="delivery_address" value="<?php echo htmlspecialchars($deliveryAddress); ?>" required>
        </label>

        <label>Phone Number:<br>
            <input type="text" name="phone" required>
        </label>

        <button type="submit">Make Payment</button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.order-container, form');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });

            elements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.8s ease-in-out';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>