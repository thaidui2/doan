<?php
// Page title
$page_title = "Liên hệ";

// Include header

// Include database connection if not already included
if (!isset($conn)) {
    include('config/config.php');
}

// Get contact information from cai_dat table (updated query)
$settings_query = $conn->query("
    SELECT khoa, gia_tri 
    FROM cai_dat 
    WHERE nhom = 'general' 
    OR khoa IN ('facebook_url', 'instagram_url', 'twitter_url', 'youtube_url')
");

$settings = [];
if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['khoa']] = $row['gia_tri'];
    }
} else {
    // Fallback if table doesn't exist
    $settings = [
        'site_name' => 'Bug Shop',
        'contact_email' => 'contact@bugshop.com',
        'contact_phone' => '0123456789',
        'address' => 'Số 123, Đường ABC, Quận XYZ, TP. HCM'
    ];
}

// Initialize variables for form
$name = $email = $phone = $subject = $message = '';
$errors = [];
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and sanitize inputs
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    // Form validation
    if (empty($name)) {
        $errors['name'] = 'Vui lòng nhập họ tên';
    }

    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    }

    if (empty($phone)) {
        $errors['phone'] = 'Vui lòng nhập số điện thoại';
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại không hợp lệ';
    }

    if (empty($subject)) {
        $errors['subject'] = 'Vui lòng nhập chủ đề';
    }

    if (empty($message)) {
        $errors['message'] = 'Vui lòng nhập nội dung';
    }

    // If no errors, process form submission
    if (empty($errors)) {
        try {
            // Check if lien_he table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'lien_he'");
            
            if ($table_check->num_rows == 0) {
                // Create lien_he table if it doesn't exist
                $create_table = $conn->query("
                    CREATE TABLE IF NOT EXISTS lien_he (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        ho_ten VARCHAR(100) NOT NULL,
                        email VARCHAR(100) NOT NULL,
                        so_dien_thoai VARCHAR(15) NOT NULL,
                        chu_de VARCHAR(255) NOT NULL,
                        noi_dung TEXT NOT NULL,
                        da_doc TINYINT(1) NOT NULL DEFAULT '0',
                        ngay_gui TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            }
            
            // Insert contact message into database
            $stmt = $conn->prepare("
                INSERT INTO lien_he (ho_ten, email, so_dien_thoai, chu_de, noi_dung, ngay_gui)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Error preparing statement: " . $conn->error);
            }
            
            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
            
            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
            
            // Reset form fields
            $name = $email = $phone = $subject = $message = '';
            
            // Set success message
            $success_message = 'Cảm ơn bạn đã gửi thông tin. Chúng tôi sẽ liên hệ với bạn sớm nhất có thể!';
            
        } catch (Exception $e) {
            $errors['general'] = 'Có lỗi xảy ra khi gửi thông tin: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên hệ - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
</head>
<body>
    <!-- Include header -->
    <?php 
    require_once('includes/head.php');
    require_once('includes/header.php');
    
    ?>
<!-- Hero Banner -->
<div class="contact-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="hero-title">Liên hệ với chúng tôi</h1>
                <p class="hero-subtitle">Chúng tôi luôn sẵn sàng hỗ trợ bạn 24/7. Hãy để lại thông tin và chúng tôi sẽ liên hệ lại ngay.</p>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <img src="images/contact-illustration.svg" alt="Contact Us" class="img-fluid contact-illustration" onerror="this.src='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/headset.svg'; this.classList.add('fallback-icon')">
            </div>
        </div>
    </div>
</div>

<!-- Breadcrumb Section -->
<div class="container mt-3 mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Liên hệ</li>
        </ol>
    </nav>
</div>

<!-- Contact Section -->
<section class="contact-section py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="contact-info">
                    <h2 class="section-title mb-4">Thông tin liên hệ</h2>
                    
                    <!-- Contact Info Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="contact-card shadow-sm h-100">
                                <div class="card-icon">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <h5>Địa chỉ</h5>
                                <p><?php echo $settings['address'] ?? 'Đang cập nhật...'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="contact-card shadow-sm h-100">
                                <div class="card-icon">
                                    <i class="bi bi-telephone"></i>
                                </div>
                                <h5>Điện thoại</h5>
                                <p><?php echo $settings['contact_phone'] ?? 'Đang cập nhật...'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="contact-card shadow-sm h-100">
                                <div class="card-icon">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <h5>Email</h5>
                                <p><?php echo $settings['contact_email'] ?? 'contact@bugshop.com'; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="contact-card shadow-sm h-100">
                                <div class="card-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <h5>Giờ làm việc</h5>
                                <p>Thứ 2 - CN: 8h00 - 21h00</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Social Media Links -->
                    <div class="social-media-section mb-4">
                        <h5>Kết nối với chúng tôi</h5>
                        <div class="social-icons">
                            <?php if (!empty($settings['facebook_url'])): ?>
                                <a href="<?php echo $settings['facebook_url']; ?>" target="_blank" class="social-icon" title="Facebook">
                                    <i class="bi bi-facebook"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($settings['instagram_url'])): ?>
                                <a href="<?php echo $settings['instagram_url']; ?>" target="_blank" class="social-icon" title="Instagram">
                                    <i class="bi bi-instagram"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($settings['twitter_url'])): ?>
                                <a href="<?php echo $settings['twitter_url']; ?>" target="_blank" class="social-icon" title="Twitter">
                                    <i class="bi bi-twitter-x"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($settings['youtube_url'])): ?>
                                <a href="<?php echo $settings['youtube_url']; ?>" target="_blank" class="social-icon" title="YouTube">
                                    <i class="bi bi-youtube"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="https://zalo.me/" target="_blank" class="social-icon" title="Zalo">
                                <i class="bi bi-chat-dots"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Google Map -->
                    <div class="map-container">
                        <h4 class="mb-3">Bản đồ cửa hàng</h4>
                        <div class="map-wrapper shadow-sm">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d6434.669492862747!2d105.7410797861001!3d21.04014864920691!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135096b31fa7abb%3A0xff645782804911af!2zVHLGsOG7nW5nIMSR4bqhaSBo4buNYyBDw7RuZyBuZ2jhu4cgxJDDtG5nIMOB!5e1!3m2!1svi!2s!4v1744082906782!5m2!1svi!2s" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="contact-form">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h2 class="section-title mb-4">Gửi tin nhắn cho chúng tôi</h2>
                            
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success fade show" role="alert">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-check-circle-fill me-2"></i>
                                        <div><?php echo $success_message; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($errors['general'])): ?>
                                <div class="alert alert-danger fade show" role="alert">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <div><?php echo $errors['general']; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form action="lienhe.php" method="post" class="contact-form-container">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Họ tên <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                               id="name" name="name" value="<?php echo $name; ?>" placeholder="Nhập họ tên của bạn" required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                                   id="email" name="email" value="<?php echo $email; ?>" placeholder="Nhập email của bạn" required>
                                            <?php if (isset($errors['email'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                                   id="phone" name="phone" value="<?php echo $phone; ?>" placeholder="Nhập số điện thoại" required>
                                            <?php if (isset($errors['phone'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Chủ đề <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                                        <input type="text" class="form-control <?php echo isset($errors['subject']) ? 'is-invalid' : ''; ?>" 
                                               id="subject" name="subject" value="<?php echo $subject; ?>" placeholder="Nhập chủ đề liên hệ" required>
                                        <?php if (isset($errors['subject'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['subject']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="message" class="form-label">Nội dung <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text align-self-start"><i class="bi bi-chat-right-quote"></i></span>
                                        <textarea class="form-control <?php echo isset($errors['message']) ? 'is-invalid' : ''; ?>" 
                                                  id="message" name="message" rows="5" placeholder="Nhập nội dung tin nhắn" required><?php echo $message; ?></textarea>
                                        <?php if (isset($errors['message'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['message']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5 submit-btn">
                                        <i class="bi bi-send me-2"></i> Gửi tin nhắn
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h2 class="section-title">Câu hỏi thường gặp</h2>
                    <p class="section-subtitle">Những thắc mắc phổ biến từ khách hàng của chúng tôi</p>
                </div>
                
                <div class="accordion faq-accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="faq1">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1" aria-expanded="true" aria-controls="faqCollapse1">
                                <i class="bi bi-truck me-2"></i> Thời gian giao hàng là bao lâu?
                            </button>
                        </h3>
                        <div id="faqCollapse1" class="accordion-collapse collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>Thời gian giao hàng của chúng tôi thường từ 2-5 ngày làm việc tùy thuộc vào khu vực. 
                                Đối với nội thành các thành phố lớn, thời gian giao hàng có thể nhanh hơn.</p>
                                <p class="mb-0 text-muted small">Lưu ý: Thời gian giao hàng có thể thay đổi trong các dịp lễ, Tết hoặc khi có sự cố bất khả kháng.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                <i class="bi bi-arrow-repeat me-2"></i> Làm thế nào để đổi trả sản phẩm?
                            </button>
                        </h3>
                        <div id="faqCollapse2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Bạn có thể đổi trả sản phẩm trong vòng 7 ngày kể từ ngày nhận hàng nếu sản phẩm còn nguyên tem mác, 
                                chưa qua sử dụng và có hóa đơn mua hàng. Vui lòng liên hệ với chúng tôi qua hotline hoặc email để được hướng dẫn chi tiết.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                <i class="bi bi-credit-card me-2"></i> Phương thức thanh toán nào được chấp nhận?
                            </button>
                        </h3>
                        <div id="faqCollapse3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Chúng tôi chấp nhận thanh toán qua nhiều hình thức: tiền mặt khi nhận hàng (COD), chuyển khoản ngân hàng, 
                                ví điện tử (MoMo, ZaloPay, VNPay) và thẻ tín dụng/ghi nợ quốc tế (Visa, MasterCard, JCB).
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="faq4">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                                <i class="bi bi-shield-check me-2"></i> Sản phẩm có bảo hành không?
                            </button>
                        </h3>
                        <div id="faqCollapse4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Tất cả sản phẩm của chúng tôi đều được bảo hành theo chính sách của từng nhà sản xuất, thông thường từ 6 tháng đến 1 năm. 
                                Chi tiết bảo hành sẽ được ghi rõ trong phiếu bảo hành đi kèm sản phẩm. Nếu có bất kỳ vấn đề gì về bảo hành, vui lòng liên hệ với chúng tôi.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="faq5">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse5" aria-expanded="false" aria-controls="faqCollapse5">
                                <i class="bi bi-gift me-2"></i> Có chương trình khuyến mãi định kỳ không?
                            </button>
                        </h3>
                        <div id="faqCollapse5" class="accordion-collapse collapse" aria-labelledby="faq5" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Chúng tôi thường xuyên có các chương trình khuyến mãi vào các dịp đặc biệt như Black Friday, Tết, 
                                sinh nhật cửa hàng và các dịp lễ khác. Để không bỏ lỡ các chương trình khuyến mãi, 
                                bạn có thể đăng ký nhận thông báo qua email hoặc theo dõi chúng tôi trên các kênh mạng xã hội.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="cta-content text-center">
                    <h3>Cần hỗ trợ ngay?</h3>
                    <p class="mb-4">Gọi cho chúng tôi hoặc để lại tin nhắn, đội ngũ hỗ trợ sẽ liên hệ lại với bạn trong thời gian sớm nhất!</p>
                    <div class="cta-buttons">
                        <a href="tel:<?php echo $settings['contact_phone'] ?? '0123456789'; ?>" class="btn btn-primary me-3">
                            <i class="bi bi-telephone-fill me-2"></i> Gọi ngay
                        </a>
                        <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickContactModal">
                            <i class="bi bi-chat-fill me-2"></i> Chat với tư vấn viên
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Contact Modal -->
<div class="modal fade" id="quickContactModal" tabindex="-1" aria-labelledby="quickContactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickContactModalLabel">Liên hệ nhanh</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="quickContactForm">
                    <div class="mb-3">
                        <label for="quickName" class="form-label">Họ tên</label>
                        <input type="text" class="form-control" id="quickName" placeholder="Nhập họ tên của bạn">
                    </div>
                    <div class="mb-3">
                        <label for="quickPhone" class="form-label">Số điện thoại</label>
                        <input type="tel" class="form-control" id="quickPhone" placeholder="Nhập số điện thoại">
                    </div>
                    <div class="mb-3">
                        <label for="quickMessage" class="form-label">Nội dung</label>
                        <textarea class="form-control" id="quickMessage" rows="3" placeholder="Nhập nội dung cần tư vấn"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="submitQuickContact">Gửi yêu cầu</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for Contact page -->
<style>
    /* Hero Section */
    .contact-hero {
        background: linear-gradient(135deg, #0d6efd20 0%, #0d6efd05 100%);
        padding: 60px 0;
        margin-bottom: 30px;
        border-radius: 0 0 30px 30px;
    }
    
    .hero-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: #212529;
        margin-bottom: 20px;
    }
    
    .hero-subtitle {
        font-size: 1.1rem;
        color: #6c757d;
        margin-bottom: 0;
    }
    
    .contact-illustration {
        max-height: 250px;
    }
    
    .fallback-icon {
        width: 150px;
        height: 150px;
        opacity: 0.5;
    }
    
    /* Section Titles */
    .section-title {
        font-weight: 700;
        color: #212529;
        position: relative;
        padding-bottom: 10px;
    }
    
    .section-title:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background-color: #0d6efd;
    }
    
    .section-subtitle {
        color: #6c757d;
        margin-bottom: 30px;
    }
    
    /* Contact Info Cards */
    .contact-card {
        background: #ffffff;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.08);
    }
    
    .contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        border-color: #0d6efd50;
    }
    
    .card-icon {
        width: 50px;
        height: 50px;
        background: #0d6efd10;
        color: #0d6efd;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 15px;
    }
    
    .contact-card h5 {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .contact-card p {
        color: #6c757d;
        margin-bottom: 0;
    }
    
    /* Social Icons */
    .social-media-section {
        margin-top: 30px;
    }
    
    .social-icons {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .social-icon {
        width: 40px;
        height: 40px;
        background: #ffffff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        font-size: 1.2rem;
        border: 1px solid #0d6efd20;
        transition: all 0.3s ease;
    }
    
    .social-icon:hover {
        background: #0d6efd;
        color: #ffffff;
        transform: scale(1.1);
    }
    
    /* Map Container */
    .map-container {
        margin-top: 30px;
    }
    
    .map-wrapper {
        border-radius: 10px;
        overflow: hidden;
    }
    
    /* Contact Form */
    .contact-form .card {
        border: none;
        border-radius: 15px;
    }
    
    .contact-form-container {
        margin-top: 20px;
    }
    
    .input-group-text {
        background-color: #f8f9fa;
        border-right: none;
    }
    
    .form-control {
        border-left: none;
    }
    
    .form-control:focus {
        box-shadow: none;
        border-color: #ced4da;
    }
    
    .input-group:focus-within .input-group-text {
        border-color: #86b7fe;
    }
    
    .submit-btn {
        transition: all 0.3s ease;
    }
    
    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
    }
    
    /* FAQ Section */
    .faq-section {
        background-color: #f8f9fa;
    }
    
    .faq-accordion .accordion-item {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 16px;
        border: 1px solid rgba(0,0,0,.125);
    }
    
    .faq-accordion .accordion-header {
        margin: 0;
    }
    
    .faq-accordion .accordion-button {
        font-weight: 600;
        padding: 16px;
        background-color: #ffffff;
    }
    
    .faq-accordion .accordion-button:not(.collapsed) {
        color: #0d6efd;
        background-color: #e7f1ff;
    }
    
    .faq-accordion .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(0,0,0,.125);
    }
    
    /* CTA Section */
    .cta-section {
        background: linear-gradient(135deg, #0d6efd10 0%, #0d6efd05 100%);
    }
    
    .cta-content {
        background-color: #ffffff;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }
    
    .cta-content h3 {
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    /* Responsive Styles */
    @media (max-width: 992px) {
        .contact-hero {
            padding: 40px 0;
        }
        
        .hero-title {
            font-size: 2rem;
        }
    }
    
    @media (max-width: 768px) {
        .contact-hero {
            text-align: center;
            padding: 30px 0;
        }
        
        .section-title:after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .contact-form {
            margin-top: 2rem;
        }
        
        .cta-content {
            padding: 30px 20px;
        }
    }
    
    /* Animation Classes */
    .animate-up {
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    // Add animation classes when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation to elements
        const elementsToAnimate = document.querySelectorAll('.contact-card, .social-icons, .contact-form .card');
        elementsToAnimate.forEach(element => {
            element.classList.add('animate-up');
        });
        
        // Handle quick contact form submission
        const quickContactBtn = document.getElementById('submitQuickContact');
        if (quickContactBtn) {
            quickContactBtn.addEventListener('click', function() {
                alert('Cảm ơn bạn đã gửi yêu cầu! Chúng tôi sẽ liên hệ lại với bạn sớm nhất có thể.');
                const modal = bootstrap.Modal.getInstance(document.getElementById('quickContactModal'));
                modal.hide();
            });
        }
    });
</script>

<?php
// Include footer
include('includes/footer.php');
?>
