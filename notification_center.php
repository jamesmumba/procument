<?php
/**
 * Notification Center
 * Displays and manages user notifications
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication
requireLogin();

$db = new Database();
$conn = $db->getConnection();

$action = $_GET['action'] ?? 'list';
$notification_id = $_GET['id'] ?? null;
$message = '';
$error = '';

$__user = getCurrentUser();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $notificationSystem = new NotificationSystem();
        
        switch ($_POST['action']) {
            case 'mark_read':
                $result = $notificationSystem->markAsRead($_POST['notification_id'], $__user['id']);
                echo json_encode(['success' => $result]);
                break;
                
            case 'mark_all_read':
                $result = $notificationSystem->markAllAsRead($__user['id']);
                echo json_encode(['success' => $result]);
                break;
                
            case 'get_count':
                $count = $notificationSystem->getUnreadCount($__user['id']);
                echo json_encode(['count' => $count]);
                break;
                
            case 'get_notifications':
                $notifications = $notificationSystem->getUserNotifications($__user['id'], 10, true);
                echo json_encode(['notifications' => $notifications]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle regular form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        try {
            $notificationSystem = new NotificationSystem();
            
            switch ($_POST['action']) {
                case 'mark_read':
                    $notification_id = $_POST['notification_id'];
                    $result = $notificationSystem->markAsRead($notification_id, $__user['id']);
                    if ($result) {
                        $message = 'Notification marked as read.';
                    } else {
                        $error = 'Failed to mark notification as read.';
                    }
                    break;
                    
                case 'mark_all_read':
                    $result = $notificationSystem->markAllAsRead($__user['id']);
                    if ($result) {
                        $message = 'All notifications marked as read.';
                    } else {
                        $error = 'Failed to mark notifications as read.';
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get notifications for the current user
$notificationSystem = new NotificationSystem();
$notifications = $notificationSystem->getUserNotifications($__user['id'], 50);
$unread_count = $notificationSystem->getUnreadCount($__user['id']);

// Debug: Log the counts for troubleshooting
error_log("Notification Center - Total notifications: " . count($notifications) . ", Unread count: " . $unread_count);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo APP_NAME; ?></title>
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
        .notification-item {
            border-left: 4px solid #667eea;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        .notification-item.unread {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .notification-item.warning {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        .notification-item.success {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .notification-item.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .notification-badge {
            position: relative;
        }
        .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.7rem;
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
                        <a class="nav-link active" href="notification_center.php">
                            <i class="bi bi-bell me-2"></i>Notifications
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $unread_count; ?></span>
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
                    <h2>Notification Center</h2>
                    <div class="d-flex gap-2">
                        <?php if ($unread_count > 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-check-all me-2"></i>Mark All Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-sm" onclick="refreshNotifications()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
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

                <!-- Notifications List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-bell me-2"></i>
                            Notifications 
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo $unread_count; ?> Unread</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications) && $unread_count == 0): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell-slash fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No notifications</h5>
                                <p class="text-muted">You're all caught up! Check back later for new notifications.</p>
                            </div>
                        <?php elseif (empty($notifications) && $unread_count > 0): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                                <h5 class="text-warning mt-3">Notification Sync Issue</h5>
                                <p class="text-muted">There are <?php echo $unread_count; ?> unread notifications, but they're not displaying properly.</p>
                                <div class="mt-3">
                                    <button class="btn btn-warning me-2" onclick="location.reload()">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh Page
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="mark_all_read">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-check-all me-2"></i>Clear Count
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-danger ms-2">New</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-2 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo formatDate($notification['created_at']); ?>
                                                <span class="ms-3">
                                                    <i class="bi bi-tag me-1"></i>
                                                    <?php echo ucfirst($notification['category']); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <div class="d-flex flex-column gap-1">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($notification['action_url']): ?>
                                                <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-arrow-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshNotifications() {
            location.reload();
        }
        
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            fetch('notification_center.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax=1&action=get_count'
            })
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    // Update notification badge in sidebar
                    const badge = document.querySelector('.nav-link[href="notification_center.php"] .badge');
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        const navLink = document.querySelector('.nav-link[href="notification_center.php"]');
                        navLink.innerHTML += ` <span class="badge bg-danger ms-2">${data.count}</span>`;
                    }
                }
            })
            .catch(error => console.error('Error refreshing notifications:', error));
        }, 30000);
    </script>
</body>
</html>
