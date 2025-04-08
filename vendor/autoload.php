<?php
// filepath: c:\xampp\htdocs\bug_shop\vendor\autoload.php
// Sửa đường dẫn - thêm thư mục 'src'
$phpspreadsheetPath = __DIR__ . '/phpoffice/phpspreadsheet/src/';

// Hàm tự động nạp các lớp của PhpSpreadsheet
spl_autoload_register(function ($class) use ($phpspreadsheetPath) {
    // Chỉ nạp các lớp thuộc namespace PhpOffice\PhpSpreadsheet
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet\\') === 0) {
        // Chuyển namespace thành đường dẫn file
        $path = str_replace('\\', '/', $class);
        $path = str_replace('PhpOffice/PhpSpreadsheet/', '', $path);
        $file = $phpspreadsheetPath . $path . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});