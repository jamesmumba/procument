<?php
/**
 * Contract Renewal Check Cron Job
 * This script should be run periodically (e.g., daily) to check for expiring contracts
 * and create notifications for relevant users.
 * 
 * Usage: php cron/check_contract_renewals.php
 * Or add to crontab: 0 9 * * * php /path/to/procurement/cron/check_contract_renewals.php
 */

// Set the base directory
$base_dir = dirname(__DIR__);

// Include required files
require_once $base_dir . '/config/config.php';
require_once $base_dir . '/includes/notifications.php';

// Set timezone
date_default_timezone_set('Africa/Lusaka');

echo "[" . date('Y-m-d H:i:s') . "] Starting contract renewal check...\n";

try {
    $notificationSystem = new NotificationSystem();
    
    // Check for expiring contracts and create notifications
    $notifications_created = $notificationSystem->checkContractRenewalNotifications();
    
    echo "[" . date('Y-m-d H:i:s') . "] Created {$notifications_created} contract renewal notifications\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Contract renewal check completed successfully\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("Contract renewal check cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
?>

