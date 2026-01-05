<?php
/**
 * Approval Management Page
 * Handles approval workflow and decisions
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('approve_requisition');

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$approval_id = $_GET['id'] ?? null;
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
                case 'approve':
                case 'reject':
                    $approval_id = $_POST['approval_id'] ?? null;
                    $requisition_id = $_POST['requisition_id'] ?? null;
                    $comments = sanitizeInput($_POST['comments'] ?? '');
                    
                    $status = $action == 'approve' ? 'approved' : 'rejected';
                    
                    // If approval_id is provided, use existing approval record
                    // Otherwise (for admin direct approval), get requisition_id directly
                    if ($approval_id) {
                        // Get requisition_id from approval record
                        $sql = "SELECT requisition_id FROM approvals WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$approval_id]);
                        $approval_data = $stmt->fetch();
                        $requisition_id = $approval_data['requisition_id'];
                        
                        // Update the approval status
                        $sql = "UPDATE approvals SET status = ?, comments = ?, approved_at = NOW() WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$status, $comments, $approval_id]);
                    } else if ($requisition_id && $_SESSION['role_id'] == 1) {
                        // Admin can approve directly without approval record
                        // Create an approval record for audit purposes
                        $sql = "INSERT INTO approvals (requisition_id, approver_id, approval_level, status, comments, approved_at) 
                                VALUES (?, ?, 1, ?, ?, NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$requisition_id, $_SESSION['user_id'], $status, $comments]);
                        $approval_id = $db->lastInsertId();
                    } else {
                        throw new Exception('Invalid approval request');
                    }
                    
                    // Approve or reject requisition immediately based on this approval action
                    // (No need to wait for all approvers - first approval/rejection wins)
                    if ($action == 'approve') {
                        // Check if requisition is already rejected (don't override rejection)
                        $sql = "SELECT status FROM purchase_requisitions WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$requisition_id]);
                        $current_status = $stmt->fetchColumn();
                        
                        if ($current_status != 'rejected') {
                            // Approve the requisition immediately
                            $sql = "UPDATE purchase_requisitions SET status = 'approved' WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $result = $stmt->execute([$requisition_id]);
                            if ($result) {
                                error_log("Successfully updated requisition ID $requisition_id to approved status (approved by user ID: {$_SESSION['user_id']})");
                                $all_approved = true;
                            } else {
                                error_log("Failed to update requisition status to approved: " . print_r($stmt->errorInfo(), true));
                                $all_approved = false;
                            }
                        } else {
                            error_log("Requisition ID $requisition_id is already rejected, cannot approve");
                            $all_approved = false;
                        }
                        $rejected = false;
                    } else {
                        // Reject the requisition immediately
                        $sql = "UPDATE purchase_requisitions SET status = 'rejected' WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $result = $stmt->execute([$requisition_id]);
                        if ($result) {
                            error_log("Successfully updated requisition ID $requisition_id to rejected status (rejected by user ID: {$_SESSION['user_id']})");
                            $rejected = true;
                        } else {
                            error_log("Failed to update requisition status to rejected: " . print_r($stmt->errorInfo(), true));
                            $rejected = false;
                        }
                        $all_approved = false;
                    }
                    
                    logAudit($action . '_approval', 'approvals', $approval_id, null, ['status' => $status, 'comments' => $comments]);
                    
                    // Send notification to requisition requester (only after status is updated)
                    if ($all_approved || $rejected) {
                        $sql = "SELECT pr.requested_by, pr.requisition_number, pr.status, pr.total_amount, u.first_name, u.last_name 
                                FROM purchase_requisitions pr 
                                JOIN users u ON pr.requested_by = u.id 
                                WHERE pr.id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$requisition_id]);
                        $requisition_info = $stmt->fetch();
                        
                        if ($requisition_info) {
                            $notificationSystem = new NotificationSystem();
                            $notificationSystem->createRequisitionStatusNotification(
                                $requisition_info['requested_by'],
                                $requisition_info['requisition_number'],
                                $requisition_info['status'], // Use actual status from database
                                $comments
                            );
                        }
                    }
                    
                    $message = 'Approval ' . $action . 'd successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get pending approvals for current user
// Admin can see all pending requisitions, others see only their assigned approvals
if ($_SESSION['role_id'] == 1) {
    // Admin: Show all pending requisitions (submitted or pending status)
    $sql = "SELECT pr.id as requisition_id, pr.requisition_number, pr.department, pr.total_amount, pr.priority, pr.justification, 
                   pr.created_at as requisition_date, pr.status as requisition_status,
                   u.first_name, u.last_name,
                   NULL as approval_id, NULL as approval_level, NULL as approval_status
            FROM purchase_requisitions pr 
            JOIN users u ON pr.requested_by = u.id 
            WHERE pr.status IN ('submitted', 'pending')
            ORDER BY pr.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $pending_approvals = $stmt->fetchAll();
    
    // Add approval_id if an approval record exists
    foreach ($pending_approvals as &$approval) {
        $check_sql = "SELECT id, status FROM approvals WHERE requisition_id = ? AND approver_id = ? AND status = 'pending' LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$approval['requisition_id'], $_SESSION['user_id']]);
        $existing_approval = $check_stmt->fetch();
        if ($existing_approval) {
            $approval['approval_id'] = $existing_approval['id'];
            $approval['approval_status'] = $existing_approval['status'];
        }
    }
    unset($approval);
} else {
    // Non-admin: Show only approvals assigned to them
    $sql = "SELECT a.*, pr.requisition_number, pr.department, pr.total_amount, pr.priority, pr.justification, 
                   pr.created_at as requisition_date, pr.status as requisition_status,
                   u.first_name, u.last_name
            FROM approvals a 
            JOIN purchase_requisitions pr ON a.requisition_id = pr.id 
            JOIN users u ON pr.requested_by = u.id 
            WHERE a.approver_id = ? AND a.status = 'pending' 
            ORDER BY a.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $pending_approvals = $stmt->fetchAll();
}

// Debug logging
error_log("Current user ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role_id']);
error_log("Found " . count($pending_approvals) . " pending approvals for current user");

// Also check all approvals in the system
$sql = "SELECT a.*, pr.requisition_number, u.username as approver_name 
        FROM approvals a 
        JOIN purchase_requisitions pr ON a.requisition_id = pr.id 
        JOIN users u ON a.approver_id = u.id 
        ORDER BY a.created_at DESC LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->execute();
$all_approvals = $stmt->fetchAll();
error_log("Total approvals in system: " . count($all_approvals));

// Get approval history
$sql = "SELECT a.*, pr.requisition_number, pr.department, pr.total_amount, pr.priority, 
               pr.created_at as requisition_date, u.first_name, u.last_name
        FROM approvals a 
        JOIN purchase_requisitions pr ON a.requisition_id = pr.id 
        JOIN users u ON pr.requested_by = u.id 
        WHERE a.approver_id = ? AND a.status != 'pending' 
        ORDER BY a.approved_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$approval_history = $stmt->fetchAll();

// Get requisition details for view
$requisition_details = null;
$requisition_items = [];
if ($action == 'view' && $approval_id) {
    // Check if approval_id is actually a requisition_id (for admin direct view)
    $sql = "SELECT * FROM approvals WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$approval_id]);
    $approval_check = $stmt->fetch();
    
    if ($approval_check) {
        // It's an approval_id, get requisition via approval
        $sql = "SELECT a.*, pr.*, u.first_name, u.last_name 
                FROM approvals a 
                JOIN purchase_requisitions pr ON a.requisition_id = pr.id 
                JOIN users u ON pr.requested_by = u.id 
                WHERE a.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$approval_id]);
        $requisition_details = $stmt->fetch();
    } else {
        // It might be a requisition_id (admin direct view)
        $sql = "SELECT pr.*, u.first_name, u.last_name 
                FROM purchase_requisitions pr 
                JOIN users u ON pr.requested_by = u.id 
                WHERE pr.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$approval_id]);
        $requisition_details = $stmt->fetch();
    }
    
    if ($requisition_details) {
        $req_id = $requisition_details['requisition_id'] ?? $requisition_details['id'];
        $sql = "SELECT ri.*, ii.name as item_name, ii.item_code, ii.unit_of_measure 
                FROM requisition_items ri 
                JOIN inventory_items ii ON ri.item_id = ii.id 
                WHERE ri.requisition_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$req_id]);
        $requisition_items = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals - <?php echo APP_NAME; ?></title>
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
        .approval-card {
            border-left: 4px solid #667eea;
            margin-bottom: 1rem;
        }
        .approval-card.urgent {
            border-left-color: #dc3545;
        }
        .approval-card.high {
            border-left-color: #fd7e14;
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
                        <a class="nav-link active" href="approval.php">
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
                    <h2>Approval Center</h2>
                    <div class="badge bg-warning fs-6">
                        <?php echo count($pending_approvals); ?> Pending
                    </div>
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

                <!-- Pending Approvals -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Pending Approvals (<?php echo count($pending_approvals); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_approvals)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle fs-1 text-success"></i>
                                <h5 class="text-muted mt-3">No pending approvals</h5>
                                <p class="text-muted">All caught up! Check back later for new requests.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_approvals as $approval): ?>
                                <div class="card approval-card <?php echo $approval['priority']; ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="card-title">
                                                    <strong><?php echo htmlspecialchars($approval['requisition_number']); ?></strong>
                                                    <span class="badge bg-<?php echo $approval['priority'] == 'urgent' ? 'danger' : ($approval['priority'] == 'high' ? 'warning' : 'info'); ?> ms-2">
                                                        <?php echo ucfirst($approval['priority']); ?>
                                                    </span>
                                                </h6>
                                                <p class="card-text">
                                                    <strong>Department:</strong> <?php echo htmlspecialchars($approval['department']); ?><br>
                                                    <strong>Requested by:</strong> <?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?><br>
                                                    <strong>Amount:</strong> <?php echo formatCurrency($approval['total_amount']); ?><br>
                                                    <strong>Date:</strong> <?php echo formatDate($approval['requisition_date']); ?>
                                                </p>
                                                <p class="card-text">
                                                    <strong>Justification:</strong><br>
                                                    <em><?php echo htmlspecialchars($approval['justification']); ?></em>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="btn-group-vertical" role="group">
                                                    <a href="?action=view&id=<?php echo $approval['approval_id'] ?? $approval['requisition_id']; ?>" class="btn btn-outline-info btn-sm mb-2">
                                                        <i class="bi bi-eye me-2"></i>View Details
                                                    </a>
                                                    <button class="btn btn-success btn-sm mb-2" onclick="approveRequisition(<?php echo $approval['approval_id'] ? $approval['approval_id'] : 'null'; ?>, <?php echo $approval['requisition_id']; ?>, '<?php echo htmlspecialchars($approval['requisition_number']); ?>')">
                                                        <i class="bi bi-check-circle me-2"></i>Approve
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="rejectRequisition(<?php echo $approval['approval_id'] ? $approval['approval_id'] : 'null'; ?>, <?php echo $approval['requisition_id']; ?>, '<?php echo htmlspecialchars($approval['requisition_number']); ?>')">
                                                        <i class="bi bi-x-circle me-2"></i>Reject
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Approval History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-history me-2"></i>Approval History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($approval_history)): ?>
                            <div class="text-center py-3">
                                <i class="bi bi-clock-history fs-1 text-muted"></i>
                                <h6 class="text-muted mt-2">No approval history</h6>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Requisition #</th>
                                            <th>Department</th>
                                            <th>Amount</th>
                                            <th>Priority</th>
                                            <th>Decision</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($approval_history as $approval): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($approval['requisition_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($approval['department']); ?></td>
                                                <td><?php echo formatCurrency($approval['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $approval['priority'] == 'urgent' ? 'danger' : ($approval['priority'] == 'high' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($approval['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $approval['status'] == 'approved' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($approval['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($approval['approved_at']); ?></td>
                                                <td>
                                                    <a href="?action=view&id=<?php echo $approval['id']; ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalTitle">Approve Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="approvalForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="approvalAction" value="approve">
                        <input type="hidden" name="approval_id" id="approvalId">
                        <input type="hidden" name="requisition_id" id="requisitionId">
                        
                        <p>Are you sure you want to <span id="approvalActionText">approve</span> requisition <strong id="approvalRequisitionNumber"></strong>?</p>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Add any comments about your decision..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="approvalSubmitBtn">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Requisition Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Requisition Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($requisition_details): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6><strong>Requisition Information</strong></h6>
                                <p><strong>Number:</strong> <?php echo htmlspecialchars($requisition_details['requisition_number']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($requisition_details['department']); ?></p>
                                <p><strong>Cost Center:</strong> <?php echo htmlspecialchars($requisition_details['cost_center']); ?></p>
                                <p><strong>Priority:</strong> 
                                    <span class="badge bg-<?php echo $requisition_details['priority'] == 'urgent' ? 'danger' : ($requisition_details['priority'] == 'high' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($requisition_details['priority']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6><strong>Request Details</strong></h6>
                                <p><strong>Requested by:</strong> <?php echo htmlspecialchars($requisition_details['first_name'] . ' ' . $requisition_details['last_name']); ?></p>
                                <p><strong>Date:</strong> <?php echo formatDate($requisition_details['created_at']); ?></p>
                                <p><strong>Total Amount:</strong> <?php echo formatCurrency($requisition_details['total_amount']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $requisition_details['status'] == 'approved' ? 'success' : ($requisition_details['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($requisition_details['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6><strong>Justification</strong></h6>
                            <p class="border p-3 rounded"><?php echo nl2br(htmlspecialchars($requisition_details['justification'])); ?></p>
                        </div>
                        
                        <?php if (!empty($requisition_items)): ?>
                            <h6><strong>Items Requested</strong></h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Code</th>
                                            <th>Item Name</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
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
                                                <td><?php echo number_format($item['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                                <td><?php echo formatCurrency($item['unit_cost']); ?></td>
                                                <td><?php echo formatCurrency($item['total_cost']); ?></td>
                                                <td><?php echo htmlspecialchars($item['specifications']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

        function approveRequisition(approvalId, requisitionId, number) {
            document.getElementById('approvalModalTitle').textContent = 'Approve Requisition';
            document.getElementById('approvalAction').value = 'approve';
            document.getElementById('approvalActionText').textContent = 'approve';
            document.getElementById('approvalRequisitionNumber').textContent = number;
            document.getElementById('approvalId').value = approvalId || '';
            document.getElementById('requisitionId').value = requisitionId;
            document.getElementById('approvalSubmitBtn').textContent = 'Approve';
            document.getElementById('approvalSubmitBtn').className = 'btn btn-success';
            
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
        }
        
        function rejectRequisition(approvalId, requisitionId, number) {
            document.getElementById('approvalModalTitle').textContent = 'Reject Requisition';
            document.getElementById('approvalAction').value = 'reject';
            document.getElementById('approvalActionText').textContent = 'reject';
            document.getElementById('approvalRequisitionNumber').textContent = number;
            document.getElementById('approvalId').value = approvalId || '';
            document.getElementById('requisitionId').value = requisitionId;
            document.getElementById('approvalSubmitBtn').textContent = 'Reject';
            document.getElementById('approvalSubmitBtn').className = 'btn btn-danger';
            
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
        }
        
        // Show details modal if viewing
        <?php if ($action == 'view' && $requisition_details): ?>
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        <?php endif; ?>
    </script>
</body>
</html>

