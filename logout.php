<?php
/**
 * Logout Page
 * Handles user logout
 */

require_once 'config/config.php';
require_once 'auth/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: login.php?message=logged_out');
exit;
?>

