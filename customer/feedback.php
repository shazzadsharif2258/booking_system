<?php
   session_start();
   require_once '../db.php';

   if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
       http_response_code(403);
       echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
       exit;
   }

   $action = $_POST['action'] ?? $_GET['action'] ?? '';
   $customerId = $_SESSION['user_id'];

   if ($action === 'check_feedback') {
       $orderId = $_GET['order_id'] ?? 0;
       $bookingId = $_GET['booking_id'] ?? 0;
       $hasFeedback = $db->selectOne(
           "SELECT id FROM feedback WHERE customer_id = ? AND (order_id = ? OR booking_id = ?)",
           [$customerId, $orderId, $bookingId]
       );
       echo json_encode([
           'status' => 'success',
           'has_feedback' => $hasFeedback !== false
       ]);
       exit;
   }

   if ($action === 'submit_feedback') {
       $orderId = $_POST['order_id'] ?? 0;
       $bookingId = $_POST['booking_id'] ?? 0;
       $rating = $_POST['rating'] ?? '';
       $comment = $_POST['comment'] ?? '';
       $itemType = $_POST['item_type'] ?? '';

       if (!in_array($rating, ['1', '2', '3', '4', '5']) || empty($comment)) {
           echo json_encode(['status' => 'error', 'message' => 'Invalid rating or comment']);
           exit;
       }

       $existingFeedback = $db->selectOne(
           "SELECT id FROM feedback WHERE customer_id = ? AND (order_id = ? OR booking_id = ?)",
           [$customerId, $orderId, $bookingId]
       );

       if ($existingFeedback) {
           echo json_encode(['status' => 'error', 'message' => 'Feedback already submitted']);
           exit;
       }

       $chefId = null;
       $vendorId = null;
       if ($itemType === 'food' && $orderId) {
           $orderItem = $db->selectOne(
               "SELECT fi.chef_id FROM order_items oi JOIN food_items fi ON oi.food_item_id = fi.id WHERE oi.order_id = ?",
               [$orderId]
           );
           $chefId = $orderItem['chef_id'] ?? null;
       } elseif ($itemType === 'event' && ($orderId || $bookingId)) {
           $eventId = $orderId ? $db->selectOne("SELECT event_id FROM order_items WHERE order_id = ?", [$orderId])['event_id'] ?? null
                              : $db->selectOne("SELECT event_id FROM booking_items WHERE booking_id = ?", [$bookingId])['event_id'] ?? null;
           $vendorId = $eventId ? $db->selectOne("SELECT vendor_id FROM events WHERE id = ?", [$eventId])['vendor_id'] ?? null : null;
       }

       $query = "INSERT INTO feedback (customer_id, order_id, booking_id, chef_id, vendor_id, rating, comment, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
       $result = $db->insert($query, [$customerId, $orderId ?: null, $bookingId ?: null, $chefId, $vendorId, $rating, $comment]);

       if ($result) {
           echo json_encode(['status' => 'success', 'message' => 'Feedback submitted successfully']);
       } else {
           echo json_encode(['status' => 'error', 'message' => 'Failed to submit feedback']);
       }
       exit;
   }
   ?>