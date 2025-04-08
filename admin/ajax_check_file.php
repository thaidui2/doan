<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized access");
}

$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    echo "<div class='alert alert-danger'>Không có file nào được chỉ định</div>";
    exit;
}

// Đảm bảo chỉ kiểm tra file trong thư mục ajax
if (strpos($file, 'ajax/') !== 0) {
    echo "<div class='alert alert-danger'>Chỉ cho phép kiểm tra file trong thư mục ajax/</div>";
    exit;
}

$file_path = __DIR__ . '/' . $file;
$real_path = realpath($file_path);

if (file_exists($file_path)) {
    echo "<div class='alert alert-success'>
        <strong>File tồn tại!</strong><br>
        Đường dẫn: $file_path<br>
        Đường dẫn thực: $real_path<br>
        Kích thước: " . filesize($file_path) . " bytes<br>
        Quyền: " . substr(sprintf('%o', fileperms($file_path)), -4) . "
    </div>";
} else {
    echo "<div class='alert alert-danger'>
        <strong>File không tồn tại!</strong><br>
        Đường dẫn kiểm tra: $file_path
    </div>";
    
    // Kiểm tra thư mục
    $dir = dirname($file_path);
    if (file_exists($dir)) {
        echo "<div class='alert alert-info'>
            Thư mục <code>" . dirname($file) . "</code> tồn tại.<br>
            Các file trong thư mục này:<br><ul>";
        
        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f != "." && $f != "..") {
                echo "<li>$f</li>";
            }
        }
        echo "</ul></div>";
    } else {
        echo "<div class='alert alert-warning'>Thư mục <code>" . dirname($file) . "</code> không tồn tại!</div>";
    }
}
