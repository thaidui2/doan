<?php
// Set page title
$page_title = 'Quản lý khách hàng';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$user_type_filter = isset($_GET['user_type']) ? (int)$_GET['user_type'] : -1;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id_user';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = "SELECT * FROM users"; // Get all users initially

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(taikhoan LIKE '%$search_keyword%' OR email LIKE '%$search_keyword%' OR sdt LIKE '%$search_keyword%' OR tenuser LIKE '%$search_keyword%')";
}

if ($status_filter !== -1) {
    $where_conditions[] = "trang_thai = $status_filter";
}

// Apply user type filter correctly
if ($user_type_filter !== -1) {
    $where_conditions[] = "loai_user = $user_type_filter";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add sorting
$valid_sort_columns = ['id_user', 'taikhoan', 'tenuser', 'email', 'ngay_tao', 'trang_thai'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id_user';
}

$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY $sort_by $sort_order";

// Pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Add limit for pagination
$query .= " LIMIT $offset, $per_page";

// Execute query
$result = $conn->query($query);
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý khách hàng</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="add_customer.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus"></i> Thêm khách hàng mới
            </a>
        </div>
    </div>

    <?php
    // Display success/error messages if they exist
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $_SESSION['success_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . $_SESSION['error_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Search and filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3" id="searchForm">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="Tên, email, SĐT...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1" <?php echo $status_filter === -1 ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Đã khóa</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user_type" class="form-label">Loại người dùng</label>
                    <select class="form-select" id="user_type" name="user_type">
                        <option value="-1" <?php echo !isset($_GET['user_type']) || $_GET['user_type'] == -1 ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="0" <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 0 ? 'selected' : ''; ?>>Người mua</option>
                        <option value="1" <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 1 ? 'selected' : ''; ?>>Người bán</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Sắp xếp theo</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="id_user" <?php echo $sort_by === 'id_user' ? 'selected' : ''; ?>>ID</option>
                        <option value="tenuser" <?php echo $sort_by === 'tenuser' ? 'selected' : ''; ?>>Tên</option>
                        <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="ngay_tao" <?php echo $sort_by === 'ngay_tao' ? 'selected' : ''; ?>>Ngày đăng ký</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="order" class="form-label">Thứ tự</label>
                    <select class="form-select" id="order" name="order">
                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Tăng dần</option>
                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Giảm dần</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="customers.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách khách hàng</h5>
                <span class="badge bg-secondary"><?php echo $total_rows; ?> khách hàng</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Tài khoản</th>
                            <th scope="col">Tên khách hàng</th>
                            <th scope="col">Liên hệ</th>
                            <th scope="col">Ngày đăng ký</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Loại tài khoản</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($customer = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $customer['id_user']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['taikhoan']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['tenuser']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($customer['email']); ?></div>
                                        <div><?php echo htmlspecialchars($customer['sdt']); ?></div>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($customer['ngay_tao'])); ?></td>
                                    <td>
                                        <?php if ($customer['trang_thai'] == 1): ?>
                                            <span class="badge bg-success">Đang hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Đã khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($customer['loai_user'] == 0): ?>
                                            <span class="badge bg-info">Người mua</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Người bán</span>
                                            <?php if (!empty($customer['ten_shop'])): ?>
                                                <div class="small mt-1"><?php echo htmlspecialchars($customer['ten_shop']); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="customer-detail.php?id=<?php echo $customer['id_user']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                            <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="edit_customer.php?id=<?php echo $customer['id_user']; ?>">
                                                        <i class="bi bi-pencil"></i> Chỉnh sửa
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item toggle-status" href="#" 
                                                       data-id="<?php echo $customer['id_user']; ?>" 
                                                       data-status="<?php echo $customer['trang_thai']; ?>"
                                                       data-username="<?php echo htmlspecialchars($customer['taikhoan']); ?>">
                                                        <?php if ($customer['trang_thai'] == 1): ?>
                                                            <i class="bi bi-lock"></i> Khóa tài khoản
                                                        <?php else: ?>
                                                            <i class="bi bi-unlock"></i> Mở khóa tài khoản
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item change-user-type" href="#" 
                                                       data-id="<?php echo $customer['id_user']; ?>"
                                                       data-type="<?php echo $customer['loai_user']; ?>"
                                                       data-username="<?php echo htmlspecialchars($customer['taikhoan']); ?>">
                                                        <?php if ($customer['loai_user'] == 0): ?>
                                                            <i class="bi bi-shop"></i> Chuyển thành người bán
                                                        <?php else: ?>
                                                            <i class="bi bi-person"></i> Chuyển thành người mua
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger delete-customer" href="#" 
                                                       data-id="<?php echo $customer['id_user']; ?>"
                                                       data-username="<?php echo htmlspecialchars($customer['taikhoan']); ?>">
                                                        <i class="bi bi-trash"></i> Xóa tài khoản
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">Không tìm thấy khách hàng nào</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>&user_type=<?php echo $user_type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        <i class="bi bi-chevron-left"></i> Trước
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&user_type=' . $user_type_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">';
                    echo '<a class="page-link" href="?page=' . $i . '&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&user_type=' . $user_type_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $i . '</a>';
                    echo '</li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&user_type=' . $user_type_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>&user_type=<?php echo $user_type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        Tiếp <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<!-- Toast container for notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Xác nhận</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                Bạn có chắc chắn muốn thực hiện hành động này?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- Change User Type Modal -->
<div class="modal fade" id="changeUserTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeTitle">Thay đổi loại người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="changeTypeBody">
                <p>Bạn có chắc chắn muốn thay đổi loại tài khoản của người dùng này?</p>
                
                <div id="toSellerInfo" class="d-none">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> 
                        Chuyển tài khoản này thành người bán sẽ cho phép họ đăng bán sản phẩm trên hệ thống.
                    </div>
                    <div class="mb-3">
                        <label for="shop_name" class="form-label">Tên cửa hàng</label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name">
                    </div>
                    <div class="mb-3">
                        <label for="shop_description" class="form-label">Mô tả cửa hàng</label>
                        <textarea class="form-control" id="shop_description" name="shop_description" rows="3"></textarea>
                    </div>
                </div>
                
                <div id="toBuyerInfo" class="d-none">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i> 
                        Chuyển tài khoản này thành người mua thông thường sẽ xóa quyền đăng bán sản phẩm. 
                        Các sản phẩm hiện tại của họ sẽ bị ẩn.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmChangeType">
                    Xác nhận thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Toggle user status (active/inactive)
        const toggleStatusLinks = document.querySelectorAll(".toggle-status");
        toggleStatusLinks.forEach(link => {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                const userId = this.getAttribute("data-id");
                const currentStatus = parseInt(this.getAttribute("data-status"));
                const username = this.getAttribute("data-username");
                const newStatus = currentStatus === 1 ? 0 : 1;
                const actionText = newStatus === 1 ? "mở khóa" : "khóa";
                
                // Set modal content
                document.getElementById("modalTitle").textContent = currentStatus === 1 ? "Khóa tài khoản" : "Mở khóa tài khoản";
                document.getElementById("modalBody").innerHTML = `Bạn có chắc chắn muốn <strong>${actionText}</strong> tài khoản <strong>${username}</strong>?`;
                
                // Set confirm action
                document.getElementById("confirmAction").className = `btn ${newStatus === 1 ? "btn-success" : "btn-danger"}`;
                document.getElementById("confirmAction").textContent = currentStatus === 1 ? "Khóa" : "Mở khóa";
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("confirmationModal"));
                modal.show();
                
                // Set up action for confirm button
                document.getElementById("confirmAction").onclick = function() {
                    updateUserStatus(userId, newStatus);
                    modal.hide();
                };
            });
        });
        
        // Delete user
        const deleteLinks = document.querySelectorAll(".delete-customer");
        deleteLinks.forEach(link => {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                const userId = this.getAttribute("data-id");
                const username = this.getAttribute("data-username");
                
                // Set modal content
                document.getElementById("modalTitle").textContent = "Xóa tài khoản";
                document.getElementById("modalBody").innerHTML = `Bạn có chắc chắn muốn xóa tài khoản <strong>${username}</strong>? <br><br><span class="text-danger">Lưu ý: Hành động này không thể hoàn tác và sẽ xóa tất cả dữ liệu liên quan đến tài khoản này.</span>`;
                
                // Set confirm action
                document.getElementById("confirmAction").className = "btn btn-danger";
                document.getElementById("confirmAction").textContent = "Xóa";
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("confirmationModal"));
                modal.show();
                
                // Set up action for confirm button
                document.getElementById("confirmAction").onclick = function() {
                    deleteUser(userId);
                    modal.hide();
                };
            });
        });
        
        // Xử lý thay đổi loại người dùng (user/seller)
        const changeTypeLinks = document.querySelectorAll(".change-user-type");
        changeTypeLinks.forEach(link => {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                const userId = this.getAttribute("data-id");
                const currentType = parseInt(this.getAttribute("data-type"));
                const username = this.getAttribute("data-username");
                
                // Thiết lập modal theo loại thay đổi
                document.getElementById("changeTypeTitle").textContent = 
                    currentType === 0 ? "Chuyển thành người bán" : "Chuyển thành người mua";
                
                if (currentType === 0) {
                    // Chuyển từ người mua -> người bán
                    document.getElementById("toSellerInfo").classList.remove("d-none");
                    document.getElementById("toBuyerInfo").classList.add("d-none");
                    document.getElementById("changeTypeBody").querySelector("p").innerHTML = 
                        `Bạn đang chuyển <strong>${username}</strong> thành người bán hàng.`;
                } else {
                    // Chuyển từ người bán -> người mua
                    document.getElementById("toBuyerInfo").classList.remove("d-none");
                    document.getElementById("toSellerInfo").classList.add("d-none");
                    document.getElementById("changeTypeBody").querySelector("p").innerHTML = 
                        `Bạn đang chuyển <strong>${username}</strong> thành người mua thông thường.`;
                }
                
                // Hiện modal
                const modal = new bootstrap.Modal(document.getElementById("changeUserTypeModal"));
                modal.show();
                
                // Thiết lập hành động cho nút xác nhận
                document.getElementById("confirmChangeType").onclick = function() {
                    // Chuẩn bị dữ liệu gửi đi
                    const newType = currentType === 0 ? 1 : 0;
                    let formData = new FormData();
                    formData.append("user_id", userId);
                    formData.append("new_type", newType);
                    
                    // Thêm thông tin shop nếu chuyển thành người bán
                    if (newType === 1) {
                        formData.append("shop_name", document.getElementById("shop_name").value);
                        formData.append("shop_description", document.getElementById("shop_description").value);
                    }
                    
                    // Gửi yêu cầu AJAX
                    fetch("ajax/change_user_type.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, "success");
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showToast("Lỗi: " + data.message, "danger");
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        showToast("Đã xảy ra lỗi khi thay đổi loại người dùng", "danger");
                    });
                    
                    modal.hide();
                };
            });
        });
        
        // Function to update user status
        function updateUserStatus(userId, newStatus) {
            fetch("ajax/update-customer-status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `id=${userId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast("Cập nhật trạng thái thành công!", "success");
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(`Lỗi: ${data.message}`, "danger");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showToast("Đã xảy ra lỗi khi cập nhật trạng thái!", "danger");
            });
        }
        
        // Function to delete user
        function deleteUser(userId) {
            fetch("ajax/delete-customer.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast("Xóa tài khoản thành công!", "success");
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(`Lỗi: ${data.message}`, "danger");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showToast("Đã xảy ra lỗi khi xóa tài khoản!", "danger");
            });
        }
        
        // Function to show toast notification
        function showToast(message, type = "info") {
            const toastContainer = document.querySelector(".toast-container");
            const toastId = `toast-${Date.now()}`;
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML("beforeend", toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 3000
            });
            toast.show();
            
            toastElement.addEventListener("hidden.bs.toast", function() {
                toastElement.remove();
            });
        }
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
