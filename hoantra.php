<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Kiểm tra thông tin đơn hàng và sản phẩm
$stmt = $conn->prepare("
    SELECT dh.*, dhct.*, sp.tensanpham, sp.hinhanh 
    FROM donhang dh
    JOIN donhang_chitiet dhct ON dh.id_donhang = dhct.id_donhang  
    JOIN sanpham sp ON dhct.id_sanpham = sp.id_sanpham
    WHERE dh.id_donhang = ? 
    AND dhct.id_sanpham = ?
    AND dh.id_nguoidung = ?
    AND dh.trangthai = 4 /* Chỉ cho phép hoàn trả đơn hàng đã giao */
");
$stmt->bind_param("iii", $order_id, $product_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Không tìm thấy thông tin hợp lệ
    header('Location: donhang.php');
    exit();
}

$order_item = $result->fetch_assoc();

// Xử lý form submit
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lydo = isset($_POST['lydo']) ? trim($_POST['lydo']) : '';
    $mota_chitiet = isset($_POST['mota_chitiet']) ? trim($_POST['mota_chitiet']) : '';
    
    // Validate input
    if (empty($lydo)) {
        $errorMsg = "Vui lòng chọn lý do hoàn trả!";
    } elseif (empty($mota_chitiet)) {
        $errorMsg = "Vui lòng mô tả chi tiết vấn đề!";
    } else {
        // Kiểm tra xem đã có yêu cầu hoàn trả cho sản phẩm này trong đơn hàng chưa
        $check_stmt = $conn->prepare("
            SELECT id_hoantra FROM hoantra 
            WHERE id_donhang = ? AND id_sanpham = ?
        ");
        $check_stmt->bind_param("ii", $order_id, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errorMsg = "Bạn đã gửi yêu cầu hoàn trả cho sản phẩm này rồi!";
        } else {
            // Tạo yêu cầu hoàn trả mới
            $insert_stmt = $conn->prepare("
                INSERT INTO hoantra (id_donhang, id_sanpham, id_nguoidung, lydo, mota_chitiet, trangthai, ngaytao)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $insert_stmt->bind_param("iiiss", $order_id, $product_id, $user_id, $lydo, $mota_chitiet);
            
            if ($insert_stmt->execute()) {
                $successMsg = "Yêu cầu hoàn trả đã được gửi thành công!";
            } else {
                $errorMsg = "Đã xảy ra lỗi khi xử lý yêu cầu. Vui lòng thử lại sau!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yêu cầu hoàn trả - Bug Shop</title>
    <?php include('includes/head.php'); ?>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <h1 class="mb-4">Yêu cầu hoàn trả sản phẩm</h1>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="taikhoan.php">Tài khoản</a></li>
                <li class="breadcrumb-item"><a href="donhang.php">Đơn hàng của tôi</a></li>
                <li class="breadcrumb-item"><a href="chitietdonhang.php?id=<?php echo $order_id; ?>">Đơn hàng #<?php echo $order_id; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Hoàn trả sản phẩm</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Thông tin sản phẩm -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Thông tin sản phẩm hoàn trả</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <img src="<?php echo !empty($order_item['hinhanh']) ? 'uploads/products/' . $order_item['hinhanh'] : 'images/no-image.png'; ?>" 
                                 alt="<?php echo $order_item['tensanpham']; ?>" 
                                 class="rounded me-3" style="width: 100px;">
                            <div>
                                <h5><?php echo $order_item['tensanpham']; ?></h5>
                                <p class="mb-1">
                                    <strong>Đơn hàng:</strong> #<?php echo $order_id; ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Số lượng:</strong> <?php echo $order_item['soluong']; ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Giá:</strong> <?php echo number_format($order_item['gia'], 0, ',', '.'); ?>₫
                                </p>
                                <p class="mb-0">
                                    <strong>Thành tiền:</strong> <?php echo number_format($order_item['thanh_tien'], 0, ',', '.'); ?>₫
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if($successMsg): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $successMsg; ?>
                    <div class="mt-3">
                        <a href="chitietdonhang.php?id=<?php echo $order_id; ?>" class="btn btn-primary">
                            Trở về chi tiết đơn hàng
                        </a>
                    </div>
                </div>
                <?php else: ?>
                
                <!-- Form hoàn trả -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Thông tin hoàn trả</h5>
                    </div>
                    <div class="card-body">
                        <?php if($errorMsg): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $errorMsg; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="lydo" class="form-label">Lý do hoàn trả <span class="text-danger">*</span></label>
                                <select class="form-select" id="lydo" name="lydo" required>
                                    <option value="">-- Chọn lý do --</option>
                                    <option value="Sản phẩm bị lỗi/hỏng">Sản phẩm bị lỗi/hỏng</option>
                                    <option value="Sản phẩm không đúng mô tả">Sản phẩm không đúng mô tả</option>
                                    <option value="Sản phẩm không đúng kích thước">Sản phẩm không đúng kích thước</option>
                                    <option value="Sản phẩm không đúng màu sắc">Sản phẩm không đúng màu sắc</option>
                                    <option value="Nhận được sai sản phẩm">Nhận được sai sản phẩm</option>
                                    <option value="Khác">Lý do khác</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mota_chitiet" class="form-label">Mô tả chi tiết <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="mota_chitiet" name="mota_chitiet" rows="5" placeholder="Vui lòng mô tả chi tiết vấn đề của sản phẩm..." required></textarea>
                                <div class="form-text">Vui lòng cung cấp càng nhiều chi tiết càng tốt để chúng tôi có thể hỗ trợ bạn nhanh chóng.</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-flex justify-content-end">
                                <a href="chitietdonhang.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                                    Hủy
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Gửi yêu cầu hoàn trả
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Chính sách hoàn trả -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Chính sách hoàn trả</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Thời gian yêu cầu hoàn trả: trong vòng 7 ngày kể từ ngày nhận hàng
                            </li>
                            <li class="list-group-item px-0">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Sản phẩm phải còn nguyên tem, nhãn và chưa qua sử dụng
                            </li>
                            <li class="list-group-item px-0">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Cung cấp đầy đủ hình ảnh, video chứng minh lỗi sản phẩm
                            </li>
                            <li class="list-group-item px-0">
                                <i class="bi bi-exclamation-circle-fill text-warning me-2"></i>
                                Thời gian xử lý yêu cầu hoàn trả từ 3-5 ngày làm việc
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Các bước hoàn trả -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Quy trình hoàn trả</h5>
                    </div>
                    <div class="card-body">
                        <ol class="list-group list-group-numbered">
                            <li class="list-group-item border-0 px-0">Gửi yêu cầu hoàn trả</li>
                            <li class="list-group-item border-0 px-0">Chờ xác nhận từ shop</li>
                            <li class="list-group-item border-0 px-0">Nhận thông tin địa chỉ trả hàng</li>
                            <li class="list-group-item border-0 px-0">Đóng gói và gửi trả sản phẩm</li>
                            <li class="list-group-item border-0 px-0">Shop kiểm tra sản phẩm hoàn trả</li>
                            <li class="list-group-item border-0 px-0">Nhận hoàn tiền hoặc đổi sản phẩm mới</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>