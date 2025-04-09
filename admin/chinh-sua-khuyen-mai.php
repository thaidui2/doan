<?php
ob_start(); // Thêm dòng này để tránh lỗi headers
// Thiết lập tiêu đề trang
$page_title = "Chỉnh Sửa Mã Giảm Giá";

// Include header
include('includes/header.php');

// Kiểm tra quyền truy cập
if (!hasPermission('promo_edit')) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này!";
    header("Location: khuyen-mai.php");
    exit();
}

// Lấy ID mã giảm giá từ tham số URL
$promo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($promo_id <= 0) {
    $_SESSION['error_message'] = "ID mã giảm giá không hợp lệ!";
    header("Location: khuyen-mai.php");
    exit();
}

// Lấy thông tin mã giảm giá
$promo_query = $conn->prepare("SELECT * FROM khuyen_mai WHERE id = ?");
$promo_query->bind_param("i", $promo_id);
$promo_query->execute();
$promo_result = $promo_query->get_result();

if ($promo_result->num_rows === 0) {
    $_SESSION['error_message'] = "Không tìm thấy mã giảm giá!";
    header("Location: khuyen-mai.php");
    exit();
}

$promo = $promo_result->fetch_assoc();

// Lấy danh sách sản phẩm đã áp dụng
$applied_products = [];
$product_query = $conn->prepare("
    SELECT kmsp.id_sanpham
    FROM khuyen_mai_sanpham kmsp
    WHERE kmsp.id_khuyen_mai = ?
");
$product_query->bind_param("i", $promo_id);
$product_query->execute();
$product_result = $product_query->get_result();

while ($row = $product_result->fetch_assoc()) {
    $applied_products[] = $row['id_sanpham'];
}

// Lấy danh sách loại sản phẩm đã áp dụng
$applied_categories = [];
$category_query = $conn->prepare("
    SELECT kml.id_loai
    FROM khuyen_mai_loai kml
    WHERE kml.id_khuyen_mai = ?
");
$category_query->bind_param("i", $promo_id);
$category_query->execute();
$category_result = $category_query->get_result();

while ($row = $category_result->fetch_assoc()) {
    $applied_categories[] = $row['id_loai'];
}

// Xác định loại áp dụng
$ap_dung_cho = 0;
if ($promo['ap_dung_sanpham'] == 1) {
    $ap_dung_cho = 1;
} elseif ($promo['ap_dung_loai'] == 1) {
    $ap_dung_cho = 2;
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ma_code = $promo['ma_code']; // Không cho phép thay đổi mã
    $mo_ta = trim($_POST['mo_ta']);
    $loai_giam_gia = (int)$_POST['loai_giam_gia'];
    $gia_tri = (float)$_POST['gia_tri'];
    $gia_tri_giam_toi_da = !empty($_POST['gia_tri_giam_toi_da']) ? (float)$_POST['gia_tri_giam_toi_da'] : 0;
    $gia_tri_don_toi_thieu = !empty($_POST['gia_tri_don_toi_thieu']) ? (float)$_POST['gia_tri_don_toi_thieu'] : 0;
    $so_luong = !empty($_POST['so_luong']) ? (int)$_POST['so_luong'] : 0;
    $so_luong_da_dung = $promo['so_luong_da_dung'];
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    $ap_dung_cho = isset($_POST['ap_dung_cho']) ? (int)$_POST['ap_dung_cho'] : 0;
    
    // Kiểm tra dữ liệu đầu vào
    $errors = [];
    
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
    
    // Kiểm tra số lượng đã sử dụng
    if ($so_luong > 0 && $so_luong < $so_luong_da_dung) {
        $errors[] = "Số lượng mã giảm giá không thể ít hơn số lượng đã sử dụng ({$so_luong_da_dung})";
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
    
    // Nếu không có lỗi, cập nhật mã giảm giá trong database
    if (empty($errors)) {
        try {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            // Cập nhật mã giảm giá
            $stmt = $conn->prepare("
                UPDATE khuyen_mai SET 
                    mo_ta = ?, loai_giam_gia = ?, gia_tri = ?, gia_tri_giam_toi_da = ?, 
                    gia_tri_don_toi_thieu = ?, so_luong = ?, ngay_bat_dau = ?, 
                    ngay_ket_thuc = ?, trang_thai = ?, ap_dung_sanpham = ?, ap_dung_loai = ?,
                    ngay_capnhat = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param(
                "sidddissiiiii",
                $mo_ta, $loai_giam_gia, $gia_tri, $gia_tri_giam_toi_da,
                $gia_tri_don_toi_thieu, $so_luong, $ngay_bat_dau, $ngay_ket_thuc,
                $trang_thai, $ap_dung_sanpham, $ap_dung_loai, $promo_id
            );
            
            $stmt->execute();
            
            // Xóa sản phẩm áp dụng cũ
            $delete_products = $conn->prepare("DELETE FROM khuyen_mai_sanpham WHERE id_khuyen_mai = ?");
            $delete_products->bind_param("i", $promo_id);
            $delete_products->execute();
            
            // Thêm sản phẩm áp dụng mới
            if ($ap_dung_sanpham && !empty($san_pham_ap_dung)) {
                $product_stmt = $conn->prepare("INSERT INTO khuyen_mai_sanpham (id_khuyen_mai, id_sanpham) VALUES (?, ?)");
                
                foreach ($san_pham_ap_dung as $product_id) {
                    $product_stmt->bind_param("ii", $promo_id, $product_id);
                    $product_stmt->execute();
                }
            }
            
            // Xóa loại sản phẩm áp dụng cũ
            $delete_categories = $conn->prepare("DELETE FROM khuyen_mai_loai WHERE id_khuyen_mai = ?");
            $delete_categories->bind_param("i", $promo_id);
            $delete_categories->execute();
            
            // Thêm loại sản phẩm áp dụng mới
            if ($ap_dung_loai && !empty($loai_ap_dung)) {
                $category_stmt = $conn->prepare("INSERT INTO khuyen_mai_loai (id_khuyen_mai, id_loai) VALUES (?, ?)");
                
                foreach ($loai_ap_dung as $category_id) {
                    $category_stmt->bind_param("ii", $promo_id, $category_id);
                    $category_stmt->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Ghi log hành động
            logAction('edit_promo', 'Chỉnh sửa mã giảm giá: ' . $ma_code);
            
            $_SESSION['success_message'] = "Cập nhật mã giảm giá thành công!";
            header("Location: khuyen-mai.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            $errors[] = "Lỗi: " . $e->getMessage();
        }
    }
}

// Lấy danh sách sản phẩm cho dropdown
$products_query = $conn->query("
    SELECT sp.id_sanpham, sp.tensanpham, sp.gia
    FROM sanpham sp
    WHERE sp.trangthai = 1
    ORDER BY sp.tensanpham ASC
");

$products = [];
while ($product = $products_query->fetch_assoc()) {
    $products[] = $product;
}

// Lấy danh sách danh mục cho dropdown
$categories_query = $conn->query("
    SELECT l.id_loai, l.tenloai
    FROM loaisanpham l
    WHERE l.trangthai = 1
    ORDER BY l.tenloai ASC
");

$categories = [];
while ($category = $categories_query->fetch_assoc()) {
    $categories[] = $category;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Chỉnh sửa mã giảm giá</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="khuyen-mai.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="chinh-sua-khuyen-mai.php?id=<?php echo $promo_id; ?>" method="post" id="editPromoForm">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="ma_code" class="form-label">Mã giảm giá:</label>
                    <input type="text" class="form-control" id="ma_code" value="<?php echo htmlspecialchars($promo['ma_code']); ?>" readonly>
                    <small class="text-muted">Mã giảm giá không thể thay đổi sau khi tạo</small>
                </div>
                
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" <?php echo $promo['trang_thai'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="trang_thai">Kích hoạt mã giảm giá</label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="loai_giam_gia" class="form-label">Loại giảm giá:</label>
                    <select class="form-select" id="loai_giam_gia" name="loai_giam_gia" required>
                        <option value="1" <?php echo $promo['loai_giam_gia'] == 1 ? 'selected' : ''; ?>>Giảm theo phần trăm (%)</option>
                        <option value="2" <?php echo $promo['loai_giam_gia'] == 2 ? 'selected' : ''; ?>>Giảm theo số tiền cố định</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="gia_tri" class="form-label">Giá trị giảm:</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="gia_tri" name="gia_tri" value="<?php echo $promo['gia_tri']; ?>" step="0.01" min="0" required>
                        <span class="input-group-text" id="discount-unit"><?php echo $promo['loai_giam_gia'] == 1 ? '%' : 'VNĐ'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="gia_tri_don_toi_thieu" class="form-label">Giá trị đơn hàng tối thiểu:</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="gia_tri_don_toi_thieu" name="gia_tri_don_toi_thieu" value="<?php echo $promo['gia_tri_don_toi_thieu']; ?>" step="1000" min="0">
                        <span class="input-group-text">VNĐ</span>
                    </div>
                    <small class="text-muted">Để 0 nếu không có giới hạn</small>
                </div>
                
                <div class="col-md-4">
                    <label for="gia_tri_giam_toi_da" class="form-label">Giá trị giảm tối đa:</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="gia_tri_giam_toi_da" name="gia_tri_giam_toi_da" value="<?php echo $promo['gia_tri_giam_toi_da']; ?>" step="1000" min="0">
                        <span class="input-group-text">VNĐ</span>
                    </div>
                    <small class="text-muted">Áp dụng khi giảm theo %. Để 0 nếu không có giới hạn</small>
                </div>
                
                <div class="col-md-4">
                    <label for="so_luong" class="form-label">Số lượng mã:</label>
                    <input type="number" class="form-control" id="so_luong" name="so_luong" value="<?php echo $promo['so_luong']; ?>" min="<?php echo $promo['so_luong_da_dung']; ?>">
                    <small class="text-muted">Để 0 nếu không giới hạn. Đã sử dụng: <?php echo $promo['so_luong_da_dung']; ?></small>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="ngay_bat_dau" class="form-label">Ngày bắt đầu:</label>
                    <input type="datetime-local" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau" value="<?php echo str_replace(' ', 'T', $promo['ngay_bat_dau']); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="ngay_ket_thuc" class="form-label">Ngày kết thúc:</label>
                    <input type="datetime-local" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc" value="<?php echo str_replace(' ', 'T', $promo['ngay_ket_thuc']); ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="mo_ta" class="form-label">Mô tả:</label>
                <textarea class="form-control" id="mo_ta" name="mo_ta" rows="2"><?php echo htmlspecialchars($promo['mo_ta']); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Áp dụng cho:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="ap_dung_cho" id="ap_dung_cho_0" value="0" <?php echo $ap_dung_cho == 0 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ap_dung_cho_0">
                        Tất cả sản phẩm
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="ap_dung_cho" id="ap_dung_cho_1" value="1" <?php echo $ap_dung_cho == 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ap_dung_cho_1">
                        Sản phẩm cụ thể
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="ap_dung_cho" id="ap_dung_cho_2" value="2" <?php echo $ap_dung_cho == 2 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ap_dung_cho_2">
                        Danh mục sản phẩm
                    </label>
                </div>
            </div>
            
            <!-- Chọn sản phẩm -->
            <div id="san_pham_section" class="mb-3 card p-3" style="display: <?php echo $ap_dung_cho == 1 ? 'block' : 'none'; ?>;">
                <label class="form-label">Chọn sản phẩm áp dụng:</label>
                <select class="form-control select2" id="san_pham_ap_dung" name="san_pham_ap_dung[]" multiple>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id_sanpham']; ?>" 
                                <?php echo in_array($product['id_sanpham'], $applied_products) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['tensanpham']); ?> - 
                            <?php echo number_format($product['gia'], 0, ',', '.'); ?>₫
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Chọn danh mục -->
            <div id="danh_muc_section" class="mb-3 card p-3" style="display: <?php echo $ap_dung_cho == 2 ? 'block' : 'none'; ?>;">
                <label class="form-label">Chọn danh mục áp dụng:</label>
                <select class="form-control select2" id="loai_ap_dung" name="loai_ap_dung[]" multiple>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id_loai']; ?>" 
                                <?php echo in_array($category['id_loai'], $applied_categories) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['tenloai']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="khuyen-mai.php" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Cập nhật mã giảm giá</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý thay đổi loại giảm giá
    const loaiGiamGiaSelect = document.getElementById('loai_giam_gia');
    const discountUnitSpan = document.getElementById('discount-unit');
    const giaTriGiamToiDaGroup = document.getElementById('gia_tri_giam_toi_da').closest('.col-md-4');
    
    loaiGiamGiaSelect.addEventListener('change', function() {
        if (this.value === '1') {
            discountUnitSpan.textContent = '%';
            giaTriGiamToiDaGroup.style.display = 'block';
        } else {
            discountUnitSpan.textContent = 'VNĐ';
            giaTriGiamToiDaGroup.style.display = 'block';
        }
    });
    
    // Xử lý thay đổi áp dụng cho
    const apDungChoRadios = document.querySelectorAll('input[name="ap_dung_cho"]');
    const sanPhamSection = document.getElementById('san_pham_section');
    const danhMucSection = document.getElementById('danh_muc_section');
    
    apDungChoRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === '0') {
                sanPhamSection.style.display = 'none';
                danhMucSection.style.display = 'none';
            } else if (this.value === '1') {
                sanPhamSection.style.display = 'block';
                danhMucSection.style.display = 'none';
            } else if (this.value === '2') {
                sanPhamSection.style.display = 'none';
                danhMucSection.style.display = 'block';
            }
        });
    });
    
    // Kích hoạt thư viện Select2 nếu có
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            placeholder: 'Chọn...',
            width: '100%'
        });
    }
});
</script>

<?php 
include('includes/footer.php');
ob_end_flush(); // Kết thúc output buffering
?>