<?php
/**
 * Kiểm tra xem menu có đang active không
 * 
 * @param string|array $menu Tên file menu hoặc mảng các tên file
 * @return bool
 */
function isActiveMenu($menu) {
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (is_array($menu)) {
        return in_array($current_page, $menu);
    }
    
    return $current_page == $menu;
}

/**
 * Kiểm tra quyền của người dùng
 * 
 * @param string $permission Mã quyền cần kiểm tra
 * @return bool
 */
function hasPermission($permission) {
    // Nếu người dùng là admin cấp cao nhất (id_admin = 6 hoặc cấp bậc = 2)
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == 6) {
        return true;
    }
    
    if (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] == 2) {
        return true;
    }
    
    // Nếu có biến permission được set trong session
    if (isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])) {
        return in_array($permission, $_SESSION['admin_permissions']);
    }
    
    return false;
}