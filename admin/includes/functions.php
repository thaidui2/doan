<?php
/**
 * Functions file for admin panel
 */

/**
 * Set flash message to display on next page load
 */
function set_flash_message($type, $message) {
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display flash message if exists and clear it
 */
function display_flash_message() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        $alert_class = 'alert-info';
        if ($type === 'success') $alert_class = 'alert-success';
        if ($type === 'error') $alert_class = 'alert-danger';
        if ($type === 'warning') $alert_class = 'alert-warning';
        
        echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
        echo $message;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        unset($_SESSION['flash_message']);
    }
}

/**
 * Log activity in the system
 */
function log_activity($action, $object_type, $object_id, $details = '') {
    global $conn;
    
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $user_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $object_type, $object_id, $details, $ip_address]);
}

/**
 * Format date to display in the UI
 */
function format_date($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Sanitize input to prevent XSS
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
