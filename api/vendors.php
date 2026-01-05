<?php
/**
 * Vendors API Endpoints
 */

require_once '../auth/auth.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

requirePermission('manage_vendors');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$db = new Database();
$conn = $db->getConnection();

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            // Get vendors list
            $search = $_GET['search'] ?? '';
            $page = max(1, $_GET['page'] ?? 1);
            $limit = $_GET['limit'] ?? ITEMS_PER_PAGE;
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($search) {
                $whereClause .= " AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            
            $sql = "SELECT * FROM vendors $whereClause ORDER BY name ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $vendors = $stmt->fetchAll();
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM vendors $whereClause";
            $countStmt = $conn->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            echo json_encode([
                'vendors' => $vendors,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } elseif ($action === 'get' && isset($_GET['id'])) {
            // Get single vendor
            $sql = "SELECT * FROM vendors WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['id']]);
            $vendor = $stmt->fetch();
            
            if ($vendor) {
                echo json_encode(['vendor' => $vendor]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Vendor not found']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
        }
        break;
        
    case 'POST':
        if ($action === 'create') {
            // Create vendor
            $required_fields = ['name', 'contact_person', 'email'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field '$field' is required"]);
                    exit;
                }
            }
            
            try {
                $sql = "INSERT INTO vendors (name, contact_person, email, phone, address, tax_id, payment_terms, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $input['name'],
                    $input['contact_person'],
                    $input['email'],
                    $input['phone'] ?? '',
                    $input['address'] ?? '',
                    $input['tax_id'] ?? '',
                    $input['payment_terms'] ?? '',
                    $input['is_active'] ?? 1
                ]);
                
                $vendor_id = $db->lastInsertId();
                logAudit('create_vendor', 'vendors', $vendor_id, null, $input);
                
                echo json_encode([
                    'success' => true,
                    'vendor_id' => $vendor_id,
                    'message' => 'Vendor created successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create vendor: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
        }
        break;
        
    case 'PUT':
        if ($action === 'update' && isset($_GET['id'])) {
            // Update vendor
            $vendor_id = $_GET['id'];
            
            try {
                // Get old values for audit
                $sql = "SELECT * FROM vendors WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$vendor_id]);
                $old_vendor = $stmt->fetch();
                
                if (!$old_vendor) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Vendor not found']);
                    exit;
                }
                
                $sql = "UPDATE vendors SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, tax_id = ?, payment_terms = ?, is_active = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $input['name'] ?? $old_vendor['name'],
                    $input['contact_person'] ?? $old_vendor['contact_person'],
                    $input['email'] ?? $old_vendor['email'],
                    $input['phone'] ?? $old_vendor['phone'],
                    $input['address'] ?? $old_vendor['address'],
                    $input['tax_id'] ?? $old_vendor['tax_id'],
                    $input['payment_terms'] ?? $old_vendor['payment_terms'],
                    $input['is_active'] ?? $old_vendor['is_active'],
                    $vendor_id
                ]);
                
                logAudit('update_vendor', 'vendors', $vendor_id, $old_vendor, $input);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Vendor updated successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update vendor: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
        }
        break;
        
    case 'DELETE':
        if ($action === 'delete' && isset($_GET['id'])) {
            // Delete vendor (soft delete)
            $vendor_id = $_GET['id'];
            
            try {
                $sql = "UPDATE vendors SET is_active = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$vendor_id]);
                
                logAudit('delete_vendor', 'vendors', $vendor_id);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Vendor deleted successfully'
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete vendor: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
