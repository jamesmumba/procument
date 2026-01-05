<?php
/**
 * Notification System
 * Handles system notifications for inventory, approvals, and other events
 */

require_once __DIR__ . '/../config/config.php';

class NotificationSystem {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Create a notification
     */
    public function createNotification($user_id, $title, $message, $type = 'info', $category = 'system', $action_url = null, $metadata = null) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "INSERT INTO notifications (user_id, title, message, type, category, action_url, metadata) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $user_id,
                $title,
                $message,
                $type,
                $category,
                $action_url,
                $metadata ? json_encode($metadata) : null
            ]);
            
            if ($result) {
                logAudit('create_notification', 'notifications', $this->db->lastInsertId(), null, [
                    'user_id' => $user_id,
                    'title' => $title,
                    'type' => $type,
                    'category' => $category
                ]);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Create notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user
     */
    public function getUserNotifications($user_id, $limit = 50, $unread_only = false) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$user_id];
            
            if ($unread_only) {
                $sql .= " AND is_read = 0";
            }
            
            // Inline LIMIT to avoid binding issues with MySQL native prepares
            $safeLimit = (int)$limit;
            if ($safeLimit <= 0) {
                $safeLimit = 50;
            }
            $sql .= " ORDER BY created_at DESC LIMIT " . $safeLimit;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            
            // Debug logging
            error_log("getUserNotifications - User ID: $user_id, Found: " . count($result) . " notifications");
            
            return $result;
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$notification_id, $user_id]);
            
            if ($result) {
                logAudit('read_notification', 'notifications', $notification_id);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$user_id]);
            
            if ($result) {
                logAudit('read_all_notifications', 'notifications', null, null, ['user_id' => $user_id]);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Mark all notifications as read error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount($user_id) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $count = $stmt->fetchColumn();
            
            // Debug logging
            error_log("getUnreadCount - User ID: $user_id, Count: $count");
            
            return $count;
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Delete old notifications (cleanup)
     */
    public function deleteOldNotifications($days = 30) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$days]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Delete old notifications error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for low stock items and create notifications
     */
    public function checkLowStockNotifications() {
        try {
            $conn = $this->db->getConnection();
            
            // First, clean up any orphaned notifications
            $cleanup_sql = "DELETE FROM notifications WHERE user_id NOT IN (SELECT id FROM users WHERE is_active = 1)";
            $conn->exec($cleanup_sql);
            
            // Get low stock items
            $sql = "SELECT ii.*, v.name as supplier_name 
                    FROM inventory_items ii 
                    LEFT JOIN vendors v ON ii.supplier_id = v.id 
                    WHERE ii.is_active = 1 AND ii.current_stock <= ii.reorder_point";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $low_stock_items = $stmt->fetchAll();
            
            // Get users who should be notified (CPO role_id=2, Inventory Manager role_id=4, Admin role_id=1)
            $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.is_active = 1 
                    AND (u.role_id = 2 OR u.role_id = 4 OR u.role_id = 1)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $notify_users = $stmt->fetchAll();
            
            $notifications_created = 0;
            
            foreach ($low_stock_items as $item) {
                foreach ($notify_users as $user) {
                    $title = "Low Stock Alert: " . $item['name'];
                    $message = "Item '{$item['name']}' (Code: {$item['item_code']}) is running low. Current stock: {$item['current_stock']}, Reorder point: {$item['reorder_point']}";
                    
                    $metadata = [
                        'item_id' => $item['id'],
                        'item_code' => $item['item_code'],
                        'current_stock' => $item['current_stock'],
                        'reorder_point' => $item['reorder_point'],
                        'supplier_name' => $item['supplier_name']
                    ];
                    
                    // Check if notification already exists for this item and user (to avoid spam)
                    $check_sql = "SELECT COUNT(*) FROM notifications 
                                  WHERE user_id = ? AND category = 'inventory' 
                                  AND metadata LIKE ? AND is_read = 0 
                                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
                    
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([$user['id'], '%"item_id":' . $item['id'] . '%']);
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        if ($this->createNotification(
                            $user['id'],
                            $title,
                            $message,
                            $item['current_stock'] == 0 ? 'error' : 'warning',
                            'inventory',
                            'inventory.php?action=view&id=' . $item['id'],
                            $metadata
                        )) {
                            $notifications_created++;
                        }
                    }
                }
            }
            
            return $notifications_created;
        } catch (Exception $e) {
            error_log("Check low stock notifications error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Notify low stock for a single item (used on stock changes)
     */
    public function notifyLowStockForItem($item_id) {
        try {
            $conn = $this->db->getConnection();

            // Load the specific item (ensure active)
            $sql = "SELECT ii.*, v.name as supplier_name 
                    FROM inventory_items ii 
                    LEFT JOIN vendors v ON ii.supplier_id = v.id 
                    WHERE ii.id = ? AND ii.is_active = 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if (!$item) {
                return 0;
            }

            // Only notify if below or equal to reorder point
            if ((int)$item['current_stock'] > (int)$item['reorder_point']) {
                return 0;
            }

            // Get users to notify (CPO 2, Inventory Manager 4, Admin 1)
            $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.is_active = 1 
                    AND (u.role_id = 2 OR u.role_id = 4 OR u.role_id = 1)";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $notify_users = $stmt->fetchAll();

            $notifications_created = 0;
            foreach ($notify_users as $user) {
                $title = ($item['current_stock'] == 0 ? 'Out of Stock Alert: ' : 'Low Stock Alert: ') . $item['name'];
                $message = "Item '{$item['name']}' (Code: {$item['item_code']}) is " . ($item['current_stock'] == 0 ? 'out of stock' : 'running low') . ". Current stock: {$item['current_stock']}, Reorder point: {$item['reorder_point']}";

                $metadata = [
                    'item_id' => $item['id'],
                    'item_code' => $item['item_code'],
                    'current_stock' => $item['current_stock'],
                    'reorder_point' => $item['reorder_point'],
                    'supplier_name' => $item['supplier_name']
                ];

                // Avoid duplicates in the last 24h for this item/user
                $check_sql = "SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND category = 'inventory' 
                              AND metadata LIKE ? AND is_read = 0 
                              AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->execute([$user['id'], '%"item_id":' . $item['id'] . '%']);

                if ($check_stmt->fetchColumn() == 0) {
                    if ($this->createNotification(
                        $user['id'],
                        $title,
                        $message,
                        $item['current_stock'] == 0 ? 'error' : 'warning',
                        'inventory',
                        'inventory.php?action=view&id=' . $item['id'],
                        $metadata
                    )) {
                        $notifications_created++;
                    }
                }
            }

            return $notifications_created;
        } catch (Exception $e) {
            error_log("Notify low stock for item error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check for expiring contracts and create renewal notifications
     */
    public function checkContractRenewalNotifications() {
        try {
            $conn = $this->db->getConnection();
            
            // Check if vendor_contracts table exists
            $check_sql = "SELECT COUNT(*) FROM information_schema.tables 
                          WHERE table_schema = DATABASE() AND table_name = 'vendor_contracts'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() == 0) {
                return 0; // Table doesn't exist
            }
            
            // Get contracts expiring within their renewal_notification_days period
            $sql = "SELECT vc.*, v.name as vendor_name, u.id as creator_id
                    FROM vendor_contracts vc
                    JOIN vendors v ON vc.vendor_id = v.id
                    LEFT JOIN users u ON vc.created_by = u.id
                    WHERE vc.status = 'active'
                    AND vc.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL vc.renewal_notification_days DAY)
                    AND vc.end_date > CURDATE()";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $expiring_contracts = $stmt->fetchAll();
            
            // Get users who should be notified (CPO role_id=2, Admin role_id=1)
            $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.is_active = 1 
                    AND (u.role_id = 2 OR u.role_id = 1)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $notify_users = $stmt->fetchAll();
            
            $notifications_created = 0;
            
            foreach ($expiring_contracts as $contract) {
                $days_to_expiry = (int)((strtotime($contract['end_date']) - time()) / 86400);
                
                // Determine notification type based on urgency
                if ($days_to_expiry <= 30) {
                    $type = 'error'; // Critical - expiring soon
                } elseif ($days_to_expiry <= 60) {
                    $type = 'warning'; // Warning
                } else {
                    $type = 'info'; // Informational
                }
                
                // Notify CPO and Admin
                foreach ($notify_users as $user) {
                    $title = "Contract Expiring: " . $contract['contract_name'];
                    $message = "Contract '{$contract['contract_name']}' (Contract #{$contract['contract_number']}) with vendor '{$contract['vendor_name']}' is expiring in {$days_to_expiry} days. Expiry date: " . date('Y-m-d', strtotime($contract['end_date']));
                    
                    $metadata = [
                        'contract_id' => $contract['id'],
                        'contract_number' => $contract['contract_number'],
                        'vendor_id' => $contract['vendor_id'],
                        'vendor_name' => $contract['vendor_name'],
                        'end_date' => $contract['end_date'],
                        'days_to_expiry' => $days_to_expiry,
                        'total_value' => $contract['total_value']
                    ];
                    
                    // Check if notification already exists (avoid duplicates within last 7 days)
                    $check_sql = "SELECT COUNT(*) FROM notifications 
                                  WHERE user_id = ? AND category = 'system' 
                                  AND metadata LIKE ? AND is_read = 0 
                                  AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([$user['id'], '%"contract_id":' . $contract['id'] . '%']);
                    
                    if ($check_stmt->fetchColumn() == 0) {
                        if ($this->createNotification(
                            $user['id'],
                            $title,
                            $message,
                            $type,
                            'system',
                            'vendor.php?action=view&id=' . $contract['vendor_id'],
                            $metadata
                        )) {
                            $notifications_created++;
                        }
                    }
                }
                
                // Also notify the contract creator if they're still active and different from current user
                if ($contract['creator_id']) {
                    $check_sql = "SELECT is_active FROM users WHERE id = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([$contract['creator_id']]);
                    $creator = $check_stmt->fetch();
                    
                    if ($creator && $creator['is_active']) {
                        $days_to_expiry = (int)((strtotime($contract['end_date']) - time()) / 86400);
                        $title = "Contract Expiring: " . $contract['contract_name'];
                        $message = "A contract you created '{$contract['contract_name']}' (Contract #{$contract['contract_number']}) with vendor '{$contract['vendor_name']}' is expiring in {$days_to_expiry} days.";
                        
                        $check_sql = "SELECT COUNT(*) FROM notifications 
                                      WHERE user_id = ? AND category = 'system' 
                                      AND metadata LIKE ? AND is_read = 0 
                                      AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->execute([$contract['creator_id'], '%"contract_id":' . $contract['id'] . '%']);
                        
                        if ($check_stmt->fetchColumn() == 0) {
                            if ($this->createNotification(
                                $contract['creator_id'],
                                $title,
                                $message,
                                $days_to_expiry <= 30 ? 'error' : ($days_to_expiry <= 60 ? 'warning' : 'info'),
                                'system',
                                'vendor.php?action=view&id=' . $contract['vendor_id'],
                                $metadata
                            )) {
                                $notifications_created++;
                            }
                        }
                    }
                }
            }
            
            return $notifications_created;
        } catch (Exception $e) {
            error_log("Check contract renewal notifications error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create approval notification
     */
    public function createApprovalNotification($approver_id, $requisition_number, $amount, $requester_name) {
        $title = "New Requisition for Approval: " . $requisition_number;
        $message = "Requisition {$requisition_number} from {$requester_name} requires your approval. Amount: " . formatCurrency($amount);
        
        $metadata = [
            'requisition_number' => $requisition_number,
            'amount' => $amount,
            'requester_name' => $requester_name
        ];
        
        return $this->createNotification(
            $approver_id,
            $title,
            $message,
            'info',
            'approval',
            'approval.php',
            $metadata
        );
    }
    
    /**
     * Create requisition status notification
     */
    public function createRequisitionStatusNotification($requester_id, $requisition_number, $status, $comments = null) {
        $title = "Requisition Status Update: " . $requisition_number;
        
        switch ($status) {
            case 'approved':
                $message = "Your requisition {$requisition_number} has been approved.";
                $type = 'success';
                break;
            case 'rejected':
                $message = "Your requisition {$requisition_number} has been rejected.";
                $type = 'error';
                if ($comments) {
                    $message .= " Comments: " . $comments;
                }
                break;
            default:
                $message = "Your requisition {$requisition_number} status has been updated to {$status}.";
                $type = 'info';
        }
        
        $metadata = [
            'requisition_number' => $requisition_number,
            'status' => $status,
            'comments' => $comments
        ];
        
        return $this->createNotification(
            $requester_id,
            $title,
            $message,
            $type,
            'requisition',
            'requisition.php?action=view&id=' . $requisition_number,
            $metadata
        );
    }
}

// Helper functions for notifications
function createNotification($user_id, $title, $message, $type = 'info', $category = 'system', $action_url = null, $metadata = null) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->createNotification($user_id, $title, $message, $type, $category, $action_url, $metadata);
}

function getUserNotifications($user_id, $limit = 50, $unread_only = false) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->getUserNotifications($user_id, $limit, $unread_only);
}

function getUnreadNotificationCount($user_id) {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->getUnreadCount($user_id);
}

function checkLowStockNotifications() {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->checkLowStockNotifications();
}

function checkContractRenewalNotifications() {
    $notificationSystem = new NotificationSystem();
    return $notificationSystem->checkContractRenewalNotifications();
}
?>
