<?php
session_start();

// Giữ thông tin đăng nhập
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_username = $_SESSION['admin_username'] ?? null;
$admin_name = $_SESSION['admin_name'] ?? null;
$admin_level = $_SESSION['admin_level'] ?? 1;

// Xóa các biến session quyền hạn
unset($_SESSION['admin_permissions']);
unset($_SESSION['admin_roles']);

// Đặt lại thông tin cơ bản
$_SESSION['admin_id'] = $admin_id;
$_SESSION['admin_username'] = $admin_username; 
$_SESSION['admin_name'] = $admin_name;
$_SESSION['admin_level'] = $admin_level;

// Chuyển hướng về trang chủ admin
header('Location: index.php?reset=1');
exit();
?>