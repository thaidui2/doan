<?php
// Đơn giản hóa lỗi CORS bằng cách tạo một proxy local
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Nhận endpoint từ query parameter
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : null;
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Kiểm tra endpoint có hợp lệ không
if (!$endpoint || !in_array($endpoint, ['provinces', 'districts', 'wards'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint']);
    exit;
}

// Xây dựng URL
$base_url = 'https://open.oapi.vn/location';
$url = $base_url . '/' . $endpoint;

// Nếu có ID, thêm vào URL
if ($id) {
    $url .= '/' . $id;
}

// Thêm các tham số khác nếu có
$params = [];
foreach ($_GET as $key => $value) {
    if ($key != 'endpoint' && $key != 'id') {
        $params[$key] = $value;
    }
}

if (!empty($params)) {
    $url .= '?' . http_build_query($params);
}

// Thiết lập cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// Thực hiện request
$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Kiểm tra và trả về lỗi nếu có
if ($error) {
    http_response_code(500);
    echo json_encode([
        'error' => 'cURL Error: ' . $error,
        'url' => $url
    ]);
    exit;
}

// Log phản hồi
file_put_contents(__DIR__ . '/api_log.txt', 
    date('Y-m-d H:i:s') . " | $url | $status_code | $response\n", 
    FILE_APPEND);

// Trả về phản hồi từ API
http_response_code($status_code);
echo $response;
