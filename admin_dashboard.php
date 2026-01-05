<?php
/**
 * Enhanced Admin Dashboard
 * Provides comprehensive system administration controls
 */

require_once 'config/config.php';
require_once 'auth/auth.php';

// Check authentication and admin permissions
requireLogin();
requirePermission('manage_users');

$db = new Database();
$conn = $db->getConnection();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $role_id = (int)$_POST['role_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate input
            if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $error = 'All fields are required';
                break;
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format';
                break;
            }
            
            if (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
                break;
            }
            
            // Check if username or email already exists
            $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
                break;
            }
            
            // Create user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$username, $email, $password_hash, $first_name, $last_name, $role_id, $is_active])) {
                $user_id = $conn->lastInsertId();
                logAudit('create_user', 'users', $user_id, null, [
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role_id' => $role_id,
                    'is_active' => $is_active
                ]);
                $message = 'User created successfully!';
            } else {
                $error = 'Failed to create user';
            }
            break;
            
        case 'update_user':
            $user_id = (int)$_POST['user_id'];
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $role_id = (int)$_POST['role_id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Get old values for audit
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $old_user = $stmt->fetch();
            
            if (!$old_user) {
                $error = 'User not found';
                break;
            }
            
            // Update user
            $sql = "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role_id = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$username, $email, $first_name, $last_name, $role_id, $is_active, $user_id])) {
                logAudit('update_user', 'users', $user_id, $old_user, [
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role_id' => $role_id,
                    'is_active' => $is_active
                ]);
                $message = 'User updated successfully!';
            } else {
                $error = 'Failed to update user';
            }
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            // Prevent deleting self
            if ($user_id == $user['id']) {
                $error = 'Cannot delete your own account';
                break;
            }
            
            // Get user info for audit
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $user_to_delete = $stmt->fetch();
            
            if (!$user_to_delete) {
                $error = 'User not found';
                break;
            }
            
            // Delete user
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$user_id])) {
                logAudit('delete_user', 'users', $user_id, $user_to_delete, null);
                $message = 'User deleted successfully!';
            } else {
                $error = 'Failed to delete user';
            }
            break;
            
        case 'update_role':
            $role_id = (int)$_POST['role_id'];
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $permissions = json_encode($_POST['permissions'] ?? []);
            
            // Get old values for audit
            $sql = "SELECT * FROM roles WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$role_id]);
            $old_role = $stmt->fetch();
            
            if (!$old_role) {
                $error = 'Role not found';
                break;
            }
            
            // Update role
            $sql = "UPDATE roles SET name = ?, description = ?, permissions = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$name, $description, $permissions, $role_id])) {
                logAudit('update_role', 'roles', $role_id, $old_role, [
                    'name' => $name,
                    'description' => $description,
                    'permissions' => $permissions
                ]);
                $message = 'Role updated successfully!';
            } else {
                $error = 'Failed to update role';
            }
            break;
    }
}

// Get system statistics
$stats = [];

try {
    // User statistics
    $sql = "SELECT COUNT(*) as total_users FROM users";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    $sql = "SELECT COUNT(*) as active_users FROM users WHERE is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['active_users'] = $stmt->fetchColumn();
    
    // System activity
    $sql = "SELECT COUNT(*) as total_requisitions FROM purchase_requisitions";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['total_requisitions'] = $stmt->fetchColumn();
    
    $sql = "SELECT COUNT(*) as total_orders FROM purchase_orders";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['total_orders'] = $stmt->fetchColumn();
    
    $sql = "SELECT COUNT(*) as total_vendors FROM vendors WHERE is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['total_vendors'] = $stmt->fetchColumn();
    
    // Recent activity
    $sql = "SELECT al.*, u.username 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats['recent_activity'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'total_requisitions' => 0,
        'total_orders' => 0,
        'total_vendors' => 0,
        'recent_activity' => []
    ];
}

// Get all users
$sql = "SELECT u.*, r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        ORDER BY u.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll();

// Get all roles
$sql = "SELECT * FROM roles ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$roles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            background: #f8f9fa;
            min-height: 100vh;
            border-right: 1px solid #dee2e6;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .admin-nav {
            background: #2c3e50;
            color: white;
        }
        .admin-nav .nav-link {
            color: #ecf0f1;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            margin: 0.25rem 0;
        }
        .admin-nav .nav-link:hover {
            background: #34495e;
            color: white;
        }
        .admin-nav .nav-link.active {
            background: #3498db;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <div class="sidebar">
                    <div class="p-3">
                        <h5 class="text-center mb-4">
                            <i class="bi bi-shield-check me-2"></i>Admin Panel
                        </h5>
                        <nav class="admin-nav">
                            <a class="nav-link active" href="admin_dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house me-2"></i>Main Dashboard
                            </a>
                            <a class="nav-link" href="?tab=users">
                                <i class="bi bi-people me-2"></i>User Management
                            </a>
                            <a class="nav-link" href="?tab=roles">
                                <i class="bi bi-person-badge me-2"></i>Role Management
                            </a>
                            <hr>
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-shield-check me-2"></i>System Administration</h2>
                        <div class="text-muted">
                            Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
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

                    <?php
                    $tab = $_GET['tab'] ?? 'dashboard';
                    ?>

                    <?php if ($tab == 'dashboard'): ?>
                    <!-- Dashboard Overview -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-white-50">Total Users</h6>
                                        <h3 class="fw-bold"><?php echo $stats['total_users']; ?></h3>
                                    </div>
                                    <i class="bi bi-people fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card success">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-white-50">Active Users</h6>
                                        <h3 class="fw-bold"><?php echo $stats['active_users']; ?></h3>
                                    </div>
                                    <i class="bi bi-person-check fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-white-50">Total Requisitions</h6>
                                        <h3 class="fw-bold"><?php echo $stats['total_requisitions']; ?></h3>
                                    </div>
                                    <i class="bi bi-file-earmark-text fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card info">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-white-50">Total Orders</h6>
                                        <h3 class="fw-bold"><?php echo $stats['total_orders']; ?></h3>
                                    </div>
                                    <i class="bi bi-receipt fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent System Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($stats['recent_activity'])): ?>
                                <p class="text-muted">No recent activity</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Table</th>
                                                <th>Record ID</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                                            <tr>
                                                <td><?php echo formatDate($activity['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['table_name']); ?></td>
                                                <td><?php echo $activity['record_id']; ?></td>
                                                <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($tab == 'users'): ?>
                    <!-- User Management -->
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-people me-2"></i>User Management</h5>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                    <i class="bi bi-plus-circle me-2"></i>Create User
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                                            <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($u['role_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($u['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($u['id'] != $user['id']): ?>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($tab == 'roles'): ?>
                    <!-- Role Management -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Role Management</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Role Name</th>
                                            <th>Description</th>
                                            <th>Permissions</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $role): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($role['name']); ?></td>
                                            <td><?php echo htmlspecialchars($role['description']); ?></td>
                                            <td>
                                                <?php 
                                                $permissions = json_decode($role['permissions'], true);
                                                if (isset($permissions['all']) && $permissions['all']) {
                                                    echo '<span class="badge bg-success">All Permissions</span>';
                                                } else {
                                                    $permission_count = count($permissions);
                                                    echo '<span class="badge bg-info">' . $permission_count . ' Permissions</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required minlength="6">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active User
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" id="edit_username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" id="edit_role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active User
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editRoleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="role_id" id="edit_role_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role Name</label>
                                    <input type="text" class="form-control" name="name" id="edit_role_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description" id="edit_role_description">
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Permissions</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="perm_all" name="permissions[all]" value="1">
                                        <label class="form-check-label" for="perm_all">All Permissions</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_manage_users" name="permissions[manage_users]" value="1">
                                        <label class="form-check-label" for="perm_manage_users">Manage Users</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_manage_vendors" name="permissions[manage_vendors]" value="1">
                                        <label class="form-check-label" for="perm_manage_vendors">Manage Vendors</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_view_inventory" name="permissions[view_inventory]" value="1">
                                        <label class="form-check-label" for="perm_view_inventory">View Inventory</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_create_requisition" name="permissions[create_requisition]" value="1">
                                        <label class="form-check-label" for="perm_create_requisition">Create Requisition</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_view_requisitions" name="permissions[view_requisitions]" value="1">
                                        <label class="form-check-label" for="perm_view_requisitions">View Requisitions</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_approve_requisition" name="permissions[approve_requisition]" value="1">
                                        <label class="form-check-label" for="perm_approve_requisition">Approve Requisition</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_manage_notifications" name="permissions[manage_notifications]" value="1">
                                        <label class="form-check-label" for="perm_manage_notifications">Manage Notifications</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_create_po" name="permissions[create_po]" value="1">
                                        <label class="form-check-label" for="perm_create_po">Create Purchase Orders</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_approve_inventory_issues" name="permissions[approve_inventory_issues]" value="1">
                                        <label class="form-check-label" for="perm_approve_inventory_issues">Approve Inventory Issues</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_approve_stock_transfers" name="permissions[approve_stock_transfers]" value="1">
                                        <label class="form-check-label" for="perm_approve_stock_transfers">Approve Stock Transfers</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input perm" type="checkbox" id="perm_view_analytics" name="permissions[view_analytics]" value="1">
                                        <label class="form-check-label" for="perm_view_analytics">View Analytics</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_role_id').value = user.role_id;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
        
        function editRole(role) {
            // Ensure permissions is an object
            let perms = {};
            try {
                if (typeof role.permissions === 'string') {
                    perms = JSON.parse(role.permissions || '{}') || {};
                } else {
                    perms = role.permissions || {};
                }
            } catch (e) {
                perms = {};
            }

            document.getElementById('edit_role_id').value = role.id;
            document.getElementById('edit_role_name').value = role.name || '';
            document.getElementById('edit_role_description').value = role.description || '';

            // Reset all checkboxes first
            document.querySelectorAll('#editRoleModal .form-check-input').forEach(cb => { cb.checked = false; });

            // Apply permissions
            Object.keys(perms).forEach(key => {
                const el = document.getElementById('perm_' + key);
                if (el) { el.checked = !!perms[key]; }
            });

            // Handle ALL toggling
            document.getElementById('perm_all').addEventListener('change', function() {
                const checked = this.checked;
                document.querySelectorAll('#editRoleModal .perm').forEach(cb => { cb.checked = checked; });
            }, { once: true });

            new bootstrap.Modal(document.getElementById('editRoleModal')).show();
        }
    </script>
</body>
</html>
