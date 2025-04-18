<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];
$product_id = isset($_GET['id_sp']) ? (int)$_GET['id_sp'] : 0;
$order_id = isset($_GET['id_dh']) ? (int)$_GET['id_dh'] : 0;

// Xử lý form gửi đánh giá
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $recommend = isset($_POST['recommend']) ? 1 : 0;
    
    // Kiểm tra dữ liệu đầu vào
    if ($rating < 1 || $rating > 5) {
        $error_message = "Vui lòng chọn số sao đánh giá từ 1-5";
    } elseif (empty($comment)) {
        $error_message = "Vui lòng nhập nội dung đánh giá";
    } else {
        // Tiếp tục xử lý...
        if ($product_id <= 0 || $order_id <= 0) {
            $error_message = "Thông tin sản phẩm hoặc đơn hàng không hợp lệ";
        } else {
            // Kiểm tra quyền đánh giá - Cập nhật schema mới
            $check_query = $conn->prepare("
                SELECT dc.id 
                FROM donhang_chitiet dc
                JOIN donhang d ON dc.id_donhang = d.id
                WHERE d.id = ? AND dc.id_sanpham = ? AND d.id_user = ? AND d.trang_thai_don_hang = 4
            ");
            $check_query->bind_param("iii", $order_id, $product_id, $user_id);
            $check_query->execute();
            $check_result = $check_query->get_result();
            
            if ($check_result->num_rows === 0) {
                $error_message = "Bạn không có quyền đánh giá sản phẩm này hoặc đơn hàng chưa được giao thành công";
            } else {
                // Kiểm tra đã đánh giá chưa
                $existing_check = $conn->prepare("
                    SELECT id FROM danhgia 
                    WHERE id_sanpham = ? AND id_user = ? AND id_donhang = ?
                ");
                $existing_check->bind_param("iii", $product_id, $user_id, $order_id);
                $existing_check->execute();
                
                if ($existing_check->get_result()->num_rows > 0) {
                    $error_message = "Bạn đã đánh giá sản phẩm này từ đơn hàng này rồi";
                } else {
                    // Xử lý upload hình ảnh (nếu có)
                    $images = [];
                    if (isset($_FILES['review_images']) && $_FILES['review_images']['error'][0] != 4) {
                        $upload_dir = "uploads/reviews/";
                        
                        // Tạo thư mục nếu chưa tồn tại
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Giới hạn số hình tối đa
                        $max_files = 3;
                        $file_count = count($_FILES['review_images']['name']);
                        $file_count = min($file_count, $max_files);
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['review_images']['error'][$i] === 0) {
                                $file_name = $_FILES['review_images']['name'][$i];
                                $file_tmp = $_FILES['review_images']['tmp_name'][$i];
                                $file_size = $_FILES['review_images']['size'][$i];
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                
                                // Kiểm tra định dạng
                                $extensions = ["jpeg", "jpg", "png", "gif", "webp"];
                                if (in_array($file_ext, $extensions)) {
                                    // Kiểm tra kích thước (2MB)
                                    if ($file_size <= 2097152) {
                                        $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
                                        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                                            $images[] = $new_file_name;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    try {
                        // Bắt đầu transaction
                        $conn->begin_transaction();
                        
                        // Lấy dữ liệu chi tiết đơn hàng
                        $chitiet_query = $conn->prepare("
                            SELECT id FROM donhang_chitiet 
                            WHERE id_donhang = ? AND id_sanpham = ? LIMIT 1
                        ");
                        $chitiet_query->bind_param("ii", $order_id, $product_id);
                        $chitiet_query->execute();
                        $chitiet_result = $chitiet_query->get_result();
                        
                        if ($chitiet_result->num_rows === 0) {
                            throw new Exception("Không tìm thấy chi tiết đơn hàng");
                        }
                        
                        $chitiet_data = $chitiet_result->fetch_assoc();
                        $chitiet_id = $chitiet_data['id'];
                        
                        // Chuẩn bị giá trị hình ảnh (NULL nếu không có)
                        $images_str = !empty($images) ? implode('|', $images) : null;
                        
                        // Lưu đánh giá - Cập nhật schema mới
                        $insert_review = $conn->prepare("
                            INSERT INTO danhgia (id_user, id_sanpham, id_donhang, diem, noi_dung, hinh_anh, khuyen_dung, ngay_danhgia, trang_thai) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
                        ");
                        $insert_review->bind_param("iiiissi", $user_id, $product_id, $order_id, $rating, $comment, $images_str, $recommend);
                        
                        $insert_result = $insert_review->execute();
                        if (!$insert_result) {
                            // Ghi log lỗi chi tiết
                            error_log("Lỗi đánh giá: " . $insert_review->error);
                            throw new Exception("Lỗi khi lưu đánh giá: " . $insert_review->error);
                        }
                        
                        // Đánh dấu sản phẩm đã được đánh giá trong đơn hàng
                        $update_order_item = $conn->prepare("
                            UPDATE donhang_chitiet 
                            SET da_danh_gia = 1 
                            WHERE id_donhang = ? AND id_sanpham = ?
                        ");
                        $update_order_item->bind_param("ii", $order_id, $product_id);
                        $update_order_item->execute();
                        
                        $conn->commit();
                        $success_message = "Cảm ơn bạn đã đánh giá sản phẩm!";
                        
                        // Chuyển về trang chi tiết đơn hàng sau 2 giây
                        header("refresh:2;url=chitiet-donhang.php?id=" . $order_id);
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = $e->getMessage();
                        
                        // Ghi log lỗi
                        error_log("Lỗi đánh giá: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

if ($product_id > 0 && $order_id > 0) {
    // Lấy thông tin sản phẩm và kiểm tra quyền đánh giá - Cập nhật schema mới
    $stmt = $conn->prepare("
        SELECT sp.id, sp.tensanpham, sp.hinhanh, 
               sbt.id_mau, sbt.id_size,
               size.gia_tri AS ten_kichthuoc, 
               color.gia_tri AS ten_mau,
               d.trang_thai_don_hang
        FROM donhang_chitiet dc
        JOIN sanpham sp ON dc.id_sanpham = sp.id
        JOIN donhang d ON dc.id_donhang = d.id
        LEFT JOIN sanpham_bien_the sbt ON dc.id_bienthe = sbt.id
        LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
        LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
        WHERE d.id = ? AND sp.id = ? AND d.id_user = ?
    ");
    $stmt->bind_param("iii", $order_id, $product_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không thể đánh giá sản phẩm này hoặc đơn hàng chưa được giao thành công";
        header('Location: donhang.php');
        exit();
    }
    
    $product_info = $result->fetch_assoc();
    
    // Kiểm tra xem người dùng đã đánh giá sản phẩm này từ đơn hàng này chưa
    $check_existing = $conn->prepare("
        SELECT id
        FROM danhgia
        WHERE id_sanpham = ? AND id_user = ? AND id_donhang = ?
    ");
    $check_existing->bind_param("iii", $product_id, $user_id, $order_id);
    $check_existing->execute();
    $existing_result = $check_existing->get_result();
    
    if ($existing_result->num_rows > 0) {
        $_SESSION['error_message'] = "Bạn đã đánh giá sản phẩm này từ đơn hàng này";
        header('Location: chitiet-donhang.php?id=' . $order_id);
        exit();
    }
    
    // Kiểm tra đơn hàng đã hoàn thành chưa
    if ($product_info['trang_thai_don_hang'] != 4) {
        $_SESSION['error_message'] = "Bạn chỉ có thể đánh giá sản phẩm khi đơn hàng đã hoàn thành";
        header('Location: chitiet-donhang.php?id=' . $order_id);
        exit();
    }
} else {
    $_SESSION['error_message'] = "Thông tin sản phẩm không hợp lệ";
    header('Location: donhang.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá sản phẩm - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .rating {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 5px;
        }
        
        .rating > input {
            display: none;
        }
        
        .rating > label {
            color: #ddd;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .rating > label:before {
            content: "\2605";
        }
        
        .rating > input:checked ~ label,
        .rating > label:hover,
        .rating > label:hover ~ label {
            color: #ffc107;
        }
        
        .product-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            display: block; /* Thêm thuộc tính này để ổn định vị trí */
            margin: 0 auto; /* Căn giữa ảnh nếu cần */
            position: absolute;
            top: 140px;
            left: 40px;
        }
        
        .flex-shrink-0 {
            display: flex;
            align-items: center;
            justify-content: center; /* Căn giữa nội dung bên trong */
        }
        
        .preview-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
        }
        
        .preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .remove-preview {
            position: absolute;
            top: -8px;
            right: -8px;
            background: rgba(255,255,255,0.7);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            text-align: center;
            line-height: 20px;
            cursor: pointer;
            font-size: 12px;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h1 class="h4 mb-0">Đánh giá sản phẩm</h1>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex mb-4 align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <?php 
                                $product_image = !empty($product_info['hinhanh']) ? 
                                    (strpos($product_info['hinhanh'], 'uploads/') !== false ? $product_info['hinhanh'] : 'uploads/products/' . $product_info['hinhanh']) : 
                                    'images/no-image.png';
                                ?>
                                <img src="<?php echo $product_image; ?>" 
                                     class="product-img rounded border" 
                                     alt="<?php echo htmlspecialchars($product_info['tensanpham']); ?>">
                            </div>
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($product_info['tensanpham']); ?></h5>
                                <?php if (!empty($product_info['ten_kichthuoc']) || !empty($product_info['ten_mau'])): ?>
                                    <p class="text-muted mb-0 small">
                                        <?php 
                                        $variants = [];
                                        if (!empty($product_info['ten_kichthuoc'])) $variants[] = "Size: " . $product_info['ten_kichthuoc'];
                                        if (!empty($product_info['ten_mau'])) $variants[] = "Màu: " . $product_info['ten_mau'];
                                        echo implode(', ', $variants);
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            
                            <div class="mb-4 text-center">
                                <p class="mb-2">Đánh giá của bạn về sản phẩm này</p>
                                <div class="rating">
                                    <input type="radio" name="rating" value="5" id="rate5" required>
                                    <label for="rate5"></label>
                                    <input type="radio" name="rating" value="4" id="rate4">
                                    <label for="rate4"></label>
                                    <input type="radio" name="rating" value="3" id="rate3">
                                    <label for="rate3"></label>
                                    <input type="radio" name="rating" value="2" id="rate2">
                                    <label for="rate2"></label>
                                    <input type="radio" name="rating" value="1" id="rate1">
                                    <label for="rate1"></label>
                                </div>
                                <div class="form-text">Click vào số sao để đánh giá</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="comment" class="form-label">Nội dung đánh giá</label>
                                <textarea class="form-control" id="comment" name="comment" rows="5" placeholder="Hãy chia sẻ trải nghiệm của bạn với sản phẩm này..." required></textarea>
                                <div class="form-text">Tối thiểu 10 ký tự</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Thêm hình ảnh (tùy chọn)</label>
                                <input type="file" class="form-control" id="review_images" name="review_images[]" 
                                       accept="image/*" multiple onchange="previewImages()">
                                <div class="form-text">Tối đa 3 hình, mỗi hình không quá 2MB</div>
                                <div id="preview-container" class="preview-container"></div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="recommend" name="recommend" checked>
                                    <label class="form-check-label" for="recommend">
                                        Tôi khuyên dùng sản phẩm này
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="chitiet-donhang.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Quay lại
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> Gửi đánh giá
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script>
        // Hàm xem trước các hình ảnh được chọn
        function previewImages() {
            const input = document.getElementById('review_images');
            const previewContainer = document.getElementById('preview-container');
            previewContainer.innerHTML = '';
            
            if (input.files) {
                const maxFiles = 3;
                const fileCount = Math.min(input.files.length, maxFiles);
                
                for (let i = 0; i < fileCount; i++) {
                    const file = input.files[i];
                    if (file) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'preview-item';
                            
                            const img = document.createElement('img');
                            img.className = 'preview-img';
                            img.src = e.target.result;
                            
                            const removeBtn = document.createElement('div');
                            removeBtn.className = 'remove-preview';
                            removeBtn.innerHTML = '<i class="bi bi-x"></i>';
                            removeBtn.onclick = function() {
                                // Xử lý phức tạp, tạm thời chỉ xóa preview
                                previewItem.remove();
                            };
                            
                            previewItem.appendChild(img);
                            previewItem.appendChild(removeBtn);
                            previewContainer.appendChild(previewItem);
                        }
                        
                        reader.readAsDataURL(file);
                    }
                }
                
                // Hiển thị thông báo nếu người dùng chọn quá nhiều file
                if (input.files.length > maxFiles) {
                    const message = document.createElement('div');
                    message.className = 'text-danger mt-2';
                    message.textContent = `Chỉ hiển thị ${maxFiles} hình đầu tiên trong số ${input.files.length đã chọn.`;
                    previewContainer.appendChild(message);
                }
            }
        }
    </script>
</body>
</html>