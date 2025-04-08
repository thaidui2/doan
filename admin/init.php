<?php
/**
 * Initialization file for the admin section
 * Includes necessary files in the correct order and sets up the environment
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define constants
define('ADMIN_ROOT', __DIR__);
define('SITE_ROOT', dirname(__DIR__));

// Include helper functions first
require_once(ADMIN_ROOT . '/includes/functions.php');

// Include database connection
require_once(SITE_ROOT . '/config/config.php');

// Setup error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in (unless on the login page)
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page !== 'login.php' && 
    (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}
