<?php
session_start();
require '../config/db_connect.php'; 
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Về Chúng Tôi - iDental</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>

    <?php include '../includes/header.php'; ?>

    <section id="home" class="hero-section" >
        <div class="container text-center">
            <h1 class="hero-title">Phòng khám nha khoa iDental</h1>
            <p class="hero-desc">
                Đồng hành cùng bạn trên hành trình chăm sóc răng miệng toàn diện với sự tận tâm và chuyên nghiệp nhất.
            </p>
            <div class="hero-actions">
                <a href="datlich.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-calendar-check"></i> Đặt Lịch Ngay
                </a>
                <a href="#about" class="btn btn-secondary btn-lg" style="background: white;">
                    <i class="fas fa-arrow-down"></i> Tìm Hiểu Thêm
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="section bg-white">
        <div class="container">
            <h2 class="section-title text-center">Về phòng khám iDental</h2>
            
            <div class="features-grid">
                <div class="feature-list">
                    <div class="feature-item">
                        <div>
                            <p style="margin-bottom: 20px; color: #555;">
                                Phòng khám nha khoa iDental đã phục vụ cộng đồng hơn 15 năm, mang đến dịch vụ chăm sóc răng miệng chất lượng cao với công nghệ tiên tiến và phương pháp điều trị đầy nhân ái. Đội ngũ bác sĩ và chuyên viên giàu kinh nghiệm của chúng tôi luôn tận tâm giúp bạn đạt được sức khỏe răng miệng tối ưu.
                            </p>
                            <p style="margin-bottom: 20px; color: #555;">
                                Chúng tôi tin rằng ai cũng xứng đáng có một nụ cười khỏe mạnh và rạng rỡ. Cơ sở hiện đại của chúng tôi được trang bị công nghệ nha khoa mới nhất nhằm đảm bảo quá trình điều trị diễn ra thoải mái, hiệu quả cho mọi lứa tuổi.
                            </p>
                            
                            <h3 style="color: var(--primary); margin-bottom: 15px;">Điểm nổi bật của iDental</h3>
                            <ul style="list-style: none; padding: 0;">
                                <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success); margin-right: 10px;"></i> Hơn 15 năm kinh nghiệm</li>
                                <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success); margin-right: 10px;"></i> Trang thiết bị hiện đại nhập khẩu</li>
                                <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success); margin-right: 10px;"></i> Đội ngũ chuyên gia đầu ngành</li>
                                <li style="margin-bottom: 10px;"><i class="fas fa-check-circle" style="color: var(--success); margin-right: 10px;"></i> Quy trình vô trùng tuyệt đối</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="feature-image">
                    <img src="../assets/img/3.jpg" alt="Phòng khám iDental">
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-light">
        <div class="container text-center">
            <h2 class="section-title">Dịch vụ của chúng tôi</h2>
            <p class="section-subtitle">Giải pháp toàn diện cho nụ cười của bạn.</p>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-tooth"></i></div>
                    <h3>Nha khoa Tổng quát</h3>
                    <p>Làm sạch răng, trám răng, điều trị tủy và phòng ngừa.</p>
                    <span class="service-price">Từ 300.000đ</span>
                </div>

                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-scissors"></i></div>
                    <h3>Phẫu thuật Nha khoa</h3>
                    <p>Nhổ răng khôn, tiểu phẫu an toàn, không đau.</p>
                    <span class="service-price">Từ 1.000.000đ</span>
                </div>

                <div class="service-card">
                    <div class="service-icon"><i class="far fa-star"></i></div>
                    <h3>Nha khoa Thẩm mỹ</h3>
                    <p>Tẩy trắng răng, dán sứ Veneer cho nụ cười rạng rỡ.</p>
                    <span class="service-price">Từ 3.000.000đ</span>
                </div>
                
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Trồng răng Implant</h3>
                    <p>Phục hồi răng mất vĩnh viễn, ăn nhai như thật.</p>
                    <span class="service-price">Từ 10.000.000đ</span>
                </div>
            </div>
            
            <div style="margin-top: 40px;">
                <a href="banggia.php" class="btn btn-secondary btn-lg" style="background: white; border: 1px solid var(--primary); color: var(--primary);">
                    Xem Chi Tiết Bảng Giá <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <section class="section bg-white">
        <div class="container">
            <div class="features-grid">
                <div class="feature-item" style="background: #f8f9fa; padding: 30px; border-radius: 15px; display: block; text-align: center;">
                    <div class="feature-icon" style="background: var(--primary-light); color: var(--primary); margin: 0 auto 20px auto;"><i class="fas fa-clock"></i></div>
                    <h3>Thời gian làm việc</h3>
                    <p>Thứ Hai – Thứ Sáu: 8:00 - 18:00</p>
                    <p>Thứ Bảy: 9:00 - 16:00</p>
                    <p>Chủ Nhật: Chỉ nhận lịch hẹn trước</p>
                </div>

                <div class="feature-item" style="background: #f8f9fa; padding: 30px; border-radius: 15px; display: block; text-align: center;">
                    <div class="feature-icon" style="background: #ffebee; color: var(--danger); margin: 0 auto 20px auto;"><i class="fas fa-ambulance"></i></div>
                    <h3>Dịch vụ Khẩn cấp</h3>
                    <p>Hỗ trợ 24/7 cho các tình huống đau răng cấp tính, gãy răng hoặc chấn thương.</p>
                    <p><strong>Hotline: (028) 38 505 520</strong></p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container text-center">
            <h2 style="color: white; font-size: 28px; margin-bottom: 15px;">Đã đến lúc chăm sóc nụ cười của bạn</h2>
            <p style="max-width: 700px; margin: 0 auto 30px auto; color: white; opacity: 0.9;">
                Hãy bắt đầu hành trình hướng tới một nụ cười khỏe mạnh và rạng rỡ hơn. Đội ngũ thân thiện của chúng tôi luôn sẵn sàng hỗ trợ bạn.
            </p>
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <a href="datlich.php" class="btn btn-white btn-lg" style="color: var(--primary); background: white;">
                    Đặt Lịch Ngay
                </a>
                <a href="#" class="btn btn-lg" style="color: white; border: 1px solid white;"><i class="fas fa-phone"></i> (028) 38 505 520</a>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/file.js"></script>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof setActiveMenu === "function") setActiveMenu();
        });
    </script>
</body>
</html>