<?php
session_start();
include('../config/config.php');

// Kiểm tra đăng nhập người dùng
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['logged_in']) || $_SESSION['user']['logged_in'] != "1") {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập'
    ]);
    exit;
}

// Kiểm tra quyền người bán (cần thêm vào database)
$user_id = $_SESSION['user']['id'];
$check_seller = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE id_user = ? AND loai_user = 1");
$check_seller->bind_param("i", $user_id);
$check_seller->execute();
$seller_result = $check_seller->get_result()->fetch_assoc();

if ($seller_result['count'] == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Tài khoản không có quyền thêm màu mới'
    ]);
    exit;
}

// Lấy dữ liệu từ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['name']) || empty($_POST['code'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dữ liệu không hợp lệ'
    ]);
    exit;
}

$colorName = trim($_POST['name']);
$colorCode = trim($_POST['code']);
$user_id = $_SESSION['user']['id'];

// Kiểm tra xem màu đã tồn tại chưa
$check = $conn->prepare("SELECT COUNT(*) as count FROM mausac WHERE tenmau = ?");
$check->bind_param("s", $colorName);
$check->execute();
$result = $check->get_result()->fetch_assoc();

if ($result['count'] > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Màu này đã tồn tại trong hệ thống'
    ]);
    exit;
}

// Thêm màu mới vào database
try {
    $stmt = $conn->prepare("INSERT INTO mausac (tenmau, mamau, id_nguoithem, trangthai) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("ssi", $colorName, $colorCode, $user_id);
    
    if ($stmt->execute()) {
        $color_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Thêm màu mới thành công',
            'id' => $color_id,
            'name' => $colorName,
            'code' => $colorCode
        ]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi thêm màu mới: ' . $e->getMessage()
    ]);
}
?>