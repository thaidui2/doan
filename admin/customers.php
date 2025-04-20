<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Xử lý lock/unlock tài khoản
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $user_id = $_GET['toggle_status'];
    
    // Kiểm tra user tồn tại và không phải admin
    $check_sql = "SELECT taikhoan, ten, trang_thai, loai_user FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // Không cho phép khóa tài khoản admin
        if ($user_data['loai_user'] > 0) {
            header('Location: customers.php?error=Không thể thay đổi trạng thái tài khoản quản trị');
            exit();
        }
        
        $current_status = $user_data['trang_thai'];
        $new_status = $current_status ? 0 : 1;
        $action = $new_status ? 'unlock' : 'lock';
        
        $update_sql = "UPDATE users SET trang_thai = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ii', $new_status, $user_id);
        
        if ($update_stmt->execute()) {
            // Ghi log
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, ?, 'customer', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Đã " . ($new_status ? 'mở khóa' : 'khóa') . " tài khoản khách hàng: " . $user_data['ten'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('isiss', $admin_id, $action, $user_id, $detail, $ip);
            $log_stmt->execute();
            
            header('Location: customers.php?success=Đã ' . ($new_status ? 'mở khóa' : 'khóa') . ' tài khoản thành công');
        } else {
            header('Location: customers.php?error=Không thể thay đổi trạng thái tài khoản');
        }
        exit();
    } else {
        header('Location: customers.php?error=Người dùng không tồn tại');
        exit();
    }
}

// Xử lý reset password
if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    $user_id = $_GET['reset_password'];
    
    // Kiểm tra user tồn tại
    $check_sql = "SELECT taikhoan, ten FROM users WHERE id = ? AND loai_user = 0";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $username = $user_data['taikhoan'];
        $new_password = $username . '@' . rand(1000, 9999);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_sql = "UPDATE users SET matkhau = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('si', $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            // Ghi log
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, 'reset_password', 'customer', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Đã đặt lại mật khẩu cho khách hàng: " . $user_data['ten'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('iiss', $admin_id, $user_id, $detail, $ip);
            $log_stmt->execute();
            
            header('Location: customers.php?success=Đã đặt lại mật khẩu thành công. Mật khẩu mới: ' . $new_password);
        } else {
            header('Location: customers.php?error=Không thể đặt lại mật khẩu');
        }
        exit();
    } else {
        header('Location: customers.php?error=Người dùng không tồn tại hoặc không phải là khách hàng');
        exit();
    }
}

// Xử lý xóa tài khoản
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    // Kiểm tra user tồn tại và không phải admin
    $check_sql = "SELECT taikhoan, ten, loai_user FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // Không cho phép xóa tài khoản admin
        if ($user_data['loai_user'] > 0) {
            header('Location: customers.php?error=Không thể xóa tài khoản quản trị');
            exit();
        }
        
        // Kiểm tra xem người dùng có đơn hàng không
        $check_orders_sql = "SELECT COUNT(*) as count FROM donhang WHERE id_user = ?";
        $check_orders_stmt = $conn->prepare($check_orders_sql);
        $check_orders_stmt->bind_param('i', $user_id);
        $check_orders_stmt->execute();
        $orders_count = $check_orders_stmt->get_result()->fetch_assoc()['count'];
        
        if ($orders_count > 0) {
            header('Location: customers.php?error=Không thể xóa tài khoản này vì có ' . $orders_count . ' đơn hàng liên quan');
            exit();
        }
        
        // Bắt đầu giao dịch
        $conn->begin_transaction();
        
        try {
            // Xóa các bản ghi liên quan
            $tables = [
                "giohang" => "id_user",
                "yeu_thich" => "id_user",
                "danhgia" => "id_user", 
                "users" => "id"
            ];
            
            foreach ($tables as $table => $column) {
                $delete_sql = "DELETE FROM $table WHERE $column = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param('i', $user_id);
                $delete_stmt->execute();
            }
            
            // Ghi log
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, 'delete', 'customer', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Xóa tài khoản khách hàng: " . $user_data['ten'] . " (ID: $user_id)";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('iiss', $admin_id, $user_id, $detail, $ip);
            $log_stmt->execute();
            
            // Hoàn tất giao dịch
            $conn->commit();
            
            header('Location: customers.php?success=Đã xóa tài khoản thành công');
            exit();
        } catch (Exception $e) {
            // Rollback nếu có lỗi
            $conn->rollback();
            header('Location: customers.php?error=Không thể xóa tài khoản. Lỗi: ' . $e->getMessage());
            exit();
        }
    } else {
        header('Location: customers.php?error=Người dùng không tồn tại');
        exit();
    }
}

// Thiết lập tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = $_GET['sort'] ?? 'id_desc';

// Phân trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM donhang WHERE id_user = u.id) AS order_count,
          (SELECT SUM(thanh_tien) FROM donhang WHERE id_user = u.id) AS total_spent
          FROM users u
          WHERE loai_user = 0 "; // Chỉ lấy khách hàng, không lấy admin

$count_query = "SELECT COUNT(*) as total 
                FROM users
                WHERE loai_user = 0 ";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.ten LIKE ? OR u.email LIKE ? OR u.sodienthoai LIKE ? OR u.taikhoan LIKE ?)";
    $count_query .= " AND (ten LIKE ? OR email LIKE ? OR sodienthoai LIKE ? OR taikhoan LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
}

// Lọc theo trạng thái
if ($status !== '') {
    $query .= " AND u.trang_thai = ?";
    $count_query .= " AND trang_thai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Sắp xếp
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY u.ten ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY u.ten DESC";
        break;
    case 'newest':
        $query .= " ORDER BY u.ngay_tao DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY u.ngay_tao ASC";
        break;
    case 'most_orders':
        $query .= " ORDER BY order_count DESC";
        break;
    case 'highest_spent':
        $query .= " ORDER BY total_spent DESC";
        break;
    default:
        $query .= " ORDER BY u.id DESC";
}

// Thêm phân trang
$query .= " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

// Thực hiện truy vấn đếm tổng số
$count_stmt = $conn->prepare($count_query);
if (!empty($param_types)) {
    // Xóa 2 tham số cuối (limit và offset) vì query đếm không cần
    $count_param_types = substr($param_types, 0, -2);
    $count_params = array_slice($params, 0, -2);
    
    // Chỉ bind_param nếu có parameter types
    if (!empty($count_param_types)) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Thực hiện truy vấn danh sách
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$customers = $stmt->get_result();

// Format tiền VNĐ
function formatVND($amount) {
    if ($amount === null) return '0 ₫';
    return number_format($amount, 0, ',', '.') . ' ₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách hàng - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/customers.css">
</head>
<body>
        
        <!-- Main Content -->
        <div class="col-md-10 col-lg-10 ms-auto">
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Quản lý khách hàng</h1>
                </div>
                
                <!-- Thông báo -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Tìm kiếm và lọc -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm và lọc</h6>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                            <i class="fas fa-filter me-1"></i> Lọc nâng cao
                        </button>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row align-items-end">
                                <div class="col-md-10 mb-3">
                                    <label for="search" class="form-label">Tìm kiếm</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Tên khách hàng, email, số điện thoại..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i> Tìm kiếm
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Lọc nâng cao -->
                            <div class="collapse <?php echo ($status !== '' || $sort != 'id_desc') ? 'show' : ''; ?>" id="filtersCollapse">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="status" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Tất cả</option>
                                            <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Đang hoạt động</option>
                                            <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>>Đã khóa</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="sort" class="form-label">Sắp xếp</label>
                                        <select class="form-select" id="sort" name="sort">
                                            <option value="id_desc" <?php echo ($sort == 'id_desc') ? 'selected' : ''; ?>>Mặc định (ID giảm dần)</option>
                                            <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Tên A-Z</option>
                                            <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Tên Z-A</option>
                                            <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                                            <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Cũ nhất</option>
                                            <option value="most_orders" <?php echo ($sort == 'most_orders') ? 'selected' : ''; ?>>Nhiều đơn hàng nhất</option>
                                            <option value="highest_spent" <?php echo ($sort == 'highest_spent') ? 'selected' : ''; ?>>Chi tiêu nhiều nhất</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3 d-flex align-items-end">
                                        <a href="customers.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-redo me-1"></i> Đặt lại bộ lọc
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Danh sách khách hàng -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Danh sách khách hàng
                            <span class="badge bg-secondary ms-1"><?php echo $total_items; ?> khách hàng</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">ID</th>
                                        <th>Tài khoản</th>
                                        <th>Tên khách hàng</th>
                                        <th>Liên hệ</th>
                                        <th>Đơn hàng</th>
                                        <th>Chi tiêu</th>
                                        <th>Ngày đăng ký</th>
                                        <th width="100">Trạng thái</th>
                                        <th width="170">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($customers && $customers->num_rows > 0): ?>
                                        <?php while ($customer = $customers->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $customer['id']; ?></td>
                                                <td><?php echo htmlspecialchars($customer['taikhoan']); ?></td>
                                                <td>
                                                    <span class="customer-name">
                                                        <?php echo htmlspecialchars($customer['ten']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?php if (!empty($customer['email'])): ?>
                                                            <div><i class="fas fa-envelope fa-sm text-muted me-1"></i> <?php echo htmlspecialchars($customer['email']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($customer['sodienthoai'])): ?>
                                                            <div><i class="fas fa-phone fa-sm text-muted me-1"></i> <?php echo htmlspecialchars($customer['sodienthoai']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info">
                                                        <?php echo $customer['order_count']; ?> đơn
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <?php echo formatVND($customer['total_spent']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($customer['ngay_tao'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($customer['trang_thai']): ?>
                                                        <span class="badge bg-success">Hoạt động</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Đã khóa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 mb-1">
                                                        <a href="customer_detail.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info flex-grow-1">
                                                            <i class="fas fa-eye"></i> Chi tiết
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <a href="customers.php?toggle_status=<?php echo $customer['id']; ?>" 
                                                           class="btn btn-sm <?php echo $customer['trang_thai'] ? 'btn-warning' : 'btn-success'; ?> flex-grow-1"
                                                           onclick="return confirm('Bạn có chắc muốn <?php echo $customer['trang_thai'] ? 'khóa' : 'mở khóa'; ?> tài khoản này?')">
                                                            <i class="fas <?php echo $customer['trang_thai'] ? 'fa-lock' : 'fa-unlock'; ?>"></i> <?php echo $customer['trang_thai'] ? 'Khóa' : 'Mở khóa'; ?>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-secondary flex-grow-1" 
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="customers.php?reset_password=<?php echo $customer['id']; ?>"
                                                                   onclick="return confirm('Bạn có chắc muốn đặt lại mật khẩu cho tài khoản này?')">
                                                                    <i class="fas fa-key me-1"></i> Đặt lại mật khẩu
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="customers.php?delete=<?php echo $customer['id']; ?>"
                                                                   onclick="return confirm('CẢNH BÁO: Việc xóa tài khoản sẽ xóa toàn bộ dữ liệu liên quan như giỏ hàng, danh sách yêu thích, đánh giá. Bạn có chắc chắn muốn xóa?')">
                                                                    <i class="fas fa-trash me-1"></i> Xóa tài khoản
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="text-muted mb-3">
                                                    <i class="fas fa-users fa-3x"></i>
                                                </div>
                                                <h5>Không tìm thấy khách hàng nào</h5>
                                                <p>Thử thay đổi tiêu chí tìm kiếm hoặc bộ lọc</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Phân trang -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <div>
                                Hiển thị <?php echo min(($page - 1) * $items_per_page + 1, $total_items); ?> - 
                                <?php echo min($page * $items_per_page, $total_items); ?> 
                                trong <?php echo $total_items; ?> khách hàng
                            </div>
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=1' . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page - 1) . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, min($page - 2, $total_pages - 4));
                                    $end_page = min($total_pages, max($page + 2, 5));
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo '?page=' . $i . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page + 1) . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . $total_pages . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/customers.js"></script>
</body>
</html>
