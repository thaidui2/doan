<?php
// Tạo một mảng settings rỗng ban đầu
$settings = [];

// Sử dụng kết nối từ file config.php
try {
    // Include file cấu hình nếu chưa được include
    if (!isset($conn) || $conn->connect_error) {
        require_once(__DIR__ . '/../config/config.php');
    }

    // Kiểm tra kết nối
    if (!$conn->connect_error) {
        // Nếu kết nối thành công, thực hiện truy vấn settings
        $settingsQuery = "SELECT setting_key, setting_value FROM settings";
        $settingsResult = $conn->query($settingsQuery);

        if ($settingsResult && $settingsResult->num_rows > 0) {
            while ($row = $settingsResult->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        // Không đóng kết nối ở đây vì có thể được sử dụng ở nơi khác
    }
} catch (Exception $e) {
    // Ghi log lỗi
    error_log('Lỗi footer.php: ' . $e->getMessage());
}

// Nếu không có thông tin từ database, sử dụng giá trị mặc định
$shop_name = $settings['site_name'] ?? 'Bug Shoes';
$shop_address = $settings['address'] ?? 'Đại học Công Nghệ Đông Á';
$shop_phone = $settings['contact_phone'] ?? '(+84) 1234 5678';
$shop_email = $settings['contact_email'] ?? '20210140@eaut.edu.vn';
$shop_hours = $settings['shop_hours'] ?? '08:00 - 21:00, Thứ 2 - Chủ nhật';
$facebook_url = $settings['facebook_url'] ?? 'https://www.facebook.com/thai.dui57';
$instagram_url = $settings['instagram_url'] ?? '#';
$twitter_url = $settings['twitter_url'] ?? '#';
$youtube_url = $settings['youtube_url'] ?? '#';
$shop_description = $settings['site_description'] ?? 'Cửa hàng giày chất lượng cao với đa dạng mẫu mã, phong cách hiện đại cho mọi lứa tuổi.';
?>

<footer class="bg-dark text-white pt-5 pb-4">
    <div class="container">
        <div class="row">
            <!-- Shop Info -->
            <div class="col-md-3 mb-4">
                <h5 class="mb-3"><?php echo htmlspecialchars($shop_name); ?></h5>
                <img src="images/logo.png" alt="<?php echo htmlspecialchars($shop_name); ?> Logo" height="40"
                    class="d-inline-block mb-3">
                <p class="small"><?php echo htmlspecialchars($shop_description); ?></p>
            </div>

            <!-- Quick Links -->
            <div class="col-md-3 mb-4">
                <h5 class="mb-3">Liên kết nhanh</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="index.php" class="text-white text-decoration-none">Trang chủ</a></li>
                    <li class="mb-2"><a href="sanpham.php?loai=1" class="text-white text-decoration-none">Giày nam</a>
                    </li>
                    <li class="mb-2"><a href="sanpham.php?loai=2" class="text-white text-decoration-none">Giày nữ</a>
                    </li>
                    <li class="mb-2"><a href="khuyen-mai.php" class="text-white text-decoration-none">Khuyến mãi</a>
                    </li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-md-3 mb-4">
                <h5 class="mb-3">Thông tin liên hệ</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($shop_address); ?>
                    </li>
                    <li class="mb-2"><i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($shop_phone); ?>
                    </li>
                    <li class="mb-2"><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($shop_email); ?>
                    </li>
                    <li class="mb-2"><i class="bi bi-clock me-2"></i><?php echo htmlspecialchars($shop_hours); ?></li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div class="col-md-3 mb-4">
                <h5 class="mb-3">Đăng ký nhận tin</h5>
                <p class="small">Nhận thông tin về sản phẩm mới và khuyến mãi đặc biệt.</p>
                <form action="process_newsletter.php" method="post">
                    <div class="input-group mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email của bạn" required>
                        <button class="btn btn-primary" type="submit">Đăng ký</button>
                    </div>
                </form>

                <!-- Social Media Links -->
                <div class="mt-3">
                    <a href="<?php echo htmlspecialchars($facebook_url); ?>" class="text-white me-3" target="_blank"><i
                            class="bi bi-facebook fs-5"></i></a>
                    <a href="<?php echo htmlspecialchars($instagram_url); ?>" class="text-white me-3" target="_blank"><i
                            class="bi bi-instagram fs-5"></i></a>
                    <a href="<?php echo htmlspecialchars($twitter_url); ?>" class="text-white me-3" target="_blank"><i
                            class="bi bi-twitter fs-5"></i></a>
                    <a href="<?php echo htmlspecialchars($youtube_url); ?>" class="text-white" target="_blank"><i
                            class="bi bi-youtube fs-5"></i></a>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="row mt-4 pt-3 border-top">
            <div class="col-md-6 small">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($shop_name); ?>. Tất cả các
                    quyền được bảo lưu.</p>
            </div>
            <div class="col-md-6 text-md-end small">
                <a href="chinh-sach-bao-mat.php" class="text-white text-decoration-none me-3">Chính sách bảo mật</a>
                <a href="dieu-khoan-su-dung.php" class="text-white text-decoration-none">Điều khoản sử dụng</a>
            </div>
        </div>
    </div>
</footer>

<!-- Hết phần footer -->





<!-- Custom JS -->
<script>

    // Kiểm tra nếu script wishlist.js đã được tải
    if (typeof initWishlistButtons !== 'function') {
        console.error('Lỗi: wishlist.js không được tải!');
    }

    // Kiểm tra nút wishlist có tồn tại không
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.wishlist-button');
        console.log('Số nút wishlist tìm thấy:', buttons.length);
        if (buttons.length > 0) {
            console.log('Chi tiết nút đầu tiên:', buttons[0].outerHTML);
        }
    });
</script>
</body>

</html>