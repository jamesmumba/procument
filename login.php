<?php
/**
 * Login Page
 * Handles user authentication
 */

require_once 'config/config.php';
require_once 'auth/auth.php';

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } else {
            $auth = new Auth();
            if ($auth->login($username, $password)) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = 'Invalid username or password.';
            }
        }
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $userData = [
            'username' => sanitizeInput($_POST['reg_username']),
            'email' => sanitizeInput($_POST['reg_email']),
            'password' => $_POST['reg_password'],
            'first_name' => sanitizeInput($_POST['reg_first_name']),
            'last_name' => sanitizeInput($_POST['reg_last_name']),
            'role_id' => 4 // Default to inventory_manager role
        ];
        
        // Validate input
        if (empty($userData['username']) || empty($userData['email']) || empty($userData['password']) || 
            empty($userData['first_name']) || empty($userData['last_name'])) {
            $error_message = 'Please fill in all fields.';
        } elseif ($userData['password'] !== $_POST['reg_confirm_password']) {
            $error_message = 'Passwords do not match.';
        } elseif (strlen($userData['password']) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } else {
            $auth = new Auth();
            $result = $auth->register($userData);
            
            if ($result['success']) {
                $success_message = 'Registration successful! Please login.';
            } else {
                $error_message = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary"><?php echo APP_NAME; ?></h2>
                        <p class="text-muted">Sign in to your account</p>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-house me-1"></i>Back to Home
                        </a>
                    </div>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <div id="loginForm">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="login" value="1">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                                </button>
                            </div>
                        </form>
                        
                        
                    </div>

                    
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Registration disabled
    </script>
</body>
</html>
