<?php
session_start();
require_once('config/config.php');

echo "<h1>Kiểm tra Chức Năng Wishlist</h1>";

// Kiểm tra đăng nhập
echo "<h2>1. Kiểm tra đăng nhập</h2>";
if (isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true) {
    echo "<p style='color:green'>✓ Người dùng đã đăng nhập: ID {$_SESSION['user']['id']}, Username: {$_SESSION['user']['username']}</p>";
} else {
    echo "<p style='color:red'>✗ Người dùng chưa đăng nhập. Hãy <a href='dangnhap.php'>đăng nhập</a> trước khi dùng chức năng yêu thích.</p>";
}

// Kiểm tra bảng wishlist
echo "<h2>2. Kiểm tra bảng wishlist</h2>";
$check_table = $conn->query("SHOW TABLES LIKE 'wishlist'");
if ($check_table->num_rows > 0) {
    echo "<p style='color:green'>✓ Bảng wishlist đã tồn tại</p>";
    
    // Kiểm tra cấu trúc bảng
    $check_structure = $conn->query("DESCRIBE wishlist");
    echo "<p>Cấu trúc bảng:</p><ul>";
    while ($row = $check_structure->fetch_assoc()) {
        echo "<li>{$row['Field']} - {$row['Type']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>✗ Bảng wishlist chưa tồn tại. Cần tạo bảng này.</p>";
}

// Thêm kiểm tra này vào test_wishlist.php
echo "<h2>2.1. Kiểm tra bảng sản phẩm</h2>";
$check_products = $conn->query("SELECT id_sanpham, tensanpham, trangthai FROM sanpham LIMIT 5");
if ($check_products->num_rows > 0) {
    echo "<p>Các sản phẩm trong database:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Tên sản phẩm</th><th>Trạng thái</th></tr>";
    while ($product = $check_products->fetch_assoc()) {
        $status = $product['trangthai'] ? 'Hiển thị' : 'Đã ẩn';
        echo "<tr>";
        echo "<td>{$product['id_sanpham']}</td>";
        echo "<td>{$product['tensanpham']}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Không tìm thấy sản phẩm nào trong database!</p>";
}

// Thêm đoạn code này để lấy một sản phẩm có thực trong database
$valid_product_query = $conn->query("SELECT id_sanpham FROM sanpham WHERE trangthai = 1 LIMIT 1");
$valid_product_id = ($valid_product_query && $valid_product_query->num_rows > 0) ? 
    $valid_product_query->fetch_assoc()['id_sanpham'] : 1;

echo "<h2>3. Kiểm tra JavaScript</h2>";
echo "<p>Mở Console trình duyệt (F12) để xem logs khi click vào nút yêu thích</p>";
echo "<button id='testButton' style='padding:10px;' data-product-id='{$valid_product_id}'>Click để kiểm tra AJAX request</button>";
echo "<div id='testResult' style='margin-top:10px;'></div>";
?>

<script>
document.getElementById('testButton').addEventListener('click', function() {
    // Tạo form data
    const formData = new FormData();
    formData.append("product_id", this.dataset.productId);
    
    document.getElementById('testResult').innerHTML = "Đang gửi request...";
    
    // Gửi request đến server
    fetch("ajax/toggle_wishlist.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin"
    })
    .then(response => {
        console.log("Response status:", response.status);
        return response.json();
    })
    .then(data => {
        console.log("Toggle wishlist response:", data);
        document.getElementById('testResult').innerHTML = 
            "<pre>Response: " + JSON.stringify(data, null, 2) + "</pre>";
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('testResult').innerHTML = 
            "<p style='color:red'>Lỗi: " + error.message + "</p>";
    });
});
</script>

<?php
// Link để quay lại
echo "<p><a href='index.php'>Quay lại trang chủ</a></p>";
?>