<?php
// Khởi động phiên làm việc nếu chưa được khởi động
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kết nối CSDL, etc...
require_once(__DIR__ . '/../config/config.php');

// Hàm xử lý đường dẫn ảnh (sản phẩm, danh mục, thương hiệu, etc.)
function getProductImagePath($imageName, $defaultImage = 'images/no-image.jpg')
{
    // Nếu không có tên ảnh, trả về ảnh mặc định
    if (empty($imageName)) {
        return $defaultImage;
    }

    // Nếu đường dẫn đã có prefix 'uploads/'
    if (strpos($imageName, 'uploads/') === 0) {
        // Kiểm tra xem file có tồn tại không
        if (file_exists($imageName)) {
            $imgPath = $imageName;
        } else {
            $imgPath = $defaultImage;
        }
    } else {
        // Thử các thư mục uploads phổ biến
        $possiblePaths = [
            'uploads/products/' . $imageName,
            'uploads/categories/' . $imageName,
            'uploads/brands/' . $imageName,
            $imageName // Đường dẫn trực tiếp
        ];

        $imgPath = $defaultImage; // Mặc định

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $imgPath = $path;
                break;
            }
        }
    }
    return $imgPath;
}

// Các cài đặt khác...
?>