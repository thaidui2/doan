<?php
// Script tạo tài khoản admin khi cần thiết
include('../config/config.php');

$username = 'admin'; // Thay đổi nếu cần
$password = 'admin123'; // Thay đổi mật khẩu này
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$name = 'Administrator';
$email = 'admin@bugshop.com';

try {
    // Kiểm tra xem tài khoản đã tồn tại chưa
    $check = $conn->prepare("SELECT * FROM users WHERE taikhoan = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows == 0) {
        // Thêm tài khoản admin mới
        $stmt = $conn->prepare("INSERT INTO users (taikhoan, matkhau, ten, email, loai_user, trang_thai) VALUES (?, ?, ?, ?, 2, 1)");
        $stmt->bind_param("ssss", $username, $hashed_password, $name, $email);
        
        if ($stmt->execute()) {
            echo "<h3 style='color:green'>Tạo tài khoản admin thành công!</h3>";
            echo "<p>Tài khoản: $username</p>";
            echo "<p>Mật khẩu: $password</p>";
            echo "<p><a href='login.php'>Đăng nhập ngay</a></p>";
        } else {
            echo "<h3 style='color:red'>Lỗi tạo tài khoản: " . $stmt->error . "</h3>";
        }
    } else {
        echo "<h3 style='color:blue'>Tài khoản admin đã tồn tại!</h3>";
        echo "<p><a href='login.php'>Đăng nhập ngay</a></p>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red'>Lỗi: " . $e->getMessage() . "</h3>";
}
?>
