<?php
// /customer/services_api.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
  if ($action === 'list') {
    $type     = trim($_POST['type'] ?? $_GET['type'] ?? '');
    $location = trim($_POST['location'] ?? $_GET['location'] ?? '');
    $max      = (float)($_POST['max'] ?? $_GET['max'] ?? 0);
    $sort     = $_POST['sort'] ?? $_GET['sort'] ?? 'popular';

    $where = ["e.is_available=1"];
    $params = [];

    if ($type !== '') {
      $where[] = "e.event_type = ?";
      $params[] = $type;
    }
    if ($location !== '') {
      $where[] = "u.address LIKE ?";
      $params[] = "%$location%";
    }
    if ($max > 0) {
      $where[] = "e.base_cost <= ?";
      $params[] = $max;
    }
    $whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

    // ratings subquery (event-based)
    $ratingsSql = "
      SELECT bi.event_id, AVG(f.rating) avg_rating, COUNT(*) reviews
      FROM feedback f
      JOIN booking_items bi ON bi.booking_id=f.booking_id
      GROUP BY bi.event_id
    ";

    $order = "ORDER BY r.reviews DESC, e.created_at DESC";
    if ($sort === 'price_asc')  $order = "ORDER BY e.base_cost ASC";
    if ($sort === 'price_desc') $order = "ORDER BY e.base_cost DESC";
    if ($sort === 'rating_desc')$order = "ORDER BY COALESCE(r.avg_rating,0) DESC, r.reviews DESC";

    $sql = "
      SELECT e.id, e.name, e.description, e.event_type, e.base_cost, e.capacity, e.services, e.image, e.is_available,
             u.id AS vendor_id, u.name AS vendor_name, u.address AS vendor_address,
             COALESCE(r.avg_rating,0) avg_rating, COALESCE(r.reviews,0) reviews
      FROM events e
      JOIN users u ON u.id=e.vendor_id
      LEFT JOIN ($ratingsSql) r ON r.event_id=e.id
      $whereSql
      $order
      LIMIT 36
    ";
    $rows = $db->select($sql, $params) ?: [];

    $items = array_map(function($x) use($uploadUrls){
      $img = !empty($x['image']) ? $uploadUrls['events'] . $x['image'] : app_url('assets/images/placeholder.jpg');
      $x['image_url'] = $img;
      // normalize services to array
      $x['services'] = $x['services'] ? json_decode($x['services'], true) : [];
      return $x;
    }, $rows);

    // facets for filters (types + locations)
    $types = $db->select("SELECT DISTINCT e.event_type t FROM events e WHERE e.event_type IS NOT NULL AND e.event_type<>'' ORDER BY t") ?: [];
    $locs  = $db->select("
      SELECT DISTINCT TRIM(SUBSTRING_INDEX(u.address, ',', 1)) l
      FROM users u
      JOIN events e ON e.vendor_id=u.id
      WHERE u.address IS NOT NULL AND u.address<>''
      ORDER BY l
    ") ?: [];

    echo json_encode([
      'success'=>1,
      'items'=>$items,
      'facets'=>[
        'types'=>array_values(array_filter(array_map(fn($r)=>$r['t']??'', $types))),
        'locations'=>array_values(array_filter(array_map(fn($r)=>$r['l']??'', $locs))),
      ]
    ]);
    exit;
  }

  if ($action === 'detail') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>0,'message'=>'Missing id']); exit; }

    $ratingsSql = "
      SELECT bi.event_id, AVG(f.rating) avg_rating, COUNT(*) reviews
      FROM feedback f
      JOIN booking_items bi ON bi.booking_id=f.booking_id
      GROUP BY bi.event_id
    ";
    $sql = "
      SELECT e.*, u.name AS vendor_name, u.email AS vendor_email, u.phone AS vendor_phone, u.address AS vendor_address,
             COALESCE(r.avg_rating,0) avg_rating, COALESCE(r.reviews,0) reviews
      FROM events e
      JOIN users u ON u.id=e.vendor_id
      LEFT JOIN ($ratingsSql) r ON r.event_id=e.id
      WHERE e.id=?
      LIMIT 1
    ";
    $x = $db->selectOne($sql, [$id]);
    if (!$x) { echo json_encode(['success'=>0,'message'=>'Not found']); exit; }

    $x['image_url'] = !empty($x['image']) ? $uploadUrls['events'].$x['image'] : app_url('assets/images/placeholder.jpg');
    $x['services']  = $x['services'] ? json_decode($x['services'], true) : [];

    echo json_encode(['success'=>1,'item'=>$x]); exit;
  }

  if ($action === 'add_to_cart') {
    if (!isLoggedIn() || $_SESSION['user_type']!=='customer') {
      echo json_encode(['success'=>0,'message'=>'Please sign in as a customer']); exit;
    }
    $customerId = (int)$_SESSION['user_id'];
    $eventId = (int)($_POST['id'] ?? 0);
    if (!$eventId) { echo json_encode(['success'=>0,'message'=>'Missing event']); exit; }

    $ev = $db->selectOne("SELECT id, vendor_id, base_cost FROM events WHERE id=? LIMIT 1", [$eventId]);
    if (!$ev) { echo json_encode(['success'=>0,'message'=>'Event not found']); exit; }

    // Insert cart row (quantity 1)
    $ok = $db->insert("INSERT INTO cart (customer_id, event_id, vendor_id, quantity, added_at) VALUES (?,?,?,?,NOW())",
                      [$customerId, $ev['id'], $ev['vendor_id'], 1]);
    echo json_encode(['success'=> (bool)$ok, 'message'=> $ok ? 'Added to cart.' : 'Unable to add to cart.']); exit;
  }

  echo json_encode(['success'=>0,'message'=>'Unknown action']);
} catch (Throwable $e) {
  echo json_encode(['success'=>0,'message'=>$e->getMessage()]);
}
