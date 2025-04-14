<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];
$page_title = "Yêu cầu hoàn trả của tôi";

// Phân trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// Lấy tổng số yêu cầu hoàn trả
$count_query = "SELECT COUNT(*) as total FROM hoantra WHERE id_nguoidung = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Lấy danh sách yêu cầu hoàn trả
$query = "SELECT hr.*, dh.id_donhang, sp.tensanpham, sp.hinhanh, sp.gia
          FROM hoantra hr
          JOIN donhang dh ON hr.id_donhang = dh.id_donhang
          JOIN sanpham sp ON hr.id_sanpham = sp.id_sanpham
          WHERE hr.id_nguoidung = ?
          ORDER BY hr.ngaytao DESC
          LIMIT ?, ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $offset, $items_per_page);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bug Shop</title>
    <?php include('includes/head.php'); ?>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="row">
            <!-- Menu tài khoản -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <img src="assets/img/default-avatar.png" class="rounded-circle" alt="Avatar" width="60">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0"><?php echo $_SESSION['user']['tenuser']; ?></h5>
                                <p class="text-muted mb-0 small">
                                    <i class="bi bi-envelope-fill"></i> <?php echo $_SESSION['user']['emailuser'] ?? $_SESSION['user']['username'] ?? ''; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <a href="taikhoan.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-person me-2"></i> Thông tin tài khoản
                            </a>
                            <a href="donhang.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-receipt me-2"></i> Đơn hàng của tôi
                            </a>
                            <a href="donhoantra.php" class="list-group-item list-group-item-action active">
                                <i class="bi bi-arrow-return-left me-2"></i> Yêu cầu hoàn trả
                            </a>
                            <a href="wishlist.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-heart me-2"></i> Sản phẩm yêu thích
                            </a>
                            <a href="doimatkhau.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-key me-2"></i> Đổi mật khẩu
                            </a>
                            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Danh sách yêu cầu hoàn trả -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4 mb-0">Yêu cầu hoàn trả của tôi</h2>
                    <a href="donhang.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Quay lại đơn hàng
                    </a>
                </div>

                <?php if ($result->num_rows > 0): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Sản phẩm</th>
                                            <th scope="col">Lý do</th>
                                            <th scope="col">Ngày yêu cầu</th>
                                            <th scope="col">Trạng thái</th>
                                            <th scope="col">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['id_hoantra']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo !empty($row['hinhanh']) ? 'uploads/products/'.$row['hinhanh'] : 'assets/img/default-product.jpg'; ?>" 
                                                            class="img-thumbnail me-2" width="50" height="50" style="object-fit: cover;">
                                                        <div class="small">
                                                            <div class="fw-bold"><?php echo $row['tensanpham']; ?></div>
                                                            <div class="text-muted"><?php echo number_format($row['gia'], 0, ',', '.'); ?>₫</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 150px;">
                                                        <?php echo $row['lydo']; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($row['ngaytao'])); ?></td>
                                                <td>
                                                    <?php
                                                    // Hiển thị trạng thái
                                                    switch($row['trangthai']) {
                                                        case 1:
                                                            echo '<span class="badge bg-warning text-dark">Chờ xác nhận</span>';
                                                            break;
                                                        case 2:
                                                            echo '<span class="badge bg-info">Đã xác nhận</span>';
                                                            break;
                                                        case 3:
                                                            echo '<span class="badge bg-primary">Đang xử lý</span>';
                                                            break;
                                                        case 4:
                                                            echo '<span class="badge bg-success">Hoàn thành</span>';
                                                            break;
                                                        case 5:
                                                            echo '<span class="badge bg-danger">Từ chối</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">Không xác định</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="chi-tiet-hoan-tra.php?id=<?php echo $row['id_hoantra']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Chi tiết
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Phân trang -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1" aria-label="Trang đầu">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Trang trước">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Trang sau">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>" aria-label="Trang cuối">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="lead mt-3">Bạn chưa có yêu cầu hoàn trả nào</p>
                        <a href="donhang.php" class="btn btn-primary mt-3">Xem đơn hàng của tôi</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>