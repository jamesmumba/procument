<?php
/**
 * Stock Transfer Management Page
 * Handles inter-department and inter-location stock transfers
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('transfer_stock');

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$transfer_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'create':
                case 'update':
                    $transferData = [
                        'requested_by' => $_SESSION['user_id'],
                        'department' => sanitizeInput($_POST['department']),
                        'priority' => sanitizeInput($_POST['priority']),
                        'reason' => sanitizeInput($_POST['reason']),
                        'status' => 'draft'
                    ];
                    
                    if ($action == 'create') {
                        $transferData['transfer_number'] = 'TRF' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $transferData['from_location_id'] = (int)$_POST['from_location_id'];
                        $transferData['to_location_id'] = (int)$_POST['to_location_id'];
                        
                        $sql = "INSERT INTO stock_transfers (transfer_number, from_location_id, to_location_id, requested_by, department, priority, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $transferData['transfer_number'],
                            $transferData['from_location_id'],
                            $transferData['to_location_id'],
                            $transferData['requested_by'],
                            $transferData['department'],
                            $transferData['priority'],
                            $transferData['reason'],
                            $transferData['status']
                        ]);
                        
                        $transfer_id = $conn->lastInsertId();
                        logAudit('create_stock_transfer', 'stock_transfers', $transfer_id, null, $transferData);
                        $message = 'Stock transfer created successfully!';
                    } else {
                        $sql = "UPDATE stock_transfers SET department = ?, priority = ?, reason = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $transferData['department'],
                            $transferData['priority'],
                            $transferData['reason'],
                            $transfer_id
                        ]);
                        
                        logAudit('update_stock_transfer', 'stock_transfers', $transfer_id, null, $transferData);
                        $message = 'Stock transfer updated successfully!';
                    }
                    break;
                    
                case 'add_item':
                    $item_id = (int)$_POST['item_id'];
                    $quantity = (int)$_POST['quantity'];
                    
                    // Check if item has sufficient stock at source location
                    $sql = "SELECT current_stock FROM inventory_items WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$item_id]);
                    $current_stock = $stmt->fetchColumn();
                    
                    if ($current_stock < $quantity) {
                        $error = 'Insufficient stock available. Current stock: ' . $current_stock;
                    } else {
                        // Get item details
                        $sql = "SELECT unit_cost FROM inventory_items WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$item_id]);
                        $item = $stmt->fetch();
                        
                        if ($item) {
                            $unit_cost = $item['unit_cost'];
                            $total_cost = $quantity * $unit_cost;
                            
                            $sql = "INSERT INTO stock_transfer_items (transfer_id, item_id, quantity_transferred, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$transfer_id, $item_id, $quantity, $unit_cost, $total_cost]);
                            
                            // Update transfer total
                            $sql = "UPDATE stock_transfers SET total_value = (SELECT SUM(total_cost) FROM stock_transfer_items WHERE transfer_id = ?) WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$transfer_id, $transfer_id]);
                            
                            logAudit('add_transfer_item', 'stock_transfer_items', $conn->lastInsertId(), null, ['item_id' => $item_id, 'quantity' => $quantity]);
                            $message = 'Item added to transfer successfully!';
                        }
                    }
                    break;
                    
                case 'remove_item':
                    $item_id = (int)$_POST['item_id'];
                    $sql = "DELETE FROM stock_transfer_items WHERE id = ? AND transfer_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$item_id, $transfer_id]);
                    
                    // Update transfer total
                    $sql = "UPDATE stock_transfers SET total_value = (SELECT COALESCE(SUM(total_cost), 0) FROM stock_transfer_items WHERE transfer_id = ?) WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$transfer_id, $transfer_id]);
                    
                    logAudit('remove_transfer_item', 'stock_transfer_items', $item_id);
                    $message = 'Item removed from transfer successfully!';
                    break;
                    
                case 'submit':
                    $sql = "UPDATE stock_transfers SET status = 'submitted' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$transfer_id]);
                    
                    // Send notification to CPO for approval
                    $sql = "SELECT st.*, u.first_name, u.last_name 
                            FROM stock_transfers st 
                            JOIN users u ON st.requested_by = u.id 
                            WHERE st.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$transfer_id]);
                    $transfer_info = $stmt->fetch();
                    
                    if ($transfer_info) {
                        // Get CPO users
                        $sql = "SELECT id FROM users WHERE role_id = 2 AND is_active = 1";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $cpo_users = $stmt->fetchAll();
                        
                        foreach ($cpo_users as $cpo_user) {
                            $notificationSystem = new NotificationSystem();
                            $notificationSystem->createNotification(
                                $cpo_user['id'],
                                "Stock Transfer for Approval: " . $transfer_info['transfer_number'],
                                "Stock transfer {$transfer_info['transfer_number']} from {$transfer_info['first_name']} {$transfer_info['last_name']} requires your approval. Total value: " . formatCurrency($transfer_info['total_value']),
                                'info',
                                'approval',
                                'stock_transfer.php?action=view&id=' . $transfer_id,
                                [
                                    'transfer_id' => $transfer_id,
                                    'transfer_number' => $transfer_info['transfer_number'],
                                    'total_value' => $transfer_info['total_value'],
                                    'requester_name' => $transfer_info['first_name'] . ' ' . $transfer_info['last_name']
                                ]
                            );
                        }
                    }
                    
                    logAudit('submit_stock_transfer', 'stock_transfers', $transfer_id);
                    $message = 'Stock transfer submitted for approval!';
                    break;
                    
                case 'approve':
                    $sql = "UPDATE stock_transfers SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                    
                    // Send notification to requester
                    $sql = "SELECT st.*, u.first_name, u.last_name, u.id as requester_id 
                            FROM stock_transfers st 
                            JOIN users u ON st.requested_by = u.id 
                            WHERE st.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$transfer_id]);
                    $transfer_info = $stmt->fetch();
                    
                    if ($transfer_info) {
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->createNotification(
                            $transfer_info['requester_id'],
                            "Stock Transfer Approved: " . $transfer_info['transfer_number'],
                            "Your stock transfer {$transfer_info['transfer_number']} has been approved and is ready for execution.",
                            'success',
                            'inventory',
                            'stock_transfer.php?action=view&id=' . $transfer_id,
                            [
                                'transfer_id' => $transfer_id,
                                'transfer_number' => $transfer_info['transfer_number'],
                                'status' => 'approved'
                            ]
                        );
                    }
                    
                    logAudit('approve_stock_transfer', 'stock_transfers', $transfer_id);
                    $message = 'Stock transfer approved successfully!';
                    break;
                    
                case 'transfer':
                    $conn->beginTransaction();
                    try {
                        // Get transfer details
                        $sql = "SELECT st.*, fl.location_name as from_location, tl.location_name as to_location 
                                FROM stock_transfers st 
                                LEFT JOIN inventory_locations fl ON st.from_location_id = fl.id 
                                LEFT JOIN inventory_locations tl ON st.to_location_id = tl.id 
                                WHERE st.id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$transfer_id]);
                        $transfer = $stmt->fetch();
                        
                        // Get transfer items
                        $sql = "SELECT sti.*, ii.name as item_name, ii.item_code 
                                FROM stock_transfer_items sti 
                                JOIN inventory_items ii ON sti.item_id = ii.id 
                                WHERE sti.transfer_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$transfer_id]);
                        $transfer_items = $stmt->fetchAll();
                        
                        foreach ($transfer_items as $item) {
                            // Check current stock
                            $sql = "SELECT current_stock FROM inventory_items WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$item['item_id']]);
                            $current_stock = $stmt->fetchColumn();
                            
                            if ($current_stock < $item['quantity_transferred']) {
                                throw new Exception("Insufficient stock for item: " . $item['item_name']);
                            }
                            
                            // Update inventory stock
                            $sql = "UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$item['quantity_transferred'], $item['item_id']]);
                            
                            // Record transactions
                            $sql = "INSERT INTO inventory_transactions (item_id, location_id, transaction_type, quantity_change, unit_cost, total_value, reference_type, reference_id, notes, created_by) VALUES (?, ?, 'transfer_out', ?, ?, ?, 'stock_transfer', ?, 'Stock transferred out', ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                $item['item_id'],
                                $transfer['from_location_id'],
                                -$item['quantity_transferred'],
                                $item['unit_cost'],
                                $item['total_cost'],
                                $transfer_id,
                                $_SESSION['user_id']
                            ]);
                            
                            $sql = "INSERT INTO inventory_transactions (item_id, location_id, transaction_type, quantity_change, unit_cost, total_value, reference_type, reference_id, notes, created_by) VALUES (?, ?, 'transfer_in', ?, ?, ?, 'stock_transfer', ?, 'Stock transferred in', ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                $item['item_id'],
                                $transfer['to_location_id'],
                                $item['quantity_transferred'],
                                $item['unit_cost'],
                                $item['total_cost'],
                                $transfer_id,
                                $_SESSION['user_id']
                            ]);
                            
                            logAudit('transfer_stock', 'inventory_items', $item['item_id'], null, ['quantity_transferred' => $item['quantity_transferred']]);
                        }
                        
                        $sql = "UPDATE stock_transfers SET status = 'transferred', transferred_by = ?, transferred_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                        
                        $conn->commit();
                        $message = 'Stock transfer completed successfully!';
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;
                    
                case 'reject':
                    $comments = sanitizeInput($_POST['comments']);
                    $sql = "UPDATE stock_transfers SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_SESSION['user_id'], $transfer_id]);
                    
                    // Send notification to requester
                    $sql = "SELECT st.*, u.first_name, u.last_name, u.id as requester_id 
                            FROM stock_transfers st 
                            JOIN users u ON st.requested_by = u.id 
                            WHERE st.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$transfer_id]);
                    $transfer_info = $stmt->fetch();
                    
                    if ($transfer_info) {
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->createNotification(
                            $transfer_info['requester_id'],
                            "Stock Transfer Rejected: " . $transfer_info['transfer_number'],
                            "Your stock transfer {$transfer_info['transfer_number']} has been rejected. Comments: " . $comments,
                            'error',
                            'inventory',
                            'stock_transfer.php?action=view&id=' . $transfer_id,
                            [
                                'transfer_id' => $transfer_id,
                                'transfer_number' => $transfer_info['transfer_number'],
                                'status' => 'rejected',
                                'comments' => $comments
                            ]
                        );
                    }
                    
                    logAudit('reject_stock_transfer', 'stock_transfers', $transfer_id, null, ['comments' => $comments]);
                    $message = 'Stock transfer rejected!';
                    break;
                    
                case 'cancel':
                    $sql = "UPDATE stock_transfers SET status = 'cancelled' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$transfer_id]);
                    
                    logAudit('cancel_stock_transfer', 'stock_transfers', $transfer_id);
                    $message = 'Stock transfer cancelled!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get transfer data for edit
$transfer = null;
$transfer_items = [];
if ($transfer_id && in_array($action, ['edit', 'view', 'approve', 'transfer'])) {
    $sql = "SELECT st.*, u.first_name, u.last_name, fl.location_name as from_location, tl.location_name as to_location 
            FROM stock_transfers st 
            LEFT JOIN users u ON st.requested_by = u.id 
            LEFT JOIN inventory_locations fl ON st.from_location_id = fl.id 
            LEFT JOIN inventory_locations tl ON st.to_location_id = tl.id 
            WHERE st.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();
    
    if ($transfer) {
        $sql = "SELECT sti.*, ii.name as item_name, ii.item_code, ii.unit_of_measure, ii.current_stock 
                FROM stock_transfer_items sti 
                JOIN inventory_items ii ON sti.item_id = ii.id 
                WHERE sti.transfer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$transfer_id]);
        $transfer_items = $stmt->fetchAll();
    }
}

// Get stock transfers list
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (st.transfer_number LIKE ? OR st.department LIKE ? OR st.reason LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

if ($status_filter) {
    $whereClause .= " AND st.status = ?";
    $params[] = $status_filter;
}

// Filter by user only for basic users (non-admin without approval/transfer permissions)
if ($_SESSION['role_id'] != 1 && !hasPermission('approve_stock_transfers') && !hasPermission('transfer_stock')) {
    $whereClause .= " AND st.requested_by = ?";
    $params[] = $_SESSION['user_id'];
}

$sql = "SELECT st.*, u.first_name, u.last_name, fl.location_name as from_location, tl.location_name as to_location 
        FROM stock_transfers st 
        LEFT JOIN users u ON st.requested_by = u.id 
        LEFT JOIN inventory_locations fl ON st.from_location_id = fl.id 
        LEFT JOIN inventory_locations tl ON st.to_location_id = tl.id 
        $whereClause 
        ORDER BY st.created_at DESC 
        LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$stock_transfers = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM stock_transfers st LEFT JOIN users u ON st.requested_by = u.id LEFT JOIN inventory_locations fl ON st.from_location_id = fl.id LEFT JOIN inventory_locations tl ON st.to_location_id = tl.id $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);

// Get inventory items for dropdown
$sql = "SELECT id, item_code, name, current_stock, unit_of_measure, unit_cost FROM inventory_items WHERE is_active = 1 AND current_stock > 0 ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$inventory_items = $stmt->fetchAll();

// Get locations for dropdown
$sql = "SELECT id, location_code, location_name FROM inventory_locations WHERE is_active = 1 ORDER BY location_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$locations = $stmt->fetchAll();

$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfers - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
        }
        .priority-high { background-color: #dc3545; color: white; }
        .priority-medium { background-color: #ffc107; color: black; }
        .priority-low { background-color: #28a745; color: white; }
        .priority-urgent { background-color: #6f42c1; color: white; }
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
                        <a class="nav-link" href="purchase_order.php">
                            <i class="bi bi-receipt me-2"></i>Purchase Orders
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_inventory')): ?>
                        <a class="nav-link" href="inventory.php">
                            <i class="bi bi-boxes me-2"></i>Inventory
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('issue_inventory')): ?>
                        <a class="nav-link" href="inventory_issue.php">
                            <i class="bi bi-box-arrow-up me-2"></i>Issue Inventory
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('transfer_stock')): ?>
                        <a class="nav-link active" href="stock_transfer.php">
                            <i class="bi bi-arrow-left-right me-2"></i>Stock Transfer
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('manage_consumption')): ?>
                        <a class="nav-link" href="consumption.php">
                            <i class="bi bi-graph-down me-2"></i>Consumption
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('process_returns')): ?>
                        <a class="nav-link" href="inventory_returns.php">
                            <i class="bi bi-arrow-return-left me-2"></i>Returns
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_analytics')): ?>
                        <a class="nav-link" href="analytics.php">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </a>
                        <?php endif; ?>
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
                    <h2>Stock Transfers</h2>
                    <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($user['role_name']); ?></span>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>
                <!-- Transfers List -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Stock Transfers</h5>
                            <a href="?action=create" class="btn btn-light btn-sm">
                                <i class="bi bi-plus-circle me-1"></i>New Transfer
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="search" placeholder="Search transfers..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="status_filter">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="transferred" <?php echo $status_filter == 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary" onclick="applyFilters()">
                                    <i class="bi bi-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>

                        <!-- Transfers Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Transfer #</th>
                                        <th>From Location</th>
                                        <th>To Location</th>
                                        <th>Department</th>
                                        <th>Requested By</th>
                                        <th>Priority</th>
                                        <th>Total Value</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_transfers as $transfer): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($transfer['transfer_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($transfer['from_location']); ?></td>
                                        <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>
                                        <td><?php echo htmlspecialchars($transfer['department']); ?></td>
                                        <td><?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?></td>
                                        <td>
                                            <span class="status-badge priority-<?php echo $transfer['priority']; ?>">
                                                <?php echo ucfirst($transfer['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($transfer['total_value']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $transfer['status'] == 'approved' ? 'success' : ($transfer['status'] == 'rejected' ? 'danger' : ($transfer['status'] == 'transferred' ? 'info' : 'warning')); ?>">
                                                <?php echo ucfirst($transfer['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($transfer['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=view&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>
                                                <a href="?action=edit&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission('approve_stock_transfers') && $transfer['status'] == 'submitted'): ?>
                                                <a href="?action=approve&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission('transfer_stock') && $transfer['status'] == 'approved'): ?>
                                                <a href="?action=transfer&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-info">
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </a>
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
                        <nav aria-label="Transfers pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'create' || $action == 'edit'): ?>
                <!-- Create/Edit Transfer Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'pencil'; ?> me-2"></i>
                            <?php echo $action == 'create' ? 'Create New' : 'Edit'; ?> Stock Transfer
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="from_location_id" class="form-label">From Location *</label>
                                    <select class="form-select" id="from_location_id" name="from_location_id" required>
                                        <option value="">Select Source Location</option>
                                        <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['id']; ?>" 
                                                <?php echo ($transfer['from_location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location['location_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="to_location_id" class="form-label">To Location *</label>
                                    <select class="form-select" id="to_location_id" name="to_location_id" required>
                                        <option value="">Select Destination Location</option>
                                        <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['id']; ?>" 
                                                <?php echo ($transfer['to_location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location['location_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department *</label>
                                    <input type="text" class="form-control" id="department" name="department" 
                                           value="<?php echo htmlspecialchars($transfer['department'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority *</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="low" <?php echo ($transfer['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($transfer['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($transfer['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo ($transfer['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label">Transfer Reason *</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo htmlspecialchars($transfer['reason'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="?" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Back to List
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'check'; ?> me-1"></i>
                                    <?php echo $action == 'create' ? 'Create Transfer' : 'Update Transfer'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && $transfer): ?>
                <!-- View Transfer -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-eye me-2"></i>Transfer Details: <?php echo htmlspecialchars($transfer['transfer_number']); ?>
                            </h5>
                            <div>
                                <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>
                                <a href="?action=edit&id=<?php echo $transfer['id']; ?>" class="btn btn-warning btn-sm me-2">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                                <?php endif; ?>
                                <a href="?" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-arrow-left me-1"></i>Back
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Transfer Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Transfer Number:</strong></td><td><?php echo htmlspecialchars($transfer['transfer_number']); ?></td></tr>
                                    <tr><td><strong>From Location:</strong></td><td><?php echo htmlspecialchars($transfer['from_location']); ?></td></tr>
                                    <tr><td><strong>To Location:</strong></td><td><?php echo htmlspecialchars($transfer['to_location']); ?></td></tr>
                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($transfer['department']); ?></td></tr>
                                    <tr><td><strong>Priority:</strong></td><td>
                                        <span class="status-badge priority-<?php echo $transfer['priority']; ?>">
                                            <?php echo ucfirst($transfer['priority']); ?>
                                        </span>
                                    </td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Status Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Status:</strong></td><td>
                                        <span class="badge bg-<?php echo $transfer['status'] == 'approved' ? 'success' : ($transfer['status'] == 'rejected' ? 'danger' : ($transfer['status'] == 'transferred' ? 'info' : 'warning')); ?>">
                                            <?php echo ucfirst($transfer['status']); ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Requested By:</strong></td><td><?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?></td></tr>
                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($transfer['created_at']); ?></td></tr>
                                    <tr><td><strong>Total Value:</strong></td><td><?php echo formatCurrency($transfer['total_value']); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Transfer Reason</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($transfer['reason'])); ?></p>
                        </div>
                        
                        <?php if (!empty($transfer_items)): ?>
                        <h6>Transfer Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Current Stock</th>
                                        <th>Qty to Transfer</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transfer_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo $item['current_stock']; ?></td>
                                        <td><?php echo $item['quantity_transferred']; ?></td>
                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>
                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>
                        <div class="mt-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="submit">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-send me-1"></i>Submit for Approval
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status_filter').value;
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status', status);
            window.location.href = '?' + params.toString();
        }
    </script>
</body>
</html>

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

                        <a class="nav-link" href="purchase_order.php">

                            <i class="bi bi-receipt me-2"></i>Purchase Orders

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('view_inventory')): ?>

                        <a class="nav-link" href="inventory.php">

                            <i class="bi bi-boxes me-2"></i>Inventory

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('issue_inventory')): ?>

                        <a class="nav-link" href="inventory_issue.php">

                            <i class="bi bi-box-arrow-up me-2"></i>Issue Inventory

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('transfer_stock')): ?>

                        <a class="nav-link active" href="stock_transfer.php">

                            <i class="bi bi-arrow-left-right me-2"></i>Stock Transfer

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('manage_consumption')): ?>

                        <a class="nav-link" href="consumption.php">

                            <i class="bi bi-graph-down me-2"></i>Consumption

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('process_returns')): ?>

                        <a class="nav-link" href="inventory_returns.php">

                            <i class="bi bi-arrow-return-left me-2"></i>Returns

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('view_analytics')): ?>

                        <a class="nav-link" href="analytics.php">

                            <i class="bi bi-graph-up me-2"></i>Analytics

                        </a>

                        <?php endif; ?>

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

                    <h2>Stock Transfers</h2>

                    <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($user['role_name']); ?></span>

                </div>



                <?php if ($message): ?>

                <div class="alert alert-success alert-dismissible fade show" role="alert">

                    <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if ($error): ?>

                <div class="alert alert-danger alert-dismissible fade show" role="alert">

                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if ($action == 'list'): ?>

                <!-- Transfers List -->

                <div class="card">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Stock Transfers</h5>

                            <a href="?action=create" class="btn btn-light btn-sm">

                                <i class="bi bi-plus-circle me-1"></i>New Transfer

                            </a>

                        </div>

                    </div>

                    <div class="card-body">

                        <!-- Filters -->

                        <div class="row mb-3">

                            <div class="col-md-4">

                                <input type="text" class="form-control" id="search" placeholder="Search transfers..." value="<?php echo htmlspecialchars($search); ?>">

                            </div>

                            <div class="col-md-3">

                                <select class="form-select" id="status_filter">

                                    <option value="">All Status</option>

                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>

                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>

                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>

                                    <option value="transferred" <?php echo $status_filter == 'transferred' ? 'selected' : ''; ?>>Transferred</option>

                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>

                                </select>

                            </div>

                            <div class="col-md-2">

                                <button class="btn btn-primary" onclick="applyFilters()">

                                    <i class="bi bi-search me-1"></i>Filter

                                </button>

                            </div>

                        </div>



                        <!-- Transfers Table -->

                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>

                                    <tr>

                                        <th>Transfer #</th>

                                        <th>From Location</th>

                                        <th>To Location</th>

                                        <th>Department</th>

                                        <th>Requested By</th>

                                        <th>Priority</th>

                                        <th>Total Value</th>

                                        <th>Status</th>

                                        <th>Created</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($stock_transfers as $transfer): ?>

                                    <tr>

                                        <td><strong><?php echo htmlspecialchars($transfer['transfer_number']); ?></strong></td>

                                        <td><?php echo htmlspecialchars($transfer['from_location']); ?></td>

                                        <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>

                                        <td><?php echo htmlspecialchars($transfer['department']); ?></td>

                                        <td><?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?></td>

                                        <td>

                                            <span class="status-badge priority-<?php echo $transfer['priority']; ?>">

                                                <?php echo ucfirst($transfer['priority']); ?>

                                            </span>

                                        </td>

                                        <td><?php echo formatCurrency($transfer['total_value']); ?></td>

                                        <td>

                                            <span class="badge bg-<?php echo $transfer['status'] == 'approved' ? 'success' : ($transfer['status'] == 'rejected' ? 'danger' : ($transfer['status'] == 'transferred' ? 'info' : 'warning')); ?>">

                                                <?php echo ucfirst($transfer['status']); ?>

                                            </span>

                                        </td>

                                        <td><?php echo formatDate($transfer['created_at']); ?></td>

                                        <td>

                                            <div class="btn-group btn-group-sm">

                                                <a href="?action=view&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-primary">

                                                    <i class="bi bi-eye"></i>

                                                </a>

                                                <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>

                                                <a href="?action=edit&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-warning">

                                                    <i class="bi bi-pencil"></i>

                                                </a>

                                                <?php endif; ?>

                                                <?php if (hasPermission('approve_stock_transfers') && $transfer['status'] == 'submitted'): ?>

                                                <a href="?action=approve&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-success">

                                                    <i class="bi bi-check"></i>

                                                </a>

                                                <?php endif; ?>

                                                <?php if (hasPermission('transfer_stock') && $transfer['status'] == 'approved'): ?>

                                                <a href="?action=transfer&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-info">

                                                    <i class="bi bi-arrow-left-right"></i>

                                                </a>

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

                        <nav aria-label="Transfers pagination">

                            <ul class="pagination justify-content-center">

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">

                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>

                                </li>

                                <?php endfor; ?>

                            </ul>

                        </nav>

                        <?php endif; ?>

                    </div>

                </div>



                <?php elseif ($action == 'create' || $action == 'edit'): ?>

                <!-- Create/Edit Transfer Form -->

                <div class="card">

                    <div class="card-header">

                        <h5 class="mb-0">

                            <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'pencil'; ?> me-2"></i>

                            <?php echo $action == 'create' ? 'Create New' : 'Edit'; ?> Stock Transfer

                        </h5>

                    </div>

                    <div class="card-body">

                        <form method="POST">

                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <input type="hidden" name="action" value="<?php echo $action; ?>">

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="from_location_id" class="form-label">From Location *</label>

                                    <select class="form-select" id="from_location_id" name="from_location_id" required>

                                        <option value="">Select Source Location</option>

                                        <?php foreach ($locations as $location): ?>

                                        <option value="<?php echo $location['id']; ?>" 

                                                <?php echo ($transfer['from_location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>

                                            <?php echo htmlspecialchars($location['location_name']); ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="to_location_id" class="form-label">To Location *</label>

                                    <select class="form-select" id="to_location_id" name="to_location_id" required>

                                        <option value="">Select Destination Location</option>

                                        <?php foreach ($locations as $location): ?>

                                        <option value="<?php echo $location['id']; ?>" 

                                                <?php echo ($transfer['to_location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>

                                            <?php echo htmlspecialchars($location['location_name']); ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="department" class="form-label">Department *</label>

                                    <input type="text" class="form-control" id="department" name="department" 

                                           value="<?php echo htmlspecialchars($transfer['department'] ?? ''); ?>" required>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="priority" class="form-label">Priority *</label>

                                    <select class="form-select" id="priority" name="priority" required>

                                        <option value="low" <?php echo ($transfer['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>

                                        <option value="medium" <?php echo ($transfer['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>

                                        <option value="high" <?php echo ($transfer['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>

                                        <option value="urgent" <?php echo ($transfer['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>

                                    </select>

                                </div>

                            </div>

                            

                            <div class="mb-3">

                                <label for="reason" class="form-label">Transfer Reason *</label>

                                <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo htmlspecialchars($transfer['reason'] ?? ''); ?></textarea>

                            </div>

                            

                            <div class="d-flex justify-content-between">

                                <a href="?" class="btn btn-secondary">

                                    <i class="bi bi-arrow-left me-1"></i>Back to List

                                </a>

                                <button type="submit" class="btn btn-primary">

                                    <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'check'; ?> me-1"></i>

                                    <?php echo $action == 'create' ? 'Create Transfer' : 'Update Transfer'; ?>

                                </button>

                            </div>

                        </form>

                    </div>

                </div>



                <?php elseif ($action == 'view' && $transfer): ?>

                <!-- View Transfer -->

                <div class="card">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0">

                                <i class="bi bi-eye me-2"></i>Transfer Details: <?php echo htmlspecialchars($transfer['transfer_number']); ?>

                            </h5>

                            <div>

                                <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>

                                <a href="?action=edit&id=<?php echo $transfer['id']; ?>" class="btn btn-warning btn-sm me-2">

                                    <i class="bi bi-pencil me-1"></i>Edit

                                </a>

                                <?php endif; ?>

                                <a href="?" class="btn btn-secondary btn-sm">

                                    <i class="bi bi-arrow-left me-1"></i>Back

                                </a>

                            </div>

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="row mb-4">

                            <div class="col-md-6">

                                <h6>Transfer Information</h6>

                                <table class="table table-sm">

                                    <tr><td><strong>Transfer Number:</strong></td><td><?php echo htmlspecialchars($transfer['transfer_number']); ?></td></tr>

                                    <tr><td><strong>From Location:</strong></td><td><?php echo htmlspecialchars($transfer['from_location']); ?></td></tr>

                                    <tr><td><strong>To Location:</strong></td><td><?php echo htmlspecialchars($transfer['to_location']); ?></td></tr>

                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($transfer['department']); ?></td></tr>

                                    <tr><td><strong>Priority:</strong></td><td>

                                        <span class="status-badge priority-<?php echo $transfer['priority']; ?>">

                                            <?php echo ucfirst($transfer['priority']); ?>

                                        </span>

                                    </td></tr>

                                </table>

                            </div>

                            <div class="col-md-6">

                                <h6>Status Information</h6>

                                <table class="table table-sm">

                                    <tr><td><strong>Status:</strong></td><td>

                                        <span class="badge bg-<?php echo $transfer['status'] == 'approved' ? 'success' : ($transfer['status'] == 'rejected' ? 'danger' : ($transfer['status'] == 'transferred' ? 'info' : 'warning')); ?>">

                                            <?php echo ucfirst($transfer['status']); ?>

                                        </span>

                                    </td></tr>

                                    <tr><td><strong>Requested By:</strong></td><td><?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?></td></tr>

                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($transfer['created_at']); ?></td></tr>

                                    <tr><td><strong>Total Value:</strong></td><td><?php echo formatCurrency($transfer['total_value']); ?></td></tr>

                                </table>

                            </div>

                        </div>

                        

                        <div class="mb-3">

                            <h6>Transfer Reason</h6>

                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($transfer['reason'])); ?></p>

                        </div>

                        

                        <?php if (!empty($transfer_items)): ?>

                        <h6>Transfer Items</h6>

                        <div class="table-responsive">

                            <table class="table table-sm">

                                <thead>

                                    <tr>

                                        <th>Item Code</th>

                                        <th>Item Name</th>

                                        <th>Current Stock</th>

                                        <th>Qty to Transfer</th>

                                        <th>Unit Cost</th>

                                        <th>Total Cost</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($transfer_items as $item): ?>

                                    <tr>

                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>

                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>

                                        <td><?php echo $item['current_stock']; ?></td>

                                        <td><?php echo $item['quantity_transferred']; ?></td>

                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>

                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <?php endif; ?>

                        

                        <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>

                        <div class="mt-3">

                            <form method="POST" class="d-inline">

                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                <input type="hidden" name="action" value="submit">

                                <button type="submit" class="btn btn-success">

                                    <i class="bi bi-send me-1"></i>Submit for Approval

                                </button>

                            </form>

                        </div>

                        <?php endif; ?>

                    </div>

                </div>

                <?php endif; ?>

            </div>

        </div>

    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        function applyFilters() {

            const search = document.getElementById('search').value;

            const status = document.getElementById('status_filter').value;

            const params = new URLSearchParams();

            if (search) params.append('search', search);

            if (status) params.append('status', status);

            window.location.href = '?' + params.toString();

        }

    </script>

</body>

</html>



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

                        <a class="nav-link" href="purchase_order.php">

                            <i class="bi bi-receipt me-2"></i>Purchase Orders

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('view_inventory')): ?>

                        <a class="nav-link" href="inventory.php">

                            <i class="bi bi-boxes me-2"></i>Inventory

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('issue_inventory')): ?>

                        <a class="nav-link" href="inventory_issue.php">

                            <i class="bi bi-box-arrow-up me-2"></i>Issue Inventory

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('transfer_stock')): ?>

                        <a class="nav-link active" href="stock_transfer.php">

                            <i class="bi bi-arrow-left-right me-2"></i>Stock Transfer

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('manage_consumption')): ?>

                        <a class="nav-link" href="consumption.php">

                            <i class="bi bi-graph-down me-2"></i>Consumption

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('process_returns')): ?>

                        <a class="nav-link" href="inventory_returns.php">

                            <i class="bi bi-arrow-return-left me-2"></i>Returns

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('view_analytics')): ?>

                        <a class="nav-link" href="analytics.php">

                            <i class="bi bi-graph-up me-2"></i>Analytics

                        </a>

                        <?php endif; ?>

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

                    <h2>Stock Transfers</h2>

                    <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($user['role_name']); ?></span>

                </div>



                <?php if ($message): ?>

                <div class="alert alert-success alert-dismissible fade show" role="alert">

                    <i class="bi bi-check-circle me-2"></i><?php echo $message; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if ($error): ?>

                <div class="alert alert-danger alert-dismissible fade show" role="alert">

                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                </div>

                <?php endif; ?>



                <?php if ($action == 'list'): ?>

                <!-- Transfers List -->

                <div class="card">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Stock Transfers</h5>

                            <a href="?action=create" class="btn btn-light btn-sm">

                                <i class="bi bi-plus-circle me-1"></i>New Transfer

                            </a>

                        </div>

                    </div>

                    <div class="card-body">

                        <!-- Filters -->

                        <div class="row mb-3">

                            <div class="col-md-4">

                                <input type="text" class="form-control" id="search" placeholder="Search transfers..." value="<?php echo htmlspecialchars($search); ?>">

                            </div>

                            <div class="col-md-3">

                                <select class="form-select" id="status_filter">

                                    <option value="">All Status</option>

                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>

                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>

                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>

                                    <option value="transferred" <?php echo $status_filter == 'transferred' ? 'selected' : ''; ?>>Transferred</option>

                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>

                                </select>

                            </div>

                            <div class="col-md-2">

                                <button class="btn btn-primary" onclick="applyFilters()">

                                    <i class="bi bi-search me-1"></i>Filter

                                </button>

                            </div>

                        </div>



                        <!-- Transfers Table -->

                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>

                                    <tr>

                                        <th>Transfer #</th>

                                        <th>From Location</th>

                                        <th>To Location</th>

                                        <th>Department</th>

                                        <th>Requested By</th>

                                        <th>Priority</th>

                                        <th>Total Value</th>

                                        <th>Status</th>

                                        <th>Created</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($stock_transfers as $transfer): ?>

                                    <tr>

                                        <td><strong><?php echo htmlspecialchars($transfer['transfer_number']); ?></strong></td>

                                        <td><?php echo htmlspecialchars($transfer['from_location']); ?></td>

                                        <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>

                                        <td><?php echo htmlspecialchars($transfer['department']); ?></td>

                                        <td><?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?></td>

                                        <td>

                                            <span class="status-badge priority-<?php echo $transfer['priority']; ?>">

                                                <?php echo ucfirst($transfer['priority']); ?>

                                            </span>

                                        </td>

                                        <td><?php echo formatCurrency($transfer['total_value']); ?></td>

                                        <td>

                                            <span class="badge bg-<?php echo $transfer['status'] == 'approved' ? 'success' : ($transfer['status'] == 'rejected' ? 'danger' : ($transfer['status'] == 'transferred' ? 'info' : 'warning')); ?>">

                                                <?php echo ucfirst($transfer['status']); ?>

                                            </span>

                                        </td>

                                        <td><?php echo formatDate($transfer['created_at']); ?></td>

                                        <td>

                                            <div class="btn-group btn-group-sm">

                                                <a href="?action=view&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-primary">

                                                    <i class="bi bi-eye"></i>

                                                </a>

                                                <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>

                                                <a href="?action=edit&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-warning">

                                                    <i class="bi bi-pencil"></i>

                                                </a>

                                                <?php endif; ?>

                                                <?php if (hasPermission('approve_stock_transfers') && $transfer['status'] == 'submitted'): ?>

                                                <a href="?action=approve&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-success">

                                                    <i class="bi bi-check"></i>

                                                </a>

                                                <?php endif; ?>

                                                <?php if (hasPermission('transfer_stock') && $transfer['status'] == 'approved'): ?>

                                                <a href="?action=transfer&id=<?php echo $transfer['id']; ?>" class="btn btn-outline-info">

                                                    <i class="bi bi-arrow-left-right"></i>

                                                </a>

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

                        <nav aria-label="Transfers pagination">

                            <ul class="pagination justify-content-center">

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">

                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>

                                </li>

                                <?php endfor; ?>

                            </ul>

                        </nav>

                        <?php endif; ?>

                    </div>

                </div>



                <?php elseif ($action == 'create' || $action == 'edit'): ?>

                <!-- Create/Edit Transfer Form -->

                <div class="card">

                    <div class="card-header">

                        <h5 class="mb-0">

                            <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'pencil'; ?> me-2"></i>

                            <?php echo $action == 'create' ? 'Create New' : 'Edit'; ?> Stock Transfer

                        </h5>

                    </div>

                    <div class="card-body">

                        <form method="POST">

                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <input type="hidden" name="action" value="<?php echo $action; ?>">

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="from_location_id" class="form-label">From Location *</label>

                                    <select class="form-select" id="from_location_id" name="from_location_id" required>

                                        <option value="">Select Source Location</option>

                                        <?php foreach ($locations as $location): ?>

                                        <option value="<?php echo $location['id']; ?>" 

                                                <?php echo ($transfer['from_location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>

                                            <?php echo htmlspecialchars($location['location_name']); ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="to_location_id" class="form-label">To Location *</label>

                                    <select class="form-select" id="to_location_id" name="to_location_id" required>

                                        <option value="">Select Destination Location</option>

                                        <?php foreach ($locations as $location): ?>

                                        <option value="<?php echo $location['id']; ?>" 

                                                <?php echo ($transfer['to_location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>

                                            <?php echo htmlspecialchars($location['location_name']); ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="department" class="form-label">Department *</label>

                                    <input type="text" class="form-control" id="department" name="department" 

                                           value="<?php echo htmlspecialchars($transfer['department'] ?? ''); ?>" required>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="priority" class="form-label">Priority *</label>

                                    <select class="form-select" id="priority" name="priority" required>

                                        <option value="low" <?php echo ($transfer['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>

                                        <option value="medium" <?php echo ($transfer['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>

                                        <option value="high" <?php echo ($transfer['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>

                                        <option value="urgent" <?php echo ($transfer['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>

                                    </select>

                                </div>

                            </div>

                            

                            <div class="mb-3">

                                <label for="reason" class="form-label">Transfer Reason *</label>

                                <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo htmlspecialchars($transfer['reason'] ?? ''); ?></textarea>

                            </div>

                            

                            <div class="d-flex justify-content-between">

                                <a href="?" class="btn btn-secondary">

                                    <i class="bi bi-arrow-left me-1"></i>Back to List

                                </a>

                                <button type="submit" class="btn btn-primary">

                                    <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'check'; ?> me-1"></i>

                                    <?php echo $action == 'create' ? 'Create Transfer' : 'Update Transfer'; ?>

                                </button>

                            </div>

                        </form>

                    </div>

                </div>



                <?php elseif ($action == 'view' && $transfer): ?>

                <!-- View Transfer -->

                <div class="card">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0">

                                <i class="bi bi-eye me-2"></i>Transfer Details: <?php echo htmlspecialchars($transfer['transfer_number']); ?>

                            </h5>

                            <div>

                                <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>

                                <a href="?action=edit&id=<?php echo $transfer['id']; ?>" class="btn btn-warning btn-sm me-2">

                                    <i class="bi bi-pencil me-1"></i>Edit

                                </a>

                                <?php endif; ?>

                                <a href="?" class="btn btn-secondary btn-sm">

                                    <i class="bi bi-arrow-left me-1"></i>Back

                                </a>

                            </div>

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="row mb-4">

                            <div class="col-md-6">

                                <h6>Transfer Information</h6>

                                <table class="table table-sm">

                                    <tr><td><strong>Transfer Number:</strong></td><td><?php echo htmlspecialchars($transfer['transfer_number']); ?></td></tr>

                                    <tr><td><strong>From Location:</strong></td><td><?php echo htmlspecialchars($transfer['from_location']); ?></td></tr>

                                    <tr><td><strong>To Location:</strong></td><td><?php echo htmlspecialchars($transfer['to_location']); ?></td></tr>

                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($transfer['department']); ?></td></tr>

                                    <tr><td><strong>Priority:</strong></td><td>

                                        <span class="status-badge priority-<?php echo $transfer['priority']; ?>">

                                            <?php echo ucfirst($transfer['priority']); ?>

                                        </span>

                                    </td></tr>

                                </table>

                            </div>

                            <div class="col-md-6">

                                <h6>Status Information</h6>

                                <table class="table table-sm">

                                    <tr><td><strong>Status:</strong></td><td>

                                        <span class="badge bg-<?php echo $transfer['status'] == 'approved' ? 'success' : ($transfer['status'] == 'rejected' ? 'danger' : ($transfer['status'] == 'transferred' ? 'info' : 'warning')); ?>">

                                            <?php echo ucfirst($transfer['status']); ?>

                                        </span>

                                    </td></tr>

                                    <tr><td><strong>Requested By:</strong></td><td><?php echo htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']); ?></td></tr>

                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($transfer['created_at']); ?></td></tr>

                                    <tr><td><strong>Total Value:</strong></td><td><?php echo formatCurrency($transfer['total_value']); ?></td></tr>

                                </table>

                            </div>

                        </div>

                        

                        <div class="mb-3">

                            <h6>Transfer Reason</h6>

                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($transfer['reason'])); ?></p>

                        </div>

                        

                        <?php if (!empty($transfer_items)): ?>

                        <h6>Transfer Items</h6>

                        <div class="table-responsive">

                            <table class="table table-sm">

                                <thead>

                                    <tr>

                                        <th>Item Code</th>

                                        <th>Item Name</th>

                                        <th>Current Stock</th>

                                        <th>Qty to Transfer</th>

                                        <th>Unit Cost</th>

                                        <th>Total Cost</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($transfer_items as $item): ?>

                                    <tr>

                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>

                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>

                                        <td><?php echo $item['current_stock']; ?></td>

                                        <td><?php echo $item['quantity_transferred']; ?></td>

                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>

                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <?php endif; ?>

                        

                        <?php if ($transfer['status'] == 'draft' && $transfer['requested_by'] == $_SESSION['user_id']): ?>

                        <div class="mt-3">

                            <form method="POST" class="d-inline">

                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                <input type="hidden" name="action" value="submit">

                                <button type="submit" class="btn btn-success">

                                    <i class="bi bi-send me-1"></i>Submit for Approval

                                </button>

                            </form>

                        </div>

                        <?php endif; ?>

                    </div>

                </div>

                <?php endif; ?>

            </div>

        </div>

    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        function applyFilters() {

            const search = document.getElementById('search').value;

            const status = document.getElementById('status_filter').value;

            const params = new URLSearchParams();

            if (search) params.append('search', search);

            if (status) params.append('status', status);

            window.location.href = '?' + params.toString();

        }

    </script>

</body>

</html>


