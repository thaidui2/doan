<?php
include('../config/config.php');

// Kiểm tra trường hinhanh_mau trong bảng sanpham_chitiet
$check_column = $conn->query("SHOW COLUMNS FROM sanpham_chitiet LIKE 'hinhanh_mau'");

if ($check_column->num_rows == 0) {
    // Trường không tồn tại, thêm vào
    $alter_table = "ALTER TABLE sanpham_chitiet ADD COLUMN hinhanh_mau VARCHAR(255) NULL AFTER soluong";
    if ($conn->query($alter_table)) {
        echo "Đã thêm trường hinhanh_mau vào bảng sanpham_chitiet thành công!<br>";
    } else {
        echo "Lỗi khi thêm trường hinhanh_mau: " . $conn->error . "<br>";
    }
} else {
    echo "Trường hinhanh_mau đã tồn tại trong bảng sanpham_chitiet.<br>";
}

// Kiểm tra thư mục uploads/colors
$color_upload_dir = "../uploads/colors/";
if(!file_exists($color_upload_dir)) {
    if(mkdir($color_upload_dir, 0777, true)) {
        echo "Đã tạo thư mục $color_upload_dir thành công!<br>";
    } else {
        echo "Không thể tạo thư mục $color_upload_dir. Hãy tạo thủ công và cấp quyền ghi.<br>";
    }
} else {
    echo "Thư mục $color_upload_dir đã tồn tại.<br>";
}

echo "<br><a href='products.php' class='btn btn-primary'>Quay lại quản lý sản phẩm</a>";
?>
