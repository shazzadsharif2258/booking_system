<?php
  require_once '../includes/db.php';
  header('Content-Type: application/json');

  $categoryId = $_GET['category_id'] ?? '';
  $eventType = $_GET['event_type'] ?? '';
  $location = $_GET['location'] ?? '';

  $foodQuery = "SELECT fi.id, fi.name, fi.price, fi.image, u.name AS chef_name, u.location
                FROM food_items fi
                JOIN users u ON fi.chef_id = u.id
                WHERE fi.is_available = 1";
  $foodParams = [];

  $eventQuery = "SELECT e.id, e.name, e.base_cost, e.image, e.event_type, e.capacity, e.services, u.name AS vendor_name, u.location
                 FROM events e
                 JOIN users u ON e.vendor_id = u.id
                 WHERE e.is_available = 1";
  $eventParams = [];

  if ($categoryId) {
      $foodQuery .= " AND fi.category_id = ?";
      $foodParams[] = $categoryId;
  }

  if ($eventType) {
      $eventQuery .= " AND e.event_type = ?";
      $eventParams[] = $eventType;
  }

  if ($location) {
      $foodQuery .= " AND u.location LIKE ?";
      $eventQuery .= " AND u.location LIKE ?";
      $foodParams[] = "%$location%";
      $eventParams[] = "%$location%";
  }

  try {
      $foods = $db->select($foodQuery, $foodParams);
      $events = $db->select($eventQuery, $eventParams);

      foreach ($events as &$event) {
          $event['services'] = json_decode($event['services'], true) ?? [];
      }

      echo json_encode(['foods' => $foods ?: [], 'events' => $events ?: []]);
  } catch (Exception $e) {
      error_log("Error fetching items: " . $e->getMessage());
      echo json_encode(['foods' => [], 'events' => [], 'error' => 'Server error']);
  }
  ?>