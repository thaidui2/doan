<?php
/**
 * Hệ thống quản lý quyền hạn (đã đơn giản hóa - chỉ Admin và Quản lý)
 */

// Chỉ định nghĩa hàm nếu chưa tồn tại
if (!function_exists('hasPermission')) {
    function hasPermission($permission_code) {
        // Admins có tất cả các quyền
        if (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] >= 2) {
            return true;
        }
        
        // Lấy danh sách quyền
        $permissions = getAdminPermissions();
        
        return in_array($permission_code, $permissions);
    }
}

if (!function_exists('checkPermissionRedirect')) {
    function checkPermissionRedirect($permission_code, $redirect_url = 'index.php') {
        if (!hasPermission($permission_code)) {
            $_SESSION['error_message'] = 'Bạn không có quyền thực hiện thao tác này.';
            header("Location: $redirect_url");
            exit();
        }
    }
}

if (!function_exists('getAdminPermissions')) {
    /**
     * Lấy danh sách quyền của admin dựa theo cấp bậc
     * @return array Mảng các mã quyền
     */
    function getAdminPermissions($admin_id = null) {
        global $conn;
        
        if (!$admin_id && isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
        }
        
        if (!$admin_id) {
            return [];
        }
        
        // Kiểm tra cache trong session
        if (isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])) {
            return $_SESSION['admin_permissions'];
        }
        
        // Nếu không có cache, lấy cấp bậc từ database
        $query = "SELECT cap_bac FROM admin WHERE id_admin = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [];
        }
        
        $admin = $result->fetch_assoc();
        $cap_bac = $admin['cap_bac'];
        
        // Phân quyền dựa vào cấp bậc
        $permissions = [];
        
        // Admin cấp cao (cấp 2 trở lên) - có tất cả quyền
        if ($cap_bac >= 2) {
            // Tất cả các quyền
            $admin_permissions = [
                'product_view', 'product_add', 'product_edit', 'product_delete',
                'category_view', 'category_add', 'category_edit', 'category_delete',
                'order_view', 'order_update_status', 'order_cancel', 'order_delete',
                'customer_view', 'customer_add', 'customer_edit', 'customer_delete', 'customer_toggle_status',
                'admin_view', 'admin_add', 'admin_edit', 'admin_delete',
                'role_view', 'role_add', 'role_edit', 'role_delete', 'permission_assign',
                'promo_view', 'promo_add', 'promo_edit', 'promo_delete',
                'report_view', 'report_export',
                'setting_manage', 'log_view'
            ];
            $permissions = array_merge($permissions, $admin_permissions);
        }
        // Quản lý (cấp 1) - quyền giới hạn
        else if ($cap_bac == 1) {
            $manager_permissions = [
                'product_view', 'product_add', 'product_edit',
                'category_view',
                'order_view', 'order_update_status',
                'customer_view',
                'promo_view',
                'report_view'
            ];
            $permissions = array_merge($permissions, $manager_permissions);
        }
        
        // Lưu vào session
        $_SESSION['admin_permissions'] = $permissions;
        
        return $permissions;
    }
}

if (!function_exists('getAdminRoles')) {
    /**
     * Lấy danh sách vai trò của admin
     * @param int $admin_id ID của admin
     * @return array Mảng các vai trò của admin
     */
    function getAdminRoles($admin_id = null) {
        global $conn;
        
        if (!$admin_id && isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
        }
        
        if (!$admin_id) {
            return [];
        }
        
        // Kiểm tra cache trong session
        if (isset($_SESSION['admin_roles']) && is_array($_SESSION['admin_roles'])) {
            return $_SESSION['admin_roles'];
        }
        
        // Lấy vai trò từ database trước
        $query = "
            SELECT r.* 
            FROM roles r
            JOIN admin_roles ar ON r.id_role = ar.id_role
            WHERE ar.id_admin = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        
        // Nếu không có vai trò từ bảng admin_roles, lấy từ cấp bậc
        if (empty($roles)) {
            $query = "SELECT cap_bac FROM admin WHERE id_admin = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                
                // Chỉ phân loại thành 2 cấp: Admin và Quản lý
                if ($admin['cap_bac'] >= 2) {
                    $roles[] = ['id_role' => 1, 'ten_role' => 'Admin'];
                } else {
                    $roles[] = ['id_role' => 2, 'ten_role' => 'Quản lý'];
                }
            }
        }
        
        // Cache vào session
        $_SESSION['admin_roles'] = $roles;
        
        return $roles;
    }
}
?>
