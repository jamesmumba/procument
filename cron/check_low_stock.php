<?php
/**
 * Low Stock Check Cron Job
 * This script should be run periodically (e.g., daily) to check for low stock items
 * and create notifications for relevant users.
 * 
 * Usage: php cron/check_low_stock.php
 * Or add to crontab: 0 9 * * * php /path/to/procurement/cron/check_low_stock.php
 */

// Set the base directory
$base_dir = dirname(__DIR__);

// Include required files
require_once $base_dir . '/config/config.php';
require_once $base_dir . '/includes/notifications.php';

// Set timezone
date_default_timezone_set('Africa/Lusaka');

echo "[" . date('Y-m-d H:i:s') . "] Starting low stock check...\n";

try {
    $notificationSystem = new NotificationSystem();
    
    // Check for low stock items and create notifications
    $notifications_created = $notificationSystem->checkLowStockNotifications();
    
    echo "[" . date('Y-m-d H:i:s') . "] Created {$notifications_created} low stock notifications\n";
    
    // Clean up old notifications (older than 30 days)
    $old_notifications_deleted = $notificationSystem->deleteOldNotifications(30);
    echo "[" . date('Y-m-d H:i:s') . "] Deleted {$old_notifications_deleted} old notifications\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Low stock check completed successfully\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Low stock check cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
?>
