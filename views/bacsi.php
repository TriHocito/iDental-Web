<?php
// views/bacsi.php
session_start();
require '../config/db_connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: dangnhap.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// --- 1. LẤY PROFILE ---
$stmt_profile = $conn->prepare("SELECT * FROM bacsi WHERE id_bacsi = ?");
$stmt_profile->execute([$doctor_id]);
$my_profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);
$current_avatar = !empty($my_profile['link_anh_dai_dien']) ? $my_profile['link_anh_dai_dien'] : 'https://i.pravatar.cc/150?img=11';

// --- 2. XỬ LÝ TÌM KIẾM BỆNH NHÂN ---
$search_result = null;
$history_list = [];
$search_sdt = '';

if (isset($_GET['search_sdt'])) {
    $search_sdt = trim($_GET['search_sdt']);
    $stmt_bn = $conn->prepare("SELECT * FROM benhnhan WHERE sdt = ?");
    $stmt_bn->execute([$search_sdt]);
    $patient = $stmt_bn->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        $search_result = $patient;
        $sql_hist = "SELECT lh.ngay_gio_hen, dv.ten_dich_vu, ba.chan_doan, ba.ghi_chu_bac_si, bs.ten_day_du as ten_bacsi
                     FROM lichhen lh
                     JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                     LEFT JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
                     LEFT JOIN benhan ba ON lh.id_lichhen = ba.id_lichhen
                     WHERE lh.id_benhnhan = ? 
                     ORDER BY lh.ngay_gio_hen DESC";
        $stmt_hist = $conn->prepare($sql_hist);
        $stmt_hist->execute([$patient['id_benhnhan']]);
        $history_list = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- 4. CHUẨN BỊ DỮ LIỆU CHO MODAL ĐĂNG KÝ ---
$week_options = [];
// Mặc định đăng ký cho các tuần tiếp theo (Next Monday trở đi)
$next_monday_ts = strtotime('next monday');
for ($i = 0; $i < 12; $i++) { 
    $w_start = strtotime("+$i weeks", $next_monday_ts);
    $w_end = strtotime("+6 days", $w_start);
    $week_options[] = [
        'value' => $i,
        'label' => "Tuần " . date('W', $w_start) . " (" . date('d/m', $w_start) . " - " . date('d/m', $w_end) . ")"
    ];
}
$js_next_monday = date('Y-m-d', $next_monday_ts);

// --- 5. CÁC DỮ LIỆU KHÁC ---
$patients = $conn->query("SELECT * FROM benhnhan ORDER BY ten_day_du ASC")->fetchAll(PDO::FETCH_ASSOC);
$services = $conn->query("SELECT * FROM dichvu ORDER BY ten_dich_vu ASC")->fetchAll(PDO::FETCH_ASSOC);

$base_sql = "SELECT lh.*, bn.ten_day_du AS ten_bn, bn.sdt, dv.ten_dich_vu AS ten_dichvu
             FROM lichhen lh 
             JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan 
             JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
             WHERE lh.id_bacsi = $doctor_id";

$sql_pending_check = "SELECT lh.*, bn.ten_day_du AS ten_bn, bn.sdt, dv.ten_dich_vu AS ten_dichvu,
                      (SELECT COUNT(*) FROM lichlamviec llv 
                        WHERE llv.id_bacsi = lh.id_bacsi 
                        AND llv.ngay_hieu_luc = DATE(lh.ngay_gio_hen)
                        AND ((HOUR(lh.ngay_gio_hen) < 12 AND llv.gio_bat_dau = '08:00:00') OR (HOUR(lh.ngay_gio_hen) >= 12 AND llv.gio_bat_dau = '13:00:00'))
                      ) as has_shift
                      FROM lichhen lh 
                      JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan 
                      JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                      WHERE lh.id_bacsi = $doctor_id AND lh.trang_thai = 'cho_xac_nhan' 
                      ORDER BY lh.ngay_gio_hen ASC";

$pending = $conn->query($sql_pending_check)->fetchAll(PDO::FETCH_ASSOC);
$upcoming = $conn->query("$base_sql AND lh.trang_thai = 'da_xac_nhan' AND lh.ngay_gio_hen >= NOW() ORDER BY lh.ngay_gio_hen ASC")->fetchAll(PDO::FETCH_ASSOC);
$completed = $conn->query("$base_sql AND lh.trang_thai = 'hoan_thanh' ORDER BY lh.ngay_gio_hen DESC")->fetchAll(PDO::FETCH_ASSOC);
$appointments_today = $conn->query("$base_sql AND DATE(lh.ngay_gio_hen) = CURDATE() ORDER BY lh.ngay_gio_hen ASC")->fetchAll(PDO::FETCH_ASSOC);

$count_patients = count($completed);
$count_pending = count($pending);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDental - Bác Sĩ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bacsi.css">
    <style>
        .tab-header { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .tab-btn { padding: 10px 20px; border: none; background: none; cursor: pointer; font-weight: 600; color: #666; border-radius: 5px; }
        .tab-btn.active { background: var(--primary); color: white; }
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .search-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .search-form { display: flex; gap: 10px; }
        .search-form input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .patient-info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-top: 15px; border-left: 4px solid var(--primary); }
        
        .reg-table th, .reg-table td { padding: 10px; text-align: center; border: 1px solid #ddd; }
        .reg-table input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; transform: scale(1.2); }
        .week-selector-container { margin-bottom: 15px; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
        
        .profile-avatar img { width: 200px; height: 200px; object-fit: cover; border-radius: 50%; margin-bottom: 15px; border: 3px solid #eee; }
        .schedule-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: #f1f8ff; padding: 10px; border-radius: 5px; }
        .schedule-nav button { background: white; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
        .schedule-nav button:hover { background: var(--primary); color: white; }
        .schedule-nav h3 { margin: 0; font-size: 1.1em; color: #333; }
        
        .work-slot-badge { padding: 8px; border-radius: 4px; font-weight: bold; font-size: 12px; display: block; margin: 2px; text-align: center; }
        .morning { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .afternoon { background: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; }
        .leave-slot { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .empty-slot { color: #ccc; font-style: italic; text-align: center; }
        
        /* CSS cho label trạng thái trong modal đăng ký */
        .status-label { font-size: 0.8em; font-weight: bold; margin-top: 5px; display: block; }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="logo"><i class="fas fa-tooth"></i> iDental Doctor</div>
            <nav>
                <div class="menu-link active" onclick="showSection('dashboard', this)"><i class="fas fa-columns"></i> Tổng Quan</div>
                <div class="menu-link" onclick="showSection('my-schedule', this)"><i class="fas fa-clock"></i> Lịch Làm Việc</div>
                <div class="menu-link" onclick="showSection('appointments', this)">
                    <i class="fas fa-calendar-check"></i> Quản Lý Lịch Hẹn
                    <?php if ($count_pending > 0): ?>
                        <span style="background:red; color:white; font-size:10px; padding:2px 6px; border-radius:10px; margin-left:auto;"><?php echo $count_pending; ?></span>
                    <?php endif; ?>
                </div>
                <div class="menu-link" onclick="showSection('profile', this)"><i class="fas fa-user-cog"></i> Hồ Sơ Cá Nhân</div>
            </nav>
            <div style="position: absolute; bottom: 50px; width: 100%; text-align: center;">
                <a href="../controllers/logout.php" class="btn" style="color: #666; background: none; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
            </div>
        </div>

        <div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

        <div class="main-content-wrapper">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button id="sidebarToggle" style="display: none; background: none; border: none; font-size: 22px; cursor: pointer; color: #555;"><i class="fas fa-bars"></i></button>
                    <div style="font-weight:600; color:#555;">Khu vực làm việc Bác sĩ</div>
                </div>
                <div class="user-profile" onclick="toggleUserMenu()">
                    <div style="text-align:right;">
                        <div style="font-weight:700;">Dr. <?php echo htmlspecialchars($my_profile['ten_day_du']); ?></div>
                        <div style="font-size:0.8em; color:#888;"><?php echo htmlspecialchars($my_profile['chuyen_khoa']); ?></div>
                    </div>
                    <img src="<?php echo htmlspecialchars($current_avatar); ?>" class="avatar" alt="Avatar">
                    <div id="userMenuDropdown" class="user-dropdown">
                        <a href="#" onclick="showSection('profile', document.querySelectorAll('.menu-link')[3])"><i class="fas fa-user"></i> Hồ sơ</a>
                        <a href="#" onclick="document.getElementById('changePassModal').style.display='block'"><i class="fas fa-key"></i> Đổi mật khẩu</a>
                        <a href="../controllers/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
                    </div>
                </div>
            </header>

            <div class="main-content">
                <div id="dashboard" class="content-section active">
                   <div class="search-box">
                        <h3 style="margin-top:0; color:var(--primary);"><i class="fas fa-search"></i> Tra Cứu Bệnh Nhân</h3>
                        <form method="GET" class="search-form">
                            <input type="hidden" name="section" value="dashboard">
                            <input type="text" name="search_sdt" placeholder="Nhập số điện thoại bệnh nhân..." value="<?php echo htmlspecialchars($search_sdt); ?>" required>
                            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                        </form>
                        <?php if ($search_sdt): ?>
                            <?php if ($search_result): ?>
                                <div class="patient-info">
                                    <h4>Hồ Sơ: <?php echo htmlspecialchars($search_result['ten_day_du']); ?></h4>
                                    <p><strong>SĐT:</strong> <?php echo htmlspecialchars($search_result['sdt']); ?> | <strong>Email:</strong> <?php echo htmlspecialchars($search_result['email']); ?></p>
                                </div>
                                <div class="table-container" style="margin-top:10px;">
                                    <h4 style="margin:10px 0;">Lịch Sử Khám & Bệnh Án</h4>
                                    <table class="data-table">
                                        <thead><tr><th>Ngày khám</th><th>Dịch vụ</th><th>Bác sĩ</th><th>Chẩn đoán</th><th>Ghi chú</th></tr></thead>
                                        <tbody>
                                            <?php if (count($history_list) > 0): foreach ($history_list as $row): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($row['ngay_gio_hen'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['ten_dich_vu']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['ten_bacsi']); ?></td>
                                                    <td><?php echo nl2br(htmlspecialchars($row['chan_doan'] ?? 'Chưa cập nhật')); ?></td>
                                                    <td><?php echo nl2br(htmlspecialchars($row['ghi_chu_bac_si'] ?? '')); ?></td>
                                                </tr>
                                            <?php endforeach; else: ?>
                                                <tr><td colspan="5" style="text-align:center">Bệnh nhân chưa có lịch sử khám.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="margin-top:10px; color:red; font-style:italic;">Không tìm thấy bệnh nhân với SĐT này.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #e3f2fd; color: var(--primary);"><i class="fas fa-users"></i></div>
                            <div><h2><?php echo $count_patients; ?></h2><small>Bệnh nhân đã khám</small></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #fff3e0; color: var(--warning);"><i class="fas fa-clock"></i></div>
                            <div><h2><?php echo $count_pending; ?></h2><small>Chờ xác nhận</small></div>
                        </div>
                    </div>
                    <div class="table-container">
                        <div class="section-head"><h3 style="margin:0; color: var(--primary);">Lịch Hẹn Hôm Nay</h3></div>
                        <table class="data-table">
                            <thead><tr><th>Giờ</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Trạng thái</th><th>Tác vụ</th></tr></thead>
                            <tbody>
                                <?php if (count($appointments_today) > 0): foreach ($appointments_today as $appt): ?>
                                    <tr>
                                        <td><strong><?php echo date('H:i', strtotime($appt['ngay_gio_hen'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($appt['ten_bn']); ?></td>
                                        <td><?php echo htmlspecialchars($appt['ten_dichvu']); ?></td>
                                        <td><span class="badge bg-confirmed"><?php echo htmlspecialchars($appt['trang_thai']); ?></span></td>
                                        <td><button class="btn btn-primary" onclick="openMedicalModal(<?php echo $appt['id_lichhen']; ?>, '<?php echo $appt['ten_bn']; ?>')"><i class="fas fa-file-medical"></i> Bệnh án</button></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="5" style="text-align:center;">Hôm nay không có lịch hẹn.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="my-schedule" class="content-section">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h2><i class="fas fa-calendar-alt"></i> Lịch Làm Việc Của Tôi</h2>
                        <div style="display:flex; gap:10px;">
                            <button class="btn btn-primary" onclick="document.getElementById('regModal').style.display='block'"><i class="fas fa-plus-circle"></i> Đăng ký lịch làm việc</button>
                            <button class="btn btn-warning" onclick="document.getElementById('leaveRequestModal').style.display='block'"><i class="fas fa-user-clock"></i> Xin nghỉ phép</button>
                        </div>
                    </div>
                    
                    <div class="schedule-nav">
                        <button onclick="changeWeek(-1)"><i class="fas fa-chevron-left"></i> Tuần trước</button>
                        <h3 id="currentWeekLabel">Đang tải...</h3>
                        <div style="display:flex; gap:5px;">
                            <button onclick="changeWeek(0)">Hiện tại</button>
                            <button onclick="changeWeek(1)">Tuần sau <i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="table-container schedule-container">
                        <table class="table-schedule">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Ca / Thứ</th>
                                    <th id="th-1">Thứ 2</th>
                                    <th id="th-2">Thứ 3</th>
                                    <th id="th-3">Thứ 4</th>
                                    <th id="th-4">Thứ 5</th>
                                    <th id="th-5">Thứ 6</th>
                                    <th id="th-6">Thứ 7</th>
                                    <th id="th-7">CN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="shift-label"><strong>SÁNG</strong></td>
                                    <td id="cell-Sang-1"></td><td id="cell-Sang-2"></td><td id="cell-Sang-3"></td><td id="cell-Sang-4"></td><td id="cell-Sang-5"></td><td id="cell-Sang-6"></td><td id="cell-Sang-7"></td>
                                </tr>
                                <tr>
                                    <td class="shift-label"><strong>CHIỀU</strong></td>
                                    <td id="cell-Chieu-1"></td><td id="cell-Chieu-2"></td><td id="cell-Chieu-3"></td><td id="cell-Chieu-4"></td><td id="cell-Chieu-5"></td><td id="cell-Chieu-6"></td><td id="cell-Chieu-7"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="appointments" class="content-section">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2><i class="fas fa-tasks"></i> Quản Lý Lịch Hẹn</h2>
                        <div style="display:flex; gap:10px;">
                            <button class="btn btn-primary" onclick="document.getElementById('addApptModal').style.display='block'"><i class="fas fa-calendar-plus"></i> Đặt Lịch (BN Cũ)</button>
                            <button class="btn" style="background:#ff9800; color:white;" onclick="document.getElementById('walkinModal').style.display='block'"><i class="fas fa-user-plus"></i> Tiếp Nhận Khách Vãng Lai</button>
                        </div>
                    </div>

                    <div class="tab-header">
                        <button class="tab-btn active" onclick="switchTabContent('tab-upcoming', this)">Sắp tới</button>
                        <button class="tab-btn" onclick="switchTabContent('tab-pending', this)">Chờ duyệt <span style="color:red">(<?php echo count($pending); ?>)</span></button>
                        <button class="tab-btn" onclick="switchTabContent('tab-completed', this)">Đã khám</button>
                    </div>

                    <div id="tab-upcoming" class="tab-content active">
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Thời gian</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Tác vụ</th></tr></thead>
                                <tbody>
                                    <?php foreach ($upcoming as $row): ?>
                                        <tr>
                                            <td style="color:var(--primary); font-weight:bold;"><?php echo date('H:i d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_dichvu']); ?></td>
                                            <td>
                                                <button class="btn btn-primary" onclick="openMedicalModal(<?php echo $row['id_lichhen']; ?>, '<?php echo $row['ten_bn']; ?>')">Khám</button>
                                                <a href="../controllers/doctor_actions.php?action=reject_appointment&id=<?php echo $row['id_lichhen']; ?>" class="btn btn-danger" onclick="return confirm('Hủy lịch này?')">Hủy</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tab-pending" class="tab-content">
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Thời gian</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php foreach ($pending as $row): ?>
                                        <tr>
                                            <td><?php echo date('H:i d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_dichvu']); ?></td>
                                            <td><?php if($row['has_shift']>0):?><span class="badge bg-pending">Chờ xác nhận</span><?php else:?><span class="badge" style="background:#607d8b; color:white;">Lịch Đặc Biệt</span><?php endif;?></td>
                                            <td>
                                                <?php if($row['has_shift']>0):?>
                                                    <a href="../controllers/doctor_actions.php?action=approve_appointment&id=<?php echo $row['id_lichhen']; ?>" class="btn btn-success" onclick="return confirm('Nhận lịch này?')">Duyệt</a>
                                                <?php else:?>
                                                    <button class="btn" style="background:#ccc;" disabled>Duyệt</button>
                                                <?php endif;?>
                                                <a href="../controllers/doctor_actions.php?action=reject_appointment&id=<?php echo $row['id_lichhen']; ?>" class="btn btn-danger" onclick="return confirm('Từ chối?')">Hủy</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tab-completed" class="tab-content">
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Ngày khám</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Trạng thái</th></tr></thead>
                                <tbody>
                                    <?php foreach ($completed as $row): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_dichvu']); ?></td>
                                            <td><span class="badge bg-confirmed">Hoàn thành</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="profile" class="content-section">
                    <h2><i class="fas fa-user-edit"></i> Cập nhật Hồ Sơ</h2>
                    <div class="profile-container">
                        <div class="profile-avatar">
                            <img src="<?php echo htmlspecialchars($current_avatar); ?>" id="avatarPreview" alt="Ảnh đại diện">
                            <form action="../controllers/doctor_actions.php" method="POST" enctype="multipart/form-data">
                                <label for="fileUpload" class="custom-file-upload"><i class="fas fa-camera"></i> Chọn ảnh mới</label>
                                <input id="fileUpload" type="file" name="avatar" accept="image/*" style="display:none;" onchange="previewImage(this)">
                        </div>
                        <div class="profile-form">
                            <div class="form-group"><label>Họ và Tên:</label><input type="text" name="ten_day_du" class="form-control" value="<?php echo htmlspecialchars($my_profile['ten_day_du']); ?>" required></div>
                            <div class="form-group" style="display:flex; gap:20px;">
                                <div style="flex:1"><label>Số điện thoại:</label><input type="text" name="sdt" class="form-control" value="<?php echo htmlspecialchars($my_profile['sdt']); ?>" required></div>
                                <div style="flex:1"><label>Email:</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($my_profile['email'] ?? ''); ?>" placeholder="Nhập email..."></div>
                            </div>
                            <div class="form-group"><label>Chuyên khoa:</label><input type="text" name="chuyen_khoa" class="form-control" value="<?php echo htmlspecialchars($my_profile['chuyen_khoa']); ?>" required></div>
                            
                            <div style="margin-top:20px; display:flex; gap:10px;">
                                <button type="submit" name="update_profile" class="btn btn-primary">Lưu Thay Đổi</button>
                                <button type="button" class="btn" style="background:#666; color:#fff;" onclick="document.getElementById('changePassModal').style.display='block'">Đổi Mật Khẩu</button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="regModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:850px;">
            <div class="modal-header"><h3>Đăng Ký Lịch Làm Việc</h3><span class="close-btn" onclick="document.getElementById('regModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <div class="modal-body">
                    <div class="week-selector-container">
                        <label for="weekSelector" style="font-weight:bold;">Chọn tuần muốn đăng ký:</label>
                        <select id="weekSelector" class="form-control" style="width:auto; display:inline-block;" onchange="updateRegModalDates()">
                            <?php foreach($week_options as $opt): ?>
                                <option value="<?php echo $opt['value']; ?>"><?php echo $opt['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p style="color:#0046ad; background:#e3f2fd; padding:10px; border-radius:5px;">
                        <i class="fas fa-info-circle"></i> Vui lòng tích chọn các ca bạn có thể làm việc. Dữ liệu sẽ tự động cập nhật ngày theo tuần bạn chọn ở trên.
                    </p>
                    <table class="reg-table" style="width:100%; border-collapse:collapse;">
                        <tr style="background:#f5f5f5">
                            <th style="width:100px;">Ca / Ngày</th>
                            <?php for($i=0; $i<7; $i++): ?>
                                <th id="reg-th-<?php echo $i; ?>">Loading...</th>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <td><strong>SÁNG</strong><br>(08:00 - 12:00)</td>
                            <?php for($i=0; $i<7; $i++): ?>
                                <td><input type="checkbox" id="reg-chk-sang-<?php echo $i; ?>" value="1"></td>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <td><strong>CHIỀU</strong><br>(13:00 - 17:00)</td>
                            <?php for($i=0; $i<7; $i++): ?>
                                <td><input type="checkbox" id="reg-chk-chieu-<?php echo $i; ?>" value="1"></td>
                            <?php endfor; ?>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer"><button type="submit" name="submit_schedule_request" class="btn btn-primary">Gửi Đăng Ký</button></div>
            </form>
        </div>
    </div>

    <div id="addApptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Thêm Lịch Hẹn Mới</h3><span class="close-btn" onclick="document.getElementById('addApptModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <div class="modal-body">
                    <div class="alert-info" style="background:#e3f2fd; color:#0d47a1; padding:10px; border-radius:5px; margin-bottom:15px; font-size:0.9em;">
                        <i class="fas fa-info-circle"></i> <strong>Lưu ý:</strong><br>
                        - Nếu đặt vào ngày <strong>có ca trực</strong>: Lịch được xác nhận ngay.<br>
                        - Nếu đặt vào ngày <strong>không có ca</strong>: Cần Admin duyệt.
                    </div>
                    <div class="form-group">
                        <label>Chọn Bệnh nhân (Đã có hồ sơ):</label>
                        <select name="id_benhnhan" class="form-control" required>
                            <?php foreach ($patients as $p): ?><option value="<?php echo $p['id_benhnhan']; ?>"><?php echo $p['ten_day_du']; ?> - <?php echo $p['sdt']; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Dịch vụ:</label>
                        <select name="id_dichvu" class="form-control">
                            <?php foreach ($services as $s): ?><option value="<?php echo $s['id_dichvu']; ?>"><?php echo $s['ten_dich_vu']; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; gap:10px;">
                        <div style="flex:1"><label>Ngày:</label><input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>"></div>
                        <div style="flex:1"><label>Giờ:</label><input type="time" name="time" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="add_appointment" class="btn btn-primary">Lưu Lịch</button></div>
            </form>
        </div>
    </div>

    <div id="leaveRequestModal" class="modal">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header"><h3><i class="fas fa-bed"></i> Đăng Ký Nghỉ Phép</h3><span class="close-btn" onclick="document.getElementById('leaveRequestModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <div class="modal-body">
                    <div class="form-group"><label>Chọn ngày nghỉ:</label><input type="date" name="leave_date" id="leaveDateInput" class="form-control" required min="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>Ca nghỉ:</label><select name="leave_shift" id="leaveShiftSelect" class="form-control" disabled><option value="">-- Vui lòng chọn ngày --</option></select></div>
                    <div class="form-group"><label>Lý do:</label><textarea name="leave_reason" class="form-control" rows="3" required placeholder="VD: Bận việc gia đình..."></textarea></div>
                    <div id="leaveAlert" style="display:none; padding:10px; border-radius:5px; font-size:0.9em; margin-top:10px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="document.getElementById('leaveRequestModal').style.display='none'">Hủy</button>
                    <button type="submit" name="request_leave" id="btnSubmitLeave" class="btn btn-warning" disabled>Gửi Yêu Cầu</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="walkinModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-walking"></i> Tiếp Nhận Khách Mới</h3><span class="close-btn" onclick="document.getElementById('walkinModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <div class="modal-body">
                    <p style="color:#666; font-size:0.9em; margin-bottom:15px;">Dành cho khách chưa có hồ sơ. Hệ thống sẽ tạo tài khoản mới và xác nhận lịch ngay.</p>
                    <div class="form-group"><label>Họ và Tên:</label><input type="text" name="ten_day_du" class="form-control" required placeholder="Nhập tên khách..."></div>
                    <div class="form-group" style="display:flex; gap:10px;">
                        <div style="flex:1"><label>Số điện thoại:</label><input type="text" name="sdt" class="form-control" required placeholder="SĐT liên hệ"></div>
                        <div style="flex:1"><label>Email (Tùy chọn):</label><input type="email" name="email" class="form-control" placeholder="Để gửi kết quả"></div>
                    </div>
                    <div class="form-group"><label>Dịch vụ khám:</label><select name="id_dichvu" class="form-control" required>
                        <?php foreach ($services as $s): ?><option value="<?php echo $s['id_dichvu']; ?>"><?php echo $s['ten_dich_vu']; ?></option><?php endforeach; ?>
                    </select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="document.getElementById('walkinModal').style.display='none'">Hủy</button>
                    <button type="submit" name="add_walkin" class="btn btn-primary" style="background:#ff9800;">Tạo & Khám Ngay</button>
                </div>
            </form>
        </div>
    </div>

    <div id="medicalRecordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Cập Nhật Bệnh Án</h3><span class="close-btn" onclick="document.getElementById('medicalRecordModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <input type="hidden" name="id_lichhen" id="record_appt_id">
                <div class="modal-body">
                    <p style="margin-bottom:10px;">Bệnh nhân: <strong id="record_patient_name" style="color:var(--primary); font-size:1.2em;"></strong></p>
                    <div class="form-group"><label>Chẩn đoán / Kết quả khám:</label><textarea name="chuan_doan" class="form-control" rows="4" required placeholder="Ví dụ: Sâu răng hàm dưới R36..."></textarea></div>
                    <div class="form-group"><label>Ghi chú / Đơn thuốc / Hẹn tái khám:</label><textarea name="ghi_chu" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" name="save_medical_record" class="btn btn-primary">Lưu & Hoàn Tất</button></div>
            </form>
        </div>
    </div>

    <div id="changePassModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header"><h3>Đổi Mật Khẩu</h3><span class="close-btn" onclick="document.getElementById('changePassModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <div class="modal-body">
                    <div class="form-group"><label>Mật khẩu cũ:</label><input type="password" name="current_pass" class="form-control" required></div>
                    <div class="form-group"><label>Mật khẩu mới:</label><input type="password" name="new_pass" class="form-control" required></div>
                    <div class="form-group"><label>Nhập lại:</label><input type="password" name="confirm_pass" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="submit" name="change_password" class="btn btn-primary">Lưu</button></div>
            </form>
        </div>
    </div>

    <script src="../assets/js/bacsi.js"></script>
    <script>
        // --- 1. JS XỬ LÝ LỊCH LÀM VIỆC DYNAMIC ---
        let currentWeekOffset = 0;

        async function loadSchedule(offset) {
            currentWeekOffset = offset;
            document.getElementById('currentWeekLabel').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tải...';
            try {
                const response = await fetch(`../controllers/get_weekly_schedule.php?offset=${currentWeekOffset}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    document.getElementById('currentWeekLabel').innerText = data.week_label;
                    for (let i = 1; i <= 7; i++) {
                        const dayName = ["", "Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7", "CN"][i];
                        document.getElementById(`th-${i}`).innerHTML = `${dayName}<br><small>${data.dates[i].display}</small>`;
                    }
                    for (let i = 1; i <= 7; i++) {
                        const cellSang = document.getElementById(`cell-Sang-${i}`);
                        const sVal = data.schedule['Sang'][i];
                        cellSang.innerHTML = (sVal === 'active') ? '<div class="work-slot-badge morning">TRỰC</div>' : (sVal === 'leave' ? '<div class="work-slot-badge leave-slot">NGHỈ</div>' : '<div class="empty-slot">-</div>');
                        
                        const cellChieu = document.getElementById(`cell-Chieu-${i}`);
                        const cVal = data.schedule['Chieu'][i];
                        cellChieu.innerHTML = (cVal === 'active') ? '<div class="work-slot-badge afternoon">TRỰC</div>' : (cVal === 'leave' ? '<div class="work-slot-badge leave-slot">NGHỈ</div>' : '<div class="empty-slot">-</div>');
                    }
                } else { alert('Lỗi tải lịch: ' + data.message); }
            } catch (error) { console.error('Error:', error); document.getElementById('currentWeekLabel').innerText = 'Lỗi kết nối!'; }
        }

        function changeWeek(newOffset) {
            currentWeekOffset = (newOffset === 0) ? 0 : currentWeekOffset + newOffset;
            loadSchedule(currentWeekOffset);
        }

        document.addEventListener("DOMContentLoaded", function() { loadSchedule(0); });

        // --- 2. JS CẬP NHẬT MODAL ĐĂNG KÝ (CHECK TRÙNG LỊCH) ---
        async function updateRegModalDates() {
            const selector = document.getElementById('weekSelector');
            const offset = parseInt(selector.value);
            // Offset trong modal bắt đầu từ Next Monday (0)
            // Offset trong API get_weekly_schedule tính từ Current Monday (0)
            // => Cần +1 để khớp tuần
            const apiOffset = offset + 1; 

            const serverNextMonday = "<?php echo $js_next_monday; ?>";
            let baseDate = new Date(serverNextMonday);
            baseDate.setDate(baseDate.getDate() + (offset * 7));
            const daysOfWeek = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'];
            
            // Fetch dữ liệu lịch của tuần ĐƯỢC CHỌN để check trùng
            let scheduleData = { Sang: {}, Chieu: {} };
            try {
                const res = await fetch(`../controllers/get_weekly_schedule.php?offset=${apiOffset}`);
                const data = await res.json();
                if(data.status === 'success') { scheduleData = data.schedule; }
            } catch(e) { console.error("Lỗi check trùng:", e); }

            for (let i = 0; i < 7; i++) {
                let currentDay = new Date(baseDate);
                currentDay.setDate(baseDate.getDate() + i);
                let d = String(currentDay.getDate()).padStart(2, '0');
                let m = String(currentDay.getMonth() + 1).padStart(2, '0');
                let y = currentDay.getFullYear();
                let dateStr = `${y}-${m}-${d}`;
                let showStr = `${d}/${m}`;
                
                const th = document.getElementById(`reg-th-${i}`);
                if(th) th.innerHTML = `${daysOfWeek[i]}<br><small>${showStr}</small>`;
                
                const phpIndex = i + 1; // 1=Mon, 7=Sun

                // Hàm helper setup checkbox
                const setupCheckbox = (chkId, shiftType) => {
                    const chk = document.getElementById(chkId);
                    if(chk) {
                        chk.name = `reg[${dateStr}][${shiftType}]`;
                        chk.checked = false;
                        chk.disabled = false;
                        const oldLbl = chk.parentElement.querySelector('.status-label');
                        if(oldLbl) oldLbl.remove();

                        // Check status từ API
                        const status = scheduleData[shiftType] ? scheduleData[shiftType][phpIndex] : null;
                        
                        if (status === 'active') {
                            chk.checked = true;
                            chk.disabled = true; // Disable không cho chọn lại
                            const span = document.createElement('span');
                            span.className = 'status-label';
                            span.style.color = 'green';
                            span.innerText = '(Đã có)';
                            chk.parentElement.appendChild(span);
                        } else if (status === 'leave') {
                            chk.disabled = true;
                            const span = document.createElement('span');
                            span.className = 'status-label';
                            span.style.color = 'red';
                            span.innerText = '(Nghỉ)';
                            chk.parentElement.appendChild(span);
                        }
                    }
                };

                setupCheckbox(`reg-chk-sang-${i}`, 'Sang');
                setupCheckbox(`reg-chk-chieu-${i}`, 'Chieu');
            }
        }

        // Tự động chạy khi mở trang (để load tuần đầu tiên trong modal)
        document.addEventListener("DOMContentLoaded", function() {
            updateRegModalDates();
        });

        // --- CÁC JS CŨ GIỮ NGUYÊN ---
        <?php if(isset($_GET['section']) && $_GET['section'] == 'dashboard'): ?>
            document.addEventListener("DOMContentLoaded", function() {
                showSection('dashboard', document.querySelector('.menu-link.active'));
            });
        <?php endif; ?>

        function switchTabContent(tabId, btn) {
            document.querySelectorAll('.content-section').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            if (document.getElementById(tabId).classList.contains('tab-content')) {
                document.getElementById(tabId).classList.add('active');
            }
            if (btn.classList.contains('menu-link')) {
                document.querySelectorAll('.menu-link').forEach(el => el.classList.remove('active'));
                btn.classList.add('active');
            } else if (btn.classList.contains('tab-btn')) {
                document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.content-section').forEach(e => e.classList.remove('active'));
                document.getElementById('appointments').classList.add('active');
            }
        }

        function showSection(id, el) {
            document.querySelectorAll('.content-section').forEach(e => e.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelectorAll('.menu-link').forEach(e => e.classList.remove('active'));
            if (el) el.classList.add('active');
        }

        function toggleUserMenu() { document.getElementById("userMenuDropdown").classList.toggle("show"); }

        function openMedicalModal(id, name) {
            document.getElementById('record_appt_id').value = id;
            document.getElementById('record_patient_name').innerText = name;
            document.getElementById('medicalRecordModal').style.display = 'block';
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) { document.getElementById('avatarPreview').src = e.target.result; }
                reader.readAsDataURL(input.files[0]);
            }
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) e.target.style.display = 'none';
            if (!e.target.closest('.user-profile')) {
                let dropdowns = document.getElementsByClassName("user-dropdown");
                for (let i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
                }
            }
        }

        // Logic Modal Xin Nghỉ (Giữ nguyên)
        document.addEventListener("DOMContentLoaded", function() {
            const leaveDateInput = document.getElementById('leaveDateInput');
            const leaveShiftSelect = document.getElementById('leaveShiftSelect');
            const btnSubmitLeave = document.getElementById('btnSubmitLeave');
            const leaveAlert = document.getElementById('leaveAlert');
            const doctorId = <?php echo $doctor_id; ?>;

            if (leaveDateInput) {
                leaveDateInput.addEventListener('change', async function() {
                    const dateVal = this.value;
                    if (!dateVal) return;
                    leaveShiftSelect.innerHTML = '<option>Đang kiểm tra...</option>';
                    leaveShiftSelect.disabled = true;
                    btnSubmitLeave.disabled = true;
                    leaveAlert.style.display = 'none';
                    try {
                        const res = await fetch(`../controllers/get_shifts_by_date.php?id=${doctorId}&date=${dateVal}`);
                        const data = await res.json();
                        leaveShiftSelect.innerHTML = '';
                        if (data.status === 'has_schedule' && data.shifts.length > 0) {
                            data.shifts.forEach(shift => {
                                let opt = document.createElement('option');
                                opt.value = shift.value; opt.text = shift.label;
                                leaveShiftSelect.appendChild(opt);
                            });
                            leaveShiftSelect.disabled = false; btnSubmitLeave.disabled = false;
                            leaveAlert.innerHTML = '<i class="fas fa-check-circle"></i> Có thể gửi yêu cầu.';
                            leaveAlert.style.background = '#e8f5e9'; leaveAlert.style.color = '#2e7d32'; leaveAlert.style.display = 'block';
                        } else if (data.status === 'on_leave') {
                            let opt = document.createElement('option'); opt.text = "Đã đăng ký nghỉ"; leaveShiftSelect.appendChild(opt);
                            leaveAlert.innerHTML = '<i class="fas fa-info-circle"></i> Đã đăng ký nghỉ rồi.';
                            leaveAlert.style.background = '#fff3e0'; leaveAlert.style.color = '#ef6c00'; leaveAlert.style.display = 'block';
                        } else {
                            let opt = document.createElement('option'); opt.text = "Không có ca"; leaveShiftSelect.appendChild(opt);
                            leaveAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Không có lịch làm việc, không cần xin nghỉ!';
                            leaveAlert.style.background = '#ffebee'; leaveAlert.style.color = '#c62828'; leaveAlert.style.display = 'block';
                        }
                    } catch (e) { console.error(e); leaveAlert.innerText = "Lỗi kết nối!"; leaveAlert.style.display = 'block'; }
                });
            }
        });
    </script>
</body>
</html>