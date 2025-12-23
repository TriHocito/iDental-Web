<?php
// src/views/index.php
session_start();

// 1. Kết nối CSDL (Đi ngược ra src/ -> vào config/)
require '../config/db_connect.php'; 

// 2. Lấy ngẫu nhiên 4 dịch vụ để hiển thị
$random_services = [];
try {
    $stmt = $conn->query("SELECT * FROM dichvu ORDER BY RAND() LIMIT 4");
    $random_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Xử lý lỗi nếu cần
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDental - Hệ Thống Nha Khoa Hiện Đại</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>

<body>

    <?php include '../includes/header.php'; ?>
  
    <section id="home" class="hero-section" >
        <div class="container text-center">
            <h1 class="hero-title">Nụ cười của bạn là ưu tiên hàng đầu của chúng tôi</h1>
            <p class="hero-desc">
                Dịch vụ nha khoa đẳng cấp với đội ngũ chuyên gia tận tâm. Dù là làm sạch răng định kỳ hay điều trị chuyên sâu, chúng tôi luôn đồng hành để giữ nụ cười của bạn rạng rỡ và khỏe mạnh.
            </p>
            
            <div class="hero-actions">
                <a href="datlich.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-calendar-check"></i> Đặt lịch ngay
                </a>
                <a href="vechungtoi.php" class="btn btn-secondary btn-lg" style="background: white;">
                    <i class="fas fa-info-circle"></i> Về chúng tôi
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="section bg-white">
        <div class="container">
            <h2 class="section-title text-center">Tại sao chọn iDental?</h2>
            <div class="features-grid">
                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-icon bg-green"><i class="fas fa-trophy"></i></div>
                        <div><h3>15 năm kinh nghiệm</h3><p>Đội ngũ giàu kinh nghiệm mang đến dịch vụ chất lượng.</p></div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon bg-blue"><i class="fas fa-tools"></i></div>
                        <div><h3>Công nghệ hiện đại</h3><p>Trang thiết bị nhập khẩu, chẩn đoán chuẩn xác.</p></div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon bg-red"><i class="fas fa-heart"></i></div>
                        <div><h3>Tận tâm phục vụ</h3><p>Kế hoạch điều trị cá nhân hóa cho từng bệnh nhân.</p></div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon" style="background: #fff3e0; color: #fbc02d;"><i class="far fa-clock"></i></div>
                        <div><h3>Linh hoạt thời gian</h3><p>Làm việc cả buổi tối và ngày nghỉ.</p></div>
                    </div>
                </div>
                <div class="feature-image">
                    <img src="../assets/img/3.jpg" alt="Đội ngũ nha sĩ iDental">
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-light">
        <div class="container text-center">
            <h2 class="section-title">Dịch vụ nổi bật</h2>
            <p class="section-subtitle">Giải pháp toàn diện cho nụ cười của bạn.</p>
            
            <div class="services-grid">
                <?php if (count($random_services) > 0): ?>
                    <?php foreach ($random_services as $sv): 
                        
                        $icon = 'fas fa-tooth';
                        if (strpos(strtolower($sv['ten_dich_vu']), 'niềng') !== false) $icon = 'fas fa-teeth-open';
                        elseif (strpos(strtolower($sv['ten_dich_vu']), 'implant') !== false) $icon = 'fas fa-shield-alt';
                    ?>
                    <div class="service-card">
                        <div class="service-icon"><i class="<?php echo $icon; ?>"></i></div>
                        <h3><?php echo htmlspecialchars($sv['ten_dich_vu']); ?></h3>
                        <p><?php echo htmlspecialchars($sv['mo_ta'] ?? 'Chăm sóc chuyên sâu.'); ?></p>
                        <span class="service-price">
                            <?php echo ($sv['gia_tien'] > 0) ? number_format($sv['gia_tien'], 0, ',', '.') . ' VNĐ' : 'Liên hệ'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Đang cập nhật dịch vụ...</p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 40px;">
                <a href="banggia.php" class="btn btn-secondary btn-lg" style="background: white; border: 1px solid var(--primary); color: var(--primary);">
                    Xem Chi Tiết Bảng Giá <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                </a>
            </div>
        </div>
    </section>
    <section class="section bg-white">
        <div class="container text-center">
            <h2 class="section-title">Những lời chia sẻ từ khách hàng</h2>
            <p class="section-subtitle">Đừng chỉ nghe chúng tôi nói — hãy lắng nghe cảm nhận từ chính những bệnh nhân hài lòng.</p>
            
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="user-info">
                        <i class="far fa-user-circle user-avatar"></i>
                        <div>
                            <h4>Minh Phương</h4>
                            
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                        </div>
                    </div>
                    <p class="quote">"Dịch vụ tuyệt vời! Đội ngũ nhân viên chuyên nghiệp và tận tâm đã giúp tôi vượt qua hoàn toàn nỗi lo khi đi nha khoa."</p>
                </div>
                
                <div class="testimonial-card">
                    <div class="user-info">
                        <i class="far fa-user-circle user-avatar"></i>
                        <div>
                            <h4>Hải Trí</h4>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                        </div>
                    </div>
                    <p class="quote">"Tôi rất ấn tượng với kỹ năng của Bác sĩ trong ca điều trị tủy: nhẹ nhàng, không đau và theo dõi tận tình sau đó."</p>
                </div>

                <div class="testimonial-card">
                    <div class="user-info">
                        <i class="far fa-user-circle user-avatar"></i>
                        <div>
                            <h4>Quang Vinh</h4>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                            <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                        </div>
                    </div>
                    <p class="quote">"Phòng khám nha khoa tốt nhất thành phố: không gian sạch sẽ, công nghệ tiên tiến, phục vụ tận tâm."</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container text-center">
            <h2 style="color: white; font-size: 28px; margin-bottom: 15px;">Đã đến lúc chăm sóc nụ cười của bạn</h2>
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <a href="datlich.php" class="btn btn-white btn-lg" style="color: var(--primary); background: white;">Đặt Lịch Ngay</a>
                <a href="#" class="btn btn-lg" style="color: white; border: 1px solid white;"><i class="fas fa-phone-alt"></i> (028) 38 505 520</a>
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