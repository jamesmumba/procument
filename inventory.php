<?php
/**
 * Inventory Management Page
 * Handles inventory items and stock management
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
$__user = getCurrentUser();
if ($__user['role_id'] != 1 && strtolower($__user['role_name']) !== 'inventory_manager') {
    http_response_code(403);
    die('Access denied. Inventory is restricted to Admin and Inventory Manager.');
}

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$item_id = $_GET['id'] ?? null;
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
                    $itemData = [
                        'item_code' => sanitizeInput($_POST['item_code'] ?? ''),
                        'name' => sanitizeInput($_POST['name'] ?? ''),
                        'description' => sanitizeInput($_POST['description'] ?? ''),
                        'category' => sanitizeInput($_POST['category'] ?? ''),
                        'unit_of_measure' => sanitizeInput($_POST['unit_of_measure'] ?? ''),
                        'current_stock' => (int)($_POST['current_stock'] ?? 0),
                        'reorder_point' => (int)($_POST['reorder_point'] ?? 0),
                        'reorder_quantity' => (int)($_POST['reorder_quantity'] ?? 0),
                        'unit_cost' => (float)($_POST['unit_cost'] ?? 0),
                        'supplier_id' => !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
                        'is_active' => isset($_POST['is_active']) ? 1 : 0
                    ];
                    
                    if ($action == 'create') {
                        $sql = "INSERT INTO inventory_items (item_code, name, description, category, unit_of_measure, current_stock, reorder_point, reorder_quantity, unit_cost, supplier_id, is_active) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute(array_values($itemData));
                        $item_id = $db->lastInsertId();
                        
                        logAudit('create_inventory_item', 'inventory_items', $item_id, null, $itemData);
                        
                        // Notify only for this item if it's below reorder point
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->notifyLowStockForItem($item_id);
                        
                        $message = 'Inventory item created successfully!';
                    } else {
                        $update_id = $_POST['id'];
                        $sql = "UPDATE inventory_items SET item_code = ?, name = ?, description = ?, category = ?, unit_of_measure = ?, current_stock = ?, reorder_point = ?, reorder_quantity = ?, unit_cost = ?, supplier_id = ?, is_active = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute(array_merge(array_values($itemData), [$update_id]));
                        
                        logAudit('update_inventory_item', 'inventory_items', $update_id, null, $itemData);
                        
                        // Notify only for this item if it's below reorder point
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->notifyLowStockForItem($update_id);
                        
                        $message = 'Inventory item updated successfully!';
                    }
                    break;
                    
                case 'adjust_stock':
                    $adjust_id = $_POST['id'];
                    $adjustment_type = $_POST['adjustment_type'];
                    $quantity = (int)$_POST['quantity'];
                    $reason = sanitizeInput($_POST['reason']);
                    
                    $sql = "SELECT current_stock FROM inventory_items WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$adjust_id]);
                    $current_stock = $stmt->fetchColumn();
                    
                    $new_stock = $adjustment_type == 'add' ? $current_stock + $quantity : $current_stock - $quantity;
                    
                    if ($new_stock < 0) {
                        $error = 'Cannot reduce stock below zero.';
                    } else {
                        $sql = "UPDATE inventory_items SET current_stock = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$new_stock, $adjust_id]);
                        
                        logAudit('adjust_stock', 'inventory_items', $adjust_id, ['old_stock' => $current_stock], ['new_stock' => $new_stock, 'adjustment_type' => $adjustment_type, 'quantity' => $quantity, 'reason' => $reason]);
                        
                        // Check if this item's stock is now low and notify for this item only
                        $notificationSystem = new NotificationSystem();
                        $notificationSystem->notifyLowStockForItem($adjust_id);
                        
                        $message = 'Stock adjusted successfully!';
                    }
                    break;
                    
                case 'delete':
                    $delete_id = $_POST['id'];
                    $sql = "UPDATE inventory_items SET is_active = 0 WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$delete_id]);
                    
                    logAudit('delete_inventory_item', 'inventory_items', $delete_id);
                    $message = 'Inventory item deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get item data for edit
$item = null;
if ($item_id && $action == 'edit') {
    $sql = "SELECT ii.*, v.name as supplier_name 
            FROM inventory_items ii 
            LEFT JOIN vendors v ON ii.supplier_id = v.id 
            WHERE ii.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
}

// Get inventory items list
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (ii.item_code LIKE ? OR ii.name LIKE ? OR ii.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

if ($category_filter) {
    $whereClause .= " AND ii.category = ?";
    $params[] = $category_filter;
}

if ($stock_filter == 'low') {
    $whereClause .= " AND ii.current_stock <= ii.reorder_point";
} elseif ($stock_filter == 'out') {
    $whereClause .= " AND ii.current_stock = 0";
}

$sql = "SELECT ii.*, v.name as supplier_name 
        FROM inventory_items ii 
        LEFT JOIN vendors v ON ii.supplier_id = v.id 
        $whereClause 
        ORDER BY ii.name ASC 
        LIMIT " . (int)ITEMS_PER_PAGE . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$inventory_items = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM inventory_items ii LEFT JOIN vendors v ON ii.supplier_id = v.id $whereClause";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / ITEMS_PER_PAGE);

// Get categories for filter
$sql = "SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL AND category != '' ORDER BY category";
$stmt = $conn->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll();

// Get vendors for supplier dropdown
$sql = "SELECT id, name FROM vendors WHERE is_active = 1 ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$vendors = $stmt->fetchAll();

// Get low stock items
$sql = "SELECT COUNT(*) FROM inventory_items WHERE current_stock <= reorder_point AND is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$low_stock_count = $stmt->fetchColumn();

// Get out of stock items
$sql = "SELECT COUNT(*) FROM inventory_items WHERE current_stock = 0 AND is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$out_of_stock_count = $stmt->fetchColumn();

// Unread notifications count for sidebar badge
$notificationSystem = new NotificationSystem();
$unread_notifications = $notificationSystem->getUnreadCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - <?php echo APP_NAME; ?></title>
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
        .stock-low {
            background-color: #fff3cd !important;
        }
        .stock-out {
            background-color: #f8d7da !important;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
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
                        <a class="nav-link" href="purchase_order.php">
                            <i class="bi bi-receipt me-2"></i>Purchase Orders
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_inventory')): ?>
                        <a class="nav-link active" href="inventory.php">
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
                    <h2>Inventory Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Item
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Total Items</h6>
                                    <h3 class="fw-bold"><?php echo number_format($totalItems); ?></h3>
                                </div>
                                <i class="bi bi-boxes fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Low Stock</h6>
                                    <h3 class="fw-bold"><?php echo number_format($low_stock_count); ?></h3>
                                </div>
                                <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card danger">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Out of Stock</h6>
                                    <h3 class="fw-bold"><?php echo number_format($out_of_stock_count); ?></h3>
                                </div>
                                <i class="bi bi-x-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category']); ?>" <?php echo $category_filter == $category['category'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="stock">
                                    <option value="">All Stock Levels</option>
                                    <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search me-2"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Inventory Items Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-boxes me-2"></i>Inventory Items (<?php echo $totalItems; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inventory_items)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-boxes fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No inventory items found</h5>
                                <p class="text-muted">Start by adding your first inventory item</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Item Code</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Current Stock</th>
                                            <th>Reorder Point</th>
                                            <th>Unit Cost</th>
                                            <th>Supplier</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory_items as $inv_item): ?>
                                            <?php
                                            $row_class = '';
                                            if ($inv_item['current_stock'] == 0) {
                                                $row_class = 'stock-out';
                                            } elseif ($inv_item['current_stock'] <= $inv_item['reorder_point']) {
                                                $row_class = 'stock-low';
                                            }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($inv_item['item_code']); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($inv_item['name']); ?></strong>
                                                    <?php if ($inv_item['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($inv_item['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($inv_item['category']): ?>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($inv_item['category']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($inv_item['current_stock']); ?></strong>
                                                    <small class="text-muted"><?php echo htmlspecialchars($inv_item['unit_of_measure']); ?></small>
                                                    <?php if ($inv_item['current_stock'] <= $inv_item['reorder_point']): ?>
                                                        <br><small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Low Stock</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($inv_item['reorder_point']); ?></td>
                                                <td><?php echo formatCurrency($inv_item['unit_cost']); ?></td>
                                                <td>
                                                    <?php if ($inv_item['supplier_name']): ?>
                                                        <?php echo htmlspecialchars($inv_item['supplier_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $inv_item['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $inv_item['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($inv_item)); ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" title="Adjust Stock" onclick="adjustStock(<?php echo $inv_item['id']; ?>, '<?php echo htmlspecialchars($inv_item['name']); ?>', <?php echo $inv_item['current_stock']; ?>)">
                                                            <i class="bi bi-sliders"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?php echo $inv_item['id']; ?>, '<?php echo htmlspecialchars($inv_item['name']); ?>')">
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
                                <nav aria-label="Inventory pagination">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&stock=<?php echo urlencode($stock_filter); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Item Modal -->
    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalTitle">Add Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="itemForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="itemAction" value="create">
                        <input type="hidden" name="id" id="itemId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="item_code" class="form-label">Item Code *</label>
                                <input type="text" class="form-control" id="item_code" name="item_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Item Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category" list="categoryList">
                                <datalist id="categoryList">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="unit_of_measure" class="form-label">Unit of Measure</label>
                                <select class="form-select" id="unit_of_measure" name="unit_of_measure">
                                    <option value="pcs">Pieces</option>
                                    <option value="kg">Kilograms</option>
                                    <option value="lbs">Pounds</option>
                                    <option value="liters">Liters</option>
                                    <option value="meters">Meters</option>
                                    <option value="boxes">Boxes</option>
                                    <option value="units">Units</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="current_stock" class="form-label">Current Stock</label>
                                <input type="number" class="form-control" id="current_stock" name="current_stock" min="0" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="reorder_point" class="form-label">Reorder Point</label>
                                <input type="number" class="form-control" id="reorder_point" name="reorder_point" min="0" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="reorder_quantity" class="form-label">Reorder Quantity</label>
                                <input type="number" class="form-control" id="reorder_quantity" name="reorder_quantity" min="0" value="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unit_cost" class="form-label">Unit Cost</label>
                                <input type="number" class="form-control" id="unit_cost" name="unit_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="supplier_id" class="form-label">Primary Supplier</label>
                                <select class="form-select" id="supplier_id" name="supplier_id">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active Item
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="stockForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="id" id="stockItemId">
                        
                        <p>Adjusting stock for: <strong id="stockItemName"></strong></p>
                        <p>Current stock: <strong id="currentStock"></strong></p>
                        
                        <div class="mb-3">
                            <label for="adjustment_type" class="form-label">Adjustment Type</label>
                            <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                                <option value="add">Add Stock</option>
                                <option value="subtract">Subtract Stock</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required placeholder="Explain why you're adjusting the stock..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Adjust Stock</button>
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
                    <p>Are you sure you want to delete item <strong id="deleteItemName"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteItemId">
                        <button type="submit" class="btn btn-danger">Delete Item</button>
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

        function editItem(item) {
            document.getElementById('itemModalTitle').textContent = 'Edit Inventory Item';
            document.getElementById('itemAction').value = 'update';
            document.getElementById('itemId').value = item.id;
            document.getElementById('item_code').value = item.item_code;
            document.getElementById('name').value = item.name;
            document.getElementById('description').value = item.description || '';
            document.getElementById('category').value = item.category || '';
            document.getElementById('unit_of_measure').value = item.unit_of_measure;
            document.getElementById('current_stock').value = item.current_stock;
            document.getElementById('reorder_point').value = item.reorder_point;
            document.getElementById('reorder_quantity').value = item.reorder_quantity;
            document.getElementById('unit_cost').value = item.unit_cost;
            document.getElementById('supplier_id').value = item.supplier_id || '';
            document.getElementById('is_active').checked = item.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('itemModal')).show();
        }
        
        function adjustStock(id, name, currentStock) {
            document.getElementById('stockItemId').value = id;
            document.getElementById('stockItemName').textContent = name;
            document.getElementById('currentStock').textContent = currentStock;
            new bootstrap.Modal(document.getElementById('stockModal')).show();
        }
        
        function deleteItem(id, name) {
            document.getElementById('deleteItemId').value = id;
            document.getElementById('deleteItemName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Reset form when modal is hidden
        document.getElementById('itemModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('itemForm').reset();
            document.getElementById('itemModalTitle').textContent = 'Add Inventory Item';
            document.getElementById('itemAction').value = 'create';
            document.getElementById('itemId').value = '';
        });
    </script>
</body>
</html>
