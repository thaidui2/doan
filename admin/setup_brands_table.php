<?php
// Setup script for brands table
include('../config/config.php');

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'thuong_hieu'");
if ($table_check->num_rows == 0) {
    // Create the table
    $sql = "CREATE TABLE thuong_hieu (
        id INT(11) NOT NULL AUTO_INCREMENT,
        ten VARCHAR(255) NOT NULL,
        mo_ta TEXT DEFAULT NULL,
        logo VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql) === TRUE) {
        echo "Bảng thuong_hieu đã được tạo thành công!";
    } else {
        echo "Lỗi khi tạo bảng thuong_hieu: " . $conn->error;
    }
} else {
    echo "Bảng thuong_hieu đã tồn tại!";
}

// Check if sanpham table has thuonghieu column
$column_check = $conn->query("SHOW COLUMNS FROM sanpham LIKE 'thuonghieu'");
if ($column_check->num_rows == 0) {
    // Add the column
    $sql = "ALTER TABLE sanpham ADD COLUMN thuonghieu INT(11) DEFAULT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "<br>Đã thêm cột thuonghieu vào bảng sanpham!";
    } else {
        echo "<br>Lỗi khi thêm cột thuonghieu: " . $conn->error;
    }
} else {
    echo "<br>Cột thuonghieu đã tồn tại trong bảng sanpham!";
}

$conn->close();
?>
