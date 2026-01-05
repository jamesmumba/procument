<?php
/**
 * API Router
 * Routes API requests to appropriate endpoints
 */

require_once '../config/config.php';
require_once '../auth/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the endpoint from URL
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Remove 'api' from path parts
if ($path_parts[0] === 'api') {
    array_shift($path_parts);
}

$endpoint = $path_parts[0] ?? '';
$action = $path_parts[1] ?? '';

// Route to appropriate API endpoint
switch ($endpoint) {
    case 'auth':
        include 'auth.php';
        break;
    case 'vendors':
        include 'vendors.php';
        break;
    case 'requisitions':
        include 'requisitions.php';
        break;
    case 'approvals':
        include 'approvals.php';
        break;
    case 'inventory':
        include 'inventory.php';
        break;
    case 'analytics':
        include 'analytics.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>

