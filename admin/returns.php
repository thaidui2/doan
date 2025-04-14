<?php
// Đặt tiêu đề trang
$page_title = 'Quản lý hoàn trả';

// Include header (sẽ kiểm tra đăng nhập)
include('includes/header.php');

// Include kết nối database
include('../config/config.php');

// Lọc trạng thái
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$search_keyword = isset($_GET['search']) ? $_GET['search'] : '';

// Phân trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

// Xây dựng truy vấn
$query = "SELECT hr.*, 
          u.tenuser, u.email,
          sp.tensanpham, sp.hinhanh,
          dh.id_donhang AS ma_donhang
          FROM hoantra hr
          JOIN users u ON hr.id_nguoidung = u.id_user
          JOIN sanpham sp ON hr.id_sanpham = sp.id_sanpham
          JOIN donhang dh ON hr.id_donhang = dh.id_donhang";

$where_conditions = [];
// Lọc theo trạng thái
if ($status_filter > 0) {
    $where_conditions[] = "hr.trangthai = $status_filter";
}

// Tìm kiếm
if (!empty($search_keyword)) {
    $search_term = "%$search_keyword%";
    $where_conditions[] = "(u.tenuser LIKE '$search_term' OR u.email LIKE '$search_term' OR dh.ma_donhang LIKE '$search_term' OR sp.tensanpham LIKE '$search_term')";
}

// Thêm điều kiện WHERE nếu có
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Sắp xếp theo thời gian tạo mới nhất
$query .= " ORDER BY hr.ngaytao DESC";

// Đếm tổng số bản ghi
$count_query = str_replace("hr.*, u.tenuser, u.sodienthoai, u.email, sp.tensanpham, sp.hinhanh, dh.ma_donhang", "COUNT(*) as total", $query);
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Thêm giới hạn cho phân trang
$query .= " LIMIT $offset, $items_per_page";

// Thực thi truy vấn
$result = $conn->query($query);

// Mảng trạng thái hoàn trả
$return_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning'],
    2 => ['name' => 'Đã xác nhận', 'badge' => 'info'],
    3 => ['name' => 'Đang xử lý', 'badge' => 'primary'],
    4 => ['name' => 'Hoàn thành', 'badge' => 'success'],
    5 => ['name' => 'Từ chối', 'badge' => 'danger']
];
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý yêu cầu hoàn trả</h1>
    </div>
    
    <!-- Filter and search -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="btn-group" role="group">
                <a href="returns.php" class="btn <?php echo $status_filter === 0 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Tất cả
                </a>
                <?php foreach($return_statuses as $id => $status): ?>
                    <a href="returns.php?status=<?php echo $id; ?>" class="btn <?php echo $status_filter === $id ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        <?php echo $status['name']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-md-6">
            <form action="" method="get" class="d-flex">
                <?php if($status_filter > 0): ?>
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <?php endif; ?>
                <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Returns table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Khách hàng</th>
                            <th scope="col">Đơn hàng</th>
                            <th scope="col">Sản phẩm</th>
                            <th scope="col">Lý do</th>
                            <th scope="col">Ngày tạo</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id_hoantra']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($row['tenuser']); ?></div>
                                        <small class="text-muted"><?php echo $row['email']; ?></small>
                                    </td>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $row['id_donhang']; ?>">#<?php echo $row['ma_donhang']; ?></a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../uploads/products/<?php echo $row['hinhanh']; ?>" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div><?php echo htmlspecialchars($row['tensanpham']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($row['lydo']); ?></div>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['ngaytao'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $return_statuses[$row['trangthai']]['badge']; ?>">
                                            <?php echo $return_statuses[$row['trangthai']]['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="return-detail.php?id=<?php echo $row['id_hoantra']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Xem
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                                    <p class="text-muted">Không tìm thấy yêu cầu hoàn trả nào.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Phân trang">
            <ul class="pagination">
                <?php if($current_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</main>

<?php include('includes/footer.php'); ?>