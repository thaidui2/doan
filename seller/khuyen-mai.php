<?php
// Thiết lập tiêu đề trang
$page_title = "Quản Lý Mã Khuyến Mãi";

// Include header
include('includes/header.php');

// Thêm CSS cho Select2 vào phần đầu file
$page_specific_css = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Fix for Select2 in Bootstrap modal */
    .select2-container--bootstrap-5 {
        z-index: 1056 !important; /* Higher than modal\'s z-index */
    }
    
    /* Make sure the dropdown is visible */
    .select2-dropdown {
        z-index: 1056 !important;
    }
    
    /* Fix border and width */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #ced4da;
        width: 100% !important;
    }
    
    /* Fix for modal padding issue */
    .select2-container--open .select2-dropdown {
        left: 0;
    }
    
    /* Make sure the dropdown is wide enough */
    .select2-container {
        width: 100% !important;
    }
</style>
';

// Xử lý tạo mã khuyến mãi mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $ma_code = strtoupper(trim($_POST['ma_code'])); 
    $loai_giam_gia = (int)$_POST['loai_giam_gia']; // 1: Phần trăm, 2: Số tiền cố định
    $gia_tri = (float)$_POST['gia_tri'];
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $so_luong = (int)$_POST['so_luong'];
    $gia_tri_don_toi_thieu = !empty($_POST['gia_tri_don_toi_thieu']) ? (float)$_POST['gia_tri_don_toi_thieu'] : 0;
    $gia_tri_giam_toi_da = !empty($_POST['gia_tri_giam_toi_da']) ? (float)$_POST['gia_tri_giam_toi_da'] : 0;
    $mo_ta = trim($_POST['mo_ta']);
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    
    // Xử lý các loại sản phẩm áp dụng
    $ap_dung_type = isset($_POST['ap_dung']) ? $_POST['ap_dung'] : 'all';
    $ap_dung_sanpham = ($ap_dung_type === 'specific') ? 1 : 0;
    $ap_dung_loai = ($ap_dung_type === 'category') ? 1 : 0;
    
    $san_pham_ap_dung = [];
    $loai_ap_dung = [];
    
    // Cải thiện xử lý khi chọn nhiều sản phẩm
    if ($ap_dung_sanpham && isset($_POST['san_pham_ap_dung']) && is_array($_POST['san_pham_ap_dung'])) {
        $san_pham_ap_dung = array_map('intval', array_filter($_POST['san_pham_ap_dung']));
        
        // Kiểm tra nếu không có sản phẩm nào được chọn
        if (empty($san_pham_ap_dung)) {
            $errors[] = "Vui lòng chọn ít nhất một sản phẩm áp dụng";
        }
    } else if ($ap_dung_sanpham) {
        $errors[] = "Vui lòng chọn ít nhất một sản phẩm áp dụng";
    }
    
    // Cải thiện xử lý khi chọn nhiều loại sản phẩm
    if ($ap_dung_loai && isset($_POST['loai_ap_dung']) && is_array($_POST['loai_ap_dung'])) {
        $loai_ap_dung = array_map('intval', array_filter($_POST['loai_ap_dung']));
        
        // Kiểm tra nếu không có loại sản phẩm nào được chọn
        if (empty($loai_ap_dung)) {
            $errors[] = "Vui lòng chọn ít nhất một loại sản phẩm áp dụng";
        }
    } else if ($ap_dung_loai) {
        $errors[] = "Vui lòng chọn ít nhất một loại sản phẩm áp dụng";
    }
    
    // Kiểm tra dữ liệu đầu vào
    $errors = [];
    
    if ($loai_giam_gia == 2 && $gia_tri <= 0) {
        $errors[] = "Giá trị giảm phải lớn hơn 0";
    }
    
    if (strtotime($ngay_ket_thuc) <= strtotime($ngay_bat_dau)) {
        $errors[] = "Ngày kết thúc phải sau ngày bắt đầu";
    }
    
    if (count($errors) === 0) {
        try {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            // Thêm thông tin mã giảm giá
            $insert_stmt = $conn->prepare("
                INSERT INTO khuyen_mai (
                    ma_code, loai_giam_gia, gia_tri, 
                    ngay_bat_dau, ngay_ket_thuc, so_luong, 
                    so_luong_da_dung, gia_tri_don_toi_thieu, 
                    gia_tri_giam_toi_da, mo_ta, trang_thai,
                    id_nguoiban, ap_dung_sanpham, ap_dung_loai
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->bind_param(
                "siissiidssiiii", 
                $ma_code, $loai_giam_gia, $gia_tri,
                $ngay_bat_dau, $ngay_ket_thuc, $so_luong,
                $gia_tri_don_toi_thieu, $gia_tri_giam_toi_da, 
                $mo_ta, $trang_thai, $user_id, $ap_dung_sanpham, $ap_dung_loai
            );
            
            $insert_stmt->execute();
            $khuyen_mai_id = $conn->insert_id;
            
            // Thêm sản phẩm áp dụng nếu có
            if ($ap_dung_sanpham && !empty($san_pham_ap_dung)) {
                $product_insert = $conn->prepare("
                    INSERT INTO khuyen_mai_sanpham (id_khuyen_mai, id_sanpham) 
                    VALUES (?, ?)
                ");
                
                foreach ($san_pham_ap_dung as $product_id) {
                    $product_insert->bind_param("ii", $khuyen_mai_id, $product_id);
                    $product_insert->execute();
                }
            }
            
            // Thêm loại sản phẩm áp dụng nếu có
            if ($ap_dung_loai && !empty($loai_ap_dung)) {
                $category_insert = $conn->prepare("
                    INSERT INTO khuyen_mai_loai (id_khuyen_mai, id_loai) 
                    VALUES (?, ?)
                ");
                
                foreach ($loai_ap_dung as $category_id) {
                    $category_insert->bind_param("ii", $khuyen_mai_id, $category_id);
                    $category_insert->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Thêm mã khuyến mãi thành công!";
            header("Location: khuyen-mai.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            $_SESSION['error_message'] = "Lỗi khi thêm mã khuyến mãi: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Xử lý cập nhật mã khuyến mãi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_khuyen_mai = (int)$_POST['id_khuyen_mai'];
    $ma_code = strtoupper(trim($_POST['ma_code']));
    $loai_giam_gia = (int)$_POST['loai_giam_gia']; // 1: Phần trăm, 2: Số tiền cố định
    $gia_tri = (float)$_POST['gia_tri'];
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $so_luong = (int)$_POST['so_luong'];
    $gia_tri_don_toi_thieu = !empty($_POST['gia_tri_don_toi_thieu']) ? (float)$_POST['gia_tri_don_toi_thieu'] : 0;
    $gia_tri_giam_toi_da = !empty($_POST['gia_tri_giam_toi_da']) ? (float)$_POST['gia_tri_giam_toi_da'] : 0;
    $mo_ta = trim($_POST['mo_ta']);
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    
    // Xử lý các sản phẩm áp dụng
    $ap_dung_type = isset($_POST['ap_dung']) ? $_POST['ap_dung'] : 'all';
    $ap_dung_sanpham = ($ap_dung_type === 'specific') ? 1 : 0;
    $ap_dung_loai = ($ap_dung_type === 'category') ? 1 : 0;
    
    $san_pham_ap_dung = [];
    $loai_ap_dung = [];
    
    // Cải thiện xử lý khi chọn nhiều sản phẩm
    if ($ap_dung_sanpham && isset($_POST['san_pham_ap_dung']) && is_array($_POST['san_pham_ap_dung'])) {
        $san_pham_ap_dung = array_map('intval', array_filter($_POST['san_pham_ap_dung']));
        
        // Kiểm tra nếu không có sản phẩm nào được chọn
        if (empty($san_pham_ap_dung)) {
            $errors[] = "Vui lòng chọn ít nhất một sản phẩm áp dụng";
        }
    } else if ($ap_dung_sanpham) {
        $errors[] = "Vui lòng chọn ít nhất một sản phẩm áp dụng";
    }
    
    // Cải thiện xử lý khi chọn nhiều loại sản phẩm
    if ($ap_dung_loai && isset($_POST['loai_ap_dung']) && is_array($_POST['loai_ap_dung'])) {
        $loai_ap_dung = array_map('intval', array_filter($_POST['loai_ap_dung']));
        
        // Kiểm tra nếu không có loại sản phẩm nào được chọn
        if (empty($loai_ap_dung)) {
            $errors[] = "Vui lòng chọn ít nhất một loại sản phẩm áp dụng";
        }
    } else if ($ap_dung_loai) {
        $errors[] = "Vui lòng chọn ít nhất một loại sản phẩm áp dụng";
    }
    
    // Kiểm tra dữ liệu đầu vào
    $errors = [];
    
    if (empty($ma_code) || strlen($ma_code) < 3) {
        $errors[] = "Mã khuyến mãi phải có ít nhất 3 ký tự";
    }
    
    // Kiểm tra mã đã tồn tại chưa (trừ chính nó)
    $check_code = $conn->prepare("SELECT id FROM khuyen_mai WHERE ma_code = ? AND id_nguoiban = ? AND id != ?");
    $check_code->bind_param("sii", $ma_code, $user_id, $id_khuyen_mai);
    $check_code->execute();
    if ($check_code->get_result()->num_rows > 0) {
        $errors[] = "Mã khuyến mãi này đã tồn tại";
    }
    
    // Kiểm tra quyền cập nhật
    $check_owner = $conn->prepare("SELECT id FROM khuyen_mai WHERE id = ? AND id_nguoiban = ?");
    $check_owner->bind_param("ii", $id_khuyen_mai, $user_id);
    $check_owner->execute();
    if ($check_owner->get_result()->num_rows === 0) {
        $errors[] = "Bạn không có quyền cập nhật mã khuyến mãi này";
    }
    
    if ($loai_giam_gia == 1 && ($gia_tri <= 0 || $gia_tri > 100)) {
        $errors[] = "Giá trị phần trăm phải nằm trong khoảng 1-100%";
    }
    
    if ($loai_giam_gia == 2 && $gia_tri <= 0) {
        $errors[] = "Giá trị giảm phải lớn hơn 0";
    }
    
    if (strtotime($ngay_ket_thuc) <= strtotime($ngay_bat_dau)) {
        $errors[] = "Ngày kết thúc phải sau ngày bắt đầu";
    }
    
    if (count($errors) === 0) {
        try {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            // Lấy thông tin số lượng đã sử dụng
            $current_usage = $conn->prepare("SELECT so_luong_da_dung FROM khuyen_mai WHERE id = ?");
            $current_usage->bind_param("i", $id_khuyen_mai);
            $current_usage->execute();
            $so_luong_da_dung = $current_usage->get_result()->fetch_assoc()['so_luong_da_dung'];
            
            // Kiểm tra nếu số lượng mới nhỏ hơn số lượng đã dùng
            if ($so_luong < $so_luong_da_dung) {
                $errors[] = "Số lượng không thể nhỏ hơn số lượng đã sử dụng ($so_luong_da_dung)";
                throw new Exception("Số lượng không thể nhỏ hơn số lượng đã sử dụng");
            }
            
            // Cập nhật thông tin mã giảm giá
            $update_stmt = $conn->prepare("
                UPDATE khuyen_mai SET 
                    ma_code = ?, loai_giam_gia = ?, gia_tri = ?, 
                    ngay_bat_dau = ?, ngay_ket_thuc = ?, so_luong = ?, 
                    gia_tri_don_toi_thieu = ?, gia_tri_giam_toi_da = ?, 
                    mo_ta = ?, trang_thai = ?, ap_dung_sanpham = ?, ap_dung_loai = ?
                WHERE id = ?
            ");
            
            $update_stmt->bind_param(
                "siissiddsiiii", 
                $ma_code, $loai_giam_gia, $gia_tri,
                $ngay_bat_dau, $ngay_ket_thuc, $so_luong,
                $gia_tri_don_toi_thieu, $gia_tri_giam_toi_da, 
                $mo_ta, $trang_thai, $ap_dung_sanpham, $ap_dung_loai, $id_khuyen_mai
            );
            
            $update_stmt->execute();
            
            // Xóa các sản phẩm áp dụng cũ
            $delete_products = $conn->prepare("DELETE FROM khuyen_mai_sanpham WHERE id_khuyen_mai = ?");
            $delete_products->bind_param("i", $id_khuyen_mai);
            $delete_products->execute();
            
            // Xóa các loại sản phẩm áp dụng cũ
            $delete_categories = $conn->prepare("DELETE FROM khuyen_mai_loai WHERE id_khuyen_mai = ?");
            $delete_categories->bind_param("i", $id_khuyen_mai);
            $delete_categories->execute();
            
            // Thêm sản phẩm áp dụng mới nếu có
            if ($ap_dung_sanpham && !empty($san_pham_ap_dung)) {
                $product_insert = $conn->prepare("
                    INSERT INTO khuyen_mai_sanpham (id_khuyen_mai, id_sanpham) 
                    VALUES (?, ?)
                ");
                
                foreach ($san_pham_ap_dung as $product_id) {
                    $product_insert->bind_param("ii", $id_khuyen_mai, $product_id);
                    $product_insert->execute();
                }
            }
            
            // Thêm loại sản phẩm áp dụng mới nếu có
            if ($ap_dung_loai && !empty($loai_ap_dung)) {
                $category_insert = $conn->prepare("
                    INSERT INTO khuyen_mai_loai (id_khuyen_mai, id_loai) 
                    VALUES (?, ?)
                ");
                
                foreach ($loai_ap_dung as $category_id) {
                    $category_insert->bind_param("ii", $id_khuyen_mai, $category_id);
                    $category_insert->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Cập nhật mã khuyến mãi thành công!";
            header("Location: khuyen-mai.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            $_SESSION['error_message'] = "Lỗi khi cập nhật mã khuyến mãi: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

// Xử lý xóa mã khuyến mãi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_khuyen_mai = (int)$_GET['id'];
    
    // Kiểm tra quyền xóa
    $check_owner = $conn->prepare("SELECT id FROM khuyen_mai WHERE id = ? AND id_nguoiban = ?");
    $check_owner->bind_param("ii", $id_khuyen_mai, $user_id);
    $check_owner->execute();
    
    if ($check_owner->get_result()->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không có quyền xóa mã khuyến mãi này";
        header("Location: khuyen-mai.php");
        exit();
    }
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        // Kiểm tra xem mã đã được sử dụng chưa
        $check_usage = $conn->prepare("SELECT so_luong_da_dung FROM khuyen_mai WHERE id = ?");
        $check_usage->bind_param("i", $id_khuyen_mai);
        $check_usage->execute();
        $result = $check_usage->get_result();
        $usage = $result->fetch_assoc()['so_luong_da_dung'];
        
        if ($usage > 0) {
            // Nếu đã được sử dụng, chỉ cập nhật trạng thái thành không hoạt động
            $update_stmt = $conn->prepare("UPDATE khuyen_mai SET trang_thai = 0 WHERE id = ?");
            $update_stmt->bind_param("i", $id_khuyen_mai);
            $update_stmt->execute();
            
            $_SESSION['success_message'] = "Mã khuyến mãi đã được vô hiệu hóa vì đã có người sử dụng";
        } else {
            // Xóa sản phẩm áp dụng
            $delete_products = $conn->prepare("DELETE FROM khuyen_mai_sanpham WHERE id_khuyen_mai = ?");
            $delete_products->bind_param("i", $id_khuyen_mai);
            $delete_products->execute();
            
            // Xóa loại sản phẩm áp dụng
            $delete_categories = $conn->prepare("DELETE FROM khuyen_mai_loai WHERE id_khuyen_mai = ?");
            $delete_categories->bind_param("i", $id_khuyen_mai);
            $delete_categories->execute();
            
            // Xóa mã khuyến mãi
            $delete_stmt = $conn->prepare("DELETE FROM khuyen_mai WHERE id = ?");
            $delete_stmt->bind_param("i", $id_khuyen_mai);
            $delete_stmt->execute();
            
            $_SESSION['success_message'] = "Xóa mã khuyến mãi thành công!";
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        $_SESSION['error_message'] = "Lỗi khi xóa mã khuyến mãi: " . $e->getMessage();
    }
    
    header("Location: khuyen-mai.php");
    exit();
}

// Xử lý sao chép mã khuyến mãi
if (isset($_GET['action']) && $_GET['action'] === 'duplicate' && isset($_GET['id'])) {
    $id_khuyen_mai = (int)$_GET['id'];
    
    // Kiểm tra quyền sao chép
    $check_owner = $conn->prepare("SELECT * FROM khuyen_mai WHERE id = ? AND id_nguoiban = ?");
    $check_owner->bind_param("ii", $id_khuyen_mai, $user_id);
    $check_owner->execute();
    $result = $check_owner->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không có quyền sao chép mã khuyến mãi này";
        header("Location: khuyen-mai.php");
        exit();
    }
    
    $original_promo = $result->fetch_assoc();
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        // Tạo mã code mới bằng cách thêm COPY vào mã cũ
        $new_ma_code = $original_promo['ma_code'] . "_COPY";
        $count = 1;
        
        // Kiểm tra nếu mã đã tồn tại, thêm số vào cuối
        while (true) {
            $check_code = $conn->prepare("SELECT id FROM khuyen_mai WHERE ma_code = ? AND id_nguoiban = ?");
            $check_code->bind_param("si", $new_ma_code, $user_id);
            $check_code->execute();
            if ($check_code->get_result()->num_rows === 0) {
                break;
            }
            $new_ma_code = $original_promo['ma_code'] . "_COPY" . (++$count);
        }
        
        // Sao chép thông tin cơ bản của mã khuyến mãi
        $stmt = $conn->prepare("
            INSERT INTO khuyen_mai (
                ma_code, loai_giam_gia, gia_tri, 
                ngay_bat_dau, ngay_ket_thuc, so_luong, 
                so_luong_da_dung, gia_tri_don_toi_thieu, 
                gia_tri_giam_toi_da, mo_ta, trang_thai,
                id_nguoiban, ap_dung_sanpham, ap_dung_loai
            ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 1, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "siissiidsiii", 
            $new_ma_code, $original_promo['loai_giam_gia'], $original_promo['gia_tri'],
            $original_promo['ngay_bat_dau'], $original_promo['ngay_ket_thuc'], $original_promo['so_luong'],
            $original_promo['gia_tri_don_toi_thieu'], $original_promo['gia_tri_giam_toi_da'], 
            $original_promo['mo_ta'], $user_id, $original_promo['ap_dung_sanpham'], $original_promo['ap_dung_loai']
        );
        
        $stmt->execute();
        $new_id = $conn->insert_id;
        
        // Sao chép sản phẩm áp dụng nếu có
        if ($original_promo['ap_dung_sanpham'] == 1) {
            $get_products = $conn->prepare("SELECT id_sanpham FROM khuyen_mai_sanpham WHERE id_khuyen_mai = ?");
            $get_products->bind_param("i", $id_khuyen_mai);
            $get_products->execute();
            $products = $get_products->get_result();
            
            if ($products->num_rows > 0) {
                $insert_products = $conn->prepare("INSERT INTO khuyen_mai_sanpham (id_khuyen_mai, id_sanpham) VALUES (?, ?)");
                
                while ($product = $products->fetch_assoc()) {
                    $insert_products->bind_param("ii", $new_id, $product['id_sanpham']);
                    $insert_products->execute();
                }
            }
        }
        
        // Sao chép loại sản phẩm áp dụng nếu có
        if ($original_promo['ap_dung_loai'] == 1) {
            $get_categories = $conn->prepare("SELECT id_loai FROM khuyen_mai_loai WHERE id_khuyen_mai = ?");
            $get_categories->bind_param("i", $id_khuyen_mai);
            $get_categories->execute();
            $categories = $get_categories->get_result();
            
            if ($categories->num_rows > 0) {
                $insert_categories = $conn->prepare("INSERT INTO khuyen_mai_loai (id_khuyen_mai, id_loai) VALUES (?, ?)");
                
                while ($category = $categories->fetch_assoc()) {
                    $insert_categories->bind_param("ii", $new_id, $category['id_loai']);
                    $insert_categories->execute();
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Đã sao chép mã khuyến mãi thành công!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Lỗi khi sao chép mã khuyến mãi: " . $e->getMessage();
    }
    
    header("Location: khuyen-mai.php");
    exit();
}

// Xây dựng query với điều kiện lọc
$where_conditions = ["id_nguoiban = ?"];
$params = [$user_id];
$types = "i";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where_conditions[] = "(ma_code LIKE ? OR mo_ta LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'active':
            $where_conditions[] = "trang_thai = 1 AND ngay_bat_dau <= CURRENT_DATE() AND ngay_ket_thuc >= CURRENT_DATE() AND so_luong > so_luong_da_dung";
            break;
        case 'upcoming':
            $where_conditions[] = "trang_thai = 1 AND ngay_bat_dau > CURRENT_DATE()";
            break;
        case 'expired':
            $where_conditions[] = "(trang_thai = 1 AND ngay_ket_thuc < CURRENT_DATE()) OR (trang_thai = 1 AND so_luong <= so_luong_da_dung)";
            break;
        case 'inactive':
            $where_conditions[] = "trang_thai = 0";
            break;
    }
}

$where_clause = implode(" AND ", $where_conditions);
$query = "SELECT * FROM khuyen_mai WHERE $where_clause ORDER BY ngay_tao DESC";

$promotions_query = $conn->prepare($query);
$promotions_query->bind_param($types, ...$params);
$promotions_query->execute();
$promotions = $promotions_query->get_result();

// Cấu hình phân trang
$items_per_page = 10;
$total_items = $promotions->num_rows;
$total_pages = ceil($total_items / $items_per_page);

// Lấy trang hiện tại
$current_page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Cập nhật câu truy vấn với LIMIT và OFFSET
$query .= " LIMIT $items_per_page OFFSET $offset";

// Lấy danh sách sản phẩm của người bán
$products_query = $conn->prepare("
    SELECT id_sanpham, tensanpham 
    FROM sanpham 
    WHERE id_nguoiban = ? AND trangthai = 1
    ORDER BY tensanpham
");
$products_query->bind_param("i", $user_id);
$products_query->execute();
$products = $products_query->get_result();
?>

<!-- Tiêu đề trang -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Quản Lý Mã Khuyến Mãi</h1>
    
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPromotionModal">
            <i class="bi bi-plus-circle"></i> Thêm mã khuyến mãi
        </button>
    </div>
</div>

<!-- Hiển thị thông báo -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Tìm kiếm và lọc -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="khuyen-mai.php" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Tìm kiếm</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                       placeholder="Tìm theo mã, mô tả...">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] === 'active') ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="upcoming" <?php echo (isset($_GET['status']) && $_GET['status'] === 'upcoming') ? 'selected' : ''; ?>>Chưa bắt đầu</option>
                    <option value="expired" <?php echo (isset($_GET['status']) && $_GET['status'] === 'expired') ? 'selected' : ''; ?>>Hết hạn</option>
                    <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] === 'inactive') ? 'selected' : ''; ?>>Không hoạt động</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Lọc
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="khuyen-mai.php" class="btn btn-outline-secondary w-100">Đặt lại</a>
            </div>
        </form>
    </div>
</div>

<!-- Danh sách mã khuyến mãi -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Danh sách mã khuyến mãi</h5>
    </div>
    
    <div class="card-body p-0">
        <?php if ($promotions->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Mã code</th>
                            <th>Giá trị</th>
                            <th>Thời hạn</th>
                            <th>Sử dụng</th>
                            <th>Giá trị tối thiểu</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($promo = $promotions->fetch_assoc()): ?>
                            <?php 
                            $status_class = 'bg-secondary';
                            $status_text = 'Hết hạn';
                            
                            if ($promo['trang_thai'] == 1) {
                                if (strtotime($promo['ngay_ket_thuc']) >= time() && strtotime($promo['ngay_bat_dau']) <= time()) {
                                    if ($promo['so_luong'] > $promo['so_luong_da_dung']) {
                                        $status_class = 'bg-success';
                                        $status_text = 'Đang hoạt động';
                                    } else {
                                        $status_class = 'bg-danger';
                                        $status_text = 'Đã hết lượt';
                                    }
                                } elseif (strtotime($promo['ngay_bat_dau']) > time()) {
                                    $status_class = 'bg-warning text-dark';
                                    $status_text = 'Chưa bắt đầu';
                                }
                            } else {
                                $status_class = 'bg-danger';
                                $status_text = 'Đã vô hiệu hóa';
                            }
                            
                            // Format giá trị
                            if ($promo['loai_giam_gia'] == 1) {
                                $value = $promo['gia_tri'] . '%';
                                if ($promo['gia_tri_giam_toi_da'] > 0) {
                                    $value .= ' (tối đa ' . number_format($promo['gia_tri_giam_toi_da'], 0, ',', '.') . 'đ)';
                                }
                            } else {
                                $value = number_format($promo['gia_tri'], 0, ',', '.') . 'đ';
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo htmlspecialchars($promo['ma_code']); ?></span>
                                    <?php if (!empty($promo['mo_ta'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($promo['mo_ta']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $value; ?></td>
                                <td>
                                    <div><?php echo date('d/m/Y', strtotime($promo['ngay_bat_dau'])); ?></div>
                                    <div class="small text-muted">đến <?php echo date('d/m/Y', strtotime($promo['ngay_ket_thuc'])); ?></div>
                                </td>
                                <td>
                                    <?php echo $promo['so_luong_da_dung']; ?>/<?php echo $promo['so_luong']; ?>
                                </td>
                                <td>
                                    <?php echo $promo['gia_tri_don_toi_thieu'] > 0 ? number_format($promo['gia_tri_don_toi_thieu'], 0, ',', '.') . 'đ' : 'Không'; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item edit-promo" href="#" data-id="<?php echo $promo['id']; ?>">
                                                    <i class="bi bi-pencil me-1"></i> Chỉnh sửa
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item duplicate-promo" href="#" data-id="<?php echo $promo['id']; ?>">
                                                    <i class="bi bi-copy me-1"></i> Sao chép
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger delete-promo" href="#" data-id="<?php echo $promo['id']; ?>">
                                                    <i class="bi bi-trash me-1"></i> Xóa
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-ticket-perforated-fill fs-1 text-muted"></i>
                <p class="mt-3">Bạn chưa tạo mã khuyến mãi nào</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPromotionModal">
                    <i class="bi bi-plus-circle me-1"></i> Tạo mã khuyến mãi
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hiển thị phân trang nếu có nhiều trang -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Phân trang">
    <ul class="pagination justify-content-center">
        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>" aria-label="Trang trước">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>
        
        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>" aria-label="Trang sau">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Modal thêm mã khuyến mãi -->
<div class="modal fade" id="addPromotionModal" tabindex="-1" aria-labelledby="addPromotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPromotionModalLabel">Thêm mã khuyến mãi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="khuyen-mai.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ma_code" class="form-label">Mã khuyến mãi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ma_code" name="ma_code" required pattern="[A-Za-z0-9]+" minlength="3" maxlength="20">
                            <div class="form-text">Chỉ gồm chữ cái và số, ví dụ: SUMMER2025</div>
                        </div>
                        <div class="col-md-6">
                            <label for="trang_thai" class="form-label">Trạng thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="trang_thai" id="trang_thai" checked>
                                <label class="form-check-label" for="trang_thai">Kích hoạt mã khuyến mãi</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="loai_giam_gia" class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                            <select class="form-select" id="loai_giam_gia" name="loai_giam_gia" required>
                                <option value="1">Giảm theo phần trăm (%)</option>
                                <option value="2">Giảm số tiền cố định (VNĐ)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="gia_tri" class="form-label">Giá trị <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri" name="gia_tri" min="1" required>
                                <span class="input-group-text" id="gia_tri_suffix">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="limit_container" class="row mb-3">
                        <div class="col-md-6">
                            <label for="gia_tri_don_toi_thieu" class="form-label">Giá trị đơn tối thiểu</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri_don_toi_thieu" name="gia_tri_don_toi_thieu" min="0">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <div class="form-text">Để trống nếu không có giá trị tối thiểu</div>
                        </div>
                        <div class="col-md-6">
                            <label for="gia_tri_giam_toi_da" class="form-label">Giá trị giảm tối đa</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri_giam_toi_da" name="gia_tri_giam_toi_da" min="0">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <div class="form-text">Áp dụng cho giảm giá theo % (để trống nếu không giới hạn)</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ngay_bat_dau" class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="ngay_ket_thuc" class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="so_luong" class="form-label">Số lượng mã <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="so_luong" name="so_luong" min="1" value="100" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mo_ta" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="mo_ta" name="mo_ta" rows="2"></textarea>
                        <div class="form-text">Mô tả ngắn gọn về mã khuyến mãi này</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Áp dụng cho</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="ap_dung" id="ap_dung_all" value="all" checked>
                            <label class="form-check-label" for="ap_dung_all">
                                Tất cả sản phẩm của shop
                            </label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="ap_dung" id="ap_dung_specific" value="specific">
                            <label class="form-check-label" for="ap_dung_specific">
                                Chỉ áp dụng cho một số sản phẩm
                            </label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="ap_dung" id="ap_dung_category" value="category">
                            <label class="form-check-label" for="ap_dung_category">
                                Áp dụng cho một số loại sản phẩm
                            </label>
                        </div>
                    </div>
                    
                    <div id="specific_products" class="mb-3" style="display: none;">
                        <label for="san_pham_ap_dung" class="form-label">Chọn sản phẩm áp dụng <span class="text-danger">*</span></label>
                        <select class="form-select select2-products" id="san_pham_ap_dung" name="san_pham_ap_dung[]" multiple data-placeholder="Chọn sản phẩm áp dụng">
                            <?php 
                            // Reset con trỏ và đảm bảo có dữ liệu
                            $products_query = $conn->prepare("
                                SELECT id_sanpham, tensanpham 
                                FROM sanpham 
                                WHERE id_nguoiban = ? AND trangthai = 1
                                ORDER BY tensanpham
                            ");
                            $products_query->bind_param("i", $user_id);
                            $products_query->execute();
                            $products = $products_query->get_result();
                            
                            // Kiểm tra và in ra số lượng sản phẩm
                            if ($products->num_rows > 0) {
                                while ($product = $products->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $product['id_sanpham']; ?>"><?php echo htmlspecialchars($product['tensanpham']); ?></option>
                                <?php endwhile;
                            } else {
                                ?>
                                <option value="" disabled>Không có sản phẩm nào</option>
                                <?php
                            }
                            ?>
                        </select>
                        <div class="form-text">Bạn có thể tìm kiếm và chọn nhiều sản phẩm</div>
                    </div>
                    
                    <div id="specific_categories" class="mb-3" style="display: none;">
                        <label for="loai_ap_dung" class="form-label">Chọn loại sản phẩm áp dụng <span class="text-danger">*</span></label>
                        <select class="form-select select2-categories" id="loai_ap_dung" name="loai_ap_dung[]" multiple data-placeholder="Chọn loại sản phẩm áp dụng">
                            <option value="">Tìm và chọn loại sản phẩm</option>
                            <?php 
                            $categories_query = $conn->query("SELECT id_loai, tenloai FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
                            while ($category = $categories_query->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $category['id_loai']; ?>"><?php echo htmlspecialchars($category['tenloai']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Bạn có thể tìm kiếm và chọn nhiều loại sản phẩm</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> Mã khuyến mãi sẽ áp dụng giảm giá cho các sản phẩm của shop bạn khi khách hàng nhập mã này trong giỏ hàng.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Tạo mã khuyến mãi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal chỉnh sửa mã khuyến mãi -->
<div class="modal fade" id="editPromotionModal" tabindex="-1" aria-labelledby="editPromotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPromotionModalLabel">Chỉnh sửa mã khuyến mãi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="khuyen-mai.php" method="POST" id="editPromotionForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_khuyen_mai" id="edit_id_khuyen_mai" value="">
                
                <div class="modal-body">
                    <!-- Form giống hệt form thêm, chỉ thay đổi các ID -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_ma_code" class="form-label">Mã khuyến mãi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_ma_code" name="ma_code" required pattern="[A-Za-z0-9]+" minlength="3" maxlength="20">
                            <div class="form-text">Chỉ gồm chữ cái và số, ví dụ: SUMMER2025</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_trang_thai" class="form-label">Trạng thái</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="trang_thai" id="edit_trang_thai">
                                <label class="form-check-label" for="edit_trang_thai">Kích hoạt mã khuyến mãi</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_loai_giam_gia" class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_loai_giam_gia" name="loai_giam_gia" required>
                                <option value="1">Giảm theo phần trăm (%)</option>
                                <option value="2">Giảm số tiền cố định (VNĐ)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_gia_tri" class="form-label">Giá trị <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_gia_tri" name="gia_tri" min="1" required>
                                <span class="input-group-text" id="edit_gia_tri_suffix">%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="edit_limit_container" class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_gia_tri_don_toi_thieu" class="form-label">Giá trị đơn tối thiểu</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_gia_tri_don_toi_thieu" name="gia_tri_don_toi_thieu" min="0">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <div class="form-text">Để trống nếu không có giá trị tối thiểu</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_gia_tri_giam_toi_da" class="form-label">Giá trị giảm tối đa</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_gia_tri_giam_toi_da" name="gia_tri_giam_toi_da" min="0">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <div class="form-text">Áp dụng cho giảm giá theo % (để trống nếu không giới hạn)</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_ngay_bat_dau" class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_ngay_bat_dau" name="ngay_bat_dau" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_ngay_ket_thuc" class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_ngay_ket_thuc" name="ngay_ket_thuc" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_so_luong" class="form-label">Số lượng mã <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_so_luong" name="so_luong" min="1" required>
                            <div class="form-text" id="edit_usage_info"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_mo_ta" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="edit_mo_ta" name="mo_ta" rows="2"></textarea>
                        <div class="form-text">Mô tả ngắn gọn về mã khuyến mãi này</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Áp dụng cho</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="ap_dung" id="edit_ap_dung_all" value="all" checked>
                            <label class="form-check-label" for="edit_ap_dung_all">
                                Tất cả sản phẩm của shop
                            </label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="ap_dung" id="edit_ap_dung_specific" value="specific">
                            <label class="form-check-label" for="edit_ap_dung_specific">
                                Chỉ áp dụng cho một số sản phẩm
                            </label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="ap_dung" id="edit_ap_dung_category" value="category">
                            <label class="form-check-label" for="edit_ap_dung_category">
                                Áp dụng cho một số loại sản phẩm
                            </label>
                        </div>
                    </div>
                    
                    <div id="edit_specific_products" class="mb-3" style="display: none;">
                        <label for="edit_san_pham_ap_dung" class="form-label">Chọn sản phẩm áp dụng <span class="text-danger">*</span></label>
                        <select class="form-select select2-products" id="edit_san_pham_ap_dung" name="san_pham_ap_dung[]" multiple data-placeholder="Chọn sản phẩm áp dụng">
                            <option value="">Tìm và chọn sản phẩm</option>
                            <?php 
                            // Reset con trỏ để đảm bảo lấy đầy đủ danh sách
                            $products_query->execute();
                            $products = $products_query->get_result();
                            while ($product = $products->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $product['id_sanpham']; ?>"><?php echo htmlspecialchars($product['tensanpham']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Bạn có thể tìm kiếm và chọn nhiều sản phẩm</div>
                    </div>
                    
                    <div id="edit_specific_categories" class="mb-3" style="display: none;">
                        <label for="edit_loai_ap_dung" class="form-label">Chọn loại sản phẩm áp dụng <span class="text-danger">*</span></label>
                        <select class="form-select select2-categories" id="edit_loai_ap_dung" name="loai_ap_dung[]" multiple data-placeholder="Chọn loại sản phẩm áp dụng">
                            <option value="">Tìm và chọn loại sản phẩm</option>
                            <?php 
                            $categories_query = $conn->query("SELECT id_loai, tenloai FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
                            while ($category = $categories_query->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $category['id_loai']; ?>"><?php echo htmlspecialchars($category['tenloai']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Bạn có thể tìm kiếm và chọn nhiều loại sản phẩm</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deletePromotionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa mã khuyến mãi này không? Hành động này không thể hoàn tác.</p>
                <p class="text-danger"><strong>Lưu ý:</strong> Nếu mã đã được sử dụng, mã sẽ chỉ bị vô hiệu hóa thay vì xóa hoàn toàn.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Xóa</a>
            </div>
        </div>
    </div>
</div>

<?php
$page_specific_js = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Khởi tạo Select2 trước khi modal hiển thị
    $(document).ready(function() {
        // Khởi tạo Select2 bên ngoài modal
        initSelect2();
        
        // Khởi tạo lại Select2 khi modal mở ra
        $("#addPromotionModal").on("shown.bs.modal", function () {
            initSelect2InModal();
        });
        
        // Khởi tạo lại Select2 khi modal edit mở ra
        $("#editPromotionModal").on("shown.bs.modal", function () {
            initSelect2InModal();
        });
        
        function initSelect2() {
            $(".select2-products, .select2-categories").select2({
                theme: "bootstrap-5",
                width: "100%",
                allowClear: true,
                placeholder: "Tìm kiếm và chọn",
                dropdownParent: $("body") // Default parent
            });
        }
        
        function initSelect2InModal() {
            // Đảm bảo đã destroy trước khi khởi tạo lại trong modal
            $("#san_pham_ap_dung").select2("destroy").select2({
                theme: "bootstrap-5",
                width: "100%",
                allowClear: true,
                placeholder: "Tìm kiếm và chọn sản phẩm",
                dropdownParent: $("#addPromotionModal") // Đặt dropdown trong modal
            });
            
            $("#loai_ap_dung").select2("destroy").select2({
                theme: "bootstrap-5",
                width: "100%",
                allowClear: true,
                placeholder: "Tìm kiếm và chọn loại sản phẩm",
                dropdownParent: $("#addPromotionModal")
            });
            
            $("#edit_san_pham_ap_dung").select2("destroy").select2({
                theme: "bootstrap-5",
                width: "100%",
                allowClear: true,
                placeholder: "Tìm kiếm và chọn sản phẩm",
                dropdownParent: $("#editPromotionModal")
            });
            
            $("#edit_loai_ap_dung").select2("destroy").select2({
                theme: "bootstrap-5",
                width: "100%",
                allowClear: true,
                placeholder: "Tìm kiếm và chọn loại sản phẩm",
                dropdownParent: $("#editPromotionModal")
            });
        }
    });

    // Xử lý hiển thị suffix gia_tri dựa vào loại giảm giá
    const loaiGiamGiaSelect = document.getElementById("loai_giam_gia");
    const giaTriSuffix = document.getElementById("gia_tri_suffix");
    const giaTriGiamToiDaContainer = document.getElementById("limit_container");
    
    loaiGiamGiaSelect.addEventListener("change", function() {
        if (this.value === "1") { // Phần trăm
            giaTriSuffix.textContent = "%";
            document.getElementById("gia_tri_giam_toi_da").parentElement.style.display = "block";
        } else { // Số tiền cố định
            giaTriSuffix.textContent = "VNĐ";
            document.getElementById("gia_tri_giam_toi_da").parentElement.style.display = "none";
        }
    });
    
    // Xử lý hiện/ẩn danh sách sản phẩm
    const apDungAll = document.getElementById("ap_dung_all");
    const apDungSpecific = document.getElementById("ap_dung_specific");
    const apDungCategory = document.getElementById("ap_dung_category");
    const specificProducts = document.getElementById("specific_products");
    const specificCategories = document.getElementById("specific_categories");
    
    apDungAll.addEventListener("change", function() {
        if (this.checked) {
            specificProducts.style.display = "none";
            specificCategories.style.display = "none";
            // Reset các select box
            $("#san_pham_ap_dung").val(null).trigger("change");
            $("#loai_ap_dung").val(null).trigger("change");
        }
    });
    
    apDungSpecific.addEventListener("change", function() {
        if (this.checked) {
            specificProducts.style.display = "block";
            specificCategories.style.display = "none";
            // Reset box không liên quan
            $("#loai_ap_dung").val(null).trigger("change");
        }
    });
    
    apDungCategory.addEventListener("change", function() {
        if (this.checked) {
            specificProducts.style.display = "none";
            specificCategories.style.display = "block";
            // Reset box không liên quan
            $("#san_pham_ap_dung").val(null).trigger("change");
        }
    });
    
    // Form edit - tương tự
    const editLoaiGiamGiaSelect = document.getElementById("edit_loai_giam_gia");
    const editGiaTriSuffix = document.getElementById("edit_gia_tri_suffix");
    
    editLoaiGiamGiaSelect.addEventListener("change", function() {
        if (this.value === "1") { // Phần trăm
            editGiaTriSuffix.textContent = "%";
            document.getElementById("edit_gia_tri_giam_toi_da").parentElement.style.display = "block";
        } else { // Số tiền cố định
            editGiaTriSuffix.textContent = "VNĐ";
            document.getElementById("edit_gia_tri_giam_toi_da").parentElement.style.display = "none";
        }
    });
    
    // Xử lý hiện/ẩn danh sách sản phẩm trong form edit
    const editApDungAll = document.getElementById("edit_ap_dung_all");
    const editApDungSpecific = document.getElementById("edit_ap_dung_specific");
    const editApDungCategory = document.getElementById("edit_ap_dung_category");
    const editSpecificProducts = document.getElementById("edit_specific_products");
    const editSpecificCategories = document.getElementById("edit_specific_categories");
    
    editApDungAll.addEventListener("change", function() {
        if (this.checked) {
            editSpecificProducts.style.display = "none";
            editSpecificCategories.style.display = "none";
            // Reset các select box
            $("#edit_san_pham_ap_dung").val(null).trigger("change");
            $("#edit_loai_ap_dung").val(null).trigger("change");
        }
    });
    
    editApDungSpecific.addEventListener("change", function() {
        if (this.checked) {
            editSpecificProducts.style.display = "block";
            editSpecificCategories.style.display = "none";
            // Reset box không liên quan
            $("#edit_loai_ap_dung").val(null).trigger("change");
        }
    });
    
    editApDungCategory.addEventListener("change", function() {
        if (this.checked) {
            editSpecificProducts.style.display = "none";
            editSpecificCategories.style.display = "block";
            // Reset box không liên quan
            $("#edit_san_pham_ap_dung").val(null).trigger("change");
        }
    });
    
    // Xử lý nút xóa - giữ nguyên code
    
    // Xử lý nút chỉnh sửa - cập nhật phần chọn sản phẩm/loại
    const editButtons = document.querySelectorAll(".edit-promo");
    
    editButtons.forEach(button => {
        button.addEventListener("click", function(e) {
            e.preventDefault();
            const id = this.getAttribute("data-id");
            
            // Lấy thông tin mã khuyến mãi bằng AJAX
            fetch("ajax/get-promotion.php?id=" + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const promo = data.promotion;
                        
                        // Phần code hiện tại - giữ nguyên
                        document.getElementById("edit_id_khuyen_mai").value = promo.id;
                        document.getElementById("edit_ma_code").value = promo.ma_code;
                        document.getElementById("edit_loai_giam_gia").value = promo.loai_giam_gia;
                        document.getElementById("edit_gia_tri").value = promo.gia_tri;
                        document.getElementById("edit_ngay_bat_dau").value = promo.ngay_bat_dau;
                        document.getElementById("edit_ngay_ket_thuc").value = promo.ngay_ket_thuc;
                        document.getElementById("edit_so_luong").value = promo.so_luong;
                        document.getElementById("edit_mo_ta").value = promo.mo_ta;
                        document.getElementById("edit_gia_tri_don_toi_thieu").value = promo.gia_tri_don_toi_thieu;
                        document.getElementById("edit_gia_tri_giam_toi_da").value = promo.gia_tri_giam_toi_da;
                        document.getElementById("edit_trang_thai").checked = promo.trang_thai == 1;
                        
                        document.getElementById("edit_usage_info").textContent = "Đã sử dụng: " + promo.so_luong_da_dung + " mã";
                        
                        if (promo.loai_giam_gia == 1) {
                            document.getElementById("edit_gia_tri_suffix").textContent = "%";
                            document.getElementById("edit_gia_tri_giam_toi_da").parentElement.style.display = "block";
                        } else {
                            document.getElementById("edit_gia_tri_suffix").textContent = "VNĐ";
                            document.getElementById("edit_gia_tri_giam_toi_da").parentElement.style.display = "none";
                        }
                        
                        // Cập nhật áp dụng sản phẩm - cải thiện với Select2
                        if (promo.ap_dung_sanpham == 1) {
                            document.getElementById("edit_ap_dung_specific").checked = true;
                            document.getElementById("edit_specific_products").style.display = "block";
                            document.getElementById("edit_specific_categories").style.display = "none";
                            
                            // Clear và chọn lại các options với Select2
                            $("#edit_san_pham_ap_dung").val(null).trigger("change");
                            
                            // Chọn các sản phẩm áp dụng
                            if (promo.san_pham_ap_dung && promo.san_pham_ap_dung.length > 0) {
                                $("#edit_san_pham_ap_dung").val(promo.san_pham_ap_dung).trigger("change");
                            }
                            
                        } else if (promo.ap_dung_loai == 1) {
                            document.getElementById("edit_ap_dung_category").checked = true;
                            document.getElementById("edit_specific_products").style.display = "none";
                            document.getElementById("edit_specific_categories").style.display = "block";
                            
                            // Clear và chọn lại các options với Select2
                            $("#edit_loai_ap_dung").val(null).trigger("change");
                            
                            // Chọn các loại sản phẩm áp dụng
                            if (promo.loai_ap_dung && promo.loai_ap_dung.length > 0) {
                                $("#edit_loai_ap_dung").val(promo.loai_ap_dung).trigger("change");
                            }
                            
                        } else {
                            document.getElementById("edit_ap_dung_all").checked = true;
                            document.getElementById("edit_specific_products").style.display = "none";
                            document.getElementById("edit_specific_categories").style.display = "none";
                            
                            // Reset các select box
                            $("#edit_san_pham_ap_dung").val(null).trigger("change");
                            $("#edit_loai_ap_dung").val(null).trigger("change");
                        }
                        
                        // Hiển thị modal
                        const editModal = new bootstrap.Modal(document.getElementById("editPromotionModal"));
                        editModal.show();
                    } else {
                        alert("Không thể lấy thông tin mã khuyến mãi. Vui lòng thử lại sau.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Đã xảy ra lỗi. Vui lòng thử lại sau.");
                });
        });
    });

    
});
</script>

';
include('includes/footer.php');
?>