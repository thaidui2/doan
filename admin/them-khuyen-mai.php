<?php
// Thêm đoạn code này ở đầu file, trước mọi output (kể cả whitespace)
ob_start();

// Thiết lập tiêu đề trang
$page_title = "Thêm Mã Giảm Giá Mới";

// Include header
include('includes/header.php');

// Kiểm tra quyền truy cập
if (!hasPermission('promo_add')) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này!";
    header("Location: khuyen-mai.php");
    exit();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ma_code = strtoupper(trim($_POST['ma_code']));
    $mo_ta = trim($_POST['mo_ta']);
    $loai_giam_gia = (int)$_POST['loai_giam_gia'];
    $gia_tri = (float)$_POST['gia_tri'];
    $gia_tri_giam_toi_da = !empty($_POST['gia_tri_giam_toi_da']) ? (float)$_POST['gia_tri_giam_toi_da'] : 0;
    $gia_tri_don_toi_thieu = !empty($_POST['gia_tri_don_toi_thieu']) ? (float)$_POST['gia_tri_don_toi_thieu'] : 0;
    $so_luong = !empty($_POST['so_luong']) ? (int)$_POST['so_luong'] : 0;
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    $ap_dung_cho = isset($_POST['ap_dung_cho']) ? (int)$_POST['ap_dung_cho'] : 0;
    
    // Kiểm tra dữ liệu đầu vào
    $errors = [];
    
    if (empty($ma_code)) {
        $errors[] = "Vui lòng nhập mã giảm giá";
    }
    
    if ($loai_giam_gia != 1 && $loai_giam_gia != 2) {
        $errors[] = "Loại giảm giá không hợp lệ";
    }
    
    if ($gia_tri <= 0) {
        $errors[] = "Giá trị giảm phải lớn hơn 0";
    }
    
    if ($loai_giam_gia == 1 && $gia_tri > 100) {
        $errors[] = "Giá trị phần trăm không thể vượt quá 100%";
    }
    
    if (empty($ngay_bat_dau) || empty($ngay_ket_thuc)) {
        $errors[] = "Vui lòng chọn thời gian hiệu lực";
    }
    
    if (strtotime($ngay_ket_thuc) <= strtotime($ngay_bat_dau)) {
        $errors[] = "Ngày kết thúc phải sau ngày bắt đầu";
    }
    
    // Biến cho việc áp dụng sản phẩm hoặc danh mục
    $ap_dung_sanpham = 0;
    $ap_dung_loai = 0;
    $san_pham_ap_dung = [];
    $loai_ap_dung = [];
    
    // Kiểm tra áp dụng cho
    if ($ap_dung_cho === 1) { // Áp dụng cho sản phẩm cụ thể
        $ap_dung_sanpham = 1;
        
        if (!isset($_POST['san_pham_ap_dung']) || empty($_POST['san_pham_ap_dung'])) {
            $errors[] = "Vui lòng chọn ít nhất một sản phẩm áp dụng";
        } else {
            $san_pham_ap_dung = $_POST['san_pham_ap_dung'];
        }
    } else if ($ap_dung_cho === 2) { // Áp dụng cho danh mục
        $ap_dung_loai = 1;
        
        if (!isset($_POST['loai_ap_dung']) || empty($_POST['loai_ap_dung'])) {
            $errors[] = "Vui lòng chọn ít nhất một danh mục áp dụng";
        } else {
            $loai_ap_dung = $_POST['loai_ap_dung'];
        }
    }
    
    // Kiểm tra mã đã tồn tại chưa
    $check_code = $conn->prepare("SELECT COUNT(*) as count FROM khuyen_mai WHERE ma_code = ?");
    $check_code->bind_param("s", $ma_code);
    $check_code->execute();
    $code_result = $check_code->get_result()->fetch_assoc();
    
    if ($code_result['count'] > 0) {
        $errors[] = "Mã giảm giá '{$ma_code}' đã tồn tại. Vui lòng chọn mã khác.";
    }
    
    // Nếu không có lỗi, thêm mã giảm giá vào database
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            // Thêm mã giảm giá
            $stmt = $conn->prepare("INSERT INTO khuyen_mai (
                ma_code, mo_ta, loai_giam_gia, gia_tri, 
                gia_tri_don_toi_thieu, gia_tri_giam_toi_da, 
                so_luong, ngay_bat_dau, ngay_ket_thuc, 
                id_nguoiban, ap_dung_sanpham, ap_dung_loai, 
                trang_thai
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Lấy ID của admin đang đăng nhập
            $id_nguoiban = $_SESSION['admin_id'];
            
            // Thực hiện bind_param
            $stmt->bind_param("ssiiddsssiiii", 
                $ma_code, 
                $mo_ta, 
                $loai_giam_gia, 
                $gia_tri, 
                $gia_tri_don_toi_thieu, 
                $gia_tri_giam_toi_da, 
                $so_luong, 
                $ngay_bat_dau, 
                $ngay_ket_thuc, 
                $id_nguoiban,
                $ap_dung_sanpham, 
                $ap_dung_loai, 
                $trang_thai
            );
            
            // Thực thi câu lệnh
            $stmt->execute();
            $promo_id = $conn->insert_id;
            
            // Thêm sản phẩm áp dụng (nếu có)
            if ($ap_dung_sanpham && !empty($san_pham_ap_dung)) {
                $values = [];
                $params = [];
                $types = "";
                
                foreach ($san_pham_ap_dung as $product_id) {
                    $values[] = "(?, ?)";
                    $params[] = $promo_id;
                    $params[] = $product_id;
                    $types .= "ii";
                }
                
                $product_query = "INSERT INTO khuyen_mai_sanpham (id_khuyen_mai, id_sanpham) VALUES " . implode(", ", $values);
                $product_stmt = $conn->prepare($product_query);
                $product_stmt->bind_param($types, ...$params);
                $product_stmt->execute();
            }
            
            // Thêm danh mục áp dụng (nếu có)
            if ($ap_dung_loai && !empty($loai_ap_dung)) {
                $values = [];
                $params = [];
                $types = "";
                
                foreach ($loai_ap_dung as $category_id) {
                    $values[] = "(?, ?)";
                    $params[] = $promo_id;
                    $params[] = $category_id;
                    $types .= "ii";
                }
                
                $category_query = "INSERT INTO khuyen_mai_loai (id_khuyen_mai, id_loai) VALUES " . implode(", ", $values);
                $category_stmt = $conn->prepare($category_query);
                $category_stmt->bind_param($types, ...$params);
                $category_stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Ghi log hoạt động
            try {
                $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 
                            (isset($_SESSION['id_admin']) ? $_SESSION['id_admin'] : 0);
                            
                logAdminActivity($conn, $admin_id, 'add', 'promo', $promo_id, 'Thêm mã giảm giá mới: ' . $ma_code);
            } catch (Exception $e) {
                // Log lỗi nhưng không ngắt tiến trình
                error_log('Không thể ghi log hoạt động: ' . $e->getMessage());
            }
            
            $_SESSION['success_message'] = "Thêm mã giảm giá thành công!";
            header("Location: khuyen-mai.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $errors[] = "Lỗi: " . $e->getMessage();
        }
    }
}

// Lấy danh sách danh mục sản phẩm
$categories_query = $conn->query("SELECT * FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
$categories = [];
while ($category = $categories_query->fetch_assoc()) {
    $categories[] = $category;
}

// Lấy danh sách sản phẩm
$products_query = $conn->query("
    SELECT sp.id_sanpham, sp.tensanpham, sp.hinhanh, sp.gia, lsp.tenloai 
    FROM sanpham sp
    JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
    WHERE sp.trangthai = 1
    ORDER BY sp.tensanpham
    LIMIT 100
");
$products = [];
while ($product = $products_query->fetch_assoc()) {
    $products[] = $product;
}

// Thêm sidebar vào đây
include('includes/sidebar.php');
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Thêm Mã Giảm Giá Mới</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="khuyen-mai.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách
            </a>
        </div>
    </div>
    
    <p class="mb-4">Tạo mã giảm giá mới cho khách hàng</p>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-circle me-2"></i> Có lỗi xảy ra:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Thông tin mã giảm giá</h6>
        </div>
        <div class="card-body">
            <form action="" method="post" id="addPromoForm">
                <!-- Thông tin cơ bản -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="text-gray-800 mb-3">Thông tin cơ bản</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="ma_code" class="form-label">Mã giảm giá <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ma_code" name="ma_code" value="<?php echo isset($ma_code) ? htmlspecialchars($ma_code) : ''; ?>" required>
                            <small class="form-text text-muted">Mã giảm giá nên ngắn gọn, dễ nhớ (VD: SUMMER2025)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mo_ta" class="form-label">Mô tả</label>
                            <textarea class="form-control" id="mo_ta" name="mo_ta" rows="3"><?php echo isset($mo_ta) ? htmlspecialchars($mo_ta) : ''; ?></textarea>
                            <small class="form-text text-muted">Mô tả ngắn gọn về mã giảm giá này</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="loai_giam_gia" class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                            <select class="form-select" id="loai_giam_gia" name="loai_giam_gia" required>
                                <option value="1" <?php echo isset($loai_giam_gia) && $loai_giam_gia == 1 ? 'selected' : ''; ?>>Giảm theo phần trăm (%)</option>
                                <option value="2" <?php echo isset($loai_giam_gia) && $loai_giam_gia == 2 ? 'selected' : ''; ?>>Giảm giá trị cố định (VNĐ)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="giaTriContainer">
                            <label for="gia_tri" class="form-label">Giá trị giảm <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri" name="gia_tri" min="0" step="0.01" value="<?php echo isset($gia_tri) ? $gia_tri : ''; ?>" required>
                                <span class="input-group-text" id="giaTriDonVi">%</span>
                            </div>
                            <small class="form-text text-muted" id="giaTriHelp">Nhập phần trăm giảm giá (VD: 10 cho 10%)</small>
                        </div>
                        
                        <div class="mb-3" id="giaTriGiamToiDaContainer">
                            <label for="gia_tri_giam_toi_da" class="form-label">Giảm tối đa</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri_giam_toi_da" name="gia_tri_giam_toi_da" min="0" value="<?php echo isset($gia_tri_giam_toi_da) ? $gia_tri_giam_toi_da : ''; ?>">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <small class="form-text text-muted">Giới hạn số tiền giảm tối đa (chỉ áp dụng khi giảm theo %)</small>
                        </div>
                    </div>
                </div>
                
                <!-- Điều kiện áp dụng -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="text-gray-800 mb-3">Điều kiện áp dụng</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="gia_tri_don_toi_thieu" class="form-label">Giá trị đơn hàng tối thiểu</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri_don_toi_thieu" name="gia_tri_don_toi_thieu" min="0" value="<?php echo isset($gia_tri_don_toi_thieu) ? $gia_tri_don_toi_thieu : '0'; ?>">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <small class="form-text text-muted">Đơn hàng phải có giá trị tối thiểu để được áp dụng (0 = không giới hạn)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ap_dung_cho" class="form-label">Áp dụng cho</label>
                            <select class="form-select" id="ap_dung_cho" name="ap_dung_cho">
                                <option value="0" <?php echo isset($ap_dung_cho) && $ap_dung_cho == 0 ? 'selected' : ''; ?>>Toàn bộ sản phẩm</option>
                                <option value="1" <?php echo isset($ap_dung_cho) && $ap_dung_cho == 1 ? 'selected' : ''; ?>>Sản phẩm cụ thể</option>
                                <option value="2" <?php echo isset($ap_dung_cho) && $ap_dung_cho == 2 ? 'selected' : ''; ?>>Danh mục sản phẩm</option>
                            </select>
                        </div>
                        
                        <div id="san_pham_container" class="mb-3 d-none">
                            <label for="san_pham_ap_dung" class="form-label">Chọn sản phẩm áp dụng</label>
                            <select class="form-select" id="san_pham_ap_dung" name="san_pham_ap_dung[]" multiple data-live-search="true">
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id_sanpham']; ?>">
                                    <?php echo htmlspecialchars($product['tensanpham']) . ' - ' . number_format($product['gia'], 0, ',', '.') . '₫'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Giữ Ctrl để chọn nhiều sản phẩm</small>
                        </div>
                        
                        <div id="danh_muc_container" class="mb-3 d-none">
                            <label for="loai_ap_dung" class="form-label">Chọn danh mục áp dụng</label>
                            <select class="form-select" id="loai_ap_dung" name="loai_ap_dung[]" multiple>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id_loai']; ?>">
                                    <?php echo htmlspecialchars($category['tenloai']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Giữ Ctrl để chọn nhiều danh mục</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="so_luong" class="form-label">Số lượng mã giảm giá</label>
                            <input type="number" class="form-control" id="so_luong" name="so_luong" min="0" value="<?php echo isset($so_luong) ? $so_luong : '0'; ?>">
                            <small class="form-text text-muted">Đặt 0 nếu không giới hạn số lượng</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ngay_bat_dau" class="form-label">Thời gian hiệu lực <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col">
                                    <div class="input-group">
                                        <span class="input-group-text">Bắt đầu</span>
                                        <input type="date" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau" value="<?php echo isset($ngay_bat_dau) ? $ngay_bat_dau : date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="input-group">
                                        <span class="input-group-text">Kết thúc</span>
                                        <input type="date" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc" value="<?php echo isset($ngay_ket_thuc) ? $ngay_ket_thuc : date('Y-m-d', strtotime('+30 days')); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="trang_thai" name="trang_thai" <?php echo !isset($trang_thai) || $trang_thai == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="trang_thai">Kích hoạt mã giảm giá</label>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <a href="khuyen-mai.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Tạo mã giảm giá
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý thay đổi loại giảm giá
    const loaiGiamGiaSelect = document.getElementById('loai_giam_gia');
    const giaTriDonVi = document.getElementById('giaTriDonVi');
    const giaTriHelp = document.getElementById('giaTriHelp');
    const giaTriGiamToiDaContainer = document.getElementById('giaTriGiamToiDaContainer');
    
    loaiGiamGiaSelect.addEventListener('change', function() {
        if (this.value === '1') { // Phần trăm
            giaTriDonVi.textContent = '%';
            giaTriHelp.textContent = 'Nhập phần trăm giảm giá (VD: 10 cho 10%)';
            giaTriGiamToiDaContainer.classList.remove('d-none');
        } else { // Cố định
            giaTriDonVi.textContent = 'VNĐ';
            giaTriHelp.textContent = 'Nhập số tiền giảm giá cố định';
            giaTriGiamToiDaContainer.classList.add('d-none');
        }
    });
    
    // Xử lý thay đổi loại áp dụng
    const apDungChoSelect = document.getElementById('ap_dung_cho');
    const sanPhamContainer = document.getElementById('san_pham_container');
    const danhMucContainer = document.getElementById('danh_muc_container');
    
    apDungChoSelect.addEventListener('change', function() {
        if (this.value === '1') { // Sản phẩm cụ thể
            sanPhamContainer.classList.remove('d-none');
            danhMucContainer.classList.add('d-none');
        } else if (this.value === '2') { // Danh mục
            sanPhamContainer.classList.add('d-none');
            danhMucContainer.classList.remove('d-none');
        } else { // Toàn bộ
            sanPhamContainer.classList.add('d-none');
            danhMucContainer.classList.add('d-none');
        }
    });
    
    // Kích hoạt select2 cho dropdown sản phẩm và danh mục
    $(document).ready(function() {
        $('#san_pham_ap_dung').select2({
            placeholder: 'Chọn sản phẩm áp dụng',
            width: '100%'
        });
        
        $('#loai_ap_dung').select2({
            placeholder: 'Chọn danh mục áp dụng',
            width: '100%'
        });
    });
    
    // Hiển thị/ẩn các phần tùy thuộc vào giá trị ban đầu
    if (loaiGiamGiaSelect.value === '1') {
        giaTriDonVi.textContent = '%';
        giaTriHelp.textContent = 'Nhập phần trăm giảm giá (VD: 10 cho 10%)';
        giaTriGiamToiDaContainer.classList.remove('d-none');
    } else {
        giaTriDonVi.textContent = 'VNĐ';
        giaTriHelp.textContent = 'Nhập số tiền giảm giá cố định';
        giaTriGiamToiDaContainer.classList.add('d-none');
    }
    
    if (apDungChoSelect.value === '1') {
        sanPhamContainer.classList.remove('d-none');
        danhMucContainer.classList.add('d-none');
    } else if (apDungChoSelect.value === '2') {
        sanPhamContainer.classList.add('d-none');
        danhMucContainer.classList.remove('d-none');
    } else {
        sanPhamContainer.classList.add('d-none');
        danhMucContainer.classList.add('d-none');
    }
});
</script>

<?php include('includes/footer.php'); ?>

<?php
// Đặt dòng này ở cuối file
ob_end_flush();
?>