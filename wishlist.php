<?php
// Set page title (sẽ được sử dụng trong head.php)
$page_title = "Sản phẩm yêu thích";

// Bắt đầu output buffering để tránh lỗi header already sent
ob_start();

// Khởi tạo session và kết nối database
include('includes/init.php');

// Kiểm tra đăng nhập và chuyển hướng TRƯỚC KHI xuất bất kỳ HTML nào
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Bao gồm phần HEAD HTML (DOCTYPE, html, head, body opening tags)
include('includes/head.php');

// Bao gồm phần HEADER UI (navigation menu)
include('includes/header.php');

// Get user ID
$user_id = $_SESSION['user']['id'];

// Handle remove from wishlist action
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Delete from wishlist
    $delete_stmt = $conn->prepare("DELETE FROM wishlist WHERE id_user = ? AND id_sanpham = ?");
    $delete_stmt->bind_param("ii", $user_id, $product_id);
    $delete_stmt->execute();
    
    // Redirect to avoid resubmission
    header('Location: wishlist.php');
    exit();
}

// Handle add to cart action
if (isset($_GET['action']) && $_GET['action'] == 'addtocart' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Get product availability and size/color options
    $check_product = $conn->prepare("
        SELECT sp.id_sanpham, sp.trangthai, 
               (SELECT COUNT(*) FROM sanpham_chitiet WHERE id_sanpham = sp.id_sanpham) AS has_variants
        FROM sanpham sp 
        WHERE sp.id_sanpham = ? AND sp.trangthai = 1
    ");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $product_result = $check_product->get_result();
    
    if ($product_result->num_rows > 0) {
        $product_info = $product_result->fetch_assoc();
        
        if ($product_info['has_variants'] > 0) {
            // Product has variants - redirect to product page
            header('Location: product-detail.php?id=' . $product_id);
            exit();
        } else {
            // Get cart
            $session_id = session_id();
            $cart_id = 0;
            
            $cart_stmt = $conn->prepare("SELECT id_giohang FROM giohang WHERE id_nguoidung = ?");
            $cart_stmt->bind_param("i", $user_id);
            $cart_stmt->execute();
            $cart_result = $cart_stmt->get_result();
            
            if ($cart_result->num_rows > 0) {
                $cart = $cart_result->fetch_assoc();
                $cart_id = $cart['id_giohang'];
            } else {
                // Create new cart
                $create_cart = $conn->prepare("INSERT INTO giohang (id_nguoidung, session_id) VALUES (?, ?)");
                $create_cart->bind_param("is", $user_id, $session_id);
                $create_cart->execute();
                $cart_id = $conn->insert_id;
            }
            
            // Get product price
            $price_stmt = $conn->prepare("SELECT gia FROM sanpham WHERE id_sanpham = ?");
            $price_stmt->bind_param("i", $product_id);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();
            $price_data = $price_result->fetch_assoc();
            $price = $price_data['gia'];
            
            // Check if product is already in cart
            $check_cart = $conn->prepare("
                SELECT id_chitiet, soluong FROM giohang_chitiet 
                WHERE id_giohang = ? AND id_sanpham = ? AND id_kichthuoc IS NULL AND id_mausac IS NULL
            ");
            $check_cart->bind_param("ii", $cart_id, $product_id);
            $check_cart->execute();
            $cart_item_result = $check_cart->get_result();
            
            if ($cart_item_result->num_rows > 0) {
                // Update quantity
                $cart_item = $cart_item_result->fetch_assoc();
                $new_quantity = $cart_item['soluong'] + 1;
                $new_total = $price * $new_quantity;
                
                $update_stmt = $conn->prepare("
                    UPDATE giohang_chitiet 
                    SET soluong = ?, thanh_tien = ? 
                    WHERE id_chitiet = ?
                ");
                $update_stmt->bind_param("idi", $new_quantity, $new_total, $cart_item['id_chitiet']);
                $update_stmt->execute();
            } else {
                // Add new item
                $insert_stmt = $conn->prepare("
                    INSERT INTO giohang_chitiet (id_giohang, id_sanpham, soluong, gia, thanh_tien)
                    VALUES (?, ?, 1, ?, ?)
                ");
                $insert_stmt->bind_param("iidd", $cart_id, $product_id, $price, $price);
                $insert_stmt->execute();
            }
            
            // Update cart total
            $update_total = $conn->prepare("
                UPDATE giohang 
                SET tong_tien = (
                    SELECT SUM(thanh_tien) 
                    FROM giohang_chitiet 
                    WHERE id_giohang = ?
                )
                WHERE id_giohang = ?
            ");
            $update_total->bind_param("ii", $cart_id, $cart_id);
            $update_total->execute();
            
            // Set success message
            $_SESSION['success_message'] = "Sản phẩm đã được thêm vào giỏ hàng!";
            header('Location: wishlist.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Sản phẩm không khả dụng";
        header('Location: wishlist.php');
        exit();
    }
}

// Pagination
$items_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Count total wishlist items
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM wishlist 
    WHERE id_user = ?
");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$total_items = $count_result['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get wishlist items with product details
$wishlist_stmt = $conn->prepare("
    SELECT w.id, w.id_sanpham, w.ngay_them,
           sp.tensanpham, sp.gia, sp.giagoc, sp.hinhanh, sp.diemdanhgia_tb,
           sp.trangthai, th.tenthuonghieu
    FROM wishlist w
    JOIN sanpham sp ON w.id_sanpham = sp.id_sanpham
    LEFT JOIN thuonghieu th ON sp.id_thuonghieu = th.id_thuonghieu
    WHERE w.id_user = ?
    ORDER BY w.ngay_them DESC
    LIMIT ? OFFSET ?
");
$wishlist_stmt->bind_param("iii", $user_id, $items_per_page, $offset);
$wishlist_stmt->execute();
$wishlist_items = $wishlist_stmt->get_result();
?>

<!-- Breadcrumb -->
<div class="container mt-3 mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="taikhoan.php">Tài khoản</a></li>
            <li class="breadcrumb-item active">Sản phẩm yêu thích</li>
        </ol>
    </nav>
</div>

<!-- Phần CSS đặc biệt cho trang này nên đặt trong header hoặc trong thẻ <style> duy nhất -->
<style>
    .product-card {
        transition: all 0.3s ease;
        border: 1px solid #eee;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .product-img {
        height: 200px;
        object-fit: cover;
    }
    
    .product-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        padding: 5px 10px;
        font-size: 0.8rem;
        font-weight: 500;
        color: white;
        border-radius: 4px;
    }
    
    .product-title {
        font-size: 1rem;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        min-height: 48px;
    }
    
    .brand-name {
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .remove-wishlist-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: white;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        z-index: 2;
    }
    
    .remove-wishlist-btn:hover {
        background-color: #f8d7da;
    }
    
    .price {
        font-size: 1.1rem;
        color: #dc3545;
    }
    
    .original-price {
        font-size: 0.9rem;
    }
    
    /* Animation for card removal */
    .fade-out {
        animation: fadeOut 0.5s forwards;
    }
    
    @keyframes fadeOut {
        0% { opacity: 1; }
        100% { opacity: 0; transform: scale(0.9); }
    }
</style>

<!-- Main Content -->
<section class="wishlist-section py-4">
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h4 class="mb-0">Danh sách sản phẩm yêu thích</h4>
            </div>
            
            <div class="card-body">
                <!-- Display success/error messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if ($total_items > 0): ?>
                    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
                        <?php while ($item = $wishlist_items->fetch_assoc()): ?>
                            <div class="col">
                                <div class="card h-100 product-card">
                                    <!-- Product image -->
                                    <div class="position-relative">
                                        <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>">
                                            <?php if (!empty($item['hinhanh'])): ?>
                                                <img src="uploads/products/<?php echo $item['hinhanh']; ?>" 
                                                    class="card-img-top product-img" alt="<?php echo $item['tensanpham']; ?>">
                                            <?php else: ?>
                                                <img src="images/no-image.png" class="card-img-top product-img" 
                                                    alt="<?php echo $item['tensanpham']; ?>">
                                            <?php endif; ?>
                                        </a>
                                        
                                        <!-- Remove from wishlist button -->
                                        <button class="btn btn-sm remove-wishlist-btn" 
                                                data-id="<?php echo $item['id_sanpham']; ?>" 
                                                title="Xóa khỏi danh sách yêu thích">
                                            <i class="bi bi-heart-fill text-danger"></i>
                                        </button>
                                        
                                        <!-- Product status badge -->
                                        <?php if ($item['trangthai'] == 0): ?>
                                            <div class="product-badge bg-danger">Hết hàng</div>
                                        <?php elseif ($item['trangthai'] == 2): ?>
                                            <div class="product-badge bg-secondary">Ngừng kinh doanh</div>
                                        <?php elseif ($item['giagoc'] > $item['gia']): ?>
                                            <div class="product-badge bg-danger">
                                                -<?php echo round((($item['giagoc'] - $item['gia']) / $item['giagoc']) * 100); ?>%
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body">
                                        <!-- Brand name -->
                                        <?php if (!empty($item['tenthuonghieu'])): ?>
                                            <div class="small text-muted brand-name"><?php echo $item['tenthuonghieu']; ?></div>
                                        <?php endif; ?>
                                        
                                        <!-- Product name -->
                                        <h5 class="card-title product-title">
                                            <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo $item['tensanpham']; ?>
                                            </a>
                                        </h5>
                                        
                                        <!-- Rating -->
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="rating">
                                                <?php
                                                $rating = round($item['diemdanhgia_tb'] * 2) / 2;
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $rating) {
                                                        echo '<i class="bi bi-star-fill text-warning"></i>';
                                                    } elseif ($i - 0.5 == $rating) {
                                                        echo '<i class="bi bi-star-half text-warning"></i>';
                                                    } else {
                                                        echo '<i class="bi bi-star text-warning"></i>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Product price -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="price-container">
                                                <span class="price fw-bold"><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</span>
                                                <?php if ($item['giagoc'] > $item['gia']): ?>
                                                    <span class="original-price text-muted text-decoration-line-through ms-2">
                                                        <?php echo number_format($item['giagoc'], 0, ',', '.'); ?>₫
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Card footer with action buttons -->
                                    <div class="card-footer bg-white border-top-0 pt-0">
                                        <div class="d-grid gap-2">
                                            <?php if ($item['trangthai'] == 1): ?>
                                                <a href="wishlist.php?action=addtocart&id=<?php echo $item['id_sanpham']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4" aria-label="Phân trang">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">
                                        <i class="bi bi-chevron-left"></i> Trước
                                    </a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                    echo '</li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">
                                        Tiếp <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Empty wishlist message -->
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-heart text-muted" style="font-size: 4rem;"></i>
                        </div>
                        <h5>Danh sách sản phẩm yêu thích của bạn đang trống</h5>
                        <p class="text-muted">Hãy thêm sản phẩm yêu thích bằng cách nhấn vào biểu tượng trái tim trên các sản phẩm</p>
                        <a href="sanpham.php" class="btn btn-primary mt-3">
                            <i class="bi bi-grid"></i> Xem sản phẩm
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Delete confirmation modal -->
<div class="modal fade" id="removeWishlistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa sản phẩm này khỏi danh sách yêu thích?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <a href="#" id="confirmRemove" class="btn btn-danger">Xóa</a>
            </div>
        </div>
    </div>
</div>

<!-- Script cho trang -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle wishlist removal buttons with confirmation modal
    const removeButtons = document.querySelectorAll('.remove-wishlist-btn');
    const confirmRemoveBtn = document.getElementById('confirmRemove');
    const removeModal = new bootstrap.Modal(document.getElementById('removeWishlistModal'));
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            confirmRemoveBtn.href = `wishlist.php?action=remove&id=${productId}`;
            removeModal.show();
        });
    });
    
    // Animate card removal before actual removal
    confirmRemoveBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const productId = this.href.split('id=')[1];
        const productCard = document.querySelector(`.remove-wishlist-btn[data-id="${productId}"]`).closest('.col');
        
        productCard.classList.add('fade-out');
        
        setTimeout(() => {
            window.location.href = confirmRemoveBtn.href;
        }, 500);
    });
});
</script>

<?php
// Bao gồm phần FOOTER (closing body and html tags)
include('includes/footer.php');
?>
