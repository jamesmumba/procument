<?php
/**
 * Authentication API Endpoints
 */

require_once '../auth/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            // Login endpoint
            if (!isset($input['username']) || !isset($input['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Username and password required']);
                exit;
            }
            
            $auth = new Auth();
            if ($auth->login($input['username'], $input['password'])) {
                $user = getCurrentUser();
                echo json_encode([
                    'success' => true,
                    'user' => $user,
                    'message' => 'Login successful'
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
        } elseif ($action === 'logout') {
            // Logout endpoint
            $auth = new Auth();
            $auth->logout();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        } elseif ($action === 'register') {
            // Registration endpoint
            if (!isset($input['username']) || !isset($input['password']) || !isset($input['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Required fields missing']);
                exit;
            }
            
            $auth = new Auth();
            $result = $auth->register($input);
            
            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
        }
        break;
        
    case 'GET':
        if ($action === 'user') {
            // Get current user info
            if (!isLoggedIn()) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                exit;
            }
            
            echo json_encode(['user' => getCurrentUser()]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Action not found']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>

