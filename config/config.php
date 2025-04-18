<?php
// filepath: c:\xampp\htdocs\bug_shop\config\config.php
// Thông tin kết nối database
$servername = "localhost";
$username = "root"; // Mặc định cho XAMPP
$password = ""; // Mặc định cho XAMPP
$dbname = "shop_vippro_1"; // Tên database mới

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Đặt charset là utf8mb4
$conn->set_charset("utf8mb4");
?>