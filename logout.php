<?php
session_start();

// Xóa thông tin session người dùng
if (isset($_SESSION['user'])) {
    unset($_SESSION['user']);
}

// Xóa toàn bộ session (tuỳ chọn)
// session_destroy();

// Chuyển hướng về trang chủ
header("Location: index.php");
exit();
?>