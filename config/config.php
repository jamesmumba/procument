<?php
/**
 * Application Configuration
 * Main configuration file for the Procurement Platform
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'Procurement Platform');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/procurement');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', 'uploads/');

// Pagination settings
define('ITEMS_PER_PAGE', 20);

// Date format
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('DATE_DISPLAY_FORMAT', 'M d, Y');

// Approval behavior
// Set to true to allow automatic approvals when rules specify {"auto_approve": true}
// Defaults to false for safety, requiring human approval
if (!defined('ENABLE_AUTO_APPROVE')) {
    define('ENABLE_AUTO_APPROVE', true);
}

// Include database configuration
require_once __DIR__ . '/database.php';

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'first_name' => $_SESSION['first_name'],
        'last_name' => $_SESSION['last_name'],
        'role_id' => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name']
    ];
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Admin has all permissions
    if ($user['role_id'] == 1) {
        return true;
    }
    
    // Get role permissions from session
    $permissions = $_SESSION['permissions'] ?? [];
    
    // Special case: Inventory access restricted to Admin and Inventory Manager only
    if ($permission === 'view_inventory') {
        return strtolower($user['role_name']) === 'inventory_manager';
    }
    
    // Special case: Notification management access
    if ($permission === 'manage_notifications') {
        return in_array($user['role_id'], [1, 2, 4]); // Admin, CPO, Inventory Manager
    }

    if (isset($permissions['all']) && $permissions['all'] === true) {
        return true;
    }
    
    return isset($permissions[$permission]) && $permissions[$permission] === true;
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require permission
 */
function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('Access denied. You do not have permission to perform this action.');
    }
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'K' . number_format((float)$amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = DATE_DISPLAY_FORMAT) {
    return date($format, strtotime($date));
}

/**
 * Log audit trail
 */
function logAudit($action, $table_name, $record_id, $old_values = null, $new_values = null) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $ip_address,
            $user_agent
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Generate unique number
 */
function generateUniqueNumber($prefix, $table, $column) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        do {
            $number = $prefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$number]);
            $count = $stmt->fetchColumn();
        } while ($count > 0);
        
        return $number;
    } catch (Exception $e) {
        error_log("Generate unique number error: " . $e->getMessage());
        return $prefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

