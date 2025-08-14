<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customerId = $_SESSION['user_id'];
$itemType = $_POST['item_type'] ?? null;
$foodId = $_POST['food_id'] ?? null;
$eventId = $_POST['event_id'] ?? null;
$chefId = $_POST['chef_id'] ?? null;
$vendorId = $_POST['vendor_id'] ?? null;

try {
    if ($itemType === 'food' && $foodId && $chefId && $customerId) {
        // Check if food item already exists in cart
        $existing = $db->selectOne("SELECT id, quantity FROM cart WHERE customer_id = ? AND food_id = ?", [$customerId, $foodId]);

        if ($existing) {
            // Update quantity
            $db->update("UPDATE cart SET quantity = quantity + 1 WHERE customer_id = ? AND food_id = ?", [$customerId, $foodId]);
            echo json_encode(['success' => true, 'message' => 'Food quantity updated in cart']);
        } else {
            // Insert new food item
            $db->insert("INSERT INTO cart (customer_id, food_id, chef_id, quantity, added_at) VALUES (?, ?, ?, 1, NOW())", [$customerId, $foodId, $chefId]);
            echo json_encode(['success' => true, 'message' => 'Food item added to cart']);
        }
    } elseif ($itemType === 'event' && $eventId && $vendorId && $customerId) {
        // Check if event already exists in cart
        $existing = $db->selectOne("SELECT id FROM cart WHERE customer_id = ? AND event_id = ?", [$customerId, $eventId]);

        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Event already in cart']);
        } else {
            // Insert new event (quantity is 1 for events)
            $db->insert("INSERT INTO cart (customer_id, event_id, vendor_id, quantity, added_at) VALUES (?, ?, ?, 1, NOW())", [$customerId, $eventId, $vendorId]);
            echo json_encode(['success' => true, 'message' => 'Event added to cart']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
    }
} catch (Exception $e) {
    error_log("Error adding to cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to add item to cart: ' . $e->getMessage()]);
}