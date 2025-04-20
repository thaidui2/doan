<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
$current_page = 'promotions';

// Xử lý thêm/sửa mã khuyến mãi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Kiểm tra các trường bắt buộc
    if (empty($_POST['ten']) || empty($_POST['ma_khuyenmai']) || !isset($_POST['loai_giamgia']) || 
        empty($_POST['gia_tri']) || !isset($_POST['loai_doi_tuong'])) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        $ten = $_POST['ten'];
        $ma_khuyenmai = strtoupper($_POST['ma_khuyenmai']);
        $loai_giamgia = intval($_POST['loai_giamgia']);
        $gia_tri = floatval($_POST['gia_tri']);
        $dieu_kien_toithieu = !empty($_POST['dieu_kien_toithieu']) ? floatval($_POST['dieu_kien_toithieu']) : 0;
        $so_luong = !empty($_POST['so_luong']) ? intval($_POST['so_luong']) : null;
        $max_su_dung_per_user = !empty($_POST['max_su_dung_per_user']) ? intval($_POST['max_su_dung_per_user']) : null;
        $ngay_bat_dau = !empty($_POST['ngay_bat_dau']) ? $_POST['ngay_bat_dau'] . ' 00:00:00' : null;
        $ngay_ket_thuc = !empty($_POST['ngay_ket_thuc']) ? $_POST['ngay_ket_thuc'] . ' 23:59:59' : null;
        $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
        $loai_doi_tuong = intval($_POST['loai_doi_tuong']);
        $id_doi_tuong = null;
        // Xử lý đối tượng áp dụng
        if ($loai_doi_tuong > 0) {
            if ($loai_doi_tuong == 1 && !empty($_POST['id_danhmuc'])) {
                $id_doi_tuong = intval($_POST['id_danhmuc']);
            } elseif ($loai_doi_tuong == 2 && !empty($_POST['id_sanpham'])) {
                $id_doi_tuong = intval($_POST['id_sanpham']);
            }
        }
        // Validate giá trị khuyến mãi
        if ($loai_giamgia == 0 && ($gia_tri <= 0 || $gia_tri > 100)) {
            $error = "Giá trị phần trăm phải nằm trong khoảng từ 1% đến 100%.";
        } elseif ($loai_giamgia == 1 && $gia_tri <= 0) {
            $error = "Giá trị giảm phải lớn hơn 0.";
        } else {
            $conn->begin_transaction();
            try {
                if (isset($_POST['id']) && is_numeric($_POST['id'])) {
                    // Cập nhật khuyến mãi
                    $id = intval($_POST['id']);
                    // Kiểm tra mã khuyến mãi đã tồn tại chưa (trừ mã hiện tại)
                    $check_sql = "SELECT id FROM khuyen_mai WHERE ma_khuyenmai = ? AND id != ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("si", $ma_khuyenmai, $id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($check_result->num_rows > 0) {
                        $error = "Mã khuyến mãi đã tồn tại. Vui lòng chọn mã khác.";
                    } else {
                        // Cập nhật thông tin
                        $update_sql = "UPDATE khuyen_mai SET 
                                      ten = ?, 
                                      ma_khuyenmai = ?, 
                                      loai_giamgia = ?, 
                                      gia_tri = ?, 
                                      dieu_kien_toithieu = ?,
                                      so_luong = ?,
                                      ngay_bat_dau = ?,
                                      ngay_ket_thuc = ?,
                                      trang_thai = ?,
                                      max_su_dung_per_user = ?
                                      WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("ssiddiissii", 
                            $ten, 
                            $ma_khuyenmai, 
                            $loai_giamgia, 
                            $gia_tri, 
                            $dieu_kien_toithieu,
                            $so_luong,
                            $ngay_bat_dau,
                            $ngay_ket_thuc,
                            $trang_thai,
                            $max_su_dung_per_user,
                            $id
                        );
                        $update_stmt->execute();
                        // Xóa đối tượng áp dụng cũ
                        $delete_apply_sql = "DELETE FROM khuyen_mai_apdung WHERE id_khuyenmai = ?";
                        $delete_apply_stmt = $conn->prepare($delete_apply_sql);
                        $delete_apply_stmt->bind_param("i", $id);
                        $delete_apply_stmt->execute();
                        // Thêm đối tượng áp dụng mới
                        $insert_apply_sql = "INSERT INTO khuyen_mai_apdung (id_khuyenmai, loai_doi_tuong, id_doi_tuong) VALUES (?, ?, ?)";
                        $insert_apply_stmt = $conn->prepare($insert_apply_sql);
                        $insert_apply_stmt->bind_param("iii", $id, $loai_doi_tuong, $id_doi_tuong);
                        $insert_apply_stmt->execute();
                        // Ghi log hoạt động
                        $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                                  VALUES (?, 'update', 'promotion', ?, ?, ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        $detail = "Cập nhật khuyến mãi: " . $ten . " (ID: " . $id . ")";
                        $log_stmt->bind_param("iiss", $admin_id, $id, $detail, $ip);
                        $log_stmt->execute();
                        $conn->commit();
                        $success = "Cập nhật mã khuyến mãi thành công!";
                    }   
                } else {
                    // Thêm khuyến mãi mới
                    // Kiểm tra mã khuyến mãi đã tồn tại chưa
                    $check_sql = "SELECT id FROM khuyen_mai WHERE ma_khuyenmai = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("s", $ma_khuyenmai);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($check_result->num_rows > 0) {
                        $error = "Mã khuyến mãi đã tồn tại. Vui lòng chọn mã khác.";
                    } else {
                        // Thêm khuyến mãi mới
                        $insert_sql = "INSERT INTO khuyen_mai ( 
                                      ten, 
                                      ma_khuyenmai, 
                                      loai_giamgia, 
                                      gia_tri,
                                      dieu_kien_toithieu,
                                      so_luong,
                                      ngay_bat_dau,
                                      ngay_ket_thuc,
                                      trang_thai,
                                      max_su_dung_per_user
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bind_param("ssiddiissi", 
                            $ten, 
                            $ma_khuyenmai, 
                            $loai_giamgia, 
                            $gia_tri, 
                            $dieu_kien_toithieu,
                            $so_luong,
                            $ngay_bat_dau, 
                            $ngay_ket_thuc,
                            $trang_thai,
                            $max_su_dung_per_user
                        );
                        $insert_stmt->execute();
                        $promotion_id = $conn->insert_id;
                        // Thêm đối tượng áp dụng
                        $insert_apply_sql = "INSERT INTO khuyen_mai_apdung (id_khuyenmai, loai_doi_tuong, id_doi_tuong) VALUES (?, ?, ?)";
                        $insert_apply_stmt = $conn->prepare($insert_apply_sql);
                        $insert_apply_stmt->bind_param("iii", $promotion_id, $loai_doi_tuong, $id_doi_tuong);
                        $insert_apply_stmt->execute();
                        // Ghi log hoạt động
                        $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                                  VALUES (?, 'create', 'promotion', ?, ?, ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        $detail = "Thêm mã khuyến mãi mới: " . $ten . " (" . $ma_khuyenmai . ")";
                        $log_stmt->bind_param("iiss", $admin_id, $promotion_id, $detail, $ip);
                        $log_stmt->execute();
                        $conn->commit();
                        $success = "Thêm mã khuyến mãi thành công!";
                    }   
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Lỗi: " . $e->getMessage();
            }
        }
    }
}

// Xử lý xóa khuyến mãi
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $id = intval($_GET['id']);
    // Kiểm tra khuyến mãi tồn tại
    $check_sql = "SELECT id, ten, ma_khuyenmai FROM khuyen_mai WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $promotion_data = $check_result->fetch_assoc();
        // Bắt đầu transaction
        $conn->begin_transaction();
        try {
            // Xóa đối tượng áp dụng
            $delete_apply_sql = "DELETE FROM khuyen_mai_apdung WHERE id_khuyenmai = ?";
            $delete_apply_stmt = $conn->prepare($delete_apply_sql);
            $delete_apply_stmt->bind_param("i", $id);
            $delete_apply_stmt->execute();
            // Xóa khuyến mãi
            $delete_sql = "DELETE FROM khuyen_mai WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            // Ghi log hoạt động
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                      VALUES (?, 'delete', 'promotion', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $detail = "Xóa khuyến mãi: " . $promotion_data['ten'] . " (" . $promotion_data['ma_khuyenmai'] . ")";
            $log_stmt->bind_param("iiss", $admin_id, $id, $detail, $ip);
            $log_stmt->execute();
            $conn->commit();
            $success = "Xóa mã khuyến mãi thành công!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    } else {
        $error = "Không tìm thấy mã khuyến mãi!";
    }
}

// Lấy thông tin khuyến mãi cần sửa
$edit_data = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Lấy thông tin khuyến mãi sửa
    $edit_sql = "SELECT * FROM khuyen_mai WHERE id = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_data = $edit_result->fetch_assoc();
        // Lấy thông tin đối tượng áp dụng
        $apply_sql = "SELECT * FROM khuyen_mai_apdung WHERE id_khuyenmai = ? LIMIT 1";
        $apply_stmt = $conn->prepare($apply_sql);
        $apply_stmt->bind_param("i", $id);
        $apply_stmt->execute();
        $apply_result = $apply_stmt->get_result();
        if ($apply_result->num_rows > 0) {
            $apply_data = $apply_result->fetch_assoc();
            $edit_data['loai_doi_tuong'] = $apply_data['loai_doi_tuong'];
            $edit_data['id_doi_tuong'] = $apply_data['id_doi_tuong'];
        }
    }
}

// Thiết lập tham số tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$valid_date = isset($_GET['valid_date']) ? $_GET['valid_date'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Phân trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$query = "SELECT km.*, kma.loai_doi_tuong, kma.id_doi_tuong 
          FROM khuyen_mai km
          LEFT JOIN khuyen_mai_apdung kma ON km.id = kma.id_khuyenmai
          WHERE 1=1 ";

$count_query = "SELECT COUNT(*) AS total 
               FROM khuyen_mai km
               LEFT JOIN khuyen_mai_apdung kma ON km.id = kma.id_khuyenmai
               WHERE 1=1 ";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (km.ten LIKE ? OR km.ma_khuyenmai LIKE ?)";
    $count_query .= " AND (km.ten LIKE ? OR km.ma_khuyenmai LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term]);
    $param_types .= "ss";
}

// Lọc theo trạng thái
if ($status !== '') {
    $query .= " AND km.trang_thai = ?";
    $count_query .= " AND km.trang_thai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Lọc theo loại giảm giá
if ($type !== '') {
    $query .= " AND km.loai_giamgia = ?";
    $count_query .= " AND km.loai_giamgia = ?";
    $params[] = $type;
    $param_types .= "i";
}

// Lọc theo ngày hiệu lực
if (!empty($valid_date)) {
    $query .= " AND (? BETWEEN km.ngay_bat_dau AND km.ngay_ket_thuc OR ? >= km.ngay_bat_dau AND km.ngay_ket_thuc IS NULL)";
    $count_query .= " AND (? BETWEEN km.ngay_bat_dau AND km.ngay_ket_thuc OR ? >= km.ngay_bat_dau AND km.ngay_ket_thuc IS NULL)";
    $params = array_merge($params, [$valid_date, $valid_date]);
    $param_types .= "ss";
}

// Sắp xếp
$query .= " ORDER BY km.id DESC";

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
$promotions = $stmt->get_result();

// Lấy danh sách danh mục
$categories_sql = "SELECT id, ten FROM danhmuc WHERE trang_thai = 1 ORDER BY ten";
$categories_result = $conn->query($categories_sql);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[$row['id']] = $row['ten'];
}

// Lấy danh sách sản phẩm
$products_sql = "SELECT id, tensanpham FROM sanpham WHERE trangthai = 1 ORDER BY tensanpham";
$products_result = $conn->query($products_sql);
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[$row['id']] = $row['tensanpham'];
}

// Function to format VND
function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

include 'includes/header.php';
include 'includes/sidebar.php';
?> 
<!-- Main Content -->
<div class="col-md-10 col-lg-10 ms-auto">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Quản lý khuyến mãi</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promotionModal">
                <i class="fas fa-plus me-1"></i> Thêm mới
            </button>
        </div>
        <!-- Thông báo -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Tìm kiếm và lọc -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm và lọc</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="search" class="form-label">Tìm kiếm</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Tên khuyến mãi, mã khuyến mãi..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Tìm kiếm
                            </button>
                            <a href="promotions.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt me-1"></i> Đặt lại
                            </a>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Trạng thái</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tất cả</option>
                                <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Hoạt động</option>
                                <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>>Vô hiệu hóa</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">Loại giảm giá</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">Tất cả</option>
                                <option value="0" <?php echo ($type === '0') ? 'selected' : ''; ?>>Phần trăm</option>
                                <option value="1" <?php echo ($type === '1') ? 'selected' : ''; ?>>Số tiền cụ thể</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="valid_date" class="form-label">Có hiệu lực vào ngày</label>
                            <input type="date" class="form-control" id="valid_date" name="valid_date" 
                                  value="<?php echo $valid_date; ?>">
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Danh sách khuyến mãi -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    Danh sách khuyến mãi
                    <span class="badge bg-secondary ms-1"><?php echo $total_items; ?></span>
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên</th>
                                <th>Mã</th>
                                <th>Giảm giá</th>
                                <th>Điều kiện</th>
                                <th>Sử dụng</th>
                                <th>Giới hạn/tài khoản</th>
                                <th>Thời gian hiệu lực</th>
                                <th>Trạng thái</th>
                                <th>Áp dụng cho</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($promotions && $promotions->num_rows > 0): ?>
                                <?php while ($promotion = $promotions->fetch_assoc()): ?>
                                    <tr <?php echo $promotion['trang_thai'] ? '' : 'class="table-secondary"'; ?>>
                                        <td><?php echo $promotion['id']; ?></td>
                                        <td><?php echo htmlspecialchars($promotion['ten']); ?></td>
                                        <td><code><?php echo htmlspecialchars($promotion['ma_khuyenmai']); ?></code></td>
                                        <td>
                                            <?php if ($promotion['loai_giamgia'] == 0): ?>
                                                <?php echo $promotion['gia_tri']; ?>%
                                            <?php else: ?>
                                                <?php echo formatVND($promotion['gia_tri']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($promotion['dieu_kien_toithieu'] > 0): ?>
                                                Đơn ≥ <?php echo formatVND($promotion['dieu_kien_toithieu']); ?>
                                            <?php else: ?>
                                                Không có
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($promotion['so_luong'] !== null): ?>
                                                <?php echo $promotion['da_su_dung']; ?>/<?php echo $promotion['so_luong']; ?>
                                            <?php else: ?>
                                                Không giới hạn
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($promotion['max_su_dung_per_user'] !== null): ?>
                                                <?php echo $promotion['max_su_dung_per_user']; ?> lần
                                            <?php else: ?>
                                                Không giới hạn
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($promotion['ngay_bat_dau'] && $promotion['ngay_ket_thuc']): ?>
                                                <?php echo date('d/m/Y', strtotime($promotion['ngay_bat_dau'])); ?> -
                                                <?php echo date('d/m/Y', strtotime($promotion['ngay_ket_thuc'])); ?>
                                            <?php elseif ($promotion['ngay_bat_dau']): ?>
                                                Từ <?php echo date('d/m/Y', strtotime($promotion['ngay_bat_dau'])); ?>
                                            <?php else: ?>
                                                Không giới hạn
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($promotion['trang_thai']): ?>
                                                <span class="badge bg-success">Hoạt động</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Vô hiệu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            switch ($promotion['loai_doi_tuong']) {
                                                case 0:
                                                    echo 'Tất cả sản phẩm';
                                                    break;
                                                case 1:
                                                    echo isset($categories[$promotion['id_doi_tuong']]) ? 
                                                        'Danh mục: ' . $categories[$promotion['id_doi_tuong']] : 
                                                        'Danh mục: #' . $promotion['id_doi_tuong'];
                                                    break;
                                                case 2:
                                                    echo isset($products[$promotion['id_doi_tuong']]) ? 
                                                        'Sản phẩm: ' . $products[$promotion['id_doi_tuong']] : 
                                                        'Sản phẩm: #' . $promotion['id_doi_tuong'];
                                                    break;
                                                default:
                                                    echo 'Không xác định';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="promotions.php?action=edit&id=<?php echo $promotion['id']; ?>" 
                                               class="btn btn-sm btn-primary mb-1" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="promotions.php?action=delete&id=<?php echo $promotion['id']; ?>" 
                                               class="btn btn-sm btn-danger mb-1" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa khuyến mãi này?')" 
                                               title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center">Không có dữ liệu</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-end">
                            <?php
                            $query_params = http_build_query(array_filter([
                                'search' => $search,
                                'status' => $status,
                                'type' => $type,
                                'valid_date' => $valid_date
                            ]));
                            $query_string = !empty($query_params) ? '&' . $query_params : '';
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo $query_string; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1 . $query_string; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i . $query_string; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1 . $query_string; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages . $query_string; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="promotionModalLabel">
                        <?php echo !empty($edit_data) ? 'Chỉnh sửa khuyến mãi' : 'Thêm khuyến mãi mới'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($edit_data)): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ten" class="form-label">Tên khuyến mãi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten" name="ten" required
                                   value="<?php echo htmlspecialchars($edit_data['ten'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ma_khuyenmai" class="form-label">Mã khuyến mãi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ma_khuyenmai" name="ma_khuyenmai" required
                                   value="<?php echo htmlspecialchars($edit_data['ma_khuyenmai'] ?? ''); ?>"
                                   <?php echo !empty($edit_data) ? 'readonly' : ''; ?>>
                            <small class="text-muted">Mã không dấu, không khoảng trắng, viết hoa</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="loai_giamgia" class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                            <select class="form-select" id="loai_giamgia" name="loai_giamgia" required>
                                <option value="0" <?php echo (isset($edit_data['loai_giamgia']) && $edit_data['loai_giamgia'] == 0) ? 'selected' : ''; ?>>
                                    Giảm theo phần trăm (%)
                                </option>
                                <option value="1" <?php echo (isset($edit_data['loai_giamgia']) && $edit_data['loai_giamgia'] == 1) ? 'selected' : ''; ?>>
                                    Giảm số tiền cụ thể
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gia_tri" class="form-label">Giá trị <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri" name="gia_tri" required
                                       value="<?php echo htmlspecialchars($edit_data['gia_tri'] ?? ''); ?>"
                                       min="0" step="0.01">
                                <span class="input-group-text" id="gia_tri_suffix">%</span>
                            </div>
                            <small class="text-muted" id="gia_tri_note">Giá trị từ 1-100%</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ngay_bat_dau" class="form-label">Ngày bắt đầu</label>
                            <input type="date" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau"
                                   value="<?php echo !empty($edit_data['ngay_bat_dau']) ? date('Y-m-d', strtotime($edit_data['ngay_bat_dau'])) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ngay_ket_thuc" class="form-label">Ngày kết thúc</label>
                            <input type="date" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc"
                                   value="<?php echo !empty($edit_data['ngay_ket_thuc']) ? date('Y-m-d', strtotime($edit_data['ngay_ket_thuc'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="so_luong" class="form-label">Số lượng mã (để trống = không giới hạn)</label>
                            <input type="number" class="form-control" id="so_luong" name="so_luong" min="1"
                                   value="<?php echo isset($edit_data['so_luong']) ? $edit_data['so_luong'] : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="max_su_dung_per_user" class="form-label">Giới hạn/tài khoản</label>
                            <input type="number" class="form-control" id="max_su_dung_per_user" name="max_su_dung_per_user" min="1"
                                   value="<?php echo isset($edit_data['max_su_dung_per_user']) ? $edit_data['max_su_dung_per_user'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="dieu_kien_toithieu" class="form-label">Giá trị đơn hàng tối thiểu</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="dieu_kien_toithieu" name="dieu_kien_toithieu" 
                                       value="<?php echo htmlspecialchars($edit_data['dieu_kien_toithieu'] ?? '0'); ?>" min="0" step="1000">
                                <span class="input-group-text">₫</span>
                            </div>
                            <small class="text-muted">0 = Không có điều kiện tối thiểu</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="loai_doi_tuong" class="form-label">Áp dụng cho <span class="text-danger">*</span></label>
                            <select class="form-select" id="loai_doi_tuong" name="loai_doi_tuong" required>
                                <option value="0" <?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 0) ? 'selected' : ''; ?>>
                                    Tất cả sản phẩm
                                </option>
                                <option value="1" <?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 1) ? 'selected' : ''; ?>>
                                    Danh mục cụ thể
                                </option>
                                <option value="2" <?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 2) ? 'selected' : ''; ?>>
                                    Sản phẩm cụ thể
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="category_container" style="<?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 1) ? '' : 'display: none;'; ?>">
                            <label for="id_danhmuc" class="form-label">Chọn danh mục</label>
                            <select class="form-select" id="id_danhmuc" name="id_danhmuc">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach($categories as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 1 && isset($edit_data['id_doi_tuong']) && $edit_data['id_doi_tuong'] == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="product_container" style="<?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 2) ? '' : 'display: none;'; ?>">
                            <label for="id_sanpham" class="form-label">Chọn sản phẩm</label>
                            <select class="form-select" id="id_sanpham" name="id_sanpham">
                                <option value="">-- Chọn sản phẩm --</option>
                                <?php foreach($products as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo (isset($edit_data['loai_doi_tuong']) && $edit_data['loai_doi_tuong'] == 2 && isset($edit_data['id_doi_tuong']) && $edit_data['id_doi_tuong'] == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" 
                               <?php echo (!isset($edit_data['trang_thai']) || $edit_data['trang_thai']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="trang_thai">
                            Kích hoạt khuyến mãi
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo !empty($edit_data) ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Auto show modal for edit
    <?php if (!empty($edit_data)): ?>
    var myModal = new bootstrap.Modal(document.getElementById('promotionModal'));
    myModal.show();
    <?php endif; ?>
    
    // Handle discount type change
    const discountTypeSelect = document.getElementById('loai_giamgia');
    const valueInput = document.getElementById('gia_tri');
    const valueSuffix = document.getElementById('gia_tri_suffix');
    const valueNote = document.getElementById('gia_tri_note');
    
    function updateDiscountTypeUI() {
        if (discountTypeSelect.value === '0') {
            valueSuffix.textContent = '%';
            valueNote.textContent = 'Giá trị từ 1-100%';
        } else {
            valueSuffix.textContent = '₫';
            valueNote.textContent = 'Số tiền giảm giá cụ thể';
        }
    }
    updateDiscountTypeUI();
    discountTypeSelect.addEventListener('change', updateDiscountTypeUI);
    
    // Handle target type change
    const targetTypeSelect = document.getElementById('loai_doi_tuong');
    const categoryContainer = document.getElementById('category_container');
    const productContainer = document.getElementById('product_container');
    
    function updateTargetUI() {
        if (targetTypeSelect.value === '1') {
            categoryContainer.style.display = '';
            productContainer.style.display = 'none';
        } else if (targetTypeSelect.value === '2') {
            categoryContainer.style.display = 'none';
            productContainer.style.display = '';
        } else {
            categoryContainer.style.display = 'none';
            productContainer.style.display = 'none';
        }
    }
    updateTargetUI();
    targetTypeSelect.addEventListener('change', updateTargetUI);
    
    // Date validation for start and end dates
    const startDateInput = document.getElementById('ngay_bat_dau');
    const endDateInput = document.getElementById('ngay_ket_thuc');
    
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value && endDateInput.value) {
            if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                alert('Ngày kết thúc phải sau ngày bắt đầu!');
                endDateInput.value = '';
            }
        }
    });
    
    startDateInput.addEventListener('change', function() {
        if (startDateInput.value && endDateInput.value) {
            if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                alert('Ngày kết thúc phải sau ngày bắt đầu!');
                endDateInput.value = '';
            }
        }
    });
});
</script>

<?php
include 'includes/footer.php';
?>
