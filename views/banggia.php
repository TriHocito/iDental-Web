<?php
// src/banggia.php
session_start();
require '../config/db_connect.php';

// 1. Lấy danh sách Dịch vụ
$services = $conn->query("SELECT * FROM dichvu ORDER BY ten_dich_vu ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Lấy danh sách Bác sĩ
$doctors = $conn->query("SELECT * FROM bacsi ORDER BY ten_day_du ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Giá & Bác Sĩ - iDental</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <section class="hero-section" style="padding: 80px 0; min-height: auto;">
        <div class="container text-center">
            <h1 class="hero-title">Bảng Giá & Đội Ngũ Chuyên Gia</h1>
            <p class="hero-desc">Minh bạch về chi phí, tận tâm trong điều trị.</p>
        </div>
    </section>

    <section class="section bg-white">
        <div class="container">
            <h2 class="section-title text-center"><i class="fas fa-tags"></i> Bảng Giá Dịch Vụ</h2>
            <p class="section-subtitle">Chi phí được niêm yết rõ ràng, không phát sinh phụ phí.</p>
            
            <div class="table-container price-container">
                <table class="data-table price-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Tên Dịch Vụ</th>
                            <th style="width: 40%;">Mô Tả Chi Tiết</th>
                            <th style="width: 15%;">Thời Gian</th>
                            <th style="width: 15%;">Chi Phí</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($services) > 0): ?>
                            <?php foreach ($services as $sv): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sv['ten_dich_vu']); ?></strong></td>
                                <td style="color: #666;"><?php echo htmlspecialchars($sv['mo_ta'] ?? 'Liên hệ để biết thêm chi tiết.'); ?></td>
                                <td><i class="far fa-clock"></i> ~<?php echo $sv['thoi_gian_phut']; ?> phút</td>
                                <td class="price-text"><?php echo number_format($sv['gia_tien'], 0, ',', '.'); ?> đ</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center">Đang cập nhật bảng giá...</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center" style="margin-top: 30px;">
                <a href="datlich.php" class="btn btn-primary btn-lg">Đặt Lịch Ngay</a>
            </div>
        </div>
    </section>

    <section class="section bg-light">
        <div class="container">
            <h2 class="section-title text-center"><i class="fas fa-user-md"></i> Đội Ngũ Bác Sĩ</h2>
            <p class="section-subtitle">Các chuyên gia đầu ngành với nhiều năm kinh nghiệm.</p>

            <div class="doctors-grid">
                <?php if (count($doctors) > 0): ?>
                    <?php foreach ($doctors as $doc): 
                        $avatar = !empty($doc['link_anh_dai_dien']) ? $doc['link_anh_dai_dien'] : 'https://i.pravatar.cc/300?u=' . $doc['id_bacsi'];
                    ?>
                    <div class="doctor-card">
                        <div class="doctor-img-wrapper">
                            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($doc['ten_day_du']); ?>" class="doctor-img">
                        </div>
                        <div class="doctor-info">
                            <h3 class="doctor-name"><?php echo htmlspecialchars($doc['ten_day_du']); ?></h3>
                            <p class="doctor-spec"><?php echo htmlspecialchars($doc['chuyen_khoa']); ?></p>
                            <div class="doctor-contact">
                                <i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($doc['sdt']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center full-width">Đang cập nhật danh sách bác sĩ...</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/file.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof setActiveMenu === "function") {
                setActiveMenu();
            }
        });
    </script>
</body>
</html>