<?php
include('../config/config.php');

// Thêm trường hinhanh_mau vào bảng sanpham_chitiet nếu chưa có
$check_column = $conn->query("SHOW COLUMNS FROM sanpham_chitiet LIKE 'hinhanh_mau'");
if ($check_column->num_rows == 0) {
    $alter_table = "ALTER TABLE sanpham_chitiet ADD hinhanh_mau VARCHAR(255) NULL AFTER soluong";
    
    if ($conn->query($alter_table)) {
        echo "<p>Đã thêm trường hinhanh_mau vào bảng sanpham_chitiet thành công!</p>";
    } else {
        echo "<p>Lỗi khi thêm trường: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Trường hinhanh_mau đã tồn tại trong bảng sanpham_chitiet.</p>";
}

echo "<a href='products.php'>Quay lại trang sản phẩm</a>";
?>
