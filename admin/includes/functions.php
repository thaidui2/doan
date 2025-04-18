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
function hasPermission($permission) {
    global $admin_level, $conn;
    
    // Trong hệ thống mới, admin là loại user = 2, quản lý = 1
    if ($permission == 'admin' && $admin_level == 2) {
        return true;
    }
    
    if ($permission == 'manager' && ($admin_level >= 1)) {
        return true;
    }
    
    // Các quyền khác có thể kiểm tra từ bảng quyen_han nếu cần
    if (isset($_SESSION['admin_id'])) {
        $admin_id = $_SESSION['admin_id'];
        $query = $conn->prepare("SELECT * FROM quyen_han WHERE id_user = ? AND module = ? AND quyen = 'view'");
        $module = explode('_', $permission)[0]; // Lấy module từ tên quyền (vd: product_edit -> product)
        $query->bind_param("is", $admin_id, $module);
        $query->execute();
        $result = $query->get_result();
        return $result->num_rows > 0;
    }
    
    return false;
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
 * Ghi log hoạt động 
 */
function logAdminActivity($conn, $admin_id, $action_type, $target_type, $target_id, $details) {
    // Kiểm tra admin_id có tồn tại không
    if ($admin_id === null) {
        // Lấy admin ID từ session nếu có
        if (isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
        } else {
            // Gán giá trị mặc định nếu không có
            $admin_id = 0;
        }
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    // Thay đổi tên bảng thành nhat_ky và tên các cột cho phù hợp
    $query = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $query->bind_param("ississ", $admin_id, $action_type, $target_type, $target_id, $details, $ip_address);
    return $query->execute();
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
