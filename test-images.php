<?php
// File kiểm tra hiển thị hình ảnh
session_start();
include('config/config.php');
include('includes/init.php');

// Lấy một số sản phẩm để kiểm tra
$stmt = $conn->prepare("SELECT id, tensanpham, hinhanh FROM sanpham LIMIT 10");
$stmt->execute();
$products = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra hiển thị hình ảnh</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <style>
        .product-card {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .product-img {
            width: 100%;
            height: 200px;
            object-fit: contain;
            margin-bottom: 10px;
            border: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <h1>Kiểm tra hiển thị hình ảnh</h1>
        <p>File này dùng để kiểm tra xem hình ảnh sản phẩm hiển thị đúng không.</p>

        <div class="row">
            <?php while ($product = $products->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="product-card">
                        <h5><?php echo htmlspecialchars($product['tensanpham']); ?></h5>
                        <p>ID: <?php echo $product['id']; ?></p>
                        <p>Đường dẫn gốc: <?php echo htmlspecialchars($product['hinhanh']); ?></p>
                        <p>Đường dẫn xử lý: <?php echo htmlspecialchars(getProductImagePath($product['hinhanh'])); ?></p>
                        <img src="<?php echo getProductImagePath($product['hinhanh']); ?>" class="product-img"
                            alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">

                        <?php
                        // Kiểm tra hình ảnh bổ sung
                        $img_stmt = $conn->prepare("SELECT hinhanh FROM sanpham_hinhanh WHERE id_sanpham = ? LIMIT 3");
                        $img_stmt->bind_param("i", $product['id']);
                        $img_stmt->execute();
                        $images = $img_stmt->get_result();

                        if ($images->num_rows > 0) {
                            echo '<h6>Hình ảnh bổ sung:</h6>';
                            echo '<div class="d-flex">';
                            while ($img = $images->fetch_assoc()) {
                                echo '<div class="me-2">';
                                echo 'Gốc: ' . htmlspecialchars($img['hinhanh']) . '<br>';
                                echo '<img src="' . getProductImagePath($img['hinhanh']) . '" style="width:80px; height:80px; object-fit:cover;">';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>

</html>