<?php
/**
 * Test Notification Generator
 * Creates sample notifications to test the system
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication
requireLogin();

$db = new Database();
$conn = $db->getConnection();
$user = getCurrentUser();

echo "<h1>🧪 Test Notification Generator</h1>";
echo "<p>User: " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . " (ID: " . $user['id'] . ")</p>";

$notificationSystem = new NotificationSystem();

// Create test notifications
$test_notifications = [
    [
        'title' => 'Test Info Notification',
        'message' => 'This is a test info notification to verify the system is working.',
        'type' => 'info',
        'category' => 'system'
    ],
    [
        'title' => 'Test Warning Notification',
        'message' => 'This is a test warning notification for low stock items.',
        'type' => 'warning',
        'category' => 'inventory'
    ],
    [
        'title' => 'Test Success Notification',
        'message' => 'This is a test success notification for completed actions.',
        'type' => 'success',
        'category' => 'requisition'
    ],
    [
        'title' => 'Test Error Notification',
        'message' => 'This is a test error notification for system issues.',
        'type' => 'error',
        'category' => 'system'
    ]
];

$created_count = 0;

foreach ($test_notifications as $notification) {
    if ($notificationSystem->createNotification(
        $user['id'],
        $notification['title'],
        $notification['message'],
        $notification['type'],
        $notification['category']
    )) {
        $created_count++;
    }
}

echo "<p><strong>Created $created_count test notifications</strong></p>";

// Also trigger low stock check to create real notifications
echo "<hr>";
echo "<h2>📦 Low Stock Check</h2>";

$low_stock_count = $notificationSystem->checkLowStockNotifications();
echo "<p><strong>Low stock notifications created:</strong> $low_stock_count</p>";

// Show current count
        $unread_count = $notificationSystem->getUnreadCount($user['id']);
echo "<p><strong>Total unread notifications:</strong> $unread_count</p>";
        
echo "<hr>";
echo "<p><a href='notification_center.php'>📬 View Notifications</a></p>";
echo "<p><a href='dashboard.php'>🏠 Back to Dashboard</a></p>";
        
if ($unread_count > 0) {
    echo "<p>✅ <strong>Notifications are now working! You should see $unread_count unread notifications.</strong></p>";
} else {
    echo "<p>⚠️ <strong>No notifications were created. Check the system logs for errors.</strong></p>";
}
?>
