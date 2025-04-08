<?php
/**
 * Hệ thống quản lý quyền hạn
 */

// Chỉ định nghĩa hàm nếu chúng chưa tồn tại

if (!function_exists('hasPermission')) {
    function hasPermission($permission_code) {
        // Super admins luôn có tất cả các quyền
        if (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] == 3) {
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
        
        // Quyền cơ bản (Cấp 1 - Quản lý)
        if ($cap_bac >= 1) {
            $basic_permissions = [
                'product_view', 'category_view', 'order_view', 
                'customer_view', 'report_view'
            ];
            $permissions = array_merge($permissions, $basic_permissions);
        }
        
        // Quyền mở rộng (Cấp 2 - Admin cấp cao)
        if ($cap_bac >= 2) {
            $advanced_permissions = [
                'product_add', 'product_edit', 'product_delete',
                'category_add', 'category_edit', 'category_delete',
                'order_update_status', 'order_cancel',
                'customer_edit', 'customer_toggle_status',
                'admin_view', 'admin_add', 'admin_edit'
            ];
            $permissions = array_merge($permissions, $advanced_permissions);
        }
        
        // Tất cả quyền (Cấp 3 - Super Admin)
        if ($cap_bac >= 3) {
            $super_permissions = [
                'admin_delete', 'setting_manage', 'log_view',
                'role_view', 'role_add', 'role_edit', 'role_delete',
                'permission_assign'
            ];
            $permissions = array_merge($permissions, $super_permissions);
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
        
        // Kiểm tra xem bảng admin_roles đã tồn tại chưa
        $table_check = $conn->query("SHOW TABLES LIKE 'admin_roles'");
        
        // Nếu bảng admin_roles không tồn tại, cấp quyền dựa trên cấp bậc
        if ($table_check->num_rows == 0) {
            $query = "SELECT cap_bac FROM admin WHERE id_admin = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return [];
            }
            
            $admin = $result->fetch_assoc();
            $roles = [];
            
            // Gán vai trò dựa vào cấp bậc
            switch ($admin['cap_bac']) {
                case 3: // Super Admin
                    $roles[] = ['id_role' => 0, 'ten_role' => 'Super Admin'];
                    break;
                case 2: // Admin cấp cao
                    $roles[] = ['id_role' => 1, 'ten_role' => 'Admin'];
                    break;
                case 1: // Quản lý
                    $roles[] = ['id_role' => 2, 'ten_role' => 'Quản lý'];
                    break;
                default:
                    $roles[] = ['id_role' => 3, 'ten_role' => 'Nhân viên'];
            }
            
            // Cache vào session
            $_SESSION['admin_roles'] = $roles;
            
            return $roles;
        }
        
        // Nếu bảng tồn tại, lấy vai trò từ database
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
        
        // Cache vào session
        $_SESSION['admin_roles'] = $roles;
        
        return $roles;
    }
}
?>
