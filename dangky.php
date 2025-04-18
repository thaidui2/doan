<?php
// Kết nối database và khởi tạo session
include('config/config.php');

if (!isset($conn) || $conn->connect_error) {
    die("Kết nối database thất bại: " . ($conn->connect_error ?? "Không có kết nối"));
}

session_start();

// Nếu người dùng đã đăng nhập, chuyển hướng về trang chủ
if(isset($_SESSION['user']['id'])) { // Cập nhật theo cấu trúc session mới
    header("Location: index.php");
    exit();
}

// Khởi tạo biến
$taikhoan = "";
$ten = ""; // Đổi tenuser thành ten
$sodienthoai = ""; // Đổi sdt thành sodienthoai
$diachi = "";
$email = "";
$error = "";
$success = "";

// Xử lý form khi được gửi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy và làm sạch dữ liệu
    $taikhoan = trim($_POST['taikhoan']);
    $ten = trim($_POST['tenuser']); // Vẫn giữ tên field form là tenuser
    $sodienthoai = trim($_POST['sdt']); // Vẫn giữ tên field form là sdt
    $diachi = trim($_POST['diachi']);
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $matkhau = trim($_POST['matkhau']);
    $confirm_password = trim($_POST['confirm_password']);

    // Kiểm tra các trường
    if(empty($taikhoan) || empty($ten) || empty($matkhau) || empty($confirm_password) || empty($sodienthoai) || empty($diachi)) {
        $error = "Vui lòng điền đầy đủ thông tin.";
    } elseif(empty($email)) {
        $error = "Email không được để trống.";
    } elseif($matkhau !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    } elseif(strlen($matkhau) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif(!preg_match('/^[0-9]{10}$/', $sodienthoai)) {
        $error = "Số điện thoại phải gồm 10 chữ số.";
    } elseif($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Định dạng email không hợp lệ.";
    } else {
        // Kiểm tra taikhoan đã tồn tại chưa
        $check_user = $conn->prepare("SELECT id FROM users WHERE taikhoan = ?"); // Đổi id_user thành id
        $check_user->bind_param("s", $taikhoan);
        $check_user->execute();
        $result = $check_user->get_result();
        
        if($result->num_rows > 0) {
            $error = "Tên tài khoản đã được sử dụng.";
        } else {
            // Kiểm tra sodienthoai đã tồn tại chưa
            $check_sdt = $conn->prepare("SELECT id FROM users WHERE sodienthoai = ?"); // Đổi sdt thành sodienthoai
            $check_sdt->bind_param("s", $sodienthoai);
            $check_sdt->execute();
            $result = $check_sdt->get_result();
            
            if($result->num_rows > 0) {
                $error = "Số điện thoại đã được sử dụng.";
            } else if($email) {
                // Kiểm tra email đã tồn tại chưa (nếu có)
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?"); // Đổi id_user thành id
                $check_email->bind_param("s", $email);
                $check_email->execute();
                $result = $check_email->get_result();
                
                if($result->num_rows > 0) {
                    $error = "Email đã được sử dụng.";
                } else {
                    // Mã hóa mật khẩu
                    $hashed_password = password_hash($matkhau, PASSWORD_DEFAULT);
                    
                    // Thêm người dùng vào database - Cập nhật tên cột
                    $insert = $conn->prepare("INSERT INTO users (taikhoan, matkhau, email, sodienthoai, diachi, ten, loai_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $loai_user = 0; // Mặc định là người mua
                    $insert->bind_param("ssssssi", $taikhoan, $hashed_password, $email, $sodienthoai, $diachi, $ten, $loai_user);
                    
                    if($insert->execute()) {
                        $success = "Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.";
                        // Làm trống dữ liệu form
                        $taikhoan = $ten = $sodienthoai = $diachi = $email = "";
                    } else {
                        $error = "Đã xảy ra lỗi: " . $insert->error . " (Mã lỗi: " . $insert->errno . ")";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/dangky.css">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Đăng Ký Tài Khoản</h2>
                        
                        <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-3">
                                <label for="taikhoan" class="form-label">Tên tài khoản</label>
                                <input type="text" class="form-control" id="taikhoan" name="taikhoan" 
                                       value="<?php echo htmlspecialchars($taikhoan ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sdt" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="sdt" name="sdt" 
                                       value="<?php echo htmlspecialchars($sodienthoai ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="diachi" class="form-label">Địa chỉ</label>
                                <input type="text" class="form-control" id="diachi" name="diachi" 
                                       value="<?php echo htmlspecialchars($diachi ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tenuser" class="form-label">Họ và tên</label>
                                <input type="text" class="form-control" id="tenuser" name="tenuser" 
                                       value="<?php echo htmlspecialchars($ten ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="matkhau" class="form-label">Mật khẩu</label>
                                <input type="password" class="form-control" id="matkhau" name="matkhau" required>
                                <div class="form-text">Mật khẩu phải có ít nhất 6 ký tự.</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2">Đăng Ký</button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p>Đã có tài khoản? <a href="dangnhap.php">Đăng nhập ngay</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
</body>
</html>