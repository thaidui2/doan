<?php
// Set proper content type
header('Content-Type: application/json');

// Include database connection
require_once '../../config/database.php';

// Include authentication check 
require_once '../includes/auth_check.php';

// Check if we have a valid request
if (!isset($_GET['staff_id']) || !is_numeric($_GET['staff_id'])) {
    echo json_encode(['error' => 'Invalid staff ID']);
    exit;
}

$staff_id = (int)$_GET['staff_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build date filter condition
$date_filter = '';
switch ($filter) {
    case 'today':
        $date_filter = "AND DATE(ngay_tao) = CURDATE()";
        break;
    case 'week':
        $date_filter = "AND ngay_tao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_filter = "AND ngay_tao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    default:
        $date_filter = ""; // All time
}

try {
    // Get staff info first
    $staff_stmt = $conn->prepare("SELECT ten FROM users WHERE id = ? AND loai_user > 0");
    $staff_stmt->execute([$staff_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo json_encode(['error' => 'Nhân viên không tồn tại']);
        exit;
    }
    
    // Get activity logs for this staff
    $sql = "SELECT * FROM nhat_ky 
            WHERE id_user = ? $date_filter
            ORDER BY ngay_tao DESC 
            LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$staff_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format logs for display
    $formatted_logs = [];
    foreach ($logs as $log) {
        $formatted_logs[] = [
            'id' => $log['id'],
            'action' => getActionLabel($log['hanh_dong']),
            'object_type' => getObjectTypeLabel($log['doi_tuong_loai']),
            'object_id' => $log['doi_tuong_id'],
            'details' => $log['chi_tiet'],
            'ip' => $log['ip_address'],
            'date' => date('d/m/Y H:i:s', strtotime($log['ngay_tao']))
        ];
    }
    
    echo json_encode([
        'staff' => $staff,
        'logs' => $formatted_logs,
        'count' => count($logs)
    ]);
    exit;
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Helper functions for activity logs
function getActionLabel($action) {
    $labels = [
        'create' => 'Thêm mới',
        'update' => 'Cập nhật',
        'delete' => 'Xóa',
        'login' => 'Đăng nhập',
        'logout' => 'Đăng xuất',
        'view' => 'Xem',
        'hide' => 'Ẩn',
        'show' => 'Hiển thị',
        'feature' => 'Đặt nổi bật',
        'unfeature' => 'Bỏ nổi bật',
        'lock' => 'Khóa',
        'unlock' => 'Mở khóa',
        'disable' => 'Vô hiệu hóa',
        'reset_password' => 'Đặt lại mật khẩu',
        'update_status' => 'Cập nhật trạng thái'
    ];
    
    return isset($labels[$action]) ? $labels[$action] : $action;
}

function getObjectTypeLabel($type) {
    $labels = [
        'product' => 'Sản phẩm',
        'category' => 'Danh mục',
        'order' => 'Đơn hàng',
        'customer' => 'Khách hàng',
        'admin' => 'Nhân viên',
        'brand' => 'Thương hiệu',
        'review' => 'Đánh giá',
        'promotion' => 'Khuyến mãi',
        'settings' => 'Cài đặt',
        'return' => 'Hoàn trả',
        'size' => 'Kích thước',
        'color' => 'Màu sắc'
    ];
    
    return isset($labels[$type]) ? $labels[$type] : $type;
}
