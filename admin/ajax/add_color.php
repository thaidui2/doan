<?php
session_start();
header('Content-Type: application/json');

// Log để debug
error_log("Add color AJAX requested: " . json_encode($_POST));

include('../../config/config.php');

// Kiểm tra kết nối database
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database: ' . $conn->connect_error]);
    exit();
}

// Lấy dữ liệu từ request
$colorName = trim($_POST['name'] ?? '');
$colorCode = trim($_POST['code'] ?? '');

// Validate dữ liệu
if (empty($colorName)) {
    echo json_encode(['success' => false, 'message' => 'Tên màu không được để trống']);
    exit();
}

if (empty($colorCode)) {
    $colorCode = '#000000'; // Default to black if no color code provided
}

// Kiểm tra xem cột trangthai đã tồn tại chưa
$check_column = $conn->query("SHOW COLUMNS FROM mausac LIKE 'trangthai'");
$has_trangthai = $check_column->num_rows > 0;

try {
    // Kiểm tra xem màu đã tồn tại chưa
    $check_stmt = $conn->prepare("SELECT id_mausac FROM mausac WHERE tenmau = ? OR mamau = ?");
    $check_stmt->bind_param("ss", $colorName, $colorCode);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Màu với tên hoặc mã này đã tồn tại']);
        exit();
    }

    // Thêm màu mới vào database
    if ($has_trangthai) {
        // Nếu có cột trangthai
        $insert_stmt = $conn->prepare("INSERT INTO mausac (tenmau, mamau, trangthai) VALUES (?, ?, 1)");
        $insert_stmt->bind_param("ss", $colorName, $colorCode);
    } else {
        // Nếu không có cột trangthai
        $insert_stmt = $conn->prepare("INSERT INTO mausac (tenmau, mamau) VALUES (?, ?)");
        $insert_stmt->bind_param("ss", $colorName, $colorCode);
    }

    if ($insert_stmt->execute()) {
        $newColorId = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Thêm màu thành công',
            'id' => $newColorId,
            'name' => $colorName,
            'code' => $colorCode
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi SQL khi thêm màu: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi xử lý: ' . $e->getMessage()
    ]);
}
