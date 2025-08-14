<?php
session_start();
require_once '../db.php';
require_once '../functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(403);
    echo 'Unauthorized access';
    exit;
}

$customerId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $transactionId = $_POST['transaction_id'] ?? '';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $cartItems = $_POST['cart_items'] ?? [];

    // Validate inputs
    if (!in_array($paymentMethod, ['bkash', 'nagad', 'rocket'])) {
        $_SESSION['payment_error'] = 'Invalid payment method.';
        header('Location: dashboard.php?error=' . urlencode('Invalid payment method.'));
        exit;
    }

    if (empty($transactionId)) {
        $_SESSION['payment_error'] = 'Transaction ID is required for ' . ucfirst($paymentMethod) . '.';
        header('Location: dashboard.php?error=' . urlencode('Transaction ID is required for ' . ucfirst($paymentMethod) . '.'));
        exit;
    }

    if (empty($deliveryAddress)) {
        $_SESSION['payment_error'] = 'Delivery address is required.';
        header('Location: dashboard.php?error=' . urlencode('Delivery address is required.'));
        exit;
    }

    if (empty($cartItems)) {
        $_SESSION['payment_error'] = 'Cart is empty.';
        header('Location: dashboard.php?error=' . urlencode('Cart is empty.'));
        exit;
    }

    $totalAmount = 0;

    try {
        $db->begin_transaction();

        // Insert order
        $orderInsertQuery = "INSERT INTO orders (customer_id, total_amount, status, delivery_address, created_at, updated_at) 
                            VALUES (?, ?, 'confirmed', ?, NOW(), NOW())";
        $db->insert($orderInsertQuery, [$customerId, 0, $deliveryAddress]); // Placeholder total, updated below
        $orderId = $db->lastInsertId();
        if ($orderId === false) {
            throw new Exception("Failed to insert order.");
        }

        // Process each cart item
        foreach ($cartItems as $cartItemId => $item) {
            $itemType = $item['item_type'] ?? '';
            $foodId = $item['food_id'] ?? null;
            $eventId = $item['event_id'] ?? null;
            $chefId = $item['chef_id'] ?? null;
            $vendorId = $item['vendor_id'] ?? null;
            $price = (float)($item['price'] ?? 0.0);
            $quantity = (int)($item['quantity'] ?? 1);

            // Validate item
            if (!$itemType || ($itemType === 'food' && !$foodId) || ($itemType === 'event' && !$eventId) || $quantity <= 0) {
                throw new Exception("Invalid item or quantity for item ID $cartItemId.");
            }

            // Fetch price if not provided or invalid
            if ($price <= 0) {
                if ($itemType === 'food') {
                    $itemQuery = "SELECT price FROM food_items WHERE id = ?";
                    $itemData = $db->selectOne($itemQuery, [$foodId]);
                    if (!$itemData) {
                        throw new Exception("Food item not found for ID $foodId.");
                    }
                    $price = $itemData['price'];
                } elseif ($itemType === 'event') {
                    $itemQuery = "SELECT base_cost AS price FROM events WHERE id = ?";
                    $itemData = $db->selectOne($itemQuery, [$eventId]);
                    if (!$itemData) {
                        throw new Exception("Event not found for ID $eventId.");
                    }
                    $price = $itemData['price'];
                }
            }

            $itemTotal = $price * $quantity;
            $totalAmount += $itemTotal;

            // Insert order item
            $orderItemInsertQuery = $itemType === 'event' ?
                "INSERT INTO order_items (order_id, event_id, quantity, price) VALUES (?, ?, ?, ?)" :
                "INSERT INTO order_items (order_id, food_item_id, quantity, price) VALUES (?, ?, ?, ?)";
            $params = $itemType === 'event' ? [$orderId, $eventId, $quantity, $price] : [$orderId, $foodId, $quantity, $price];
            $result = $db->insert($orderItemInsertQuery, $params);
            if ($result === false) {
                throw new Exception("Failed to insert order item for item ID $cartItemId.");
            }

            // Remove item from cart
            $deleteQuery = $itemType === 'event' ?
                "DELETE FROM cart WHERE customer_id = ? AND event_id = ?" :
                "DELETE FROM cart WHERE customer_id = ? AND food_id = ?";
            $params = $itemType === 'event' ? [$customerId, $eventId] : [$customerId, $foodId];
            $db->delete($deleteQuery, $params);
        }

        // Update total amount in orders table
        $updateTotalQuery = "UPDATE orders SET total_amount = ? WHERE id = ?";
        $db->update($updateTotalQuery, [$totalAmount, $orderId]);

        // Insert payment
        $paymentQuery = "INSERT INTO payments (order_id, payment_method, transaction_id, created_at) 
                        VALUES (?, ?, ?, NOW())";
        $result = $db->insert($paymentQuery, [$orderId, $paymentMethod, $transactionId]);
        if ($result === false) {
            throw new Exception("Failed to insert payment.");
        }

        $db->commit();

        $_SESSION['order_success'] = 'Your ' . ucfirst($paymentMethod) . ' order has been placed successfully.';
        $_SESSION['order_id'] = $orderId;
        header('Location: payment_success.php');
        exit;
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['payment_error'] = 'Failed to process ' . ucfirst($paymentMethod) . ' order: ' . $e->getMessage();
        header('Location: dashboard.php?error=' . urlencode('Failed to process ' . ucfirst($paymentMethod) . ' order: ' . $e->getMessage()));
        exit;
    }
} else {
    $_SESSION['payment_error'] = 'Invalid request method.';
    header('Location: dashboard.php?error=' . urlencode('Invalid request method.'));
    exit;
}