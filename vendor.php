<?php
/**
 * Vendor Management Page
 * Handles vendor CRUD operations
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('manage_vendors');

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$vendor_id = $_GET['id'] ?? null;
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
                    $vendorData = [
                        'name' => sanitizeInput($_POST['name']),
                        'contact_person' => sanitizeInput($_POST['contact_person']),
                        'email' => sanitizeInput($_POST['email']),
                        'phone' => sanitizeInput($_POST['phone']),
                        'address' => sanitizeInput($_POST['address']),
                        'tax_id' => sanitizeInput($_POST['tax_id']),
                        'payment_terms' => sanitizeInput($_POST['payment_terms']),
                        'is_active' => isset($_POST['is_active']) ? 1 : 0,
                        // Ratings (validated and bounded)
                        'vendor_score' => isset($_POST['vendor_score']) && is_numeric($_POST['vendor_score'])
                            ? max(0, min(10, (float)$_POST['vendor_score'])) : 0,
                        'delivery_time_avg' => isset($_POST['delivery_time_avg']) && is_numeric($_POST['delivery_time_avg'])
                            ? max(0, (int)$_POST['delivery_time_avg']) : 0,
                        'defect_rate' => isset($_POST['defect_rate']) && is_numeric($_POST['defect_rate'])
                            ? max(0, min(100, (float)$_POST['defect_rate'])) : 0,
                        'on_time_percentage' => isset($_POST['on_time_percentage']) && is_numeric($_POST['on_time_percentage'])
                            ? max(0, min(100, (float)$_POST['on_time_percentage'])) : 0,
                    ];
                    
                    if ($action == 'create') {
                        $sql = "INSERT INTO vendors (name, contact_person, email, phone, address, tax_id, payment_terms, is_active, vendor_score, delivery_time_avg, defect_rate, on_time_percentage) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $vendorData['name'],
                            $vendorData['contact_person'],
                            $vendorData['email'],
                            $vendorData['phone'],
                            $vendorData['address'],
                            $vendorData['tax_id'],
                            $vendorData['payment_terms'],
                            $vendorData['is_active'],
                            $vendorData['vendor_score'],
                            $vendorData['delivery_time_avg'],
                            $vendorData['defect_rate'],
                            $vendorData['on_time_percentage'],
                        ]);
                        $vendor_id = $db->lastInsertId();
                        
                        logAudit('create_vendor', 'vendors', $vendor_id, null, $vendorData);
                        $message = 'Vendor created successfully!';
                    } else {
                        $update_id = $_POST['id'];
                        $sql = "UPDATE vendors SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, tax_id = ?, payment_terms = ?, is_active = ?, vendor_score = ?, delivery_time_avg = ?, defect_rate = ?, on_time_percentage = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $vendorData['id'] = $update_id;
                        $stmt->execute([
                            $vendorData['name'],
                            $vendorData['contact_person'],
                            $vendorData['email'],
                            $vendorData['phone'],
                            $vendorData['address'],
                            $vendorData['tax_id'],
                            $vendorData['payment_terms'],
                            $vendorData['is_active'],
                            $vendorData['vendor_score'],
                            $vendorData['delivery_time_avg'],
                            $vendorData['defect_rate'],
                            $vendorData['on_time_percentage'],
                            $update_id,
                        ]);
                        
                        logAudit('update_vendor', 'vendors', $update_id, null, $vendorData);
                        $message = 'Vendor updated successfully!';
                    }
                    break;
                    
                case 'delete':
                    $delete_id = $_POST['id'];
                    $sql = "UPDATE vendors SET is_active = 0 WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$delete_id]);
                    
                    logAudit('delete_vendor', 'vendors', $delete_id);
                    $message = 'Vendor deleted successfully!';
                    break;
                    
                // Contract management
                case 'create_contract':
                case 'update_contract':
                    $contractData = [
                        'vendor_id' => (int)$_POST['vendor_id'],
                        'contract_number' => sanitizeInput($_POST['contract_number']),
                        'contract_name' => sanitizeInput($_POST['contract_name']),
                        'contract_type' => sanitizeInput($_POST['contract_type']),
                        'start_date' => sanitizeInput($_POST['start_date']),
                        'end_date' => sanitizeInput($_POST['end_date']),
                        'total_value' => isset($_POST['total_value']) && is_numeric($_POST['total_value'])
                            ? max(0, (float)$_POST['total_value']) : 0,
                        'currency' => sanitizeInput($_POST['currency'] ?? 'ZMW'),
                        'status' => sanitizeInput($_POST['status']),
                        'terms_and_conditions' => sanitizeInput($_POST['terms_and_conditions'] ?? ''),
                        'renewal_notification_days' => isset($_POST['renewal_notification_days']) && is_numeric($_POST['renewal_notification_days'])
                            ? max(0, (int)$_POST['renewal_notification_days']) : 60,
                    ];
                    
                    if ($action == 'create_contract') {
                        $sql = "INSERT INTO vendor_contracts (vendor_id, contract_number, contract_name, contract_type, start_date, end_date, total_value, currency, status, terms_and_conditions, renewal_notification_days, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $contractData['vendor_id'],
                            $contractData['contract_number'],
                            $contractData['contract_name'],
                            $contractData['contract_type'],
                            $contractData['start_date'],
                            $contractData['end_date'],
                            $contractData['total_value'],
                            $contractData['currency'],
                            $contractData['status'],
                            $contractData['terms_and_conditions'],
                            $contractData['renewal_notification_days'],
                            $_SESSION['user_id']
                        ]);
                        $contract_id = $db->lastInsertId();
                        
                        logAudit('create_contract', 'vendor_contracts', $contract_id, null, $contractData);
                        $message = 'Contract created successfully!';
                        
                        // Trigger contract renewal notification check
                        $notificationSystem->checkContractRenewalNotifications();
                    } else {
                        $update_id = $_POST['contract_id'];
                        $sql = "UPDATE vendor_contracts SET contract_number = ?, contract_name = ?, contract_type = ?, start_date = ?, end_date = ?, total_value = ?, currency = ?, status = ?, terms_and_conditions = ?, renewal_notification_days = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $contractData['contract_number'],
                            $contractData['contract_name'],
                            $contractData['contract_type'],
                            $contractData['start_date'],
                            $contractData['end_date'],
                            $contractData['total_value'],
                            $contractData['currency'],
                            $contractData['status'],
                            $contractData['terms_and_conditions'],
                            $contractData['renewal_notification_days'],
                            $update_id,
                        ]);
                        
                        logAudit('update_contract', 'vendor_contracts', $update_id, null, $contractData);
                        $message = 'Contract updated successfully!';
                        
                        // Trigger contract renewal notification check
                        $notificationSystem->checkContractRenewalNotifications();
                    }
                    $action = 'view';
                    $vendor_id = $contractData['vendor_id'];
                    break;
                    
                case 'delete_contract':
                    $delete_id = $_POST['contract_id'];
                    $vendor_id = $_POST['vendor_id'];
                    $sql = "DELETE FROM vendor_contracts WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$delete_id]);
                    
                    logAudit('delete_contract', 'vendor_contracts', $delete_id);
                    $message = 'Contract deleted successfully!';
                    $action = 'view';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Check for expiring contracts and display alert
$expiring_contracts = [];
try {
    $check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'vendor_contracts'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    $has_contracts = $check_stmt->fetchColumn() > 0;
    
    if ($has_contracts) {
        $sql = "SELECT vc.*, v.name as vendor_name, DATEDIFF(vc.end_date, CURDATE()) as days_to_expiry
                FROM vendor_contracts vc
                JOIN vendors v ON vc.vendor_id = v.id
                WHERE vc.status = 'active'
                AND vc.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                ORDER BY vc.end_date ASC
                LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $expiring_contracts = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Silently fail if table doesn't exist
    $expiring_contracts = [];
}

// Get vendor data for edit or view
$vendor = null;
if ($vendor_id && ($action == 'edit' || $action == 'view')) {
    $sql = "SELECT * FROM vendors WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get contracts for this vendor if viewing
    $vendor_contracts = [];
    if ($action == 'view' && $vendor) {
        try {
            $check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'vendor_contracts'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute();
            $has_contracts = $check_stmt->fetchColumn() > 0;
            
            if ($has_contracts) {
                $sql = "SELECT vc.*, DATEDIFF(vc.end_date, CURDATE()) as days_to_expiry
                        FROM vendor_contracts vc
                        WHERE vc.vendor_id = ?
                        ORDER BY vc.end_date ASC";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$vendor_id]);
                $vendor_contracts = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            $vendor_contracts = [];
        }
    }
}

// Get vendors list
$search = $_GET['search'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

$sql = "SELECT * FROM vendors $whereClause ORDER BY created_at DESC LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM vendors $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalVendors = $countStmt->fetchColumn();
$totalPages = ceil($totalVendors / ITEMS_PER_PAGE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Management - <?php echo APP_NAME; ?></title>
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
                        <a class="nav-link active" href="vendor.php">
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
                    <h2>Vendor Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Vendor
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

                <!-- Expiring Contracts Alert -->
                <?php if (!empty($expiring_contracts) && $action == 'list'): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Contracts Expiring Soon</h6>
                        <p class="mb-2">The following contracts are expiring within 90 days:</p>
                        <ul class="mb-0">
                            <?php foreach ($expiring_contracts as $contract): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($contract['contract_name']); ?></strong> 
                                    (<?php echo htmlspecialchars($contract['vendor_name']); ?>) - 
                                    Expires in <?php echo $contract['days_to_expiry']; ?> days 
                                    (<?php echo date('Y-m-d', strtotime($contract['end_date'])); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Search vendors..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                                <a href="vendor.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Vendors Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Vendors (<?php echo $totalVendors; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($vendors)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-building fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No vendors found</h5>
                                <p class="text-muted">Start by adding your first vendor</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Contact Person</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Payment Terms</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vendors as $v): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($v['name']); ?></strong>
                                                    <?php if ($v['tax_id']): ?>
                                                        <br><small class="text-muted">Tax ID: <?php echo htmlspecialchars($v['tax_id']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($v['contact_person']); ?></td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($v['email']); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($v['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="tel:<?php echo htmlspecialchars($v['phone']); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($v['phone']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($v['payment_terms']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $v['vendor_score'] >= 8 ? 'success' : ($v['vendor_score'] >= 6 ? 'warning' : 'danger'); ?>">
                                                        <?php echo number_format($v['vendor_score'], 1); ?>/10
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $v['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $v['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="vendor.php?action=view&id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editVendor(<?php echo htmlspecialchars(json_encode($v)); ?>)" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteVendor(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars($v['name']); ?>')" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Vendor pagination">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Vendor Details View (with Contracts) -->
                <?php if ($action == 'view' && $vendor): ?>
                    <div class="card mt-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-building me-2"></i>Vendor Details: <?php echo htmlspecialchars($vendor['name']); ?></h5>
                            <a href="vendor.php" class="btn btn-sm btn-outline-light">
                                <i class="bi bi-arrow-left me-1"></i>Back to List
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Contact Information</h6>
                                    <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($vendor['contact_person']); ?></p>
                                    <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>"><?php echo htmlspecialchars($vendor['email']); ?></a></p>
                                    <p><strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($vendor['phone']); ?>"><?php echo htmlspecialchars($vendor['phone']); ?></a></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($vendor['address']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Business Information</h6>
                                    <p><strong>Tax ID:</strong> <?php echo htmlspecialchars($vendor['tax_id']); ?></p>
                                    <p><strong>Payment Terms:</strong> <?php echo htmlspecialchars($vendor['payment_terms']); ?></p>
                                    <p><strong>Vendor Score:</strong> 
                                        <span class="badge bg-<?php echo $vendor['vendor_score'] >= 8 ? 'success' : ($vendor['vendor_score'] >= 6 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($vendor['vendor_score'], 1); ?>/10
                                        </span>
                                    </p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php echo $vendor['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $vendor['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <!-- Contracts Section -->
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6><i class="bi bi-file-earmark-text me-2"></i>Contracts</h6>
                                    <button class="btn btn-sm btn-primary" onclick="addContract(<?php echo $vendor['id']; ?>)">
                                        <i class="bi bi-plus-circle me-1"></i>Add Contract
                                    </button>
                                </div>
                                
                                <?php if (empty($vendor_contracts)): ?>
                                    <p class="text-muted">No contracts found for this vendor.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Contract Number</th>
                                                    <th>Contract Name</th>
                                                    <th>Type</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Value</th>
                                                    <th>Status</th>
                                                    <th>Days to Expiry</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($vendor_contracts as $contract): ?>
                                                    <tr class="<?php echo $contract['days_to_expiry'] <= 30 && $contract['status'] == 'active' ? 'table-warning' : ''; ?>">
                                                        <td><?php echo htmlspecialchars($contract['contract_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($contract['contract_name']); ?></td>
                                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($contract['contract_type']); ?></span></td>
                                                        <td><?php echo date('Y-m-d', strtotime($contract['start_date'])); ?></td>
                                                        <td><?php echo date('Y-m-d', strtotime($contract['end_date'])); ?></td>
                                                        <td><?php echo formatCurrency($contract['total_value']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $contract['status'] == 'active' ? 'success' : 
                                                                    ($contract['status'] == 'expiring' ? 'warning' : 
                                                                    ($contract['status'] == 'expired' ? 'danger' : 'secondary')); 
                                                            ?>">
                                                                <?php echo htmlspecialchars($contract['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($contract['status'] == 'active'): ?>
                                                                <span class="badge bg-<?php echo $contract['days_to_expiry'] <= 30 ? 'danger' : ($contract['days_to_expiry'] <= 60 ? 'warning' : 'info'); ?>">
                                                                    <?php echo $contract['days_to_expiry']; ?> days
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editContract(<?php echo htmlspecialchars(json_encode($contract)); ?>)" title="Edit">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteContract(<?php echo $contract['id']; ?>, <?php echo $vendor['id']; ?>, '<?php echo htmlspecialchars($contract['contract_name']); ?>')" title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vendor Modal -->
    <div class="modal fade" id="vendorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vendorModalTitle">Add Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="vendorForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="vendorAction" value="create">
                        <input type="hidden" name="id" id="vendorId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Vendor Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tax_id" class="form-label">Tax ID</label>
                                <input type="text" class="form-control" id="tax_id" name="tax_id">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payment_terms" class="form-label">Payment Terms</label>
                                <select class="form-select" id="payment_terms" name="payment_terms">
                                    <option value="">Select Payment Terms</option>
                                    <option value="COD">COD (Cash on Delivery)</option>
                                    <option value="Cheque">Cheque Payment</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vendor_score" class="form-label">Vendor Score (0-10)</label>
                                <input type="number" step="0.1" min="0" max="10" class="form-control" id="vendor_score" name="vendor_score" placeholder="e.g., 8.5">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="delivery_time_avg" class="form-label">Avg Delivery Time (days)</label>
                                <input type="number" min="0" step="1" class="form-control" id="delivery_time_avg" name="delivery_time_avg" placeholder="e.g., 5">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="defect_rate" class="form-label">Defect Rate (%)</label>
                                <input type="number" min="0" max="100" step="0.1" class="form-control" id="defect_rate" name="defect_rate" placeholder="e.g., 2.5">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="on_time_percentage" class="form-label">On-Time Percentage (%)</label>
                                <input type="number" min="0" max="100" step="0.1" class="form-control" id="on_time_percentage" name="on_time_percentage" placeholder="e.g., 95.0">
                            </div>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active Vendor
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Vendor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete vendor <strong id="deleteVendorName"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteVendorId">
                        <button type="submit" class="btn btn-danger">Delete Vendor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Contract Modal -->
    <div class="modal fade" id="contractModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contractModalTitle">Add Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="contractForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="contractAction" value="create_contract">
                        <input type="hidden" name="contract_id" id="contractId">
                        <input type="hidden" name="vendor_id" id="contractVendorId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contract_number" class="form-label">Contract Number *</label>
                                <input type="text" class="form-control" id="contract_number" name="contract_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contract_name" class="form-label">Contract Name *</label>
                                <input type="text" class="form-control" id="contract_name" name="contract_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contract_type" class="form-label">Contract Type *</label>
                                <select class="form-select" id="contract_type" name="contract_type" required>
                                    <option value="goods">Goods</option>
                                    <option value="framework">Framework</option>
                                    <option value="blanket">Blanket</option>
                                    <option value="standing">Standing</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="expiring">Expiring</option>
                                    <option value="expired">Expired</option>
                                    <option value="terminated">Terminated</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="total_value" class="form-label">Total Value</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="total_value" name="total_value" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="ZMW" selected>ZMW</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="renewal_notification_days" class="form-label">Renewal Notification Days</label>
                                <input type="number" min="0" class="form-control" id="renewal_notification_days" name="renewal_notification_days" value="60" placeholder="Days before expiry to notify">
                                <small class="text-muted">Number of days before expiry to send notifications</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="terms_and_conditions" class="form-label">Terms and Conditions</label>
                            <textarea class="form-control" id="terms_and_conditions" name="terms_and_conditions" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Contract</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Contract Confirmation Modal -->
    <div class="modal fade" id="deleteContractModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete contract <strong id="deleteContractName"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete_contract">
                        <input type="hidden" name="contract_id" id="deleteContractId">
                        <input type="hidden" name="vendor_id" id="deleteContractVendorId">
                        <button type="submit" class="btn btn-danger">Delete Contract</button>
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

        function editVendor(vendor) {
            document.getElementById('vendorModalTitle').textContent = 'Edit Vendor';
            document.getElementById('vendorAction').value = 'update';
            document.getElementById('vendorId').value = vendor.id;
            document.getElementById('name').value = vendor.name;
            document.getElementById('contact_person').value = vendor.contact_person || '';
            document.getElementById('email').value = vendor.email || '';
            document.getElementById('phone').value = vendor.phone || '';
            document.getElementById('address').value = vendor.address || '';
            document.getElementById('tax_id').value = vendor.tax_id || '';
            document.getElementById('payment_terms').value = vendor.payment_terms || '';
            document.getElementById('is_active').checked = vendor.is_active == 1;
            document.getElementById('vendor_score').value = vendor.vendor_score ?? '';
            document.getElementById('delivery_time_avg').value = vendor.delivery_time_avg ?? '';
            document.getElementById('defect_rate').value = vendor.defect_rate ?? '';
            document.getElementById('on_time_percentage').value = vendor.on_time_percentage ?? '';
            
            new bootstrap.Modal(document.getElementById('vendorModal')).show();
        }
        
        function deleteVendor(id, name) {
            document.getElementById('deleteVendorId').value = id;
            document.getElementById('deleteVendorName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Reset form when modal is hidden
        document.getElementById('vendorModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('vendorForm').reset();
            document.getElementById('vendorModalTitle').textContent = 'Add Vendor';
            document.getElementById('vendorAction').value = 'create';
            document.getElementById('vendorId').value = '';
        });

        // Contract management functions
        function addContract(vendorId) {
            document.getElementById('contractModalTitle').textContent = 'Add Contract';
            document.getElementById('contractAction').value = 'create_contract';
            document.getElementById('contractId').value = '';
            document.getElementById('contractVendorId').value = vendorId;
            document.getElementById('contractForm').reset();
            document.getElementById('contractVendorId').value = vendorId;
            document.getElementById('renewal_notification_days').value = 60;
            document.getElementById('currency').value = 'ZMW';
            new bootstrap.Modal(document.getElementById('contractModal')).show();
        }

        function editContract(contract) {
            document.getElementById('contractModalTitle').textContent = 'Edit Contract';
            document.getElementById('contractAction').value = 'update_contract';
            document.getElementById('contractId').value = contract.id;
            document.getElementById('contractVendorId').value = contract.vendor_id;
            document.getElementById('contract_number').value = contract.contract_number || '';
            document.getElementById('contract_name').value = contract.contract_name || '';
            document.getElementById('contract_type').value = contract.contract_type || 'goods';
            document.getElementById('start_date').value = contract.start_date || '';
            document.getElementById('end_date').value = contract.end_date || '';
            document.getElementById('total_value').value = contract.total_value || 0;
            document.getElementById('currency').value = contract.currency || 'ZMW';
            document.getElementById('status').value = contract.status || 'draft';
            document.getElementById('terms_and_conditions').value = contract.terms_and_conditions || '';
            document.getElementById('renewal_notification_days').value = contract.renewal_notification_days || 60;
            new bootstrap.Modal(document.getElementById('contractModal')).show();
        }

        function deleteContract(contractId, vendorId, contractName) {
            document.getElementById('deleteContractId').value = contractId;
            document.getElementById('deleteContractVendorId').value = vendorId;
            document.getElementById('deleteContractName').textContent = contractName;
            new bootstrap.Modal(document.getElementById('deleteContractModal')).show();
        }

        // Reset contract form when modal is hidden
        document.getElementById('contractModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('contractForm').reset();
            document.getElementById('contractModalTitle').textContent = 'Add Contract';
            document.getElementById('contractAction').value = 'create_contract';
            document.getElementById('contractId').value = '';
            document.getElementById('contractVendorId').value = '';
        });
    </script>
</body>
</html>
