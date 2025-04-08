<?php
/**
 * Authentication Handler for Admin Panel
 * Use this to protect admin pages with a simple include
 */

// Include functions if needed
include_once(__DIR__ . '/includes/functions.php');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Check admin permissions if needed
function checkPermission($required_level) {
    $admin_level = $_SESSION['admin_level'] ?? 1; // Default to lowest level
    return $admin_level >= $required_level;
}

// Update last activity timestamp to track active sessions
$_SESSION['last_activity'] = time();

// Session timeout check (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // Session expired, log out the user
    session_unset();
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}
