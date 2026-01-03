<?php
/**
 * DAYFLOW HRMS - Logout
 * File: auth/logout.php
 * 
 * This file should be created separately as auth/logout.php
 */
?>

<!-- Save this as a separate file: auth/logout.php -->
<?php
// auth/logout.php
require_once '../config/db.php';

// Destroy session
session_start();
session_unset();
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Redirect to login
redirect('auth/login.php');
?>