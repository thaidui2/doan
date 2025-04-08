<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized access");
}

$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    echo "Không có file nào được chỉ định";
    exit;
}

// Đảm bảo chỉ hiển thị file trong thư mục ajax
if (strpos($file, 'ajax/') !== 0) {
    echo "Chỉ cho phép xem file trong thư mục ajax/";
    exit;
}

$file_path = __DIR__ . '/' . $file;

if (file_exists($file_path)) {
    $content = file_get_contents($file_path);
    echo htmlspecialchars($content);
} else {
    echo "File không tồn tại!";
}
