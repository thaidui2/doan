<?php
// Page title
$page_title = "Liên hệ";

// Include header

// Include database connection if not already included
if (!isset($conn)) {
    include('config/config.php');
}

// Get contact information from settings table
$settings_query = $conn->query("
    SELECT setting_key, setting_value 
    FROM settings 
    WHERE setting_key IN ('site_name', 'contact_email', 'contact_phone', 'address', 
                        'facebook_url', 'instagram_url', 'twitter_url', 'youtube_url')
");

$settings = [];
if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
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
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên hệ - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/lienhe.css">
</head>

<body>
    <!-- Include header -->
    <?php
    require_once('includes/head.php');
    require_once('includes/header.php');

    ?> <!-- Hero Banner -->
    <div class="contact-hero">
        <div class="container">
            <div class="row align-items-center text-center text-lg-start py-3">
                <div class="col-lg-7">
                    <h1 class="hero-title">Liên hệ với chúng tôi</h1>
                    <p class="hero-subtitle">Chúng tôi luôn sẵn sàng hỗ trợ bạn 24/7. Hãy để lại thông tin và chúng tôi
                        sẽ liên hệ lại ngay.</p>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                    <img src="images/contact-illustration.svg" alt="Contact Us" class="img-fluid contact-illustration"
                        onerror="this.src='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/headset.svg'; this.classList.add('fallback-icon')">
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
            <div class="row g-4 justify-content-center">
                <div class="col-lg-8">
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
                                    <a href="<?php echo $settings['facebook_url']; ?>" target="_blank" class="social-icon"
                                        title="Facebook">
                                        <i class="bi bi-facebook"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($settings['instagram_url'])): ?>
                                    <a href="<?php echo $settings['instagram_url']; ?>" target="_blank" class="social-icon"
                                        title="Instagram">
                                        <i class="bi bi-instagram"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($settings['twitter_url'])): ?>
                                    <a href="<?php echo $settings['twitter_url']; ?>" target="_blank" class="social-icon"
                                        title="Twitter">
                                        <i class="bi bi-twitter-x"></i>
                                    </a>
                                <?php endif; ?> <?php if (!empty($settings['youtube_url'])): ?>
                                    <a href="<?php echo $settings['youtube_url']; ?>" target="_blank" class="social-icon"
                                        title="YouTube">
                                        <i class="bi bi-youtube"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="https://zalo.me/" target="_blank" class="social-icon" title="Zalo">
                                    <i class="bi bi-telephone"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Google Map -->
                        <div class="map-container">
                            <h4 class="mb-3">Bản đồ cửa hàng</h4>
                            <div class="map-wrapper shadow-sm">
                                <iframe
                                    src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d6434.669492862747!2d105.7410797861001!3d21.04014864920691!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135096b31fa7abb%3A0xff645782804911af!2zVHLGsOG7nW5nIMSR4bqhaSBo4buNYyBDw7RuZyBuZ2jhu4cgxJDDtG5nIMOB!5e1!3m2!1svi!2s!4v1744082906782!5m2!1svi!2s"
                                    width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"></iframe>
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
                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faqCollapse1" aria-expanded="true" aria-controls="faqCollapse1">
                                    <i class="bi bi-truck me-2"></i> Thời gian giao hàng là bao lâu?
                                </button>
                            </h3>
                            <div id="faqCollapse1" class="accordion-collapse collapse show" aria-labelledby="faq1"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Thời gian giao hàng của chúng tôi thường từ 2-5 ngày làm việc tùy thuộc vào khu
                                        vực.
                                        Đối với nội thành các thành phố lớn, thời gian giao hàng có thể nhanh hơn.</p>
                                    <p class="mb-0 text-muted small">Lưu ý: Thời gian giao hàng có thể thay đổi trong
                                        các dịp lễ, Tết hoặc khi có sự cố bất khả kháng.</p>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h3 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                    <i class="bi bi-arrow-repeat me-2"></i> Làm thế nào để đổi trả sản phẩm?
                                </button>
                            </h3>
                            <div id="faqCollapse2" class="accordion-collapse collapse" aria-labelledby="faq2"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Bạn có thể đổi trả sản phẩm trong vòng 7 ngày kể từ ngày nhận hàng nếu sản phẩm còn
                                    nguyên tem mác,
                                    chưa qua sử dụng và có hóa đơn mua hàng. Vui lòng liên hệ với chúng tôi qua hotline
                                    hoặc email để được hướng dẫn chi tiết.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h3 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                    <i class="bi bi-credit-card me-2"></i> Phương thức thanh toán nào được chấp nhận?
                                </button>
                            </h3>
                            <div id="faqCollapse3" class="accordion-collapse collapse" aria-labelledby="faq3"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Chúng tôi chấp nhận thanh toán qua nhiều hình thức: tiền mặt khi nhận hàng (COD),
                                    chuyển khoản ngân hàng,
                                    ví điện tử (MoMo, ZaloPay, VNPay) và thẻ tín dụng/ghi nợ quốc tế (Visa, MasterCard,
                                    JCB).
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h3 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                                    <i class="bi bi-shield-check me-2"></i> Sản phẩm có bảo hành không?
                                </button>
                            </h3>
                            <div id="faqCollapse4" class="accordion-collapse collapse" aria-labelledby="faq4"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Tất cả sản phẩm của chúng tôi đều được bảo hành theo chính sách của từng nhà sản
                                    xuất, thông thường từ 6 tháng đến 1 năm.
                                    Chi tiết bảo hành sẽ được ghi rõ trong phiếu bảo hành đi kèm sản phẩm. Nếu có bất kỳ
                                    vấn đề gì về bảo hành, vui lòng liên hệ với chúng tôi.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h3 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faqCollapse5" aria-expanded="false" aria-controls="faqCollapse5">
                                    <i class="bi bi-gift me-2"></i> Có chương trình khuyến mãi định kỳ không?
                                </button>
                            </h3>
                            <div id="faqCollapse5" class="accordion-collapse collapse" aria-labelledby="faq5"
                                data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Chúng tôi thường xuyên có các chương trình khuyến mãi vào các dịp đặc biệt như Black
                                    Friday, Tết,
                                    sinh nhật cửa hàng và các dịp lễ khác. Để không bỏ lỡ các chương trình khuyến mãi,
                                    bạn có thể đăng ký nhận thông báo qua email hoặc theo dõi chúng tôi trên các kênh
                                    mạng xã hội.
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
                        <p class="mb-4">Gọi cho chúng tôi hoặc để lại tin nhắn, đội ngũ hỗ trợ sẽ liên hệ lại với bạn
                            trong thời gian sớm nhất!</p>
                        <div class="cta-buttons">
                            <a href="tel:<?php echo $settings['contact_phone'] ?? '0123456789'; ?>"
                                class="btn btn-primary me-3">
                                <i class="bi bi-telephone-fill me-2"></i> Gọi ngay
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    // Include footer
    include('includes/footer.php');
    ?>

    <script src="js/lienhe.js"></script>