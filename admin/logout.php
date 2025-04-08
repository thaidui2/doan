<?php
session_start();

// Hủy tất cả các biến session
$_SESSION = array();

// Hủy cookie session nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy phiên session
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit();
?>
