<?php
session_start();
include('config/config.php');

// Hàm lấy hoặc tạo giỏ hàng
function getCart($conn) {
    $session_id = session_id();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Kiểm tra xem giỏ hàng đã tồn tại chưa
    if ($user_id) {
        // Đã đăng nhập, tìm giỏ hàng theo user_id
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE id_nguoidung = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        // Chưa đăng nhập, tìm giỏ hàng theo session_id
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE session_id = ? AND id_nguoidung IS NULL");
        $stmt->bind_param("s", $session_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Giỏ hàng đã tồn tại
        return $result->fetch_assoc();
    } else {
        // Tạo giỏ hàng mới
        if ($user_id) {
            $stmt = $conn->prepare("INSERT INTO giohang (id_nguoidung, session_id) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $session_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO giohang (session_id) VALUES (?)");
            $stmt->bind_param("s", $session_id);
        }
        
        $stmt->execute();
        $cart_id = $conn->insert_id;
        
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE id_giohang = ?");
        $stmt->bind_param("i", $cart_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
}

// Hàm lấy chi tiết giỏ hàng
function getCartItems($conn, $cart_id) {
    $stmt = $conn->prepare("
        SELECT gct.*, 
               sp.tensanpham, 
               sp.hinhanh, 
               kt.tenkichthuoc, 
               ms.tenmau, 
               ms.mamau,
               sp.trangthai,
               IFNULL((SELECT SUM(soluong) FROM sanpham_chitiet WHERE id_sanpham = sp.id_sanpham), 0) AS tonkho
        FROM giohang_chitiet gct
        JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
        LEFT JOIN kichthuoc kt ON gct.id_kichthuoc = kt.id_kichthuoc
        LEFT JOIN mausac ms ON gct.id_mausac = ms.id_mausac
        WHERE gct.id_giohang = ?
        ORDER BY gct.ngay_them DESC
    ");
    
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    
    return $stmt->get_result();
}

// Hàm thêm sản phẩm vào giỏ hàng
function addToCart($conn, $cart_id, $product_id, $quantity, $price, $size_id = null, $color_id = null) {
    // Kiểm tra xem sản phẩm đã có trong giỏ hàng chưa với cùng kích thước và màu sắc
    $stmt = $conn->prepare("
        SELECT * FROM giohang_chitiet
        WHERE id_giohang = ? AND id_sanpham = ? AND id_kichthuoc = ? AND id_mausac = ?
    ");
    $stmt->bind_param("iiii", $cart_id, $product_id, $size_id, $color_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Sản phẩm đã tồn tại, cập nhật số lượng
        $item = $result->fetch_assoc();
        $new_quantity = $item['soluong'] + $quantity;
        $new_total = $price * $new_quantity;
        
        $update = $conn->prepare("
            UPDATE giohang_chitiet
            SET soluong = ?, thanh_tien = ?
            WHERE id_chitiet = ?
        ");
        $update->bind_param("idi", $new_quantity, $new_total, $item['id_chitiet']);
        $update->execute();
    } else {
        // Thêm sản phẩm mới
        $total = $price * $quantity;
        $insert = $conn->prepare("
            INSERT INTO giohang_chitiet (id_giohang, id_sanpham, id_kichthuoc, id_mausac, soluong, gia, thanh_tien)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param("iiiiidi", $cart_id, $product_id, $size_id, $color_id, $quantity, $price, $total);
        $insert->execute();
    }
    
    // Cập nhật tổng tiền giỏ hàng
    updateCartTotal($conn, $cart_id);
}

// Hàm cập nhật tổng tiền giỏ hàng
function updateCartTotal($conn, $cart_id) {
    $stmt = $conn->prepare("
        UPDATE giohang
        SET tong_tien = COALESCE((
            SELECT SUM(thanh_tien) FROM giohang_chitiet WHERE id_giohang = ?
        ), 0),
        ngay_capnhat = NOW()
        WHERE id_giohang = ?
    ");
    $stmt->bind_param("ii", $cart_id, $cart_id);
    $stmt->execute();
}

// Lấy thông tin giỏ hàng hiện tại
$cart = getCart($conn);
$cart_id = $cart['id_giohang'];

// Cập nhật số lượng sản phẩm trong giỏ hàng
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $item_id => $qty) {
        $qty = (int)$qty;
        
        if ($qty <= 0) {
            // Xóa sản phẩm khỏi giỏ hàng
            $delete = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_chitiet = ?");
            $delete->bind_param("i", $item_id);
            $delete->execute();
        } else {
            // Lấy thông tin giá của sản phẩm
            $price_query = $conn->prepare("SELECT gia FROM giohang_chitiet WHERE id_chitiet = ?");
            $price_query->bind_param("i", $item_id);
            $price_query->execute();
            $price_result = $price_query->get_result()->fetch_assoc();
            
            // Cập nhật số lượng và tổng tiền
            $new_total = $price_result['gia'] * $qty;
            $update = $conn->prepare("UPDATE giohang_chitiet SET soluong = ?, thanh_tien = ? WHERE id_chitiet = ?");
            $update->bind_param("idi", $qty, $new_total, $item_id);
            $update->execute();
        }
    }
    
    // Cập nhật tổng tiền giỏ hàng
    updateCartTotal($conn, $cart_id);
    
    // Chuyển hướng để tránh gửi lại form khi refresh
    header('Location: giohang.php');
    exit();
}

// Xóa sản phẩm khỏi giỏ hàng
if (isset($_GET['remove_item'])) {
    $item_id = (int)$_GET['remove_item'];
    
    $delete = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_chitiet = ? AND id_giohang = ?");
    $delete->bind_param("ii", $item_id, $cart_id);
    $delete->execute();
    
    // Cập nhật tổng tiền giỏ hàng
    updateCartTotal($conn, $cart_id);
    
    // Chuyển hướng để tránh gửi lại form khi refresh
    header('Location: giohang.php');
    exit();
}

// Xóa toàn bộ giỏ hàng (kiểm tra cả GET và POST)
if (isset($_GET['clear_cart']) || isset($_POST['clear_cart'])) {
    // Kiểm tra kết nối
    if (!$conn) {
        $_SESSION['cart_error'] = 'Không thể kết nối đến cơ sở dữ liệu.';
        header('Location: giohang.php');
        exit();
    }
    
    // Debug thông tin chi tiết
    error_log("Clear cart request - Cart ID: $cart_id, Session: " . session_id());
    
    // Sử dụng prepared statement cho xóa an toàn
    $delete_stmt = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_giohang = ?");
    $delete_stmt->bind_param("i", $cart_id);
    $delete_result = $delete_stmt->execute();

    if ($delete_result) {
        $affected_rows = $delete_stmt->affected_rows;
        error_log("Đã xóa $affected_rows mục từ giỏ hàng");
        
        // Cập nhật tổng tiền về 0 với prepared statement
        $update_stmt = $conn->prepare("UPDATE giohang SET tong_tien = 0, ngay_capnhat = NOW() WHERE id_giohang = ?");
        $update_stmt->bind_param("i", $cart_id);
        $update_stmt->execute();
        
        if ($affected_rows > 0) {
            $_SESSION['cart_message'] = "Đã xóa $affected_rows sản phẩm khỏi giỏ hàng!";
        } else {
            $_SESSION['cart_message'] = 'Giỏ hàng đã trống.';
        }
    } else {
        // Ghi lại lỗi SQL chi tiết
        error_log("Delete query failed. Error: " . $conn->error);
        $_SESSION['cart_error'] = 'Không thể xóa sản phẩm: ' . $conn->error;
    }
    
    // Buộc trình duyệt không cache trang sau khi xóa
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header('Location: giohang.php');
    exit();
}

// Xử lý xóa nhiều sản phẩm đã chọn
if (isset($_POST['delete_selected']) && isset($_POST['selected_items'])) {
    $selected_items = $_POST['selected_items'];
    
    if (!empty($selected_items)) {
        // Tạo câu truy vấn với tham số ràng buộc cho mỗi id
        $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
        $query = "DELETE FROM giohang_chitiet WHERE id_chitiet IN ($placeholders) AND id_giohang = ?";
        
        // Chuẩn bị câu lệnh
        $stmt = $conn->prepare($query);
        
        // Tạo mảng tham số, thêm cart_id vào cuối
        $params = $selected_items;
        $params[] = $cart_id;
        
        // Tạo chuỗi các loại tham số ('i' cho mỗi item_id + 'i' cho cart_id)
        $types = str_repeat('i', count($selected_items)) . 'i';
        
        // Ràng buộc tham số và thực thi
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        // Cập nhật tổng tiền giỏ hàng
        updateCartTotal($conn, $cart_id);
        
        // Thông báo thành công và chuyển hướng
        $_SESSION['cart_message'] = 'Đã xóa ' . count($selected_items) . ' sản phẩm khỏi giỏ hàng!';
        
        // Chuyển hướng để tránh gửi lại form khi refresh
        header('Location: giohang.php');
        exit();
    }
}

// Lấy danh sách sản phẩm trong giỏ hàng
$cart_items_result = getCartItems($conn, $cart_id);
$cart_items = [];
$total_items = 0;

while ($item = $cart_items_result->fetch_assoc()) {
    $cart_items[] = $item;
    $total_items += $item['soluong'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
        .quantity-input {
            width: 60px;
        }
        .badge {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container mt-5 mb-5">
        <h2 class="mb-4">Giỏ hàng của bạn</h2>
        
        <?php if (isset($_SESSION['cart_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['cart_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['cart_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['cart_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['cart_error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['cart_error']); ?>
        <?php endif; ?>
        
        <?php if (empty($cart_items)): ?>
            <div class="alert alert-info">
                <h4 class="alert-heading mb-3">Giỏ hàng trống!</h4>
                <p>Bạn chưa thêm sản phẩm nào vào giỏ hàng.</p>
                <hr>
                <p class="mb-0">
                    <a href="sanpham.php" class="btn btn-primary">
                        <i class="bi bi-cart-plus"></i> Tiếp tục mua sắm
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Sản phẩm trong giỏ (<?php echo $total_items; ?> sản phẩm)</h5>
                        </div>
                        <div class="col-auto">
                            <button id="clear-cart-btn" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Xóa giỏ hàng
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <form method="post" action="thanhtoan.php">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                            </div>
                                        </th>
                                        <th width="15%">Hình ảnh</th>
                                        <th width="25%">Tên sản phẩm</th>
                                        <th width="15%">Đơn giá</th>
                                        <th width="10%">Số lượng</th>
                                        <th width="15%">Thành tiền</th>
                                        <th width="10%">Trạng thái</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td>
                                        <div class="form-check">
    <input class="form-check-input item-checkbox" type="checkbox" 
           name="selected_items[]" value="<?php echo $item['id_chitiet']; ?>"
           data-price="<?php echo $item['thanh_tien']; ?>"
           <?php echo $item['trangthai'] == 1 ? '' : 'disabled'; ?>>
</div>
                                        </td>
                                        <td>
                                            <img src="<?php echo !empty($item['hinhanh']) ? 'uploads/products/' . $item['hinhanh'] : 'images/no-image.png'; ?>" 
                                                 class="cart-item-image rounded" 
                                                 alt="<?php echo htmlspecialchars($item['tensanpham']); ?>">
                                        </td>
                                        <td>
                                            <div>
                                                <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>" class="text-decoration-none text-dark fw-bold">
                                                    <?php echo htmlspecialchars($item['tensanpham']); ?>
                                                </a>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <?php if (!empty($item['tenkichthuoc'])): ?>
                                                    <span class="me-3">Kích thước: <?php echo $item['tenkichthuoc']; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['tenmau'])): ?>
                                                    <span>Màu: <?php echo $item['tenmau']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                                        <td>
                                            <input type="number" name="quantity[<?php echo $item['id_chitiet']; ?>]" 
                                                  value="<?php echo $item['soluong']; ?>" 
                                                  min="1" class="form-control quantity-input">
                                        </td>
                                        <td><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                        <td>
                                            <?php if ($item['trangthai'] == 1): ?>
                                                <span class="badge bg-success">Còn hàng</span>
                                            <?php elseif ($item['trangthai'] == 0): ?>
                                                <span class="badge bg-danger">Hết hàng</span>
                                            <?php elseif ($item['trangthai'] == 2): ?>
                                                <span class="badge bg-secondary">Ngừng kinh doanh</span>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($item['tonkho']) && $item['tonkho'] < 5 && $item['tonkho'] > 0): ?>
                                                <div class="small text-danger mt-1">Chỉ còn <?php echo $item['tonkho']; ?> sản phẩm</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-link text-danger p-0 remove-item-btn" 
                                                    data-item-id="<?php echo $item['id_chitiet']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold">Tổng tiền:</td>
                                        <td colspan="2" class="fw-bold text-danger">
                                            <span id="total-full"><?php echo number_format($cart['tong_tien'], 0, ',', '.'); ?>₫</span>
                                            <span id="total-selected" class="d-none"></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="p-3 border-top d-flex justify-content-between">
                            <div>
                                <a href="sanpham.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-left"></i> Tiếp tục mua sắm
                                </a>
                                <button type="submit" name="delete_selected" id="delete-selected-btn" class="btn btn-outline-danger ms-2" disabled>
                                    <i class="bi bi-trash"></i> Xóa đã chọn (<span id="selected-count-delete">0</span>)
                                </button>
                            </div>
                            <div>
                                <button type="submit" name="update_cart" class="btn btn-outline-secondary me-2" formaction="giohang.php">
                                    <i class="bi bi-arrow-clockwise"></i> Cập nhật giỏ hàng
                                </button>
                                <button type="submit" name="checkout_all" class="btn btn-primary me-2" id="checkout-all-btn">
                                    <i class="bi bi-credit-card"></i> Thanh toán tất cả
                                </button>
                                <button type="submit" name="checkout_selected" class="btn btn-success" id="checkout-selected-btn" disabled>
                                    <i class="bi bi-check2-square"></i> Thanh toán đã chọn (<span id="selected-count">0</span>)
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    
    
    <script src="js/giohang.js"></script>
</body>
</html>
