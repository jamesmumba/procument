<?php
/**
 * PO Items API Endpoint
 */

require_once '../config/config.php';
require_once '../auth/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$po_id = $_GET['po_id'] ?? null;

if (!$po_id) {
    http_response_code(400);
    echo json_encode(['error' => 'PO ID required']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

if ($method === 'GET') {
    try {
        $sql = "SELECT poi.*, ii.name as item_name, ii.item_code, ii.unit_of_measure 
                FROM po_items poi 
                JOIN inventory_items ii ON poi.item_id = ii.id 
                WHERE poi.po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$po_id]);
        $items = $stmt->fetchAll();
        
        echo json_encode(['items' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch PO items: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

