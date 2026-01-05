<?php
/**
 * Purchase Order Management Page
 * Handles PO creation, management, and stock receiving
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('create_po');

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$po_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Check for session messages (from redirects)
if (isset($_SESSION['po_message'])) {
    $message = $_SESSION['po_message'];
    unset($_SESSION['po_message']);
}
if (isset($_SESSION['po_error'])) {
    $error = $_SESSION['po_error'];
    unset($_SESSION['po_error']);
}

// Unread notifications count for sidebar badge
$notificationSystem = new NotificationSystem();
$unread_notifications = $notificationSystem->getUnreadCount($_SESSION['user_id']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'create_from_requisition':
                    $requisition_id = $_POST['requisition_id'];
                    $vendor_id = $_POST['vendor_id'];
                    $delivery_date = $_POST['delivery_date'];
                    $payment_terms = $_POST['payment_terms'];
                    $notes = sanitizeInput($_POST['notes']);
                    
                    // Get requisition details
                    $sql = "SELECT * FROM purchase_requisitions WHERE id = ? AND status = 'approved'";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$requisition_id]);
                    $requisition = $stmt->fetch();
                    
                    if (!$requisition) {
                        $error = 'Requisition not found or not approved.';
                        break;
                    }
                    
                    // Create PO
                    $po_number = generateUniqueNumber('PO', 'purchase_orders', 'po_number');
                    
                    $sql = "INSERT INTO purchase_orders (po_number, requisition_id, vendor_id, created_by, total_amount, delivery_date, payment_terms, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $po_number,
                        $requisition_id,
                        $vendor_id,
                        $_SESSION['user_id'],
                        $requisition['total_amount'],
                        $delivery_date,
                        $payment_terms,
                        $notes
                    ]);
                    
                    $po_id = $db->lastInsertId();
                    
                    // Copy requisition items to PO items
                    $sql = "SELECT * FROM requisition_items WHERE requisition_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$requisition_id]);
                    $requisition_items = $stmt->fetchAll();
                    
                    foreach ($requisition_items as $item) {
                        $sql = "INSERT INTO po_items (po_id, item_id, quantity, unit_cost, total_cost) 
                                VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $po_id,
                            $item['item_id'],
                            $item['quantity'],
                            $item['unit_cost'],
                            $item['total_cost']
                        ]);
                    }
                    
                    // Update requisition status
                    $sql = "UPDATE purchase_requisitions SET status = 'converted_to_po' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$requisition_id]);
                    
                    logAudit('create_po', 'purchase_orders', $po_id, null, ['requisition_id' => $requisition_id, 'vendor_id' => $vendor_id]);
                    
                    // Redirect to view the new PO
                    $_SESSION['po_message'] = 'Purchase Order created successfully!';
                    header('Location: purchase_order.php?action=view&id=' . $po_id);
                    exit;
                    break;
                    
                case 'receive_stock':
                    $po_id = $_POST['po_id'];
                    $received_items = $_POST['received_items'];
                    
                    $conn->beginTransaction();
                    
                    try {
                        foreach ($received_items as $item_id => $received_qty) {
                            if ($received_qty > 0) {
                                // Update PO item received quantity
                                $sql = "UPDATE po_items SET received_quantity = received_quantity + ? WHERE po_id = ? AND item_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$received_qty, $po_id, $item_id]);
                                
                                // Update inventory stock
                                $sql = "UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$received_qty, $item_id]);
                                
                                logAudit('receive_stock', 'inventory_items', $item_id, null, ['quantity_received' => $received_qty]);
                            }
                        }
                        
                        // Check if all items are fully received
                        $sql = "SELECT COUNT(*) as pending FROM po_items WHERE po_id = ? AND received_quantity < quantity";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$po_id]);
                        $pending = $stmt->fetchColumn();
                        
                        if ($pending == 0) {
                            // All items received
                            $sql = "UPDATE purchase_orders SET status = 'fully_received' WHERE id = ?";
                        } else {
                            // Partially received
                            $sql = "UPDATE purchase_orders SET status = 'partially_received' WHERE id = ?";
                        }
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$po_id]);
                        
                        $conn->commit();
                        logAudit('receive_stock', 'purchase_orders', $po_id);
                        
                        // Redirect back to view page
                        $_SESSION['po_message'] = 'Stock received successfully!';
                        header('Location: purchase_order.php?action=view&id=' . $po_id);
                        exit;
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;
                    
                case 'send_po':
                    $send_id = $_POST['id'];
                    
                    try {
                        // Update PO status to sent
                        $sql = "UPDATE purchase_orders SET status = 'sent' WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$send_id]);
                        
                        logAudit('send_po', 'purchase_orders', $send_id, null, ['status' => 'sent']);
                        
                        // Redirect back to view page with success message
                        $_SESSION['po_message'] = 'Purchase Order sent successfully!';
                        header('Location: purchase_order.php?action=view&id=' . $send_id);
                        exit;
                    } catch (Exception $e) {
                        $error = 'Failed to send purchase order: ' . $e->getMessage();
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get approved requisitions for PO creation
$sql = "SELECT pr.*, u.first_name, u.last_name 
        FROM purchase_requisitions pr 
        JOIN users u ON pr.requested_by = u.id 
        WHERE pr.status = 'approved' 
        ORDER BY pr.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$approved_requisitions = $stmt->fetchAll();

// Get vendors
$sql = "SELECT * FROM vendors WHERE is_active = 1 ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$vendors = $stmt->fetchAll();

// Get POs list
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (po.po_number LIKE ? OR v.name LIKE ? OR pr.requisition_number LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

if ($status_filter) {
    $whereClause .= " AND po.status = ?";
    $params[] = $status_filter;
}

$sql = "SELECT po.*, v.name as vendor_name, pr.requisition_number, u.first_name, u.last_name 
        FROM purchase_orders po 
        JOIN vendors v ON po.vendor_id = v.id 
        JOIN purchase_requisitions pr ON po.requisition_id = pr.id 
        JOIN users u ON po.created_by = u.id 
        $whereClause 
        ORDER BY po.created_at DESC 
        LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$purchase_orders = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM purchase_orders po 
             JOIN vendors v ON po.vendor_id = v.id 
             JOIN purchase_requisitions pr ON po.requisition_id = pr.id 
             JOIN users u ON po.created_by = u.id 
             $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalPOs = $countStmt->fetchColumn();
$totalPages = ceil($totalPOs / ITEMS_PER_PAGE);

// Get PO details for view
$po_details = null;
$po_items = [];
if ($po_id && $action == 'view') {
    $sql = "SELECT po.*, v.name as vendor_name, v.contact_person, v.email, v.phone, v.address,
                   pr.requisition_number, pr.department, u.first_name, u.last_name
            FROM purchase_orders po 
            JOIN vendors v ON po.vendor_id = v.id 
            JOIN purchase_requisitions pr ON po.requisition_id = pr.id 
            JOIN users u ON po.created_by = u.id 
            WHERE po.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$po_id]);
    $po_details = $stmt->fetch();
    
    if ($po_details) {
        $sql = "SELECT poi.*, ii.name as item_name, ii.item_code, ii.unit_of_measure 
                FROM po_items poi 
                JOIN inventory_items ii ON poi.item_id = ii.id 
                WHERE poi.po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$po_id]);
        $po_items = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #495057;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .main-content {
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h5 class="text-primary fw-bold"><?php echo APP_NAME; ?></h5>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if (hasPermission('manage_vendors')): ?>
                        <a class="nav-link" href="vendor.php">
                            <i class="bi bi-building me-2"></i>Vendors
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('create_requisition')): ?>
                        <a class="nav-link" href="requisition.php">
                            <i class="bi bi-file-earmark-text me-2"></i>Requisitions
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('approve_requisition')): ?>
                        <a class="nav-link" href="approval.php">
                            <i class="bi bi-check-circle me-2"></i>Approvals
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('create_po')): ?>
                        <a class="nav-link active" href="purchase_order.php">
                            <i class="bi bi-receipt me-2"></i>Purchase Orders
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_inventory')): ?>
                        <a class="nav-link" href="inventory.php">
                            <i class="bi bi-boxes me-2"></i>Inventory
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_analytics')): ?>
                        <a class="nav-link" href="analytics.php">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('manage_users')): ?>
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="bi bi-shield-check me-2"></i>Admin Panel
                        </a>
                        <?php endif; ?>
                        <a class="nav-link" href="notification_center.php">
                            <i class="bi bi-bell me-2"></i>Notifications
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <hr>
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Purchase Orders</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                        <i class="bi bi-plus-circle me-2"></i>Create PO
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action == 'list' || !$action): ?>
                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Search POs..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                    <option value="acknowledged" <?php echo $status_filter == 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                                    <option value="partially_received" <?php echo $status_filter == 'partially_received' ? 'selected' : ''; ?>>Partially Received</option>
                                    <option value="fully_received" <?php echo $status_filter == 'fully_received' ? 'selected' : ''; ?>>Fully Received</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                                <a href="purchase_order.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Purchase Orders Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Purchase Orders (<?php echo $totalPOs; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($purchase_orders)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-receipt fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No purchase orders found</h5>
                                <p class="text-muted">Start by creating your first purchase order</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PO Number</th>
                                            <th>Requisition</th>
                                            <th>Vendor</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Delivery Date</th>
                                            <th>Created By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchase_orders as $po): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($po['po_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($po['requisition_number']); ?></td>
                                                <td><?php echo htmlspecialchars($po['vendor_name']); ?></td>
                                                <td><?php echo formatCurrency($po['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $po['status'] == 'fully_received' ? 'success' : ($po['status'] == 'partially_received' ? 'warning' : ($po['status'] == 'sent' ? 'info' : 'secondary')); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $po['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $po['delivery_date'] ? formatDate($po['delivery_date']) : 'N/A'; ?></td>
                                                <td><?php echo htmlspecialchars($po['first_name'] . ' ' . $po['last_name']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?action=view&id=<?php echo $po['id']; ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($po['status'] == 'draft'): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="sendPO(<?php echo $po['id']; ?>)">
                                                                <i class="bi bi-send"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (in_array($po['status'], ['sent', 'acknowledged', 'partially_received'])): ?>
                                                            <button class="btn btn-sm btn-outline-success" onclick="receiveStock(<?php echo $po['id']; ?>)">
                                                                <i class="bi bi-box-arrow-in-down"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="PO pagination">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                <?php endif; ?>

                <?php if ($action == 'view' && $po_details): ?>
                <!-- View Purchase Order -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-eye me-2"></i>Purchase Order Details: <?php echo htmlspecialchars($po_details['po_number']); ?>
                            </h5>
                            <a href="?" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Purchase Order Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>PO Number:</strong></td><td><?php echo htmlspecialchars($po_details['po_number']); ?></td></tr>
                                    <tr><td><strong>Requisition:</strong></td><td><?php echo htmlspecialchars($po_details['requisition_number']); ?></td></tr>
                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($po_details['department']); ?></td></tr>
                                    <tr><td><strong>Status:</strong></td><td>
                                        <span class="badge bg-<?php echo $po_details['status'] == 'sent' ? 'success' : ($po_details['status'] == 'draft' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $po_details['status'])); ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Total Amount:</strong></td><td><?php echo formatCurrency($po_details['total_amount']); ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Vendor Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Vendor:</strong></td><td><?php echo htmlspecialchars($po_details['vendor_name']); ?></td></tr>
                                    <tr><td><strong>Contact:</strong></td><td><?php echo htmlspecialchars($po_details['contact_person']); ?></td></tr>
                                    <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($po_details['email']); ?></td></tr>
                                    <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($po_details['phone']); ?></td></tr>
                                    <tr><td><strong>Address:</strong></td><td><?php echo nl2br(htmlspecialchars($po_details['address'])); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Order Details</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Created By:</strong></td><td><?php echo htmlspecialchars($po_details['first_name'] . ' ' . $po_details['last_name']); ?></td></tr>
                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($po_details['created_at']); ?></td></tr>
                                    <tr><td><strong>Delivery Date:</strong></td><td><?php echo $po_details['delivery_date'] ? formatDate($po_details['delivery_date']) : 'Not specified'; ?></td></tr>
                                    <tr><td><strong>Payment Terms:</strong></td><td><?php echo htmlspecialchars($po_details['payment_terms']); ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <?php if ($po_details['notes']): ?>
                                <h6>Notes</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($po_details['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($po_items)): ?>
                        <h6>Order Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Received</th>
                                        <th>Remaining</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($po_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo $item['received_quantity']; ?></td>
                                        <td><?php echo $item['quantity'] - $item['received_quantity']; ?></td>
                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>
                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create PO Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createPOForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="create_from_requisition">
                        
                        <div class="mb-3">
                            <label for="requisition_id" class="form-label">Select Approved Requisition *</label>
                            <select class="form-select" id="requisition_id" name="requisition_id" required>
                                <option value="">Choose a requisition...</option>
                                <?php foreach ($approved_requisitions as $req): ?>
                                    <option value="<?php echo $req['id']; ?>" data-amount="<?php echo $req['total_amount']; ?>">
                                        <?php echo htmlspecialchars($req['requisition_number'] . ' - K' . number_format($req['total_amount'], 2)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vendor_id" class="form-label">Select Vendor *</label>
                            <select class="form-select" id="vendor_id" name="vendor_id" required>
                                <option value="">Choose a vendor...</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="delivery_date" class="form-label">Expected Delivery Date</label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payment_terms" class="form-label">Payment Terms</label>
                                <select class="form-select" id="payment_terms" name="payment_terms">
                                    <option value="Cash">Cash</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="Credit Card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes for the vendor..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Receive Stock Modal -->
    <div class="modal fade" id="receiveStockModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receive Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="receiveStockForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="receive_stock">
                        <input type="hidden" name="po_id" id="receivePOId">
                        
                        <div id="receiveStockContent">
                            <!-- Content will be loaded dynamically -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Receive Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notification badge every 30 seconds
        setInterval(function() {
            fetch('notification_center.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=get_count'
            })
            .then(response => response.json())
            .then(data => {
                const navLink = document.querySelector('.nav-link[href="notification_center.php"]');
                if (!navLink) return;
                let badge = navLink.querySelector('.badge');
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        navLink.innerHTML += ` <span class="badge bg-danger ms-2">${data.count}</span>`;
                    }
                } else if (badge) {
                    badge.remove();
                }
            })
            .catch(() => {});
        }, 30000);

        function sendPO(poId) {
            if (confirm('Are you sure you want to send this Purchase Order to the vendor?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="send_po">
                    <input type="hidden" name="id" value="${poId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function receiveStock(poId) {
            document.getElementById('receivePOId').value = poId;
            
            // Load PO items for receiving
            fetch(`api/po_items.php?po_id=${poId}`)
                .then(response => response.json())
                .then(data => {
                    let content = '<div class="table-responsive"><table class="table"><thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Receive Qty</th></tr></thead><tbody>';
                    
                    data.items.forEach(item => {
                        const remaining = item.quantity - item.received_quantity;
                        content += `
                            <tr>
                                <td>${item.item_name} (${item.item_code})</td>
                                <td>${item.quantity}</td>
                                <td>${item.received_quantity}</td>
                                <td>${remaining}</td>
                                <td>
                                    <input type="number" class="form-control" name="received_items[${item.item_id}]" 
                                           min="0" max="${remaining}" value="0">
                                </td>
                            </tr>
                        `;
                    });
                    
                    content += '</tbody></table></div>';
                    document.getElementById('receiveStockContent').innerHTML = content;
                    
                    new bootstrap.Modal(document.getElementById('receiveStockModal')).show();
                })
                .catch(error => {
                    console.error('Error loading PO items:', error);
                    alert('Error loading purchase order items');
                });
        }
    </script>
</body>
</html>
