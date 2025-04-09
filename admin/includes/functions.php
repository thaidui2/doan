<?php
/**
 * Helper functions for the admin section
 */

/**
 * Kiểm tra xem menu hiện tại có đang được active không
 * @param string|array $menuNames Tên file menu hoặc mảng các tên file
 * @return bool True nếu menu đang được active, False nếu không
 */
function isActiveMenu($menuNames) {
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (is_array($menuNames)) {
        return in_array($current_page, $menuNames);
    } else {
        return $current_page === $menuNames;
    }
}

/**
 * Format currency values consistently
 * 
 * @param float $amount The amount to format
 * @return string Formatted currency string
 */
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . '₫';
}

/**
 * Generate pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param string $url_params Additional URL parameters to preserve
 * @return string HTML for pagination
 */
function paginationLinks($current_page, $total_pages, $url_params = '') {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';
    
    // Previous button
    $html .= '<li class="page-item ' . ($current_page <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="?page=' . ($current_page - 1) . $url_params . '" aria-label="Previous">';
    $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        $html .= '<li class="page-item ' . ($current_page == $i ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="?page=' . $i . $url_params . '">' . $i . '</a></li>';
    }
    
    // Next button
    $html .= '<li class="page-item ' . ($current_page >= $total_pages ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="?page=' . ($current_page + 1) . $url_params . '" aria-label="Next">';
    $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Hàm kiểm tra quyền hạn dựa vào cấp bậc
 * @param string $permission_code Mã quyền cần kiểm tra
 * @return bool True nếu có quyền, False nếu không
 */
function hasPermission($permission_code) {
    // Kiểm tra ID admin đặc biệt (từ admin_helpers.php)
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == 6) {
        return true;
    }
    
    // Kiểm tra cấp bậc admin (từ permissions.php và admin_helpers.php)
    if (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] >= 2) {
        return true;
    }
    
    // Kiểm tra quyền từ session (từ admin_helpers.php)
    if (isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])) {
        return in_array($permission_code, $_SESSION['admin_permissions']);
    }
    
    // Kiểm tra quyền từ database (từ permissions.php)
    $permissions = getAdminPermissions();
    return in_array($permission_code, $permissions);
}

/**
 * Kiểm tra quyền và chuyển hướng nếu không có
 */
function checkPermissionRedirect($permission_code, $redirect_url = 'index.php') {
    if (!hasPermission($permission_code)) {
        $_SESSION['error_message'] = 'Bạn không có quyền thực hiện thao tác này.';
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Ghi log hoạt động của admin
 */
function logAdminActivity($conn, $admin_id, $action_type, $target_type, $target_id, $details) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $query = $conn->prepare("INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $query->bind_param("ississ", $admin_id, $action_type, $target_type, $target_id, $details, $ip_address);
    return $query->execute();
}

/**
 * Log a specific action
 */
function logAction($action, $description) {
    global $conn;
    $admin_id = $_SESSION['admin_id'];
    logAdminActivity($conn, $admin_id, $action, 'promo', 0, $description);
}
