<?php
/**
 * Authentication Class
 * Handles user authentication and authorization
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT u.*, r.name as role_name, r.permissions 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.username = ? AND u.is_active = 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['permissions'] = json_decode($user['permissions'], true);
                $_SESSION['last_activity'] = time();
                
                // Log login
                logAudit('login', 'users', $user['id'], null, ['username' => $username]);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isLoggedIn()) {
            logAudit('logout', 'users', $_SESSION['user_id']);
        }
        
        // Clear session
        session_unset();
        session_destroy();
        
        return true;
    }
    
    /**
     * Register new user
     */
    public function register($userData) {
        try {
            $conn = $this->db->getConnection();
            
            // Check if username or email already exists
            $sql = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userData['username'], $userData['email']]);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $userData['username'],
                $userData['email'],
                $password_hash,
                $userData['first_name'],
                $userData['last_name'],
                $userData['role_id']
            ]);
            
            if ($result) {
                $user_id = $this->db->lastInsertId();
                logAudit('register', 'users', $user_id, null, $userData);
                return ['success' => true, 'user_id' => $user_id];
            }
            
            return ['success' => false, 'message' => 'Registration failed'];
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            $conn = $this->db->getConnection();
            
            // Verify current password
            $sql = "SELECT password_hash FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$new_password_hash, $user_id]);
            
            if ($result) {
                logAudit('change_password', 'users', $user_id);
                return ['success' => true];
            }
            
            return ['success' => false, 'message' => 'Password change failed'];
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password change failed'];
        }
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if (isLoggedIn() && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
        }
        return true;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($user_id) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT u.*, r.name as role_name, r.permissions 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users
     */
    public function getAllUsers() {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT u.*, r.name as role_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    ORDER BY u.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update user
     */
    public function updateUser($user_id, $userData) {
        try {
            $conn = $this->db->getConnection();
            
            // Get old values for audit
            $old_user = $this->getUserById($user_id);
            
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $userData['first_name'],
                $userData['last_name'],
                $userData['email'],
                $userData['role_id'],
                $userData['is_active'],
                $user_id
            ]);
            
            if ($result) {
                logAudit('update_user', 'users', $user_id, $old_user, $userData);
                return ['success' => true];
            }
            
            return ['success' => false, 'message' => 'User update failed'];
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'User update failed'];
        }
    }
}
?>

