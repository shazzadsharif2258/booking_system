<?php
session_start();
require_once '../db.php';

$customerId = $_SESSION['user_id'] ?? null;

if (!$customerId) {
    die("Not logged in");
}

// Fetch food items in cart
$foodQuery = "SELECT c.*, f.name AS food_name, f.image AS food_image, f.price, u.name AS chef_name
              FROM cart c
              JOIN food_items f ON c.food_id = f.id
              JOIN users u ON c.chef_id = u.id
              WHERE c.customer_id = ? AND c.food_id IS NOT NULL";
$foodItems = $db->select($foodQuery, [$customerId]);

// Fetch events in cart
$eventQuery = "SELECT c.*, e.name AS event_name, e.image AS event_image, e.base_cost AS price, e.event_type, e.capacity, e.services, u.name AS vendor_name
               FROM cart c
               JOIN events e ON c.event_id = e.id
               JOIN users u ON c.vendor_id = u.id
               WHERE c.customer_id = ? AND c.event_id IS NOT NULL";
$eventItems = $db->select($eventQuery, [$customerId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Roboto', 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        h2 {
            text-align: center;
            font-size: 2.2em;
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .cart-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .cart-item {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .cart-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
            border: 2px solid #eee;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-details h3 {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .cart-item-details p {
            font-size: 1em;
            color: #666;
            margin-bottom: 5px;
        }

        .cart-item-details p strong {
            color: #333;
        }

        .cart-item-form {
            margin-top: 15px;
        }

        .cart-item-form label {
            display: block;
            font-size: 0.95em;
            color: #555;
            margin-bottom: 5px;
        }

        .cart-item-form input[type="number"],
        .cart-item-form input[type="text"] {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .cart-item-form input:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 5px rgba(46, 125, 50, 0.3);
            outline: none;
        }

        .cart-item-form button {
            background: linear-gradient(45deg, #2e7d32, #66bb6a);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cart-item-form button:hover {
            background: linear-gradient(45deg, #66bb6a, #2e7d32);
            transform: translateY(-2px);
        }

        .empty-cart {
            text-align: center;
            font-size: 1.2em;
            color: #666;
            padding: 50px 0;
        }

        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .cart-item img {
                width: 100%;
                height: auto;
                margin-bottom: 15px;
                margin-right: 0;
            }

            .cart-item-form input[type="number"],
            .cart-item-form input[type="text"] {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <h2>Your Cart</h2>

    <div class="cart-container">
        <?php if (empty($foodItems) && empty($eventItems)): ?>
            <p class="empty-cart">Your cart is empty.</p>
        <?php else: ?>
            <!-- Food Items -->
            <?php if (!empty($foodItems)): ?>
                <h3>Food Items</h3>
                <?php foreach ($foodItems as $item): ?>
                    <div class="cart-item">
                        <img src="../Uploads/dishes/<?php echo htmlspecialchars($item['food_image']); ?>" alt="<?php echo htmlspecialchars($item['food_name']); ?>">
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item['food_name']); ?></h3>
                            <p><strong>Quantity in Cart:</strong> <?php echo $item['quantity']; ?></p>
                            <p><strong>Price per Unit:</strong> <?php echo number_format($item['price'], 2); ?></p>
                            <p><strong>Chef:</strong> <?php echo htmlspecialchars($item['chef_name']); ?></p>

                            <form action="place_order.php" method="POST" class="cart-item-form">
                                <input type="hidden" name="item_type" value="food">
                                <input type="hidden" name="food_id" value="<?php echo $item['food_id']; ?>">
                                <input type="hidden" name="chef_id" value="<?php echo $item['chef_id']; ?>">
                                <input type="hidden" name="food_name" value="<?php echo $item['food_name']; ?>">
                                <input type="hidden" name="food_image" value="<?php echo $item['food_image']; ?>">
                                <input type="hidden" name="price" value="<?php echo $item['price']; ?>">

                                <label>Order Quantity:
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" required>
                                </label>
                                <label>Delivery Address:
                                    <input type="text" name="delivery_address" placeholder="Enter delivery address" value="<?php echo isset($_SESSION['user_address']) ? htmlspecialchars($_SESSION['user_address']) : ''; ?>" required>
                                </label>

                                <button type="submit">Place Your Order</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Events -->
            <?php if (!empty($eventItems)): ?>
                <h3>Events</h3>
                <?php foreach ($eventItems as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo $item['event_image'] ? '../Uploads/events/' . htmlspecialchars($item['event_image']) : '../assets/images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['event_name']); ?>">
                        <div class="cart-item-details">
                            <h3><?php echo htmlspecialchars($item['event_name']); ?></h3>
                            <p><strong>Event Type:</strong> <?php echo htmlspecialchars($item['event_type']); ?></p>
                            <p><strong>Capacity:</strong> <?php echo htmlspecialchars($item['capacity']); ?></p>
                            <p><strong>Services:</strong> <?php echo htmlspecialchars(implode(', ', json_decode($item['services'], true) ?? [])); ?></p>
                            <p><strong>Price:</strong> <?php echo number_format($item['price'], 2); ?></p>
                            <p><strong>Vendor:</strong> <?php echo htmlspecialchars($item['vendor_name']); ?></p>

                            <form action="place_order.php" method="POST" class="cart-item-form">
                                <input type="hidden" name="item_type" value="event">
                                <input type="hidden" name="event_id" value="<?php echo $item['event_id']; ?>">
                                <input type="hidden" name="vendor_id" value="<?php echo $item['vendor_id']; ?>">
                                <input type="hidden" name="event_name" value="<?php echo $item['event_name']; ?>">
                                <input type="hidden" name="event_image" value="<?php echo $item['event_image']; ?>">
                                <input type="hidden" name="price" value="<?php echo $item['price']; ?>">
                                <input type="hidden" name="quantity" value="1">

                                <label>Delivery Address:
                                    <input type="text" name="delivery_address" placeholder="Enter delivery address" value="<?php echo isset($_SESSION['user_address']) ? htmlspecialchars($_SESSION['user_address']) : ''; ?>" required>
                                </label>

                                <button type="submit">Place Your Order</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>