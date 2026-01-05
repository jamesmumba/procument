<?php
/**
 * Purchase Requisition Management Page
 * Handles requisition CRUD operations and workflow
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('create_requisition');

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$requisition_id = $_GET['id'] ?? null;
$message = '';
$error = '';

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
                case 'create':
                case 'update':
                    $requisitionData = [
                        'department' => sanitizeInput($_POST['department']),
                        'cost_center' => sanitizeInput($_POST['cost_center']),
                        'priority' => sanitizeInput($_POST['priority']),
                        'justification' => sanitizeInput($_POST['justification']),
                        'requested_by' => $_SESSION['user_id']
                    ];
                    
                    if ($action == 'create') {
                        $requisitionData['requisition_number'] = generateUniqueNumber('REQ', 'purchase_requisitions', 'requisition_number');
                        
                        $sql = "INSERT INTO purchase_requisitions (requisition_number, requested_by, department, cost_center, priority, justification) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $requisitionData['requisition_number'],
                            $requisitionData['requested_by'],
                            $requisitionData['department'],
                            $requisitionData['cost_center'],
                            $requisitionData['priority'],
                            $requisitionData['justification']
                        ]);
                        
                        $requisition_id = $db->lastInsertId();
                        
                        // Add items
                        if (isset($_POST['items']) && is_array($_POST['items'])) {
                            $total_amount = 0;
                            foreach ($_POST['items'] as $item) {
                                if (!empty($item['item_id']) && !empty($item['quantity']) && !empty($item['unit_cost'])) {
                                    $item_total = $item['quantity'] * $item['unit_cost'];
                                    $total_amount += $item_total;
                                    
                                    $sql = "INSERT INTO requisition_items (requisition_id, item_id, quantity, unit_cost, total_cost, specifications) 
                                            VALUES (?, ?, ?, ?, ?, ?)";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute([
                                        $requisition_id,
                                        $item['item_id'],
                                        $item['quantity'],
                                        $item['unit_cost'],
                                        $item_total,
                                        $item['specifications'] ?? ''
                                    ]);
                                }
                            }
                            
                            // Update total amount
                            $sql = "UPDATE purchase_requisitions SET total_amount = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$total_amount, $requisition_id]);
                        }
                        
                        logAudit('create_requisition', 'purchase_requisitions', $requisition_id, null, $requisitionData);
                        $message = 'Requisition created successfully!';
                        $action = 'list';
                    } else {
                        $sql = "UPDATE purchase_requisitions SET department = ?, cost_center = ?, priority = ?, justification = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $requisitionData['department'],
                            $requisitionData['cost_center'],
                            $requisitionData['priority'],
                            $requisitionData['justification'],
                            $requisition_id
                        ]);
                        
                        logAudit('update_requisition', 'purchase_requisitions', $requisition_id, null, $requisitionData);
                        $message = 'Requisition updated successfully!';
                        $action = 'list';
                    }
                    break;
                    
                case 'submit':
                    $submit_id = $_POST['id'];
                    $sql = "UPDATE purchase_requisitions SET status = 'submitted' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$submit_id]);
                    
                    // Create approval records based on rules
                    createApprovalRecords($submit_id);
                    
                    logAudit('submit_requisition', 'purchase_requisitions', $submit_id);
                    $message = 'Requisition submitted for approval!';
                    $action = 'list';
                    break;
                    
                case 'delete':
                    $delete_id = $_POST['id'];
                    $sql = "UPDATE purchase_requisitions SET status = 'cancelled' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$delete_id]);
                    
                    logAudit('cancel_requisition', 'purchase_requisitions', $delete_id);
                    $message = 'Requisition cancelled successfully!';
                    $action = 'list';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Create approval records based on rules
function createApprovalRecords($requisition_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Get requisition details
        $sql = "SELECT * FROM purchase_requisitions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$requisition_id]);
        $requisition = $stmt->fetch();
        
        // Check if requisition exists
        if (!$requisition) {
            error_log("Requisition not found: ID $requisition_id");
            return;
        }
        
        error_log("Creating approval records for requisition ID: $requisition_id, Amount: " . $requisition['total_amount']);
        
        // Get applicable approval rules (find rules that match the amount range)
        $sql = "SELECT * FROM approval_rules WHERE is_active = 1 AND min_amount <= ? AND (max_amount IS NULL OR max_amount >= ?) ORDER BY min_amount DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$requisition['total_amount'], $requisition['total_amount']]);
        $rules = $stmt->fetchAll();
        
        error_log("Found " . count($rules) . " applicable approval rules");
        if (empty($rules)) {
            error_log("No matching approval rule for amount: " . $requisition['total_amount']);
        }
        
        // If any rule has auto_approve = true, optionally auto-approve and stop
        // Guarded by config flag to avoid unintended silent approvals
        foreach ($rules as $rule) {
            $conditions = [];
            if (!empty($rule['conditions'])) {
                $decoded = json_decode($rule['conditions'], true);
                if (is_array($decoded)) { $conditions = $decoded; }
            }

            if (!empty($conditions['auto_approve']) && defined('ENABLE_AUTO_APPROVE') && ENABLE_AUTO_APPROVE) {
                error_log("Auto-approving requisition ID: $requisition_id due to rule: " . $rule['rule_name']);
                $sql = "UPDATE purchase_requisitions SET status = 'approved' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$requisition_id]);
                return;
            }
        }

        // Create approval records for matching rules and approvers that actually have approve permission
        foreach ($rules as $rule) {
            error_log("Processing rule: " . $rule['rule_name'] . " for role: " . $rule['role_to_notify']);
            
            // Get users with the required role (keep simple to support older MySQL/MariaDB)
            $sql = "SELECT id FROM users WHERE role_id = ? AND is_active = 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$rule['role_to_notify']]);
            $approvers = $stmt->fetchAll();
            
            error_log("Found " . count($approvers) . " approvers for role " . $rule['role_to_notify']);
            
            if (empty($approvers)) {
                error_log("WARNING: No active users found for role " . $rule['role_to_notify']);
                continue;
            }
            
            foreach ($approvers as $approver) {
                try {
                    $sql = "INSERT INTO approvals (requisition_id, approver_id, approval_level, status) VALUES (?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->execute([$requisition_id, $approver['id'], count($rules)]);
                    
                    if ($result) {
                        error_log("✅ Created approval record for user ID: " . $approver['id'] . " (requisition: " . $requisition_id . ")");
                        
                        // Send notification to approver
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->createApprovalNotification(
                            $approver['id'],
                            $requisition['requisition_number'],
                            $requisition['total_amount'],
                            $requisition['department']
                        );
                    } else {
                        error_log("❌ Failed to create approval record for user ID: " . $approver['id']);
                    }
                } catch (Exception $e) {
                    error_log("❌ Error creating approval for user " . $approver['id'] . ": " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Create approval records error: " . $e->getMessage());
    }
}

// Get requisition data for edit/view
$requisition = null;
$requisition_items = [];
if ($requisition_id && in_array($action, ['edit', 'view'])) {
    $sql = "SELECT pr.*, u.first_name, u.last_name 
            FROM purchase_requisitions pr 
            JOIN users u ON pr.requested_by = u.id 
            WHERE pr.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$requisition_id]);
    $requisition = $stmt->fetch();
    
    if ($requisition) {
        $sql = "SELECT ri.*, ii.name as item_name, ii.item_code 
                FROM requisition_items ri 
                JOIN inventory_items ii ON ri.item_id = ii.id 
                WHERE ri.requisition_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$requisition_id]);
        $requisition_items = $stmt->fetchAll();
    }
}

// Get requisitions list
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (pr.requisition_number LIKE ? OR pr.department LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

if ($status_filter) {
    $whereClause .= " AND pr.status = ?";
    $params[] = $status_filter;
}

$sql = "SELECT pr.*, u.first_name, u.last_name 
        FROM purchase_requisitions pr 
        JOIN users u ON pr.requested_by = u.id 
        $whereClause 
        ORDER BY pr.created_at DESC 
        LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$requisitions = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM purchase_requisitions pr JOIN users u ON pr.requested_by = u.id $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRequisitions = $countStmt->fetchColumn();
$totalPages = ceil($totalRequisitions / ITEMS_PER_PAGE);

// Get inventory items for dropdown
$sql = "SELECT * FROM inventory_items WHERE is_active = 1 ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$inventory_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Requisitions - <?php echo APP_NAME; ?></title>
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
        .item-row {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
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
                        <a class="nav-link active" href="requisition.php">
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
                    <h2>Purchase Requisitions</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requisitionModal">
                        <i class="bi bi-plus-circle me-2"></i>New Requisition
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
                                    <input type="text" class="form-control" name="search" placeholder="Search requisitions..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                                <a href="requisition.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Requisitions Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Requisitions (<?php echo $totalRequisitions; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requisitions)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No requisitions found</h5>
                                <p class="text-muted">Start by creating your first requisition</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Requisition #</th>
                                            <th>Requested By</th>
                                            <th>Department</th>
                                            <th>Priority</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requisitions as $req): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($req['requisition_number']); ?></strong>
                                                    <?php if ($req['cost_center']): ?>
                                                        <br><small class="text-muted">CC: <?php echo htmlspecialchars($req['cost_center']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($req['department']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $req['priority'] == 'urgent' ? 'danger' : ($req['priority'] == 'high' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($req['priority']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($req['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $req['status'] == 'approved' ? 'success' : ($req['status'] == 'rejected' ? 'danger' : ($req['status'] == 'submitted' ? 'warning' : 'secondary')); ?>">
                                                        <?php echo ucfirst($req['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($req['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="?action=view&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($req['status'] == 'draft'): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editRequisition(<?php echo htmlspecialchars(json_encode($req)); ?>)">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success" onclick="submitRequisition(<?php echo $req['id']; ?>)">
                                                                <i class="bi bi-send"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (in_array($req['status'], ['draft', 'submitted'])): ?>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="cancelRequisition(<?php echo $req['id']; ?>, '<?php echo htmlspecialchars($req['requisition_number']); ?>')">
                                                                <i class="bi bi-x-circle"></i>
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
                                <nav aria-label="Requisition pagination">
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

                <?php if ($action == 'view' && $requisition): ?>
                <!-- View Requisition -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-eye me-2"></i>Requisition Details: <?php echo htmlspecialchars($requisition['requisition_number']); ?>
                            </h5>
                            <a href="?" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Requisition Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Requisition Number:</strong></td><td><?php echo htmlspecialchars($requisition['requisition_number']); ?></td></tr>
                                    <tr><td><strong>Department:</strong></td><td><?php echo htmlspecialchars($requisition['department']); ?></td></tr>
                                    <tr><td><strong>Cost Center:</strong></td><td><?php echo htmlspecialchars($requisition['cost_center']); ?></td></tr>
                                    <tr><td><strong>Priority:</strong></td><td>
                                        <span class="badge bg-<?php echo $requisition['priority'] == 'urgent' ? 'danger' : ($requisition['priority'] == 'high' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($requisition['priority']); ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Status:</strong></td><td>
                                        <span class="badge bg-<?php echo $requisition['status'] == 'approved' ? 'success' : ($requisition['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $requisition['status'])); ?>
                                        </span>
                                    </td></tr>
                                    <tr><td><strong>Total Amount:</strong></td><td><?php echo formatCurrency($requisition['total_amount']); ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Request Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Requested By:</strong></td><td><?php echo htmlspecialchars($requisition['first_name'] . ' ' . $requisition['last_name']); ?></td></tr>
                                    <tr><td><strong>Created:</strong></td><td><?php echo formatDate($requisition['created_at']); ?></td></tr>
                                    <tr><td><strong>Last Updated:</strong></td><td><?php echo formatDate($requisition['updated_at']); ?></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Justification</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($requisition['justification'])); ?></p>
                        </div>
                        
                        <?php if (!empty($requisition_items)): ?>
                        <h6>Requested Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                        <th>Specifications</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requisition_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo formatCurrency($item['unit_cost']); ?></td>
                                        <td><?php echo formatCurrency($item['total_cost']); ?></td>
                                        <td><?php echo htmlspecialchars($item['specifications']); ?></td>
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

    <!-- Requisition Modal -->
    <div class="modal fade" id="requisitionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requisitionModalTitle">New Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="requisitionForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="requisitionAction" value="create">
                        <input type="hidden" name="id" id="requisitionId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department *</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cost_center" class="form-label">Cost Center</label>
                                <input type="text" class="form-control" id="cost_center" name="cost_center">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Priority *</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="justification" class="form-label">Justification *</label>
                            <textarea class="form-control" id="justification" name="justification" rows="3" required placeholder="Explain why this purchase is necessary..."></textarea>
                        </div>
                        
                        <!-- Items Section -->
                        <div class="mb-3">
                            <label class="form-label">Items *</label>
                            <div id="itemsContainer">
                                <div class="item-row">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Item</label>
                                            <select class="form-select item-select" name="items[0][item_id]" required>
                                                <option value="">Select Item</option>
                                                <?php foreach ($inventory_items as $item): ?>
                                                    <option value="<?php echo $item['id']; ?>" data-unit-cost="<?php echo $item['unit_cost']; ?>">
                                                        <?php echo htmlspecialchars($item['name'] . ' (' . $item['item_code'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" class="form-control item-quantity" name="items[0][quantity]" min="1" required>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Unit Cost</label>
                                            <input type="number" class="form-control item-unit-cost" name="items[0][unit_cost]" step="0.01" min="0" required>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Total</label>
                                            <input type="text" class="form-control item-total" readonly>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-danger d-block remove-item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label class="form-label">Specifications</label>
                                            <textarea class="form-control" name="items[0][specifications]" rows="2" placeholder="Additional specifications or requirements..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addItem">
                                <i class="bi bi-plus-circle me-2"></i>Add Item
                            </button>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Total Amount: <span id="totalAmount">K 0.00</span></h5>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Requisition</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Submit Confirmation Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit this requisition for approval?</p>
                    <p class="text-muted">Once submitted, you cannot edit the requisition.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="id" id="submitRequisitionId">
                        <button type="submit" class="btn btn-success">Submit for Approval</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel requisition <strong id="cancelRequisitionNumber"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="cancelRequisitionId">
                        <button type="submit" class="btn btn-danger">Cancel Requisition</button>
                    </form>
                </div>
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

        let itemIndex = 1;
        
        // Add item functionality
        document.getElementById('addItem').addEventListener('click', function() {
            const container = document.getElementById('itemsContainer');
            const newItem = document.querySelector('.item-row').cloneNode(true);
            
            // Update names and IDs
            newItem.querySelectorAll('select, input, textarea').forEach(el => {
                const name = el.getAttribute('name');
                if (name) {
                    el.setAttribute('name', name.replace('[0]', '[' + itemIndex + ']'));
                }
                el.value = '';
            });
            
            container.appendChild(newItem);
            itemIndex++;
        });
        
        // Remove item functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item') || e.target.parentElement.classList.contains('remove-item')) {
                const itemRow = e.target.closest('.item-row');
                if (document.querySelectorAll('.item-row').length > 1) {
                    itemRow.remove();
                    calculateTotal();
                }
            }
        });
        
        // Item selection change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('item-select')) {
                const unitCost = e.target.selectedOptions[0].getAttribute('data-unit-cost');
                const unitCostInput = e.target.closest('.item-row').querySelector('.item-unit-cost');
                unitCostInput.value = unitCost || 0;
                calculateItemTotal(e.target.closest('.item-row'));
            }
        });
        
        // Quantity or unit cost change
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('item-quantity') || e.target.classList.contains('item-unit-cost')) {
                calculateItemTotal(e.target.closest('.item-row'));
            }
        });
        
        function calculateItemTotal(itemRow) {
            const quantity = parseFloat(itemRow.querySelector('.item-quantity').value) || 0;
            const unitCost = parseFloat(itemRow.querySelector('.item-unit-cost').value) || 0;
            const total = quantity * unitCost;
            itemRow.querySelector('.item-total').value = total.toFixed(2);
            calculateTotal();
        }
        
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.item-total').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalAmount').textContent = 'K ' + total.toFixed(2);
        }
        
        function editRequisition(requisition) {
            document.getElementById('requisitionModalTitle').textContent = 'Edit Requisition';
            document.getElementById('requisitionAction').value = 'update';
            document.getElementById('requisitionId').value = requisition.id;
            document.getElementById('department').value = requisition.department;
            document.getElementById('cost_center').value = requisition.cost_center || '';
            document.getElementById('priority').value = requisition.priority;
            document.getElementById('justification').value = requisition.justification;
            
            new bootstrap.Modal(document.getElementById('requisitionModal')).show();
        }
        
        function submitRequisition(id) {
            document.getElementById('submitRequisitionId').value = id;
            new bootstrap.Modal(document.getElementById('submitModal')).show();
        }
        
        function cancelRequisition(id, number) {
            document.getElementById('cancelRequisitionId').value = id;
            document.getElementById('cancelRequisitionNumber').textContent = number;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
        
        // Reset form when modal is hidden
        document.getElementById('requisitionModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('requisitionForm').reset();
            document.getElementById('requisitionModalTitle').textContent = 'New Requisition';
            document.getElementById('requisitionAction').value = 'create';
            document.getElementById('requisitionId').value = '';
            
            // Reset items container
            const container = document.getElementById('itemsContainer');
            const firstItem = container.querySelector('.item-row');
            container.innerHTML = '';
            container.appendChild(firstItem);
            itemIndex = 1;
            calculateTotal();
        });
    </script>
</body>
</html>
