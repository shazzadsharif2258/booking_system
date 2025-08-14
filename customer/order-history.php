<?php
session_start();
$pageTitle = 'Order History';
$includeCart = true;

require_once '../db.php';
require_once '../auth.php';
require_once '../functions.php';

if (!isLoggedIn() || !isUserType('customer')) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$user = getUserData($userId);
$profileImage = isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image']) 
    ? '../Uploads/profiles/' . $_SESSION['profile_image'] 
    : '../assets/images/placeholder.jpg';
$customerName = $_SESSION['user_name'] ?? 'Customer';
$customerLocation = $_SESSION['user_location'] ?? '';

$cartItems = $db->select(
    "SELECT c.id, c.food_id, c.event_id, c.quantity, c.added_at, 
            COALESCE(f.name, e.name) AS name, 
            COALESCE(f.image, e.image) AS image, 
            COALESCE(f.price, e.base_cost) AS price, 
            COALESCE(u1.name, u2.name) AS provider_name,
            CASE WHEN c.food_id IS NOT NULL THEN 'food' ELSE 'event' END AS item_type
     FROM cart c 
     LEFT JOIN food_items f ON c.food_id = f.id 
     LEFT JOIN events e ON c.event_id = e.id
     LEFT JOIN users u1 ON c.chef_id = u1.id 
     LEFT JOIN users u2 ON c.vendor_id = u2.id 
     WHERE c.customer_id = ?",
    [$userId]
);

$confirmedOrders = $db->select(
    "SELECT oi.id, oi.order_id, oi.food_item_id AS food_id, oi.event_id, oi.quantity, oi.price, o.created_at AS order_date, 
            COALESCE(f.name, e.name) AS name, 
            COALESCE(f.image, e.image) AS image, 
            COALESCE(u1.name, u2.name) AS provider_name,
            CASE WHEN oi.food_item_id IS NOT NULL THEN 'food' ELSE 'event' END AS item_type
     FROM orders o 
     JOIN order_items oi ON o.id = oi.order_id 
     LEFT JOIN food_items f ON oi.food_item_id = f.id 
     LEFT JOIN events e ON oi.event_id = e.id
     LEFT JOIN users u1 ON f.chef_id = u1.id 
     LEFT JOIN users u2 ON e.vendor_id = u2.id 
     WHERE o.customer_id = ? AND o.status = 'confirmed' 
     ORDER BY o.created_at DESC",
    [$userId]
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Homemade Food Delivery</title>
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
        }

        header {
            background: #fff;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo img {
            height: 50px;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user img {
            height: 40px;
            width: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user div {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user div strong {
            font-size: 1em;
            color: #333;
        }

        .tabs {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .tab {
            padding: 10px 20px;
            background: #fff;
            border: 1px solid #ddd;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: all 0.3s;
        }

        .tab:hover, .tab.active {
            background: #2e7d32;
            color: #fff;
            border-color: #2e7d32;
        }

        .tab-content {
            display: none;
            max-width: 1200px;
            margin: 0 auto;
        }

        .tab-content.active {
            display: block;
        }

        .order-list {
            display: grid;
            gap: 20px;
        }

        .order-item {
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .order-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .order-image {
            width: 150px;
            margin-right: 20px;
        }

        .order-image img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #eee;
        }

        .order-details h3 {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .order-meta span {
            font-size: 0.95em;
            color: #666;
            margin-right: 15px;
        }

        .order-price {
            font-size: 1.2em;
            font-weight: 600;
            color: #2e7d32;
            margin: 10px 0;
        }

        .order-date {
            font-size: 0.95em;
            color: #666;
        }

        .no-items {
            text-align: center;
            font-size: 1.2em;
            color: #666;
            padding: 50px 0;
        }

        footer {
            background: #2c3e50;
            color: #fff;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-logo img {
            height: 60px;
            margin-bottom: 10px;
        }

        .footer-text {
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .footer-social a {
            color: #fff;
            font-size: 1.2em;
            margin: 0 10px;
            transition: color 0.3s;
        }

        .footer-social a:hover {
            color: #66bb6a;
        }

        .footer-contact p {
            font-size: 0.95em;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-image {
                width: 100%;
                margin-right: 0;
                margin-bottom: 15px;
            }

            .order-image img {
                height: auto;
            }

            .tabs {
                flex-direction: column;
            }

            .tab {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <img src="../assets/images/logo.png" alt="Homemade Food Delivery">
            </div>
            <div class="user">
                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile">
                <div>
                    <strong><?php echo htmlspecialchars($customerName); ?></strong>
                    <span><?php echo htmlspecialchars($customerLocation); ?></span>
                </div>
            </div>
        </div>
    </header>

    <div class="tabs">
        <div class="tab active" onclick="showTab('cart-items')">Cart</div>
        <div class="tab" onclick="showTab('confirmed-orders')">Confirmed Orders</div>
    </div>

    <div id="cart-items" class="tab-content active">
        <?php if (empty($cartItems)): ?>
            <div class="no-items">Your cart is empty.</div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($cartItems as $item): ?>
                    <div class="order-item">
                        <div class="order-image">
                            <img src="<?php echo $item['image'] ? '../Uploads/' . ($item['item_type'] === 'event' ? 'events' : 'dishes') . '/' . htmlspecialchars($item['image']) : '../assets/images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        </div>
                        <div class="order-details">
                            <h3><?php echo htmlspecialchars($item['name']); ?> (<?php echo ucfirst($item['item_type']); ?>)</h3>
                            <div class="order-meta">
                                <span>Provider: <?php echo htmlspecialchars($item['provider_name']); ?></span>
                                <span>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></span>
                            </div>
                            <div class="order-price"><?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                            <div class="order-date">Added on: <?php echo date('d M Y, H:i', strtotime($item['added_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="confirmed-orders" class="tab-content">
        <?php if (empty($confirmedOrders)): ?>
            <div class="no-items">No confirmed orders yet.</div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($confirmedOrders as $order): ?>
                    <div class="order-item">
                        <div class="order-image">
                            <img src="<?php echo $order['image'] ? '../Uploads/' . ($order['item_type'] === 'event' ? 'events' : 'dishes') . '/' . htmlspecialchars($order['image']) : '../assets/images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($order['name']); ?>">
                        </div>
                        <div class="order-details">
                            <h3><?php echo htmlspecialchars($order['name']); ?> (<?php echo ucfirst($order['item_type']); ?>)</h3>
                            <div class="order-meta">
                                <span>Provider: <?php echo htmlspecialchars($order['provider_name']); ?></span>
                                <span>Quantity: <?php echo htmlspecialchars($order['quantity']); ?></span>
                            </div>
                            <div class="order-price"><?php echo number_format($order['price'] * $order['quantity'], 2); ?></div>
                            <div class="order-date">Ordered on: <?php echo date('d M Y, H:i', strtotime($order['order_date'])); ?></div>
                            <?php if ($order['item_type'] === 'event'):
                                $event = $db->selectOne("SELECT event_type, capacity, services FROM events WHERE id = ?", [$order['event_id']]);
                                if ($event):
                            ?>
                                <div class="order-meta">
                                    <span>Event Type: <?php echo htmlspecialchars($event['event_type']); ?></span>
                                    <span>Capacity: <?php echo htmlspecialchars($event['capacity']); ?></span>
                                    <span>Services: <?php echo htmlspecialchars(implode(', ', json_decode($event['services'], true) ?? [])); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <img src="../assets/images/logo.png" alt="Homemade Food Delivery">
            </div>
            <p class="footer-text">Your Favorite Food, Delivered! 🍲🍽️</p>
            <div class="footer-social">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
            <div class="footer-contact">
                <p>Contact: 01515215020</p>
            </div>
        </div>
    </footer>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab[onclick="showTab('${tabId}')"]`).classList.add('active');
        }
    </script>
</body>
</html>