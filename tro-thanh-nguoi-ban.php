<?php
session_start();

// Thông báo cho người dùng biết chức năng này đã bị vô hiệu hóa
$_SESSION['error_message'] = 'Chức năng đăng ký người bán hiện không khả dụng.';
header('Location: index.php');
exit();
?>
