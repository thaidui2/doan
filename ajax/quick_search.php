<?php
require_once('../config/config.php');

// Đảm bảo có keyword
if (!isset($_GET['keyword']) || empty($_GET['keyword'])) {
    echo json_encode([]);
    exit;
}

$keyword = trim($_GET['keyword']);
$search_param = "%" . $keyword . "%";

// Truy vấn sản phẩm
$stmt = $conn->prepare("
    SELECT id_sanpham, tensanpham, gia, hinhanh 
    FROM sanpham 
    WHERE trangthai = 1 AND tensanpham LIKE ? 
    ORDER BY tensanpham 
    LIMIT 5
");
$stmt->bind_param("s", $search_param);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id_sanpham' => $row['id_sanpham'],
        'tensanpham' => $row['tensanpham'],
        'gia' => $row['gia'],
        'hinhanh' => $row['hinhanh']
    ];
}

// Trả về dạng JSON
header('Content-Type: application/json');
echo json_encode($products);