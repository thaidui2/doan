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
    global $conn;
    
    // Debug: Log để kiểm tra
    error_log("Đang kiểm tra quyền: " . $permission_code);
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    // Super Admin luôn có tất cả quyền
    $admin_id = $_SESSION['admin_id'];
    $check_admin = $conn->prepare("SELECT cap_bac FROM admin WHERE id_admin = ?");
    $check_admin->bind_param("i", $admin_id);
    $check_admin->execute();
    $admin_result = $check_admin->get_result();
    
    if ($admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
        if ($admin_data['cap_bac'] == 2) { // Cấp 2 là Super Admin
            return true;
        }
    }
    
    // Kiểm tra quyền cụ thể từ database
    $query = "
        SELECT p.ma_permission 
        FROM permissions p
        JOIN role_permissions rp ON p.id_permission = rp.id_permission
        JOIN admin_roles ar ON rp.id_role = ar.id_role
        WHERE ar.id_admin = ? AND p.ma_permission = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $admin_id, $permission_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug: Log để kiểm tra kết quả
    error_log("Kết quả kiểm tra quyền " . $permission_code . ": " . ($result->num_rows > 0 ? "Có quyền" : "Không có quyền"));
    
    return $result->num_rows > 0;
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
    // Kiểm tra admin_id có tồn tại không
    if ($admin_id === null) {
        // Lấy admin ID từ session nếu có
        if (isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
        } else if (isset($_SESSION['id_admin'])) {
            // Kiểm tra tên biến thay thế nếu có
            $admin_id = $_SESSION['id_admin'];
        } else {
            // Gán giá trị mặc định nếu không có
            $admin_id = 0; // hoặc giá trị admin ID mặc định khác
        }
    }
    
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
    
    $admin_id = null;
    if (isset($_SESSION['admin_id'])) {
        $admin_id = $_SESSION['admin_id'];
    } else if (isset($_SESSION['id_admin'])) {
        $admin_id = $_SESSION['id_admin']; 
    }
    
    logAdminActivity($conn, $admin_id, $action, 'promo', 0, $description);
}

/**
 * Lấy thông tin trạng thái sản phẩm dưới dạng mảng
 * @param int $status_code Mã trạng thái
 * @return array Mảng chứa text và class CSS
 */
function getProductStatusInfo($status_code) {
    $status_labels = [
        0 => ['text' => 'Đang ẩn', 'class' => 'warning text-dark', 'icon' => 'bi-eye-slash'],
        1 => ['text' => 'Đang hiển thị', 'class' => 'success', 'icon' => 'bi-eye'],
        2 => ['text' => 'Hết hàng', 'class' => 'danger', 'icon' => 'bi-x-circle'],
        3 => ['text' => 'Đang hoàn trả', 'class' => 'info', 'icon' => 'bi-arrow-return-left'],
        4 => ['text' => 'Ngừng kinh doanh', 'class' => 'secondary', 'icon' => 'bi-slash-circle']
    ];
    
    return $status_labels[$status_code] ?? ['text' => 'Không xác định', 'class' => 'secondary', 'icon' => 'bi-question-circle'];
}
