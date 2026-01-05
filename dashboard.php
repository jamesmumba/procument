<?php
/**
 * Main Dashboard
 * Displays overview of procurement activities
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication
requireLogin();

$user = getCurrentUser();
$db = new Database();
$conn = $db->getConnection();

// Get dashboard statistics
$stats = [];

try {
    // Total vendors
    $stmt = $conn->prepare("SELECT COUNT(*) FROM vendors WHERE is_active = 1");
    $stmt->execute();
    $stats['total_vendors'] = $stmt->fetchColumn();

    // Total requisitions
    $stmt = $conn->prepare("SELECT COUNT(*) FROM purchase_requisitions");
    $stmt->execute();
    $stats['total_requisitions'] = $stmt->fetchColumn();

    // Pending approvals
    $stmt = $conn->prepare("SELECT COUNT(*) FROM approvals WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_approvals'] = $stmt->fetchColumn();

    // Total purchase orders
    $stmt = $conn->prepare("SELECT COUNT(*) FROM purchase_orders");
    $stmt->execute();
    $stats['total_pos'] = $stmt->fetchColumn();

    // Total spend this month
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute();
    $stats['monthly_spend'] = $stmt->fetchColumn();

    // Low stock items
    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventory_items WHERE current_stock <= reorder_point AND is_active = 1");
    $stmt->execute();
    $stats['low_stock_items'] = $stmt->fetchColumn();
    
    // Get unread notification count for current user
    $notificationSystem = new NotificationSystem();
    $stats['unread_notifications'] = $notificationSystem->getUnreadCount($user['id']);

    // Recent requisitions
    $stmt = $conn->prepare("
        SELECT pr.*, u.first_name, u.last_name 
        FROM purchase_requisitions pr 
        JOIN users u ON pr.requested_by = u.id 
        ORDER BY pr.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_requisitions = $stmt->fetchAll();

    // Recent approvals
    $stmt = $conn->prepare("
        SELECT a.*, pr.requisition_number, u.first_name, u.last_name 
        FROM approvals a 
        JOIN purchase_requisitions pr ON a.requisition_id = pr.id 
        JOIN users u ON a.approver_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_approvals = $stmt->fetchAll();

    // Top vendors by spend
    $stmt = $conn->prepare("
        SELECT v.name, COALESCE(SUM(po.total_amount), 0) as total_spend 
        FROM vendors v 
        LEFT JOIN purchase_orders po ON v.id = po.vendor_id 
        WHERE v.is_active = 1 
        GROUP BY v.id, v.name 
        ORDER BY total_spend DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $top_vendors = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = [
        'total_vendors' => 0,
        'total_requisitions' => 0,
        'pending_approvals' => 0,
        'total_pos' => 0,
        'monthly_spend' => 0,
        'low_stock_items' => 0
    ];
    $recent_requisitions = [];
    $recent_approvals = [];
    $top_vendors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }
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
                        <a class="nav-link active" href="dashboard.php">
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
                            <?php if ($stats['unread_notifications'] > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $stats['unread_notifications']; ?></span>
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
                    <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
                    <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($user['role_name']); ?></span>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Total Vendors</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_vendors']); ?></h3>
                                </div>
                                <i class="bi bi-building fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Requisitions</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_requisitions']); ?></h3>
                                </div>
                                <i class="bi bi-file-earmark-text fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Pending Approvals</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['pending_approvals']); ?></h3>
                                </div>
                                <i class="bi bi-clock fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Monthly Spend</h6>
                                    <h3 class="fw-bold"><?php echo formatCurrency($stats['monthly_spend']); ?></h3>
                                </div>
                                <i class="bi bi-cash-coin fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stat-card danger">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Low Stock Items</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['low_stock_items']); ?></h3>
                                </div>
                                <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Total POs</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_pos']); ?></h3>
                                </div>
                                <i class="bi bi-receipt fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Requisitions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_requisitions)): ?>
                                    <p class="text-muted">No recent requisitions</p>
                                <?php else: ?>
                                    <?php foreach ($recent_requisitions as $req): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($req['requisition_number']); ?></strong>
                                                <br>
                                                <small class="text-muted">by <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $req['status'] == 'approved' ? 'success' : ($req['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($req['status']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo formatDate($req['created_at']); ?></small>
                                            </div>
                                        </div>
                                        <hr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Top Vendors by Spend</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_vendors)): ?>
                                    <p class="text-muted">No vendor data available</p>
                                <?php else: ?>
                                    <?php foreach ($top_vendors as $vendor): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong><?php echo htmlspecialchars($vendor['name']); ?></strong>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold text-primary"><?php echo formatCurrency($vendor['total_spend']); ?></span>
                                            </div>
                                        </div>
                                        <hr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
</body>
</html>
