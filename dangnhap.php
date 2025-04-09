<?php 
    include('config/config.php'); 
    session_start();
    
    // Khởi tạo biến để tránh lỗi "undefined"
    $taikhoan = "";
    $matkhau = "";
    $error = "";
    
    // Kiểm tra nếu người dùng đã đăng nhập
    if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in'] === true) {
        header('Location: index.php');
        exit();
    }
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Kiểm tra các khóa tồn tại trước khi truy cập
        $taikhoan = isset($_POST['taikhoan']) ? trim($_POST['taikhoan']) : "";
        $matkhau = isset($_POST['matkhau']) ? trim($_POST['matkhau']) : "";
        
        // Kiểm tra tài khoản tồn tại
        $stmt = $conn->prepare("SELECT id_user, matkhau, tenuser, trang_thai FROM users WHERE taikhoan = ?");
        $stmt->bind_param("s", $taikhoan);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Kiểm tra mật khẩu bằng password_verify
            if(password_verify($matkhau, $user['matkhau'])) {
                // Kiểm tra trạng thái tài khoản
                if ($user['trang_thai'] == 0) {
                    // Tài khoản bị khóa
                    $_SESSION['login_error'] = 'Tài khoản của bạn đã bị khóa.';
                    $_SESSION['account_locked'] = true;
                    $_SESSION['locked_user_id'] = $user['id_user'];
                    header('Location: tai-khoan-bi-khoa.php');
                    exit;
                }
                
                // Đăng nhập thành công - sử dụng namespace 'user'
                $_SESSION['user'] = [
                    'logged_in' => true,
                    'id' => $user['id_user'],
                    'username' => $taikhoan,
                    'tenuser' => $user['tenuser']
                ];
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Mật khẩu không chính xác";
            }
        } else {
            $error = "Tài khoản không tồn tại";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập-Bug Shop</title>
    <link rel="stylesheet" href="css\login.css">
    <link rel="stylesheet" href="node_modules\bootstrap\dist\css\bootstrap.css">
    <script src="node_modules\bootstrap\dist\js\bootstrap.bundle.js"></script>
</head>

<body>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="POST">
        <div class="container mt-5">
            <h2>Đăng Nhập</h2>
            <div class="mb-3">
                <label for="taikhoan" class="form-label">Tên đăng nhập</label>
                <input type="text" autocomplete="off" class="form-control" id="taikhoan" name="taikhoan" required>
            </div>
            <div class="mb-3">
                <label for="matkhau" class="form-label">Mật khẩu</label>
                <input type="password" autocomplete="off" class="form-control" id="matkhau" name="matkhau" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="showPassword">
                <label class="form-check-label" for="showPassword">Hiện mật khẩu</label>
            </div>
            <button type="submit" class="btn btn-primary">Đăng Nhập</button>
        </div>
        <div class="container mt-3">
            <p>Chưa có tài khoản? <a href="dangky.php">Đăng ký ngay</a></p>
        </div>
    </form>
    
            <script>
                document.getElementById('showPassword').addEventListener('change', function() {
                    var passwordField = document.getElementById('matkhau');
                    passwordField.type = this.checked ? 'text' : 'password';
                });
            </script>
</body>
</html>