<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
$current_page = 'returns';
include 'includes/header.php';
include 'includes/sidebar.php';

// Xử lý cập nhật trạng thái hoàn trả
if (isset($_POST['update_status']) && isset($_POST['return_id']) && is_numeric($_POST['return_id'])) {
    $return_id = intval($_POST['return_id']);
    $status = intval($_POST['status']);
    $feedback = $_POST['feedback'] ?? '';
    $admin_id = $_SESSION['admin_id'];
    
    // Kiểm tra yêu cầu tồn tại
    $check_sql = "SELECT id_hoantra FROM hoantra WHERE id_hoantra = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $return_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Cập nhật trạng thái
        $update_sql = "UPDATE hoantra SET trangthai = ?, phan_hoi = ?, ngaycapnhat = CURRENT_TIMESTAMP() WHERE id_hoantra = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('isi', $status, $feedback, $return_id);
        
        if ($update_stmt->execute()) {
            // Ghi log
            $action_details = '';
            switch ($status) {
                case 2:
                    $action_details = "Đã xác nhận";
                    break;
                case 3:
                    $action_details = "Đang xử lý";
                    break;
                case 4:
                    $action_details = "Hoàn thành";
                    break;
                case 5:
                    $action_details = "Từ chối";
                    break;
                default:
                    $action_details = "Không xác định";
            }
            
            // Log hành động
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                      VALUES (?, 'update', 'return', ?, ?, ?)";
            $log_detail = "Cập nhật trạng thái hoàn trả #$return_id sang \"$action_details\". Ghi chú: $feedback";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param('iiss', $admin_id, $return_id, $log_detail, $ip);
            $log_stmt->execute();
            
            // Cập nhật lịch sử đơn hàng
            $get_order_id_sql = "SELECT id_donhang FROM hoantra WHERE id_hoantra = ?";
            $get_order_id_stmt = $conn->prepare($get_order_id_sql);
            $get_order_id_stmt->bind_param('i', $return_id);
            $get_order_id_stmt->execute();
            $order_id = $get_order_id_stmt->get_result()->fetch_assoc()['id_donhang'];
            
            // Lấy tên admin
            $admin_name_sql = "SELECT ten FROM users WHERE id = ?";
            $admin_name_stmt = $conn->prepare($admin_name_sql);
            $admin_name_stmt->bind_param('i', $admin_id);
            $admin_name_stmt->execute();
            $admin_name = $admin_name_stmt->get_result()->fetch_assoc()['ten'];
            
            $history_sql = "INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) 
                          VALUES (?, 'Cập nhật yêu cầu hoàn trả', ?, ?)";
            $history_detail = "Cập nhật trạng thái hoàn trả sang \"$action_details\". Ghi chú: $feedback";
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param('iss', $order_id, $admin_name, $history_detail);
            $history_stmt->execute();
            
            $success_message = "Cập nhật trạng thái hoàn trả thành công!";
        } else {
            $error_message = "Lỗi khi cập nhật trạng thái: " . $conn->error;
        }
    } else {
        $error_message = "Không tìm thấy yêu cầu hoàn trả!";
    }
}

// Thiết lập tham số tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? intval($_GET['status']) : '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Phân trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$query = "SELECT h.*, 
          d.ma_donhang, d.ho_ten AS customer_name, 
          sp.tensanpham, sp.hinhanh AS product_image, 
          u.email AS customer_email
          FROM hoantra h
          JOIN donhang d ON h.id_donhang = d.id
          JOIN sanpham sp ON h.id_sanpham = sp.id
          JOIN users u ON h.id_nguoidung = u.id
          WHERE 1=1 ";

$count_query = "SELECT COUNT(*) AS total 
               FROM hoantra h
               JOIN donhang d ON h.id_donhang = d.id
               JOIN sanpham sp ON h.id_sanpham = sp.id
               JOIN users u ON h.id_nguoidung = u.id
               WHERE 1=1 ";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (sp.tensanpham LIKE ? OR d.ma_donhang LIKE ? OR d.ho_ten LIKE ? OR u.email LIKE ? OR h.lydo LIKE ? OR h.mota_chitiet LIKE ?)";
    $count_query .= " AND (sp.tensanpham LIKE ? OR d.ma_donhang LIKE ? OR d.ho_ten LIKE ? OR u.email LIKE ? OR h.lydo LIKE ? OR h.mota_chitiet LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssssss";
}

// Lọc theo trạng thái
if ($status !== '') {
    $query .= " AND h.trangthai = ?";
    $count_query .= " AND h.trangthai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Lọc theo ngày
if (!empty($start_date)) {
    $query .= " AND DATE(h.ngaytao) >= ?";
    $count_query .= " AND DATE(h.ngaytao) >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}

if (!empty($end_date)) {
    $query .= " AND DATE(h.ngaytao) <= ?";
    $count_query .= " AND DATE(h.ngaytao) <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

// Sắp xếp
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY h.ngaytao ASC";
        break;
    default: // newest
        $query .= " ORDER BY h.ngaytao DESC";
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
$returns = $stmt->get_result();

// Hàm hiển thị trạng thái hoàn trả
function getReturnStatusLabel($status) {
    switch ($status) {
        case 1:
            return '<span class="badge bg-warning">Chờ xác nhận</span>';
        case 2:
            return '<span class="badge bg-info">Đã xác nhận</span>';
        case 3:
            return '<span class="badge bg-primary">Đang xử lý</span>';
        case 4:
            return '<span class="badge bg-success">Hoàn thành</span>';
        case 5:
            return '<span class="badge bg-danger">Từ chối</span>';
        default:
            return '<span class="badge bg-secondary">Không xác định</span>';
    }
}

// Function to format VND
function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý hoàn trả - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Custom styles for returns page */
        .return-item {
            transition: all 0.3s ease;
        }
        
        .return-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.3rem 2rem 0 rgba(58, 59, 69, 0.15) !important;
        }
        
        .product-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .product-thumbnail:hover {
            transform: scale(1.05);
        }
        
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            border-radius: 0.5rem;
        }
        
        .return-reason {
            padding: 0.5rem;
            background-color: #f8f9fc;
            border-radius: 0.35rem;
            border-left: 4px solid #4e73df;
        }
        
        .status-group .form-select {
            min-width: 160px;
        }
    </style>
</head>
<body>
    
    <!-- Main Content -->
    <div class="col-md-10 col-lg-10 ms-auto">
        <div class="container-fluid px-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Quản lý hoàn trả</h1>
            </div>
            
            <!-- Thông báo -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Tìm kiếm và lọc -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm và lọc</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="search" class="form-label">Tìm kiếm</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Mã đơn hàng, tên khách hàng, sản phẩm..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="status" class="form-label">Trạng thái</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="1" <?php echo ($status === 1) ? 'selected' : ''; ?>>Chờ xác nhận</option>
                                    <option value="2" <?php echo ($status === 2) ? 'selected' : ''; ?>>Đã xác nhận</option>
                                    <option value="3" <?php echo ($status === 3) ? 'selected' : ''; ?>>Đang xử lý</option>
                                    <option value="4" <?php echo ($status === 4) ? 'selected' : ''; ?>>Hoàn thành</option>
                                    <option value="5" <?php echo ($status === 5) ? 'selected' : ''; ?>>Từ chối</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Sắp xếp</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                                    <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Cũ nhất</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="start_date" class="form-label">Từ ngày</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="end_date" class="form-label">Đến ngày</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i> Tìm kiếm
                                </button>
                                <a href="returns.php" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt me-1"></i> Đặt lại
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Danh sách hoàn trả -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Danh sách yêu cầu hoàn trả
                        <span class="badge bg-secondary ms-1"><?php echo $total_items; ?> yêu cầu</span>
                    </h6>
                    <?php if (!empty($search) || $status !== '' || !empty($start_date) || !empty($end_date)): ?>
                        <span class="badge bg-info">Đã lọc</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($returns && $returns->num_rows > 0): ?>
                        <div class="returns-container">
                            <?php while ($return = $returns->fetch_assoc()): ?>
                                <div class="card return-item mb-4 shadow-sm">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0">
                                                Đơn hàng: 
                                                <a href="order_detail.php?id=<?php echo $return['id_donhang']; ?>" class="text-decoration-none">
                                                    <?php echo $return['ma_donhang']; ?>
                                                </a>
                                            </h5>
                                            <div class="small text-muted">
                                                Ngày yêu cầu: <?php echo date('d/m/Y H:i', strtotime($return['ngaytao'])); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php echo getReturnStatusLabel($return['trangthai']); ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <!-- Thông tin khách hàng và sản phẩm -->
                                            <div class="col-md-7">
                                                <div class="d-flex mb-3">
                                                    <div class="me-3">
                                                        <?php if (!empty($return['product_image'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($return['product_image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($return['tensanpham']); ?>" 
                                                                 class="product-thumbnail">
                                                        <?php else: ?>
                                                            <div class="product-thumbnail d-flex align-items-center justify-content-center bg-light">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($return['tensanpham']); ?></h6>
                                                        <div class="mb-2">
                                                            <i class="fas fa-user me-1 text-muted"></i>
                                                            <?php echo htmlspecialchars($return['customer_name']); ?>
                                                            <span class="ms-2 small text-muted"><?php echo htmlspecialchars($return['customer_email']); ?></span>
                                                        </div>
                                                        <div class="return-reason p-2 mb-2">
                                                            <strong class="d-block mb-1">Lý do hoàn trả:</strong>
                                                            <?php echo htmlspecialchars($return['lydo']); ?>
                                                        </div>
                                                        <?php if (!empty($return['mota_chitiet'])): ?>
                                                            <div class="small">
                                                                <strong class="d-block">Chi tiết:</strong>
                                                                <?php echo nl2br(htmlspecialchars($return['mota_chitiet'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Cập nhật trạng thái -->
                                            <div class="col-md-5">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="return_id" value="<?php echo $return['id_hoantra']; ?>">
                                                    
                                                    <div class="status-group mb-3">
                                                        <label for="status-<?php echo $return['id_hoantra']; ?>" class="form-label">Cập nhật trạng thái:</label>
                                                        <select class="form-select" id="status-<?php echo $return['id_hoantra']; ?>" name="status" required>
                                                            <option value="">-- Chọn trạng thái --</option>
                                                            <option value="2" <?php echo ($return['trangthai'] == 2) ? 'selected' : ''; ?>>Đã xác nhận</option>
                                                            <option value="3" <?php echo ($return['trangthai'] == 3) ? 'selected' : ''; ?>>Đang xử lý</option>
                                                            <option value="4" <?php echo ($return['trangthai'] == 4) ? 'selected' : ''; ?>>Hoàn thành</option>
                                                            <option value="5" <?php echo ($return['trangthai'] == 5) ? 'selected' : ''; ?>>Từ chối</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="feedback-<?php echo $return['id_hoantra']; ?>" class="form-label">Phản hồi:</label>
                                                        <textarea class="form-control" id="feedback-<?php echo $return['id_hoantra']; ?>" name="feedback" rows="2"><?php echo htmlspecialchars($return['phan_hoi'] ?? ''); ?></textarea>
                                                    </div>
                                                    
                                                    <button type="submit" name="update_status" class="btn btn-primary">
                                                        <i class="fas fa-save me-1"></i> Cập nhật
                                                    </button>
                                                    
                                                    <?php if (!empty($return['ngaycapnhat'])): ?>
                                                        <div class="small text-muted mt-2">
                                                            Cập nhật lần cuối: <?php echo date('d/m/Y H:i', strtotime($return['ngaycapnhat'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-3">
                                <i class="fas fa-undo-alt fa-3x"></i>
                            </div>
                            <h5>Không tìm thấy yêu cầu hoàn trả nào</h5>
                            <p>Thử thay đổi tiêu chí tìm kiếm hoặc bộ lọc</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <div>
                            Hiển thị <?php echo min(($page - 1) * $items_per_page + 1, $total_items); ?> - 
                            <?php echo min($page * $items_per_page, $total_items); ?> 
                            trong tổng số <?php echo $total_items; ?> yêu cầu
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php 
                                $query_params = http_build_query(array_filter([
                                    'search' => $search,
                                    'status' => $status,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date,
                                    'sort' => $sort
                                ]));
                                $query_string = !empty($query_params) ? '&' . $query_params : '';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo $query_string; ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1) . $query_string; ?>">
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
                                        <a class="page-link" href="?page=<?php echo $i . $query_string; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1) . $query_string; ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages . $query_string; ?>">
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Auto-submit form when select elements change
    const autoSubmitFilters = document.querySelectorAll('#status, #sort');
    autoSubmitFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    
    // Animated entrance for return items
    const returnItems = document.querySelectorAll('.return-item');
    returnItems.forEach((item, index) => {
        // Add subtle entrance animation with delay based on position
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 100 + (index * 50)); // Staggered animation
    });
});
</script>
</body>
</html>
