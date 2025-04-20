<?php
session_start();
include('config/config.php');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    $_SESSION['error_message'] = "Vui lòng đăng nhập để thực hiện thao tác này.";
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Check if order ID is valid
if ($order_id <= 0) {
    $_SESSION['error_message'] = "Mã đơn hàng không hợp lệ";
    header('Location: donhang.php');
    exit();
}

// Check if order belongs to current user and is in a cancellable state
$check_query = $conn->prepare("
    SELECT id, ma_donhang, trang_thai_don_hang FROM donhang
    WHERE id = ? AND id_user = ?
");
$check_query->bind_param("ii", $order_id, $user_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Không tìm thấy đơn hàng hoặc bạn không có quyền hủy đơn hàng này";
    header('Location: donhang.php');
    exit();
}

$order = $result->fetch_assoc();

// Check if order can be canceled (only status 1: awaiting confirmation)
if ($order['trang_thai_don_hang'] != 1) {
    $_SESSION['error_message'] = "Đơn hàng này không thể hủy do đã được xử lý";
    header('Location: chitiet-donhang.php?id=' . $order_id);
    exit();
}

// Get reason for cancellation if submitted via POST, otherwise show form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($reason)) {
    // Process order cancellation
    $cancel_query = $conn->prepare("
        UPDATE donhang 
        SET trang_thai_don_hang = 5, ngay_capnhat = NOW()
        WHERE id = ? AND id_user = ? AND trang_thai_don_hang = 1
    ");
    $cancel_query->bind_param("ii", $order_id, $user_id);
    $cancel_query->execute();
    
    // Check if cancellation was successful
    if ($cancel_query->affected_rows > 0) {
        // Add record to order history
        $user_name = $_SESSION['user']['ten'] ?? $_SESSION['user']['taikhoan'] ?? 'Khách hàng';
        $note = "Đơn hàng đã bị hủy với lý do: " . $reason;
        
        $history_query = $conn->prepare("
            INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
            VALUES (?, 'Hủy đơn hàng', ?, ?)
        ");
        $history_query->bind_param("iss", $order_id, $user_name, $note);
        $history_query->execute();
        
        $_SESSION['success_message'] = "Đơn hàng #" . $order['ma_donhang'] . " đã được hủy thành công";
        header('Location: donhang.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Không thể hủy đơn hàng. Vui lòng thử lại hoặc liên hệ hỗ trợ";
        header('Location: chitiet-donhang.php?id=' . $order_id);
        exit();
    }
} else {
    // Show cancellation form
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hủy đơn hàng #<?php echo $order['ma_donhang']; ?> - Bug Shop</title>
        <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="css/index.css">
    </head>
    <body>
        <?php include('includes/header.php'); ?>
        
        <div class="container py-5">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="donhang.php">Đơn hàng của tôi</a></li>
                    <li class="breadcrumb-item"><a href="chitiet-donhang.php?id=<?php echo $order_id; ?>">Đơn hàng #<?php echo $order['ma_donhang']; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Hủy đơn hàng</li>
                </ol>
            </nav>
            
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="card-title mb-0">Hủy đơn hàng #<?php echo $order['ma_donhang']; ?></h4>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <strong>Lưu ý:</strong> Sau khi hủy, bạn sẽ không thể khôi phục lại đơn hàng này.
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Lý do hủy đơn hàng <span class="text-danger">*</span></label>
                                    <select class="form-select mb-2" id="reason-select" onchange="toggleOtherReason()">
                                        <option value="">-- Chọn lý do --</option>
                                        <option value="Tôi muốn thay đổi địa chỉ giao hàng">Tôi muốn thay đổi địa chỉ giao hàng</option>
                                        <option value="Tôi muốn thay đổi phương thức thanh toán">Tôi muốn thay đổi phương thức thanh toán</option>
                                        <option value="Đổi ý, không muốn mua nữa">Đổi ý, không muốn mua nữa</option>
                                        <option value="other">Lý do khác...</option>
                                    </select>
                                    
                                    <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Nhập lý do hủy đơn hàng..." required></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="chitiet-donhang.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Quay lại
                                    </a>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-x-circle"></i> Xác nhận hủy đơn hàng
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include('includes/footer.php'); ?>
        
        <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function toggleOtherReason() {
                const reasonSelect = document.getElementById('reason-select');
                const reasonTextarea = document.getElementById('reason');
                
                if (reasonSelect.value === 'other') {
                    reasonTextarea.value = '';
                    reasonTextarea.placeholder = 'Nhập lý do khác...';
                    reasonTextarea.focus();
                } else {
                    reasonTextarea.value = reasonSelect.value;
                }
            }
            
            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                const reasonTextarea = document.getElementById('reason');
                reasonTextarea.value = '';
            });
        </script>
    </body>
    </html>
    <?php
}
?>
