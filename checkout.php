// Add this code in the part where you validate promotional codes

// Check if user has reached their usage limit for this promotion
if (isset($_SESSION['user_id']) && !empty($promotion['max_su_dung_per_user'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check how many times this user has used this promotion
    $usage_sql = "SELECT so_lan_su_dung FROM khuyen_mai_sudung 
                  WHERE id_khuyenmai = ? AND id_user = ?";
    $usage_stmt = $conn->prepare($usage_sql);
    $usage_stmt->bind_param("ii", $promotion_id, $user_id);
    $usage_stmt->execute();
    $usage_result = $usage_stmt->get_result();
    
    if ($usage_result->num_rows > 0) {
        $usage_data = $usage_result->fetch_assoc();
        $usage_count = $usage_data['so_lan_su_dung'];
        
        if ($usage_count >= $promotion['max_su_dung_per_user']) {
            $error_message = "Bạn đã sử dụng hết số lần áp dụng mã giảm giá này.";
            // Handle this error (display message, reject code, etc.)
        }
    }
}

// When applying a promotion successfully, we need to update usage records:
if (isset($_SESSION['user_id']) && $promotion_applied) {
    $user_id = $_SESSION['user_id'];
    
    // Check if there's an existing record
    $check_usage_sql = "SELECT id, so_lan_su_dung FROM khuyen_mai_sudung 
                       WHERE id_khuyenmai = ? AND id_user = ?";
    $check_usage_stmt = $conn->prepare($check_usage_sql);
    $check_usage_stmt->bind_param("ii", $promotion_id, $user_id);
    $check_usage_stmt->execute();
    $check_result = $check_usage_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing usage record
        $usage_data = $check_result->fetch_assoc();
        $new_usage_count = $usage_data['so_lan_su_dung'] + 1;
        
        $update_usage_sql = "UPDATE khuyen_mai_sudung 
                            SET so_lan_su_dung = ?, lan_su_dung_cuoi = NOW() 
                            WHERE id = ?";
        $update_usage_stmt = $conn->prepare($update_usage_sql);
        $update_usage_stmt->bind_param("ii", $new_usage_count, $usage_data['id']);
        $update_usage_stmt->execute();
    } else {
        // Insert new usage record
        $insert_usage_sql = "INSERT INTO khuyen_mai_sudung 
                            (id_khuyenmai, id_user, so_lan_su_dung, lan_su_dung_cuoi) 
                            VALUES (?, ?, 1, NOW())";
        $insert_usage_stmt = $conn->prepare($insert_usage_sql);
        $insert_usage_stmt->bind_param("ii", $promotion_id, $user_id);
        $insert_usage_stmt->execute();
    }
}
