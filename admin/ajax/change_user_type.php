<?php
header('Content-Type: application/json');
// Vô hiệu hóa API chuyển đổi loại người dùng
echo json_encode(['success' => false, 'message' => 'Chức năng này đã bị vô hiệu hóa.']);
exit;
?>