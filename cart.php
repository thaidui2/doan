<?php
session_start();
include('config/config.php');

// Cập nhật số lượng sản phẩm trong giỏ hàng
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $key => $qty) {
        $qty = (int)$qty;
        if ($qty <= 0) {
            // Nếu số lượng <= 0, xóa sản phẩm khỏi giỏ hàng
            unset($_SESSION['cart'][$key]);
        } else {
            // Cập nhật số lượng và tổng tiền
            $_SESSION['cart'][$key]['quantity'] = $qty;
            $_SESSION['cart'][$key]['total'] = $_SESSION['cart'][$key]['price'] * $qty;
        }
    }
}

// Xóa sản phẩm khỏi giỏ hàng
if (isset($_GET['remove_item'])) {
    $item_key = $_GET['remove_item'];
    if (isset($_SESSION['cart'][$item_key])) {
        unset($_SESSION['cart'][$item_key]);
    }
    // Chuyển hướng để tránh gửi lại form khi refresh
    header('Location: cart.php');
    exit();
}

// Xóa toàn bộ giỏ hàng
if (isset($_GET['clear_cart'])) {
    unset($_SESSION['cart']);
    header('Location: cart.php');
    exit();
}

// Tính tổng tiền trong giỏ hàng
$total_cart = 0;
$total_items = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_cart += $item['total'];
        $total_items += $item['quantity'];
    }
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
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container mt-5 mb-5">
        <h2 class="mb-4">Giỏ hàng của bạn</h2>
        
        <?php if (empty($_SESSION['cart'])): ?>
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
                            <a href="cart.php?clear_cart=1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa toàn bộ giỏ hàng?')">
                                <i class="bi bi-trash"></i> Xóa tất cả
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <form method="post" action="cart.php">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="15%">Hình ảnh</th>
                                        <th width="40%">Tên sản phẩm</th>
                                        <th width="15%">Đơn giá</th>
                                        <th width="15%">Số lượng</th>
                                        <th width="15%">Thành tiền</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo !empty($item['image']) ? 'uploads/products/' . $item['image'] : 'images/no-image.png'; ?>" 
                                                 class="cart-item-image rounded" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        </td>
                                        <td>
                                            <div>
                                                <a href="product-detail.php?id=<?php echo $item['product_id']; ?>" class="text-decoration-none text-dark fw-bold">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </a>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <?php if (!empty($item['size_name'])): ?>
                                                    <span class="me-3">Kích thước: <?php echo $item['size_name']; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['color_name'])): ?>
                                                    <span>Màu: <?php echo $item['color_name']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($item['price'], 0, ',', '.'); ?>₫</td>
                                        <td>
                                            <input type="number" name="quantity[<?php echo $key; ?>]" 
                                                  value="<?php echo $item['quantity']; ?>" 
                                                  min="1" class="form-control quantity-input">
                                        </td>
                                        <td><?php echo number_format($item['total'], 0, ',', '.'); ?>₫</td>
                                        <td>
                                            <a href="cart.php?remove_item=<?php echo $key; ?>" class="text-danger" 
                                              onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Tổng tiền:</td>
                                        <td class="fw-bold text-danger"><?php echo number_format($total_cart, 0, ',', '.'); ?>₫</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="p-3 border-top d-flex justify-content-between">
                            <a href="sanpham.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> Tiếp tục mua sắm
                            </a>
                            <div>
                                <button type="submit" name="update_cart" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-arrow-clockwise"></i> Cập nhật giỏ hàng
                                </button>
                                <a href="checkout.php" class="btn btn-primary">
                                    <i class="bi bi-credit-card"></i> Thanh toán
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
</body>
</html>
