<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    $_SESSION['error_message'] = "Vui lòng đăng nhập để xem sản phẩm yêu thích";
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];

// Xử lý xóa sản phẩm khỏi danh sách yêu thích
if (isset($_POST['remove_item']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    
    $delete_query = $conn->prepare("DELETE FROM yeu_thich WHERE id_user = ? AND id_sanpham = ?");
    $delete_query->bind_param("ii", $user_id, $product_id);
    
    if ($delete_query->execute()) {
        $_SESSION['success_message'] = "Đã xóa sản phẩm khỏi danh sách yêu thích";
    } else {
        $_SESSION['error_message'] = "Có lỗi xảy ra khi xóa sản phẩm";
    }
    
    // Redirect to refresh the page and avoid form resubmission
    header("Location: yeuthich.php");
    exit();
}

// Xử lý xóa tất cả sản phẩm yêu thích
if (isset($_POST['remove_all'])) {
    $delete_all_query = $conn->prepare("DELETE FROM yeu_thich WHERE id_user = ?");
    $delete_all_query->bind_param("i", $user_id);
    
    if ($delete_all_query->execute()) {
        $_SESSION['success_message'] = "Đã xóa tất cả sản phẩm khỏi danh sách yêu thích";
    } else {
        $_SESSION['error_message'] = "Có lỗi xảy ra khi xóa sản phẩm";
    }
    
    header("Location: yeuthich.php");
    exit();
}

// Lấy danh sách sản phẩm yêu thích của người dùng
$wishlist_query = $conn->prepare("
    SELECT yt.*, sp.tensanpham, sp.gia, sp.giagoc, sp.hinhanh, sp.slug, sp.trangthai, sp.so_luong
    FROM yeu_thich yt 
    JOIN sanpham sp ON yt.id_sanpham = sp.id
    WHERE yt.id_user = ?
    ORDER BY yt.ngay_tao DESC
");

$wishlist_query->bind_param("i", $user_id);
$wishlist_query->execute();
$wishlist_result = $wishlist_query->get_result();
$wishlist_items = [];

while ($item = $wishlist_result->fetch_assoc()) {
    $wishlist_items[] = $item;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm yêu thích - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .wishlist-item {
            transition: all 0.2s ease;
        }
        .wishlist-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 180px;
            object-fit: cover;
        }
        .btn-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .wishlist-item:hover .btn-remove {
            opacity: 1;
        }
        .price-old {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9rem;
        }
        .empty-wishlist {
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <?php include('includes/head.php'); ?>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Sản phẩm yêu thích</h1>
            <?php if (count($wishlist_items) > 0): ?>
            <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tất cả sản phẩm yêu thích?');">
                <button type="submit" name="remove_all" class="btn btn-outline-danger">
                    <i class="bi bi-trash"></i> Xóa tất cả
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (count($wishlist_items) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="col">
                        <div class="card h-100 wishlist-item">
                            <form method="post" class="position-relative">
                                <input type="hidden" name="product_id" value="<?php echo $item['id_sanpham']; ?>">
                                <button type="submit" name="remove_item" class="btn btn-sm btn-danger rounded-circle btn-remove" title="Xóa khỏi danh sách yêu thích">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                            
                            <div class="position-relative">
                                <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>">
                                    <img src="<?php echo !empty($item['hinhanh']) ? 
                                        (strpos($item['hinhanh'], 'uploads/') === 0 ? $item['hinhanh'] : 'uploads/products/' . $item['hinhanh']) : 
                                        'images/no-image.png'; ?>" 
                                        class="card-img-top product-image" 
                                        alt="<?php echo htmlspecialchars($item['tensanpham']); ?>">
                                </a>
                                
                                <?php if ($item['trangthai'] == 0): ?>
                                    <div class="position-absolute top-0 start-0 bg-danger text-white py-1 px-2 m-2">Hết hàng</div>
                                <?php elseif ($item['giagoc'] > $item['gia']): ?>
                                    <div class="position-absolute top-0 start-0 bg-danger text-white py-1 px-2 m-2">
                                        -<?php echo round(($item['giagoc'] - $item['gia']) / $item['giagoc'] * 100); ?>%
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title mb-2">
                                    <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($item['tensanpham']); ?>
                                    </a>
                                </h5>
                                
                                <div class="mb-2">
                                    <span class="fw-bold text-danger"><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</span>
                                    <?php if ($item['giagoc'] > $item['gia']): ?>
                                        <span class="ms-2 price-old"><?php echo number_format($item['giagoc'], 0, ',', '.'); ?>₫</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-auto pt-3 d-grid gap-2">
                                    <?php if ($item['trangthai'] == 1 && $item['so_luong'] > 0): ?>
                                        <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>" class="btn btn-primary">
                                            <i class="bi bi-cart-plus"></i> Mua ngay
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="bi bi-x-circle"></i> Hết hàng
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body empty-wishlist text-center py-5">
                    <i class="bi bi-heart fs-1 text-muted mb-3"></i>
                    <h4>Danh sách yêu thích trống</h4>
                    <p class="text-muted mb-4">Bạn chưa thêm sản phẩm nào vào danh sách yêu thích</p>
                    <a href="sanpham.php" class="btn btn-primary">
                        <i class="bi bi-bag"></i> Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
