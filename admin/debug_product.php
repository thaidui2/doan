<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Trang debug</h1>";

// Kiểm tra session
session_start();
echo "<p>Trạng thái session: ";
var_dump(isset($_SESSION['admin_logged_in']));
echo "</p>";

// Kiểm tra kết nối database
include('../config/config.php');
echo "<p>Trạng thái kết nối: ";
var_dump($conn->ping());
echo "</p>";

// Kiểm tra truy vấn cơ bản
try {
    $result = $conn->query("SHOW TABLES");
    echo "<p>Danh sách bảng trong database:</p>";
    echo "<ul>";
    while ($row = $result->fetch_row()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>Lỗi truy vấn: " . $e->getMessage() . "</p>";
}