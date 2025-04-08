<?php
// filepath: c:\xampp\htdocs\bug_shop\get_product_quick_view.php
// Kết nối CSDL
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "shop_vippro";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Lấy ID sản phẩm từ request
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    echo '<div class="alert alert-danger">ID sản phẩm không hợp lệ.</div>';
    exit;
}

// Truy vấn thông tin sản phẩm
$sql = "SELECT s.*, l.tenloai, t.tenthuonghieu, AVG(dg.diemdanhgia) as diem_trung_binh 
        FROM sanpham s 
        LEFT JOIN danhgia dg ON s.id_sanpham = dg.id_sanpham 
        LEFT JOIN loaisanpham l ON s.id_loai = l.id_loai
        LEFT JOIN thuonghieu t ON s.id_thuonghieu = t.id_thuonghieu
        WHERE s.id_sanpham = ? AND s.trangthai = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-warning">Không tìm thấy sản phẩm.</div>';
    exit;
}

$product = $result->fetch_assoc();

// Lấy kích thước và màu sắc có sẵn
$sql_variants = "SELECT DISTINCT k.id_kichthuoc, k.tenkichthuoc, m.id_mausac, m.tenmau, m.mamau
                FROM sanpham_chitiet sc
                JOIN kichthuoc k ON sc.id_kichthuoc = k.id_kichthuoc
                JOIN mausac m ON sc.id_mausac = m.id_mausac
                WHERE sc.id_sanpham = ? AND sc.soluong > 0
                ORDER BY k.tenkichthuoc, m.tenmau";

$stmt = $conn->prepare($sql_variants);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result_variants = $stmt->get_result();

$sizes = [];
$colors = [];

while ($row = $result_variants->fetch_assoc()) {
    $sizes[$row['id_kichthuoc']] = $row['tenkichthuoc'];
    $colors[$row['id_mausac']] = [
        'name' => $row['tenmau'],
        'code' => $row['mamau']
    ];
}

// Lấy hình ảnh màu sắc
$sql_images = "SELECT mh.id_mausac, mh.hinhanh 
               FROM mausac_hinhanh mh
               WHERE mh.id_sanpham = ?";

$stmt = $conn->prepare($sql_images);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result_images = $stmt->get_result();

$color_images = [];
while ($row = $result_images->fetch_assoc()) {
    $color_images[$row['id_mausac']] = $row['hinhanh'];
}

// Xử lý hình ảnh
$main_image = !empty($product['hinhanh']) ? 'images/products/' . $product['hinhanh'] : 'images/no-image.jpg';

// Tính điểm đánh giá trung bình
$rating = round($product['diem_trung_binh']);
if (is_null($rating)) $rating = 0;

// Tính phần trăm giảm giá
$discount_percent = 0;
if ($product['giagoc'] > 0 && $product['giagoc'] > $product['gia']) {
    $discount_percent = round(100 - ($product['gia'] / $product['giagoc'] * 100));
}

$stmt->close();
$conn->close();
?>

<div class="row">
    <div class="col-md-5">
        <div class="quick-view-image">
            <img src="<?php echo $main_image; ?>" class="img-fluid" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
            <?php if ($discount_percent > 0): ?>
            <div class="sale-tag">-<?php echo $discount_percent; ?>%</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-7">
        <h4><?php echo htmlspecialchars($product['tensanpham']); ?></h4>
        
        <div class="d-flex align-items-center mb-3">
            <div class="rating me-2">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star<?php echo ($i <= $rating) ? '-fill' : ''; ?> text-warning"></i>
                <?php endfor; ?>
            </div>
            <span class="text-muted">(<?php echo $product['soluong_danhgia']; ?> đánh giá)</span>
            <span class="mx-3">|</span>
            <span class="text-success"><i class="bi bi-box-seam"></i> Còn hàng</span>
        </div>
        
        <div class="price-wrapper mb-3">
            <span class="text-danger fw-bold fs-4"><?php echo number_format($product['gia'], 0, ',', '.'); ?>₫</span>
            <?php if ($product['giagoc'] > 0 && $product['giagoc'] > $product['gia']): ?>
            <span class="text-decoration-line-through text-muted ms-2"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?>₫</span>
            <?php endif; ?>
        </div>
        
        <div class="product-info mb-3">
            <?php if (!empty($product['tenloai'])): ?>
            <p><strong>Loại sản phẩm:</strong> <?php echo htmlspecialchars($product['tenloai']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($product['tenthuonghieu'])): ?>
            <p><strong>Thương hiệu:</strong> <?php echo htmlspecialchars($product['tenthuonghieu']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($product['mota'])): ?>
        <div class="product-description mb-3">
            <p><?php echo nl2br(htmlspecialchars(substr($product['mota'], 0, 200))); ?>...</p>
        </div>
        <?php endif; ?>
        
        <form class="product-form">
            <?php if (!empty($colors)): ?>
            <div class="mb-3">
                <label class="form-label">Màu sắc:</label>
                <div class="color-options">
                    <?php foreach ($colors as $id => $color): ?>
                    <div class="color-option" data-color-id="<?php echo $id; ?>" style="background-color: <?php echo $color['code']; ?>"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="color_id" id="selected_color">
            </div>
            <?php endif; ?>
            
            <?php if (!empty($sizes)): ?>
            <div class="mb-3">
                <label class="form-label">Kích thước:</label>
                <div class="size-options">
                    <?php foreach ($sizes as $id => $size): ?>
                    <div class="size-option" data-size-id="<?php echo $id; ?>"><?php echo $size; ?></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="size_id" id="selected_size">
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Số lượng:</label>
                <div class="quantity-selector">
                    <button type="button" class="btn-decrease">-</button>
                    <input type="number" name="quantity" class="product-quantity" value="1" min="1" max="<?php echo $product['soluong']; ?>">
                    <button type="button" class="btn-increase">+</button>
                </div>
            </div>
            
            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id_sanpham']; ?>">
                    <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                </button>
                <a href="product-detail.php?id=<?php echo $product['id_sanpham']; ?>" class="btn btn-outline-dark">
                    Xem chi tiết
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.quick-view-image {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
}

.sale-tag {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
}

.color-options, .size-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 5px;
}

.color-option {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.color-option:hover, .color-option.active {
    border-color: #333;
    transform: scale(1.1);
}

.size-option {
    padding: 5px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.size-option:hover, .size-option.active {
    border-color: #0d6efd;
    background-color: #e9f0ff;
}

.quantity-selector {
    display: flex;
    align-items: center;
    width: 120px;
}

.quantity-selector .product-quantity {
    width: 50px;
    text-align: center;
    border: 1px solid #ddd;
    border-radius: 0;
    height: 38px;
    -moz-appearance: textfield;
}

.quantity-selector .product-quantity::-webkit-outer-spin-button,
.quantity-selector .product-quantity::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.btn-decrease, .btn-increase {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    width: 35px;
    height: 38px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-decrease {
    border-radius: 4px 0 0 4px;
}

.btn-increase {
    border-radius: 0 4px 4px 0;
}
</style>

<script>
// Xử lý chọn màu
document.querySelectorAll('.color-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.color-option').forEach(item => {
            item.classList.remove('active');
        });
        this.classList.add('active');
        document.getElementById('selected_color').value = this.getAttribute('data-color-id');
    });
});

// Xử lý chọn kích thước
document.querySelectorAll('.size-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.size-option').forEach(item => {
            item.classList.remove('active');
        });
        this.classList.add('active');
        document.getElementById('selected_size').value = this.getAttribute('data-size-id');
    });
});

// Xử lý chọn số lượng
document.querySelector('.btn-decrease').addEventListener('click', function() {
    var input = document.querySelector('.product-quantity');
    var value = parseInt(input.value);
    if (value > 1) {
        input.value = value - 1;
    }
});

document.querySelector('.btn-increase').addEventListener('click', function() {
    var input = document.querySelector('.product-quantity');
    var value = parseInt(input.value);
    var max = parseInt(input.getAttribute('max'));
    if (value < max) {
        input.value = value + 1;
    }
});
</script>