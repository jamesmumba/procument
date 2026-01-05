<?php
/**
 * Inventory Issue Management Page
 * Handles inventory item requests and issues
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('issue_inventory');

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$issue_id = $_GET['id'] ?? null;
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
                    $issueData = [
                        'requested_by' => $_SESSION['user_id'],
                        'department' => sanitizeInput($_POST['department']),
                        'cost_center' => sanitizeInput($_POST['cost_center']),
                        'location_id' => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
                        'priority' => sanitizeInput($_POST['priority']),
                        'justification' => sanitizeInput($_POST['justification']),
                        'status' => 'draft'
                    ];
                    
                    if ($action == 'create') {
                        $issueData['issue_number'] = 'ISS' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        
                        $sql = "INSERT INTO inventory_issues (issue_number, requested_by, department, cost_center, location_id, priority, justification, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $issueData['issue_number'],
                            $issueData['requested_by'],
                            $issueData['department'],
                            $issueData['cost_center'],
                            $issueData['location_id'],
                            $issueData['priority'],
                            $issueData['justification'],
                            $issueData['status']
                        ]);
                        
                        $issue_id = $conn->lastInsertId();
                        logAudit('create_inventory_issue', 'inventory_issues', $issue_id, null, $issueData);
                        $message = 'Inventory issue created successfully!';
                    } else {
                        $sql = "UPDATE inventory_issues SET department = ?, cost_center = ?, location_id = ?, priority = ?, justification = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $issueData['department'],
                            $issueData['cost_center'],
                            $issueData['location_id'],
                            $issueData['priority'],
                            $issueData['justification'],
                            $issue_id
                        ]);
                        
                        logAudit('update_inventory_issue', 'inventory_issues', $issue_id, null, $issueData);
                        $message = 'Inventory issue updated successfully!';
                    }
                    break;
                    
                case 'add_item':
                    $item_id = (int)$_POST['item_id'];
                    $quantity = (int)$_POST['quantity'];
                    $specifications = sanitizeInput($_POST['specifications']);
                    
                    // Get item details
                    $sql = "SELECT unit_cost FROM inventory_items WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$item_id]);
                    $item = $stmt->fetch();
                    
                    if ($item) {
                        $unit_cost = $item['unit_cost'];
                        $total_cost = $quantity * $unit_cost;
                        
                        $sql = "INSERT INTO inventory_issue_items (issue_id, item_id, quantity_requested, quantity_approved, unit_cost, total_cost, specifications) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$issue_id, $item_id, $quantity, 0, $unit_cost, $total_cost, $specifications]);
                        
                        // Update issue total
                        $sql = "UPDATE inventory_issues SET total_value = (SELECT SUM(total_cost) FROM inventory_issue_items WHERE issue_id = ?) WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$issue_id, $issue_id]);
                        
                        logAudit('add_issue_item', 'inventory_issue_items', $conn->lastInsertId(), null, ['item_id' => $item_id, 'quantity' => $quantity]);
                        $message = 'Item added to issue successfully!';
                    }
                    break;
                    
                case 'remove_item':
                    $item_id = (int)$_POST['item_id'];
                    $sql = "DELETE FROM inventory_issue_items WHERE id = ? AND issue_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$item_id, $issue_id]);
                    
                    // Update issue total
                    $sql = "UPDATE inventory_issues SET total_value = (SELECT COALESCE(SUM(total_cost), 0) FROM inventory_issue_items WHERE issue_id = ?) WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$issue_id, $issue_id]);
                    
                    logAudit('remove_issue_item', 'inventory_issue_items', $item_id);
                    $message = 'Item removed from issue successfully!';
                    break;
                    
                case 'submit':
                    $sql = "UPDATE inventory_issues SET status = 'submitted' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$issue_id]);
                    
                    // Send notification to CPO for approval
                    $sql = "SELECT ii.*, u.first_name, u.last_name 
                            FROM inventory_issues ii 
                            JOIN users u ON ii.requested_by = u.id 
                            WHERE ii.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$issue_id]);
                    $issue_info = $stmt->fetch();
                    
                    if ($issue_info) {
                        // Get CPO users
                        $sql = "SELECT id FROM users WHERE role_id = 2 AND is_active = 1";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $cpo_users = $stmt->fetchAll();
                        
                        foreach ($cpo_users as $cpo_user) {
                            $notificationSystem = new NotificationSystem();
                            $notificationSystem->createNotification(
                                $cpo_user['id'],
                                "Inventory Issue for Approval: " . $issue_info['issue_number'],
                                "Inventory issue {$issue_info['issue_number']} from {$issue_info['first_name']} {$issue_info['last_name']} requires your approval. Total value: " . formatCurrency($issue_info['total_value']),
                                'info',
                                'approval',
                                'inventory_issue.php?action=view&id=' . $issue_id,
                                [
                                    'issue_id' => $issue_id,
                                    'issue_number' => $issue_info['issue_number'],
                                    'total_value' => $issue_info['total_value'],
                                    'requester_name' => $issue_info['first_name'] . ' ' . $issue_info['last_name']
                                ]
                            );
                        }
                    }
                    
                    logAudit('submit_inventory_issue', 'inventory_issues', $issue_id);
                    $message = 'Inventory issue submitted for approval!';
                    break;
                    
                case 'approve':
                    $approved_items = $_POST['approved_items'] ?? [];
                    
                    $conn->beginTransaction();
                    try {
                        foreach ($approved_items as $item_id => $approved_qty) {
                            $sql = "UPDATE inventory_issue_items SET quantity_approved = ? WHERE id = ? AND issue_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$approved_qty, $item_id, $issue_id]);
                        }
                        
                        $sql = "UPDATE inventory_issues SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_SESSION['user_id'], $issue_id]);
                        
                        // Send notification to requester
                        $sql = "SELECT ii.*, u.first_name, u.last_name, u.id as requester_id 
                                FROM inventory_issues ii 
                                JOIN users u ON ii.requested_by = u.id 
                                WHERE ii.id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$issue_id]);
                        $issue_info = $stmt->fetch();
                        
                        if ($issue_info) {
                            $notificationSystem = new NotificationSystem();
                            $notificationSystem->createNotification(
                                $issue_info['requester_id'],
                                "Inventory Issue Approved: " . $issue_info['issue_number'],
                                "Your inventory issue {$issue_info['issue_number']} has been approved and is ready for issuance.",
                                'success',
                                'inventory',
                                'inventory_issue.php?action=view&id=' . $issue_id,
                                [
                                    'issue_id' => $issue_id,
                                    'issue_number' => $issue_info['issue_number'],
                                    'status' => 'approved'
                                ]
                            );
                        }
                        
                        $conn->commit();
                        logAudit('approve_inventory_issue', 'inventory_issues', $issue_id);
                        $message = 'Inventory issue approved successfully!';
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;
                    
                case 'issue':
                    $issued_items = $_POST['issued_items'] ?? [];
                    
                    $conn->beginTransaction();
                    try {
                        foreach ($issued_items as $item_id => $issued_qty) {
                            if ($issued_qty > 0) {
                                // Update issue item
                                $sql = "UPDATE inventory_issue_items SET quantity_issued = ? WHERE id = ? AND issue_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$issued_qty, $item_id, $issue_id]);
                                
                                // Get item details
                                $sql = "SELECT iii.item_id, iii.unit_cost, ii.location_id FROM inventory_issue_items iii 
                                        JOIN inventory_issues ii ON iii.issue_id = ii.id 
                                        WHERE iii.id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$item_id]);
                                $item = $stmt->fetch();
                                
                                // Update inventory stock
                                $sql = "UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$issued_qty, $item['item_id']]);
                                
                                // Notify only for this item if it's below reorder point
                                $notificationSystem = new NotificationSystem();
                                $notificationSystem->notifyLowStockForItem($item['item_id']);
                                
                                // Record transaction
                                $sql = "INSERT INTO inventory_transactions (item_id, location_id, transaction_type, quantity_change, unit_cost, total_value, reference_type, reference_id, notes, created_by) VALUES (?, ?, 'issue', ?, ?, ?, 'inventory_issue', ?, 'Inventory issued', ?)";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([
                                    $item['item_id'],
                                    $item['location_id'],
                                    -$issued_qty,
                                    $item['unit_cost'],
                                    $issued_qty * $item['unit_cost'],
                                    $issue_id,
                                    $_SESSION['user_id']
                                ]);
                                
                                logAudit('issue_inventory', 'inventory_items', $item['item_id'], null, ['quantity_issued' => $issued_qty]);
                            }
                        }
                        
                        $sql = "UPDATE inventory_issues SET status = 'issued', issued_by = ?, issued_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_SESSION['user_id'], $issue_id]);
                        
                        $conn->commit();
                        $message = 'Inventory issued successfully!';
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;
                    
                case 'reject':
                    $comments = sanitizeInput($_POST['comments']);
                    $sql = "UPDATE inventory_issues SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_SESSION['user_id'], $issue_id]);
                    
                    // Send notification to requester
                    $sql = "SELECT ii.*, u.first_name, u.last_name, u.id as requester_id 
                            FROM inventory_issues ii 
                            JOIN users u ON ii.requested_by = u.id 
                            WHERE ii.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$issue_id]);
                    $issue_info = $stmt->fetch();
                    
                    if ($issue_info) {
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->createNotification(
                            $issue_info['requester_id'],
                            "Inventory Issue Rejected: " . $issue_info['issue_number'],
                            "Your inventory issue {$issue_info['issue_number']} has been rejected. Comments: " . $comments,
                            'error',
                            'inventory',
                            'inventory_issue.php?action=view&id=' . $issue_id,
                            [
                                'issue_id' => $issue_id,
                                'issue_number' => $issue_info['issue_number'],
                                'status' => 'rejected',
                                'comments' => $comments
                            ]
                        );
                    }
                    
                    logAudit('reject_inventory_issue', 'inventory_issues', $issue_id, null, ['comments' => $comments]);
                    $message = 'Inventory issue rejected!';
                    break;
                    
                case 'cancel':
                    $sql = "UPDATE inventory_issues SET status = 'cancelled' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$issue_id]);
                    
                    logAudit('cancel_inventory_issue', 'inventory_issues', $issue_id);
                    $message = 'Inventory issue cancelled!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get issue data for edit
$issue = null;
$issue_items = [];
if ($issue_id && in_array($action, ['edit', 'view', 'approve', 'issue'])) {
    $sql = "SELECT ii.*, u.first_name, u.last_name, il.location_name 
            FROM inventory_issues ii 
            LEFT JOIN users u ON ii.requested_by = u.id 
            LEFT JOIN inventory_locations il ON ii.location_id = il.id 
            WHERE ii.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch();
    
    if ($issue) {
        $sql = "SELECT iii.*, ii.name as item_name, ii.item_code, ii.unit_of_measure 
                FROM inventory_issue_items iii 
                JOIN inventory_items ii ON iii.item_id = ii.id 
                WHERE iii.issue_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$issue_id]);
        $issue_items = $stmt->fetchAll();
    }
}

// Get inventory issues list
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (ii.issue_number LIKE ? OR ii.department LIKE ? OR ii.justification LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

if ($status_filter) {
    $whereClause .= " AND ii.status = ?";
    $params[] = $status_filter;
}

// Filter by user only for basic users (non-admin without approval/issue permissions)
if ($_SESSION['role_id'] != 1 && !hasPermission('approve_inventory_issues') && !hasPermission('issue_inventory')) {
    $whereClause .= " AND ii.requested_by = ?";
    $params[] = $_SESSION['user_id'];
}

$sql = "SELECT ii.*, u.first_name, u.last_name, il.location_name 
        FROM inventory_issues ii 
        LEFT JOIN users u ON ii.requested_by = u.id 
        LEFT JOIN inventory_locations il ON ii.location_id = il.id 
        $whereClause 
        ORDER BY ii.created_at DESC 
        LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$inventory_issues = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM inventory_issues ii LEFT JOIN users u ON ii.requested_by = u.id LEFT JOIN inventory_locations il ON ii.location_id = il.id $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);

// Get inventory items for dropdown
$sql = "SELECT id, item_code, name, current_stock, unit_of_measure, unit_cost FROM inventory_items WHERE is_active = 1 ORDER BY name";
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
    <title>Inventory Issues - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="inventory_issue.php">
                            <i class="bi bi-box-arrow-up me-2"></i>Issue Inventory
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('transfer_stock')): ?>
                        <a class="nav-link" href="stock_transfer.php">
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
                    <h2>Inventory Issues</h2>
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
                <!-- Issues List -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-box-arrow-up me-2"></i>Inventory Issues</h5>
                            <a href="?action=create" class="btn btn-light btn-sm">
                                <i class="bi bi-plus-circle me-1"></i>New Issue
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="search" placeholder="Search issues..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="status_filter">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="issued" <?php echo $status_filter == 'issued' ? 'selected' : ''; ?>>Issued</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary" onclick="applyFilters()">
                                    <i class="bi bi-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>

                        <!-- Issues Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Issue #</th>
                                        <th>Department</th>
                                        <th>Requested By</th>
                                        <th>Location</th>
                                        <th>Priority</th>
                                        <th>Total Value</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory_issues as $issue): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($issue['issue_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($issue['department']); ?></td>
                                        <td><?php echo htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($issue['location_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge priority-<?php echo $issue['priority']; ?>">
                                                <?php echo ucfirst($issue['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($issue['total_value']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $issue['status'] == 'approved' ? 'success' : ($issue['status'] == 'rejected' ? 'danger' : ($issue['status'] == 'issued' ? 'info' : 'warning')); ?>">
                                                <?php echo ucfirst($issue['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($issue['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=view&id=<?php echo $issue['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($issue['status'] == 'draft' && $issue['requested_by'] == $_SESSION['user_id']): ?>
                                                <a href="?action=edit&id=<?php echo $issue['id']; ?>" class="btn btn-outline-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission('approve_inventory_issues') && $issue['status'] == 'submitted'): ?>
                                                <a href="?action=approve&id=<?php echo $issue['id']; ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (hasPermission('issue_inventory') && $issue['status'] == 'approved'): ?>
                                                <a href="?action=issue&id=<?php echo $issue['id']; ?>" class="btn btn-outline-info">
                                                    <i class="bi bi-box-arrow-up"></i>
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
                        <nav aria-label="Issues pagination">
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
                <!-- Create/Edit Issue Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'pencil'; ?> me-2"></i>
                            <?php echo $action == 'create' ? 'Create New' : 'Edit'; ?> Inventory Issue
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department *</label>
                                    <input type="text" class="form-control" id="department" name="department" 
                                           value="<?php echo htmlspecialchars($issue['department'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cost_center" class="form-label">Cost Center</label>
                                    <input type="text" class="form-control" id="cost_center" name="cost_center" 
                                           value="<?php echo htmlspecialchars($issue['cost_center'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="location_id" class="form-label">Location</label>
                                    <select class="form-select" id="location_id" name="location_id">
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['id']; ?>" 
                                                <?php echo ($issue['location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location['location_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority *</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="low" <?php echo ($issue['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($issue['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($issue['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo ($issue['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="justification" class="form-label">Justification *</label>
                                <textarea class="form-control" id="justification" name="justification" rows="3" required><?php echo htmlspecialchars($issue['justification'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="?" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Back to List
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'check'; ?> me-1"></i>
                                    <?php echo $action == 'create' ? 'Create Issue' : 'Update Issue'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action == 'view' && $issue): ?>
                <!-- View Issue -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-eye me-2"></i>Issue Details: <?php echo htmlspecialchars($issue['issue_number']); ?>
                            </h5>
                            <div>
                                <?php if ($issue['status'] == 'draft' && $issue['requested_by'] == $_SESSION['user_id']): ?>
                                <a href="?action=edit&id=<?php echo $issue['id']; ?>" class="btn btn-warning btn-sm me-2">
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
                                <h6>Issue Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Issue Number:</strong></td><td><?php echo htmlspecialchars($issue['issue_number']); ?></td></tr>
                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($issue['department']); ?></td></tr>
                                    <tr><td><strong>Cost Center:</strong></td><td><?php echo htmlspecialchars($issue['cost_center']); ?></td></tr>
                                    <tr><td><strong>Location:</strong></td><td><?php echo htmlspecialchars($issue['location_name'] ?? 'N/A'); ?></td></tr>
                                    <tr><td><strong>Priority:</strong></td><td>
                                        <span class="status-badge priority-<?php echo $issue['priority']; ?>">
                                            <?php echo ucfirst($issue['priority']); ?>
                                        </span>
                                    </td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Status Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Status:</strong></td><td>
                                        <span class="badge bg-<?php echo $issue['status'] == 'approved' ? 'success' : ($issue['status'] == 'rejected' ? 'danger' : ($issue['status'] == 'issued' ? 'info' : 'warning')); ?>">
                                            <?php echo ucfirst($issue['status']); ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Requested By:</strong></td><td><?php echo htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']); ?></td></tr>
                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($issue['created_at']); ?></td></tr>
                                    <tr><td><strong>Total Value:</strong></td><td><?php echo formatCurrency($issue['total_value']); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Justification</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($issue['justification'])); ?></p>
                        </div>
                        
                        <?php if (!empty($issue_items)): ?>
                        <h6>Requested Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Qty Requested</th>
                                        <th>Qty Approved</th>
                                        <th>Qty Issued</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                        <th>Specifications</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($issue_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo $item['quantity_requested']; ?></td>
                                        <td><?php echo $item['quantity_approved']; ?></td>
                                        <td><?php echo $item['quantity_issued']; ?></td>
                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>
                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>
                                        <td><?php echo htmlspecialchars($item['specifications']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($issue['status'] == 'draft' && $issue['requested_by'] == $_SESSION['user_id']): ?>
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

                        <a class="nav-link active" href="inventory_issue.php">

                            <i class="bi bi-box-arrow-up me-2"></i>Issue Inventory

                        </a>

                        <?php endif; ?>

                        <?php if (hasPermission('transfer_stock')): ?>

                        <a class="nav-link" href="stock_transfer.php">

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

                    <h2>Inventory Issues</h2>

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

                <!-- Issues List -->

                <div class="card">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0"><i class="bi bi-box-arrow-up me-2"></i>Inventory Issues</h5>

                            <a href="?action=create" class="btn btn-light btn-sm">

                                <i class="bi bi-plus-circle me-1"></i>New Issue

                            </a>

                        </div>

                    </div>

                    <div class="card-body">

                        <!-- Filters -->

                        <div class="row mb-3">

                            <div class="col-md-4">

                                <input type="text" class="form-control" id="search" placeholder="Search issues..." value="<?php echo htmlspecialchars($search); ?>">

                            </div>

                            <div class="col-md-3">

                                <select class="form-select" id="status_filter">

                                    <option value="">All Status</option>

                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>

                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>

                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>

                                    <option value="issued" <?php echo $status_filter == 'issued' ? 'selected' : ''; ?>>Issued</option>

                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>

                                </select>

                            </div>

                            <div class="col-md-2">

                                <button class="btn btn-primary" onclick="applyFilters()">

                                    <i class="bi bi-search me-1"></i>Filter

                                </button>

                            </div>

                        </div>



                        <!-- Issues Table -->

                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>

                                    <tr>

                                        <th>Issue #</th>

                                        <th>Department</th>

                                        <th>Requested By</th>

                                        <th>Location</th>

                                        <th>Priority</th>

                                        <th>Total Value</th>

                                        <th>Status</th>

                                        <th>Created</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($inventory_issues as $issue): ?>

                                    <tr>

                                        <td><strong><?php echo htmlspecialchars($issue['issue_number']); ?></strong></td>

                                        <td><?php echo htmlspecialchars($issue['department']); ?></td>

                                        <td><?php echo htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']); ?></td>

                                        <td><?php echo htmlspecialchars($issue['location_name'] ?? 'N/A'); ?></td>

                                        <td>

                                            <span class="status-badge priority-<?php echo $issue['priority']; ?>">

                                                <?php echo ucfirst($issue['priority']); ?>

                                            </span>

                                        </td>

                                        <td><?php echo formatCurrency($issue['total_value']); ?></td>

                                        <td>

                                            <span class="badge bg-<?php echo $issue['status'] == 'approved' ? 'success' : ($issue['status'] == 'rejected' ? 'danger' : ($issue['status'] == 'issued' ? 'info' : 'warning')); ?>">

                                                <?php echo ucfirst($issue['status']); ?>

                                            </span>

                                        </td>

                                        <td><?php echo formatDate($issue['created_at']); ?></td>

                                        <td>

                                            <div class="btn-group btn-group-sm">

                                                <a href="?action=view&id=<?php echo $issue['id']; ?>" class="btn btn-outline-primary">

                                                    <i class="bi bi-eye"></i>

                                                </a>

                                                <?php if ($issue['status'] == 'draft' && $issue['requested_by'] == $_SESSION['user_id']): ?>

                                                <a href="?action=edit&id=<?php echo $issue['id']; ?>" class="btn btn-outline-warning">

                                                    <i class="bi bi-pencil"></i>

                                                </a>

                                                <?php endif; ?>

                                                <?php if (hasPermission('approve_inventory_issues') && $issue['status'] == 'submitted'): ?>

                                                <a href="?action=approve&id=<?php echo $issue['id']; ?>" class="btn btn-outline-success">

                                                    <i class="bi bi-check"></i>

                                                </a>

                                                <?php endif; ?>

                                                <?php if (hasPermission('issue_inventory') && $issue['status'] == 'approved'): ?>

                                                <a href="?action=issue&id=<?php echo $issue['id']; ?>" class="btn btn-outline-info">

                                                    <i class="bi bi-box-arrow-up"></i>

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

                        <nav aria-label="Issues pagination">

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

                <!-- Create/Edit Issue Form -->

                <div class="card">

                    <div class="card-header">

                        <h5 class="mb-0">

                            <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'pencil'; ?> me-2"></i>

                            <?php echo $action == 'create' ? 'Create New' : 'Edit'; ?> Inventory Issue

                        </h5>

                    </div>

                    <div class="card-body">

                        <form method="POST">

                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <input type="hidden" name="action" value="<?php echo $action; ?>">

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="department" class="form-label">Department *</label>

                                    <input type="text" class="form-control" id="department" name="department" 

                                           value="<?php echo htmlspecialchars($issue['department'] ?? ''); ?>" required>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="cost_center" class="form-label">Cost Center</label>

                                    <input type="text" class="form-control" id="cost_center" name="cost_center" 

                                           value="<?php echo htmlspecialchars($issue['cost_center'] ?? ''); ?>">

                                </div>

                            </div>

                            

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="location_id" class="form-label">Location</label>

                                    <select class="form-select" id="location_id" name="location_id">

                                        <option value="">Select Location</option>

                                        <?php foreach ($locations as $location): ?>

                                        <option value="<?php echo $location['id']; ?>" 

                                                <?php echo ($issue['location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>

                                            <?php echo htmlspecialchars($location['location_name']); ?>

                                        </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="priority" class="form-label">Priority *</label>

                                    <select class="form-select" id="priority" name="priority" required>

                                        <option value="low" <?php echo ($issue['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>

                                        <option value="medium" <?php echo ($issue['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>

                                        <option value="high" <?php echo ($issue['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>

                                        <option value="urgent" <?php echo ($issue['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>

                                    </select>

                                </div>

                            </div>

                            

                            <div class="mb-3">

                                <label for="justification" class="form-label">Justification *</label>

                                <textarea class="form-control" id="justification" name="justification" rows="3" required><?php echo htmlspecialchars($issue['justification'] ?? ''); ?></textarea>

                            </div>

                            

                            <div class="d-flex justify-content-between">

                                <a href="?" class="btn btn-secondary">

                                    <i class="bi bi-arrow-left me-1"></i>Back to List

                                </a>

                                <button type="submit" class="btn btn-primary">

                                    <i class="bi bi-<?php echo $action == 'create' ? 'plus-circle' : 'check'; ?> me-1"></i>

                                    <?php echo $action == 'create' ? 'Create Issue' : 'Update Issue'; ?>

                                </button>

                            </div>

                        </form>

                    </div>

                </div>



                <?php elseif ($action == 'view' && $issue): ?>

                <!-- View Issue -->

                <div class="card">

                    <div class="card-header">

                        <div class="d-flex justify-content-between align-items-center">

                            <h5 class="mb-0">

                                <i class="bi bi-eye me-2"></i>Issue Details: <?php echo htmlspecialchars($issue['issue_number']); ?>

                            </h5>

                            <div>

                                <?php if ($issue['status'] == 'draft' && $issue['requested_by'] == $_SESSION['user_id']): ?>

                                <a href="?action=edit&id=<?php echo $issue['id']; ?>" class="btn btn-warning btn-sm me-2">

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

                                <h6>Issue Information</h6>

                                <table class="table table-sm">

                                    <tr><td><strong>Issue Number:</strong></td><td><?php echo htmlspecialchars($issue['issue_number']); ?></td></tr>

                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($issue['department']); ?></td></tr>

                                    <tr><td><strong>Cost Center:</strong></td><td><?php echo htmlspecialchars($issue['cost_center']); ?></td></tr>

                                    <tr><td><strong>Location:</strong></td><td><?php echo htmlspecialchars($issue['location_name'] ?? 'N/A'); ?></td></tr>

                                    <tr><td><strong>Priority:</strong></td><td>

                                        <span class="status-badge priority-<?php echo $issue['priority']; ?>">

                                            <?php echo ucfirst($issue['priority']); ?>

                                        </span>

                                    </td></tr>

                                </table>

                            </div>

                            <div class="col-md-6">

                                <h6>Status Information</h6>

                                <table class="table table-sm">

                                    <tr><td><strong>Status:</strong></td><td>

                                        <span class="badge bg-<?php echo $issue['status'] == 'approved' ? 'success' : ($issue['status'] == 'rejected' ? 'danger' : ($issue['status'] == 'issued' ? 'info' : 'warning')); ?>">

                                            <?php echo ucfirst($issue['status']); ?>

                                        </span>

                                    </td></tr>

                                    <tr><td><strong>Requested By:</strong></td><td><?php echo htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']); ?></td></tr>

                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($issue['created_at']); ?></td></tr>

                                    <tr><td><strong>Total Value:</strong></td><td><?php echo formatCurrency($issue['total_value']); ?></td></tr>

                                </table>

                            </div>

                        </div>

                        

                        <div class="mb-3">

                            <h6>Justification</h6>

                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($issue['justification'])); ?></p>

                        </div>

                        

                        <?php if (!empty($issue_items)): ?>

                        <h6>Requested Items</h6>

                        <div class="table-responsive">

                            <table class="table table-sm">

                                <thead>

                                    <tr>

                                        <th>Item Code</th>

                                        <th>Item Name</th>

                                        <th>Qty Requested</th>

                                        <th>Qty Approved</th>

                                        <th>Qty Issued</th>

                                        <th>Unit Cost</th>

                                        <th>Total Cost</th>

                                        <th>Specifications</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($issue_items as $item): ?>

                                    <tr>

                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>

                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>

                                        <td><?php echo $item['quantity_requested']; ?></td>

                                        <td><?php echo $item['quantity_approved']; ?></td>

                                        <td><?php echo $item['quantity_issued']; ?></td>

                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>

                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>

                                        <td><?php echo htmlspecialchars($item['specifications']); ?></td>

                                    </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <?php endif; ?>

                        

                        <?php if ($issue['status'] == 'draft' && $issue['requested_by'] == $_SESSION['user_id']): ?>

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


