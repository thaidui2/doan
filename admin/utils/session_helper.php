<?php
/**
 * Session helper functions for the admin panel
 */

// Function to store a flash message in session
function setFlashMessage($type, $message) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

// Function to display and then clear flash message
function displayFlashMessage() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        echo '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">
            ' . $message['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        
        // Clear the message after displaying it
        unset($_SESSION['flash_message']);
    }
}

// Function to check if there is a flash message
function hasFlashMessage() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['flash_message']);
}

// Function to track and limit failed login attempts
function trackLoginAttempt($success = false, $username = '') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Initialize if not exists
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = null;
    }
    
    // If successful login, reset attempts
    if ($success) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = null;
        return true;
    }
    
    // Increment failed attempts
    $_SESSION['login_attempts']++;
    
    // Check if account should be temporarily locked
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_locked_until'] = time() + 300; // Lock for 5 minutes
        
        // Log the attempt for security
        $log_message = date('Y-m-d H:i:s') . " - Failed login attempt limit reached for username: " . $username . 
                      " - IP: " . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
        file_put_contents(__DIR__ . '/../logs/login_attempts.log', $log_message, FILE_APPEND);
        
        return false; // Locked
    }
    
    return true; // Not locked
}

// Function to check if login is locked
function isLoginLocked() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time()) {
        return $_SESSION['login_locked_until'] - time(); // Return seconds remaining
    }
    
    return false; // Not locked
}
