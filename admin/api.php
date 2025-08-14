<?php
// /admin/api.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../router.php';

requireRole('admin');

$action = $_POST['action'] ?? '';

try {
  if ($action === 'approve_vendor') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>0,'message'=>'Missing vendor id']); exit; }

    $row = $db->update(
      "UPDATE users
         SET is_approved=1, status='approved', updated_at=NOW()
       WHERE id=? AND user_type='vendor'
       LIMIT 1", [$id]
    );
    echo json_encode(['success'=>(bool)$row, 'message'=>$row?'Vendor approved':'Nothing changed']); exit;
  }

  if ($action === 'reject_vendor') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>0,'message'=>'Missing vendor id']); exit; }

    $row = $db->update(
      "UPDATE users
         SET is_approved=0, status='rejected', updated_at=NOW()
       WHERE id=? AND user_type='vendor'
       LIMIT 1", [$id]
    );
    echo json_encode(['success'=>(bool)$row, 'message'=>$row?'Vendor rejected':'Nothing changed']); exit;
  }

  if ($action === 'vendor_assets') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>0,'message'=>'Invalid vendor']); exit; }

    $events = $db->select("SELECT id,name,event_type,is_available,created_at
                             FROM events WHERE vendor_id=? ORDER BY created_at DESC", [$id]) ?: [];

    // If your table is named differently, rename here
    $promos = $db->select("SELECT id,title,status,start_date,end_date,created_at
                             FROM promotions WHERE vendor_id=? ORDER BY created_at DESC", [$id]) ?: [];

    echo json_encode(['success'=>1,'events'=>$events,'promotions'=>$promos]); exit;
  }

  echo json_encode(['success'=>0,'message'=>'Unknown action']);
} catch (Throwable $e) {
  echo json_encode(['success'=>0,'message'=>$e->getMessage()]);
}
