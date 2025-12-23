<?php
// src/views/admin.php
session_start();
require '../config/db_connect.php'; 

// 1. Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dangnhap.php"); exit();
}

// --- LẤY DỮ LIỆU CƠ BẢN ---
$doctors = $conn->query("SELECT * FROM bacsi ORDER BY id_bacsi DESC")->fetchAll(PDO::FETCH_ASSOC);
$patients = $conn->query("SELECT * FROM benhnhan ORDER BY id_benhnhan DESC")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT * FROM quantrivien")->fetchAll(PDO::FETCH_ASSOC);
$services = $conn->query("SELECT * FROM dichvu ORDER BY id_dichvu DESC")->fetchAll(PDO::FETCH_ASSOC);
$beds = $conn->query("SELECT * FROM giuongbenh ORDER BY id_giuongbenh ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- LẤY DANH SÁCH LỊCH HẸN ---
$base_sql_admin = "SELECT lh.*, bn.ten_day_du AS ten_bn, bn.sdt, dv.ten_dich_vu, bs.ten_day_du AS ten_bs 
                   FROM lichhen lh 
                   JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan 
                   JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                   LEFT JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi";

$pending_appts = $conn->query("$base_sql_admin WHERE lh.trang_thai = 'cho_xac_nhan' ORDER BY lh.ngay_gio_hen ASC")->fetchAll(PDO::FETCH_ASSOC);
$upcoming_appts = $conn->query("$base_sql_admin WHERE lh.trang_thai = 'da_xac_nhan' AND lh.ngay_gio_hen >= NOW() ORDER BY lh.ngay_gio_hen ASC")->fetchAll(PDO::FETCH_ASSOC);
$completed_appts = $conn->query("$base_sql_admin WHERE lh.trang_thai = 'hoan_thanh' ORDER BY lh.ngay_gio_hen DESC")->fetchAll(PDO::FETCH_ASSOC);
$cancelled_appts = $conn->query("$base_sql_admin WHERE lh.trang_thai = 'huy' ORDER BY lh.ngay_gio_hen DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_appts = $conn->query("$base_sql_admin ORDER BY lh.ngay_gio_hen DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- LẤY DANH SÁCH YÊU CẦU NGHỈ (PHÂN LOẠI) ---
// 1. Chờ duyệt
$leaves_pending = $conn->query("SELECT y.*, b.ten_day_du FROM yeucaunghi y JOIN bacsi b ON y.id_bacsi = b.id_bacsi WHERE y.trang_thai = 'cho_duyet' ORDER BY y.ngay_tao ASC")->fetchAll(PDO::FETCH_ASSOC);
// 2. Tất cả lịch sử nghỉ
$leaves_all = $conn->query("SELECT y.*, b.ten_day_du, q.ten_day_du as nguoi_duyet 
                            FROM yeucaunghi y 
                            JOIN bacsi b ON y.id_bacsi = b.id_bacsi 
                            LEFT JOIN quantrivien q ON y.id_quantrivien_duyet = q.id_quantrivien 
                            ORDER BY y.ngay_tao DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- [MỚI] LẤY DANH SÁCH LỊCH TRÙNG CA NGHỈ ---
// 1. Lịch xung đột cần xử lý (Trạng thái: Đã/Chờ xác nhận)
$sql_conflict = "SELECT lh.*, bn.ten_day_du AS ten_bn, bn.sdt, dv.ten_dich_vu, bs.ten_day_du AS ten_bs, yc.ly_do 
                 FROM lichhen lh
                 JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan
                 JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                 JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
                 JOIN yeucaunghi yc ON lh.id_bacsi = yc.id_bacsi 
                 WHERE yc.trang_thai = 'da_duyet' 
                 AND DATE(lh.ngay_gio_hen) = yc.ngay_nghi
                 AND lh.trang_thai IN ('da_xac_nhan', 'cho_xac_nhan')
                 AND (
                    (yc.ca_nghi = 'Sang' AND HOUR(lh.ngay_gio_hen) < 12) OR 
                    (yc.ca_nghi = 'Chieu' AND HOUR(lh.ngay_gio_hen) >= 12)
                 )";
$conflicts = $conn->query($sql_conflict)->fetchAll(PDO::FETCH_ASSOC);

// 2. Lịch sử đã xử lý (Đã hủy do trùng lịch nghỉ)
$sql_conflict_handled = "SELECT lh.*, bn.ten_day_du AS ten_bn, dv.ten_dich_vu, bs.ten_day_du AS ten_bs, yc.ngay_nghi
                         FROM lichhen lh
                         JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan
                         JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                         JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
                         JOIN yeucaunghi yc ON lh.id_bacsi = yc.id_bacsi 
                         WHERE yc.trang_thai = 'da_duyet' 
                         AND DATE(lh.ngay_gio_hen) = yc.ngay_nghi
                         AND lh.trang_thai = 'huy'
                         AND (
                            (yc.ca_nghi = 'Sang' AND HOUR(lh.ngay_gio_hen) < 12) OR 
                            (yc.ca_nghi = 'Chieu' AND HOUR(lh.ngay_gio_hen) >= 12)
                         )
                         ORDER BY lh.ngay_gio_hen DESC";
$conflicts_handled = $conn->query($sql_conflict_handled)->fetchAll(PDO::FETCH_ASSOC);

$status_labels = [
    'cho_xac_nhan' => '<span class="status-badge status-pending">Chờ duyệt</span>',
    'da_xac_nhan'  => '<span class="status-badge status-confirmed">Đã xác nhận</span>',
    'hoan_thanh'   => '<span class="status-badge status-done">Hoàn thành</span>',
    'huy'          => '<span class="status-badge" style="background:#ffebee; color:#c62828;">Đã Hủy</span>',
    'dang_kham'    => '<span class="status-badge" style="background:#fff3e0; color:#ef6c00;">Đang khám</span>'
];

// Thống kê
$count_docs = count($doctors);
$count_pats = count($patients);
$count_apps = $conn->query("SELECT COUNT(*) FROM lichhen WHERE ngay_gio_hen >= CURDATE()")->fetchColumn();

// --- XỬ LÝ LỊCH TUẦN ---
$week_offset = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;
if ($week_offset == 0) {
    $monday_timestamp = strtotime('monday this week');
} else {
    $sign = ($week_offset > 0) ? "+" : "";
    $monday_timestamp = strtotime("monday this week $sign$week_offset weeks");
}
$monday_date = date('Y-m-d', $monday_timestamp);
$sunday_date = date('Y-m-d', strtotime($monday_date . ' +6 days'));

$week_list = [];
for ($i = -5; $i <= 10; $i++) { 
    $ts_mon = strtotime("monday this week " . ($i > 0 ? "+$i" : $i) . " weeks");
    $ts_sun = strtotime("sunday this week " . ($i > 0 ? "+$i" : $i) . " weeks");
    $label = "Tuần " . date('W', $ts_mon) . " [" . date('d/m', $ts_mon) . " - " . date('d/m', $ts_sun) . "]";
    if ($i == 0) $label .= " (Hiện tại)";
    $week_list[$i] = $label;
}

$sql_matrix = "SELECT llv.*, bs.ten_day_du 
               FROM lichlamviec llv 
               JOIN bacsi bs ON llv.id_bacsi = bs.id_bacsi 
               WHERE llv.ngay_hieu_luc BETWEEN ? AND ? 
               ORDER BY llv.gio_bat_dau ASC";
$stmt_matrix = $conn->prepare($sql_matrix);
$stmt_matrix->execute([$monday_date, $sunday_date]);
$schedule_data = $stmt_matrix->fetchAll(PDO::FETCH_ASSOC);

$schedule_map = [
    'Sang' => [1=>[], 2=>[], 3=>[], 4=>[], 5=>[], 6=>[], 7=>[]],
    'Chieu' => [1=>[], 2=>[], 3=>[], 4=>[], 5=>[], 6=>[], 7=>[]]
];
foreach ($schedule_data as $row) {
    $day_index = date('N', strtotime($row['ngay_hieu_luc'])); 
    $hour = date('H', strtotime($row['gio_bat_dau']));
    $shift = ($hour < 12) ? 'Sang' : 'Chieu';
    $schedule_map[$shift][$day_index][] = $row['ten_day_du'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDental - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .tab-header { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .tab-btn { padding: 8px 15px; border: none; background: #eee; cursor: pointer; font-weight: 600; border-radius: 5px; color: #666; }
        .tab-btn.active { background: var(--primary); color: white; }
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .day-selector label { margin-right: 15px; cursor: pointer; display: inline-block; padding: 5px; }
        .day-selector input { margin-right: 5px; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="logo"><i class="fas fa-tooth"></i> iDental</div>
        <nav>
            <div class="nav-section">Quản trị</div>
            <a class="menu-link active" onclick="showSection('overview')"><i class="fas fa-chart-pie"></i> Tổng quan</a>
            <a class="menu-link" onclick="showSection('doctors')"><i class="fas fa-user-md"></i> QL Bác sĩ</a>
            <a class="menu-link" onclick="showSection('accounts')"><i class="fas fa-users-cog"></i> QL Tài khoản</a>
            <a class="menu-link" onclick="showSection('admins')"><i class="fas fa-user-shield"></i> QL Admin</a>
            
            <div class="nav-section">Yêu cầu & Xử lý</div>
            <a class="menu-link" onclick="showSection('leave-requests')">
                <i class="fas fa-envelope-open-text"></i> Duyệt Nghỉ Phép
                <?php if(count($leaves_pending) > 0): ?><span style="color:red; font-weight:bold;">(<?php echo count($leaves_pending); ?>)</span><?php endif; ?>
            </a>
            <a class="menu-link" onclick="showSection('conflict-appts')">
                <i class="fas fa-exclamation-triangle"></i> Lịch Cần Xử Lý
                <?php if(count($conflicts) > 0): ?><span style="color:red; font-weight:bold;">(<?php echo count($conflicts); ?>)</span><?php endif; ?>
            </a>

            <div class="nav-section">Vận hành</div>
            <a class="menu-link" onclick="showSection('appointments')"><i class="fas fa-calendar-check"></i> Xử lý Lịch hẹn</a>
            <a class="menu-link" onclick="showSection('schedule')"><i class="fas fa-clock"></i> Lịch làm việc</a>
            <a class="menu-link" onclick="showSection('services')"><i class="fas fa-list-ul"></i> QL Dịch vụ</a>
        </nav>
    </div>
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>
    <div class="main-content-wrapper">
       <header class="header">
    <div style="display: flex; align-items: center; gap: 15px;">
        <button id="sidebarToggle" style="display: none; background: none; border: none; font-size: 22px; cursor: pointer; color: #555;">
            <i class="fas fa-bars"></i>
        </button>
        <div style="font-weight:600;">Khu vực Quản Trị Viên</div>
    </div>

    <div class="user-profile" onclick="toggleUserMenu()">
        <img src="https://i.pravatar.cc/150?img=12" class="avatar" alt="Admin">
        <div><div style="font-weight: 600; font-size: 0.9em;"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Admin'); ?></div></div>
        <div id="userMenuDropdown" class="user-dropdown">
            <a href="#" onclick="openModal('profileModal')"><i class="fas fa-id-card"></i> Hồ sơ cá nhân</a>
            <a href="../controllers/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </div>
    </header>

        <div class="main-content">
            <div id="overview" class="content-section active">
                <h2><i class="fas fa-chart-line"></i> Tổng Quan Hệ Thống</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;"><i class="fas fa-user-md"></i></div>
                        <div><h2><?php echo $count_docs; ?></h2><small>Tổng Bác sĩ</small></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e8f5e9; color: #2e7d32;"><i class="fas fa-calendar-check"></i></div>
                        <div><h2><?php echo $count_apps; ?></h2><small>Lịch hẹn sắp tới</small></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fff3e0; color: #f57c00;"><i class="fas fa-users"></i></div>
                        <div><h2><?php echo $count_pats; ?></h2><small>Tổng Bệnh nhân</small></div>
                    </div>
                </div>
            </div>

            <div id="leave-requests" class="content-section">
                <h2><i class="fas fa-user-clock"></i> Quản Lý Nghỉ Phép</h2>
                
                <div class="tab-header">
                    <button class="tab-btn active" onclick="switchTabContent('tab-leave-pending', this)">
                        Chờ duyệt <span style="color:red">(<?php echo count($leaves_pending); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTabContent('tab-leave-all', this)">Tất cả hồ sơ</button>
                </div>

                <div id="tab-leave-pending" class="tab-content active">
                    <p style="color:#666; margin-bottom:15px;">Duyệt yêu cầu sẽ tự động gửi mail thông báo hoãn lịch cho các bệnh nhân bị ảnh hưởng.</p>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Bác sĩ</th><th>Ngày nghỉ</th><th>Ca</th><th>Lý do</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                <?php if(count($leaves_pending) > 0): ?>
                                    <?php foreach($leaves_pending as $l): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($l['ten_day_du']); ?></strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($l['ngay_nghi'])); ?></td>
                                        <td><?php echo ($l['ca_nghi']=='Sang')?'Sáng':'Chiều'; ?></td>
                                        <td><?php echo htmlspecialchars($l['ly_do']); ?></td>
                                        <td>
                                            <a href="../controllers/admin_actions.php?action=approve_leave&id=<?php echo $l['id_yeucau']; ?>" 
                                               class="btn btn-primary" style="padding:5px 10px; font-size:12px;"
                                               onclick="return confirm('XÁC NHẬN DUYỆT? Hệ thống sẽ gửi mail cho các khách hàng bị trùng lịch!')">Duyệt</a>
                                            <a href="../controllers/admin_actions.php?action=reject_leave&id=<?php echo $l['id_yeucau']; ?>" 
                                               class="btn btn-danger" style="padding:5px 10px; font-size:12px;">Từ chối</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center;">Không có yêu cầu chờ duyệt.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-leave-all" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Ngày tạo</th><th>Bác sĩ</th><th>Ngày nghỉ</th><th>Lý do</th><th>Trạng thái</th><th>Người duyệt</th></tr></thead>
                            <tbody>
                                <?php foreach($leaves_all as $l): 
                                    $sttColor = ($l['trang_thai']=='da_duyet') ? 'green' : (($l['trang_thai']=='tu_choi') ? 'red' : 'orange');
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/y', strtotime($l['ngay_tao'])); ?></td>
                                    <td><?php echo htmlspecialchars($l['ten_day_du']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($l['ngay_nghi'])); ?> (<?php echo $l['ca_nghi']; ?>)</td>
                                    <td><?php echo htmlspecialchars($l['ly_do']); ?></td>
                                    <td><span style="color:<?php echo $sttColor; ?>; font-weight:bold;"><?php echo $l['trang_thai']; ?></span></td>
                                    <td><?php echo htmlspecialchars($l['nguoi_duyet'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="conflict-appts" class="content-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Xử Lý Lịch Hẹn Xung Đột</h2>
                
                <div class="tab-header">
                    <button class="tab-btn active" onclick="switchTabContent('tab-conflict-active', this)">
                        Cần xử lý ngay <span style="color:red">(<?php echo count($conflicts); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTabContent('tab-conflict-history', this)">Lịch sử đã hủy</button>
                </div>

                <div id="tab-conflict-active" class="tab-content active">
                    <div class="alert-info" style="background:#fff3e0; color:#e65100; padding:10px; border-radius:5px; margin-bottom:15px;">
                        <i class="fas fa-info-circle"></i> Vui lòng liên hệ khách hàng để: <strong>Chuyển bác sĩ khác</strong> (Nếu khách đồng ý) hoặc <strong>Hủy lịch</strong> (Nếu khách không đồng ý).
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Khách hàng</th><th>SĐT</th><th>Ngày giờ</th><th>Bác sĩ (Nghỉ)</th><th>Tác vụ</th></tr></thead>
                            <tbody>
                                <?php if(count($conflicts) > 0): ?>
                                    <?php foreach($conflicts as $c): ?>
                                    <tr style="background:#fff8e1;">
                                        <td><?php echo htmlspecialchars($c['ten_bn']); ?></td>
                                        <td><?php echo htmlspecialchars($c['sdt']); ?></td>
                                        <td><strong style="color:#d32f2f;"><?php echo date('H:i d/m/Y', strtotime($c['ngay_gio_hen'])); ?></strong></td>
                                        <td style="text-decoration:line-through; color:#999;"><?php echo htmlspecialchars($c['ten_bs']); ?></td>
                                        <td>
                                            <button class="btn btn-primary" style="font-size:12px; padding:5px 10px;" 
                                                    onclick="openSwitchDoctorModal(<?php echo $c['id_lichhen']; ?>, '<?php echo $c['ten_bn']; ?>')">
                                                <i class="fas fa-exchange-alt"></i> Chuyển BS
                                            </button>
                                            <a href="../controllers/admin_actions.php?action=cancel_conflict_appt&id=<?php echo $c['id_lichhen']; ?>" 
                                               class="btn btn-danger" style="font-size:12px; padding:5px 10px;"
                                               onclick="return confirm('XÁC NHẬN: Hủy lịch và gửi mail xin lỗi?')">
                                               <i class="fas fa-times"></i> Hủy & Mail
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center;">Tuyệt vời! Không có lịch hẹn nào bị xung đột.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-conflict-history" class="tab-content">
                    <p style="color:#666;">Danh sách các lịch hẹn đã bị hủy do bác sĩ nghỉ phép.</p>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Ngày hẹn</th><th>Khách hàng</th><th>Dịch vụ</th><th>Bác sĩ (Nghỉ)</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                                <?php foreach($conflicts_handled as $ch): ?>
                                <tr>
                                    <td><?php echo date('H:i d/m/Y', strtotime($ch['ngay_gio_hen'])); ?></td>
                                    <td><?php echo htmlspecialchars($ch['ten_bn']); ?></td>
                                    <td><?php echo htmlspecialchars($ch['ten_dich_vu']); ?></td>
                                    <td><?php echo htmlspecialchars($ch['ten_bs']); ?></td>
                                    <td><span class="badge bg-danger">Đã hủy (Xung đột)</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="doctors" class="content-section">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h2>Danh Sách Bác Sĩ</h2>
                    <button class="btn btn-primary" onclick="openModal('doctorModal')"><i class="fas fa-plus"></i> Thêm Bác Sĩ</button>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Ảnh</th><th>Họ và Tên</th><th>Chuyên khoa</th><th>Liên hệ</th><th>Trạng thái</th><th>Hành động</th></tr></thead>
                        <tbody>
                            <?php foreach ($doctors as $doc): 
                                $avatar = $doc['link_anh_dai_dien'] ? $doc['link_anh_dai_dien'] : 'https://i.pravatar.cc/150?u=' . $doc['id_bacsi'];
                            ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($avatar); ?>" class="avatar"></td>
                                    <td><strong><?php echo htmlspecialchars($doc['ten_day_du']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($doc['chuyen_khoa']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['sdt']); ?></td>
                                    <td><span class="status-badge status-confirmed">Hoạt động</span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-icon-clear text-blue" title="Sửa" onclick='openEditDoctorModal(<?php echo json_encode($doc); ?>)'><i class="fas fa-user-pen"></i></button>
                                            <button class="btn-icon-clear text-orange" title="Reset Pass" onclick="openResetPassDocModal(<?php echo $doc['id_bacsi']; ?>, '<?php echo $doc['ten_day_du']; ?>')">
                                               <i class="fas fa-user-shield"></i>
                                            </button>
                                            <a href="../controllers/admin_actions.php?action=delete_doctor&id=<?php echo $doc['id_bacsi']; ?>" onclick="return confirm('CẢNH BÁO: Xóa bác sĩ này?');" class="btn-icon-clear text-red" title="Xóa"><i class="fas fa-trash-can"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="accounts" class="content-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><i class="fas fa-users-cog"></i> Quản Lý Tài Khoản Bệnh Nhân</h2>
                </div>
                <div class="table-container">
                    <h3 style="color: #f57c00; margin-top: 20px;"><i class="fas fa-user-injured"></i> Tài khoản Bệnh nhân</h3>
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Họ Tên</th><th>SĐT (Login)</th><th>Email</th><th>Lịch sử</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php foreach ($patients as $pat): ?>
                                <tr>
                                    <td>BN<?php echo $pat['id_benhnhan']; ?></td>
                                    <td><?php echo htmlspecialchars($pat['ten_day_du']); ?></td>
                                    <td><?php echo htmlspecialchars($pat['sdt']); ?></td>
                                    <td><?php echo htmlspecialchars($pat['email']); ?></td>
                                    <td>
                                        <button class="btn" style="background:#e3f2fd; color:#1565c0; font-size:12px; padding:4px 8px;" 
                                            onclick="viewPatientHistory(<?php echo $pat['id_benhnhan']; ?>, '<?php echo $pat['ten_day_du']; ?>')">
                                            <i class="fas fa-history"></i> Xem
                                        </button>
                                    </td>
                                    <td><a href="../controllers/admin_actions.php?action=delete_patient&id=<?php echo $pat['id_benhnhan']; ?>" class="btn" style="background:#ffebee; color:red; font-size:12px; padding:5px 10px;" onclick="return confirm('Xóa bệnh nhân này?')">Xóa</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="admins" class="content-section">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h2>Danh Sách Quản Trị Viên</h2>
                    <button class="btn btn-primary" onclick="openModal('adminModal')"><i class="fas fa-user-plus"></i> Thêm Admin</button>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Username</th><th>Họ Tên</th><th>Ngày tạo</th><th>Hành động</th></tr></thead>
                        <tbody>
                            <?php foreach($admins as $ad): ?>
                            <tr>
                                <td><?php echo $ad['id_quantrivien']; ?></td>
                                <td><?php echo htmlspecialchars($ad['ten_dang_nhap']); ?></td>
                                <td><?php echo htmlspecialchars($ad['ten_day_du']); ?></td>
                                <td><?php echo $ad['ngay_tao']; ?></td>
                                <td>
                                    <?php if($ad['id_quantrivien'] != 1): ?>
                                        <a href="../controllers/admin_actions.php?action=delete_admin&id=<?php echo $ad['id_quantrivien']; ?>" 
                                           class="btn-icon" style="color:red;" 
                                           onclick="return confirm('Bạn chắc chắn muốn xóa Admin này?')"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                        <span style="color:#aaa; font-style:italic;">(Mặc định)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="appointments" class="content-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2><i class="fas fa-calendar-check"></i> Quản Lý Lịch Hẹn</h2>
                    <div style="display:flex; gap:10px;">
                        <button class="btn btn-primary" onclick="openModal('adminAddApptModal')"><i class="fas fa-plus"></i> Đặt Lịch (BN Cũ)</button>
                        <button class="btn" style="background:#ff9800; color:white;" onclick="openModal('adminWalkinModal')"><i class="fas fa-user-clock"></i> Khách Vãng Lai</button>
                    </div>
                </div>

                <div class="tab-header">
                    <button class="tab-btn active" onclick="switchTabContent('tab-pending', this)">Chờ duyệt <span style="color:red">(<?php echo count($pending_appts); ?>)</span></button>
                    <button class="tab-btn" onclick="switchTabContent('tab-upcoming', this)">Sắp tới</button>
                    <button class="tab-btn" onclick="switchTabContent('tab-completed', this)">Đã hoàn thành</button>
                    <button class="tab-btn" onclick="switchTabContent('tab-cancelled', this)">Đã hủy</button>
                    <button class="tab-btn" onclick="switchTabContent('tab-all', this)">Tất cả</button>
                </div>
                
                <div id="tab-pending" class="tab-content active">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Khách hàng</th><th>SĐT</th><th>Dịch vụ</th><th>Thời gian</th><th>Bác sĩ</th><th>Tác vụ</th></tr></thead>
                            <tbody>
                                <?php foreach($pending_appts as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['ten_bn']); ?></td>
                                    <td><?php echo htmlspecialchars($req['sdt']); ?></td>
                                    <td><?php echo htmlspecialchars($req['ten_dich_vu']); ?></td>
                                    <td><?php echo date('H:i d/m/Y', strtotime($req['ngay_gio_hen'])); ?></td>
                                    <td><?php echo htmlspecialchars($req['ten_bs'] ?? 'Ngẫu nhiên'); ?></td>
                                    <td>
                                        <a href="../controllers/admin_actions.php?action=approve_appointment&id=<?php echo $req['id_lichhen']; ?>" class="btn" style="background:#e8f5e9; color:green; padding:5px 10px; text-decoration:none;" onclick="return confirm('Duyệt lịch và gửi mail?')">Duyệt</a>
                                        <a href="../controllers/admin_actions.php?action=reject_appointment&id=<?php echo $req['id_lichhen']; ?>" class="btn" style="background:#ffebee; color:red; padding:5px 10px; text-decoration:none;" onclick="return confirm('Hủy lịch này?')">Hủy</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-upcoming" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Thời gian</th><th>Bác sĩ</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Trạng thái</th><th>Tác vụ</th></tr></thead>
                            <tbody>
                                <?php foreach($upcoming_appts as $row): ?>
                                <tr>
                                    <td style="color:var(--primary); font-weight:bold;"><?php echo date('H:i d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bs']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_dich_vu']); ?></td>
                                    <td><span class="badge bg-confirmed">Đã xác nhận</span></td>
                                    <td><a href="../controllers/admin_actions.php?action=reject_appointment&id=<?php echo $row['id_lichhen']; ?>" class="btn" style="background:#ffebee; color:red; padding:5px 10px;" onclick="return confirm('Hủy lịch này?')">Hủy</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-completed" class="tab-content">
                    <div class="table-container">
                         <table class="data-table">
                            <thead><tr><th>Thời gian</th><th>Bác sĩ</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                                <?php foreach($completed_appts as $row): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bs']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_dich_vu']); ?></td>
                                    <td><span class="badge bg-confirmed">Hoàn thành</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                 <div id="tab-cancelled" class="tab-content">
                    <div class="table-container">
                         <table class="data-table">
                            <thead><tr><th>Thời gian</th><th>Bác sĩ</th><th>Bệnh nhân</th><th>Dịch vụ</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                                <?php foreach($cancelled_appts as $row): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bs']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_dich_vu']); ?></td>
                                    <td><span class="badge bg-danger">Đã hủy</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-all" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Thời gian</th><th>Bác sĩ</th><th>Bệnh nhân</th><th>Trạng thái</th></tr></thead>
                            <tbody>
                                <?php foreach($all_appts as $row): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($row['ngay_gio_hen'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bs']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                    <td>
                                        <?php echo isset($status_labels[$row['trang_thai']]) ? $status_labels[$row['trang_thai']] : $row['trang_thai']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

           <div id="schedule" class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h2><i class="fas fa-clock"></i> Lịch Làm Việc Tuần</h2>
        <button class="btn btn-primary" onclick="openModal('shiftModal')"><i class="fas fa-calendar-plus"></i> Thêm Ca Trực (Hàng Loạt)</button>
    </div>

    <div class="table-container schedule-container">
        <table class="table-schedule">
            <thead>
                <tr>
                    <th style="width: 120px;">Ca / Thứ</th>
                    <th>Thứ 2</th>
                    <th>Thứ 3</th>
                    <th>Thứ 4</th>
                    <th>Thứ 5</th>
                    <th>Thứ 6</th>
                    <th>Thứ 7</th>
                    <th>CN</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="shift-label"><strong>SÁNG</strong><br><small>08:00 - 12:00</small></td>
                    <?php for($i=1; $i<=7; $i++): ?>
                        <td>
                            <?php if(!empty($schedule_map['Sang'][$i])): ?>
                                <?php foreach($schedule_map['Sang'][$i] as $docName): ?>
                                    <div class="doc-badge doc-morning"><?php echo htmlspecialchars($docName); ?></div>
                                <?php endforeach; ?>
                            <?php else: ?> <span>-</span> <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
                <tr>
                    <td class="shift-label"><strong>CHIỀU</strong><br><small>13:00 - 17:00</small></td>
                    <?php for($i=1; $i<=7; $i++): ?>
                        <td>
                            <?php if(!empty($schedule_map['Chieu'][$i])): ?>
                                <?php foreach($schedule_map['Chieu'][$i] as $docName): ?>
                                    <div class="doc-badge doc-afternoon"><?php echo htmlspecialchars($docName); ?></div>
                                <?php endforeach; ?>
                            <?php else: ?> <span>-</span> <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            </tbody>
        </table>
    </div>
</div>

            <div id="services" class="content-section">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <h2>Quản Lý Dịch Vụ</h2>
                    <button class="btn btn-primary" onclick="openServiceModal('add_service')"><i class="fas fa-plus"></i> Thêm Dịch Vụ</button>
                </div>
                <div class="table-container">
                    <table class="data-table" id="serviceTable">
                        <thead><tr><th>Tên Dịch Vụ</th><th>Mô tả</th><th>Chi phí</th><th>Thời gian</th><th>Hành động</th></tr></thead>
                        <tbody>
                            <?php foreach($services as $sv): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sv['ten_dich_vu']); ?></strong></td>
                                <td><small><?php echo htmlspecialchars(substr($sv['mo_ta'], 0, 50)) . '...'; ?></small></td>
                                <td style="color:green;font-weight:bold;"><?php echo number_format($sv['gia_tien']); ?> đ</td>
                                <td><?php echo $sv['thoi_gian_phut']; ?> phút</td>
                                <td class="action-btns">
                                    <button class="btn-icon-clear text-blue" onclick='openServiceModal("edit_service", <?php echo json_encode($sv); ?>)'><i class="fas fa-user-pen"></i></button>
                                    <a href="../controllers/admin_actions.php?action=delete_service&id=<?php echo $sv['id_dichvu']; ?>" onclick="return confirm('Xóa dịch vụ này?')" class="btn-icon-clear text-red"><i class="fas fa-trash-can"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="switchDoctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Chuyển Lịch Cho Bác Sĩ Khác</h3><span class="close-btn" onclick="closeModal('switchDoctorModal')">Đóng</span></div>
        <form action="../controllers/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="switch_doctor">
            <input type="hidden" name="id_lichhen" id="switchApptId">
            <div class="modal-body">
                <p>Khách hàng: <strong id="switchPatientName" style="color:var(--primary);"></strong></p>
                <div class="form-group">
                    <label>Chọn Bác sĩ thay thế:</label>
                    <select name="new_doctor_id" class="form-control" required>
                        <?php foreach($doctors as $d): ?>
                            <option value="<?php echo $d['id_bacsi']; ?>">Dr. <?php echo $d['ten_day_du']; ?> (<?php echo $d['chuyen_khoa']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Giờ khám mới (Tùy chọn):</label>
                    <input type="datetime-local" name="new_datetime" class="form-control">
                    <small style="color:#666;">* Để trống nếu khách đồng ý giữ nguyên giờ cũ.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Lưu Thay Đổi & Gửi Mail</button>
            </div>
        </form>
    </div>
</div>

<div id="doctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Thêm Bác Sĩ</h3><span class="close-btn" onclick="closeModal('doctorModal')">Đóng</span></div>
        <form action="../controllers/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="add_doctor">
            <div class="modal-body">
                <div class="form-group"><label>Họ tên:</label><input type="text" name="ten_day_du" class="form-control" required></div>
                <div class="form-group"><label>Chuyên khoa:</label><input type="text" name="chuyen_khoa" class="form-control"></div>
                <div class="form-group"><label>SĐT (Login):</label><input type="text" name="sdt" class="form-control" required></div>
                <div class="form-group"><label>Mật khẩu:</label><input type="text" name="mat_khau" class="form-control" value="123456" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Lưu</button></div>
        </form>
    </div>
</div>

<div id="profileModal" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header"><h3>Hồ Sơ Quản Trị Viên</h3><span class="close-btn" onclick="closeModal('profileModal')">Đóng</span></div>
        <div class="modal-body">
            <div style="text-align:center; margin-bottom:15px;">
                <img src="https://i.pravatar.cc/150?img=12" style="width:80px;height:80px;border-radius:50%;border:3px solid #eee;">
                <h4><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Admin'); ?></h4>
                <p style="color:#666">@<?php echo htmlspecialchars($_SESSION['ten_dang_nhap'] ?? 'admin'); ?></p>
            </div>
            <hr>
            <form action="../controllers/admin_actions.php" method="POST">
                <input type="hidden" name="action" value="change_self_pass">
                <div class="form-group"><input type="password" name="new_pass" class="form-control" placeholder="Mật khẩu mới" required></div>
                <button type="submit" class="btn btn-primary" style="width:100%">Cập nhật mật khẩu</button>
            </form>
        </div>
    </div>
</div>

<div id="editDoctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Cập Nhật Thông Tin Bác Sĩ</h3><span class="close-btn" onclick="closeModal('editDoctorModal')">Đóng</span></div>
        <form action="../controllers/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="edit_doctor">
            <input type="hidden" name="id_bacsi" id="editDocId">
            <div class="modal-body">
                <div class="form-group"><label>Họ tên:</label><input type="text" name="ten_day_du" id="editDocName" class="form-control" required></div>
                <div class="form-group"><label>SĐT (Login):</label><input type="text" name="sdt" id="editDocPhone" class="form-control" required></div>
                <div class="form-group"><label>Chuyên khoa:</label><input type="text" name="chuyen_khoa" id="editDocSpec" class="form-control"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Lưu thay đổi</button></div>
        </form>
    </div>
</div>

<div id="resetPassDoctorModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3>Reset Mật Khẩu Bác Sĩ</h3><span class="close-btn" onclick="closeModal('resetPassDoctorModal')">Đóng</span></div>
        <div class="modal-body">
            <form action="../controllers/admin_actions.php" method="POST">
                <input type="hidden" name="action" value="reset_pass_doctor">
                <input type="hidden" name="id" id="resetDocId">
                <p>Đặt mật khẩu mới cho: <strong id="resetDocName"></strong></p>
                <div class="form-group">
                    <input type="text" name="new_pass" class="form-control" placeholder="Nhập mật khẩu mới" required>
                </div>
                <div class="modal-footer" style="padding:0; border:none; margin-top:15px;">
                    <button type="submit" class="btn btn-primary" style="width:100%">Xác nhận đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="adminModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Thêm Quản Trị Viên Mới</h3>
            <span class="close-btn" onclick="closeModal('adminModal')">Đóng</span>
        </div>
        <form action="../controllers/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="add_admin">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tên đăng nhập:</label>
                    <input type="text" name="username" class="form-control" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Họ và tên đầy đủ:</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Tạo tài khoản</button>
            </div>
        </form>
    </div>
</div>
<div id="patientHistoryModal" class="modal">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header"><h3>Lịch Sử Khám: <span id="histPatName"></span></h3><span class="close-btn" onclick="closeModal('patientHistoryModal')">Đóng</span></div>
        <div class="modal-body">
            <table class="data-table">
                <thead><tr><th>Ngày giờ</th><th>Dịch vụ</th><th>Bác sĩ</th><th>Trạng thái</th></tr></thead>
                <tbody id="histBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="serviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 id="serviceModalTitle">Thêm Dịch Vụ</h3><span class="close-btn" onclick="closeModal('serviceModal')">Đóng</span></div>
        <div class="modal-body">
            <form action="../controllers/admin_actions.php" method="POST">
                <input type="hidden" name="action" id="serviceAction" value="add_service">
                <input type="hidden" name="id" id="serviceId">
                <div class="form-group"><label>Tên Dịch vụ:</label><input type="text" name="name" id="servName" class="form-control" required></div>
                <div class="form-group"><label>Mô tả ngắn:</label><textarea name="desc" id="servDesc" class="form-control"></textarea></div>
                <div class="form-group" style="display:flex; gap:20px;">
                    <div style="flex:1"><label>Chi phí (VNĐ):</label><input type="number" name="price" id="servPrice" class="form-control" required></div>
                    <div style="flex:1"><label>Thời gian (phút):</label><input type="number" name="time" id="servTime" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Lưu</button></div>
            </form>
        </div>
    </div>
</div>

<div id="shiftModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header"><h3><i class="fas fa-calendar-plus"></i> Thêm Lịch Làm Việc (Hàng Loạt)</h3><span class="close-btn" onclick="closeModal('shiftModal')">Đóng</span></div>
        <div class="modal-body">
            <form id="bulkScheduleForm">
                <div class="form-group" style="display:flex; gap:15px;">
                    <div style="flex:1">
                        <label>Chọn Bác sĩ:</label>
                        <select id="bulkDoc" class="form-control" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach($doctors as $doc): ?>
                                <option value="<?php echo $doc['id_bacsi']; ?>"><?php echo htmlspecialchars($doc['ten_day_du']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1">
                        <label>Chọn Ghế/Giường:</label>
                        <select id="bulkBed" class="form-control" required>
                            <option value="">-- Chọn --</option>
                            <?php foreach($beds as $bed): ?>
                                <option value="<?php echo $bed['id_giuongbenh']; ?>"><?php echo htmlspecialchars($bed['ten_giuong']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Từ ngày:</label>
                        <input type="date" id="bulkFromDate" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Đến ngày:</label>
                        <input type="date" id="bulkToDate" class="form-control" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                </div>

                <div class="form-group day-selector">
                    <label style="display:block; margin-bottom:5px;">Chọn thứ trong tuần:</label>
                    <label><input type="checkbox" name="bulkDay" value="1" checked> Thứ 2</label>
                    <label><input type="checkbox" name="bulkDay" value="2" checked> Thứ 3</label>
                    <label><input type="checkbox" name="bulkDay" value="3" checked> Thứ 4</label>
                    <label><input type="checkbox" name="bulkDay" value="4" checked> Thứ 5</label>
                    <label><input type="checkbox" name="bulkDay" value="5" checked> Thứ 6</label>
                    <label><input type="checkbox" name="bulkDay" value="6"> Thứ 7</label>
                    <label><input type="checkbox" name="bulkDay" value="7"> CN</label>
                </div>

                <div class="form-group">
                    <label>Chọn Ca:</label>
                    <div style="display:flex; gap:20px;">
                        <label><input type="checkbox" id="bulkMorning" value="Sang"> Sáng (08:00-12:00)</label>
                        <label><input type="checkbox" id="bulkAfternoon" value="Chieu"> Chiều (13:00-17:00)</label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeModal('shiftModal')">Hủy</button>
            <button type="button" class="btn btn-primary" onclick="handleBulkSchedule()">Lưu Lịch (Ajax)</button>
        </div>
    </div>
</div>

<div id="adminAddApptModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Admin: Thêm Lịch Hẹn</h3><span class="close-btn" onclick="closeModal('adminAddApptModal')">Đóng</span></div>
        <form action="../controllers/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="add_appointment_admin">
            <div class="modal-body">
                <div class="form-group">
                    <label>Chọn Bệnh nhân:</label>
                    <select name="id_benhnhan" class="form-control" required>
                        <?php foreach($patients as $p): ?>
                            <option value="<?php echo $p['id_benhnhan']; ?>"><?php echo $p['ten_day_du']; ?> - <?php echo $p['sdt']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Chọn Bác sĩ:</label> 
                    <select name="id_bacsi" class="form-control" required>
                        <?php foreach($doctors as $d): ?>
                            <option value="<?php echo $d['id_bacsi']; ?>"><?php echo $d['ten_day_du']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Dịch vụ:</label>
                    <select name="id_dichvu" class="form-control" required>
                        <?php foreach($services as $s): ?><option value="<?php echo $s['id_dichvu']; ?>"><?php echo $s['ten_dich_vu']; ?> (<?php echo $s['thoi_gian_phut']; ?>p)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex; gap:10px;">
                    <div style="flex:1">
                        <label>Ngày:</label>
                        <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div style="flex:1">
                        <label>Ca Khám:</label>
                        <select name="shift" class="form-control" required>
                            <option value="Sang">Sáng (08:00 - 12:00)</option>
                            <option value="Chieu">Chiều (13:00 - 17:00)</option>
                        </select>
                    </div>
                </div>
                <div style="font-size:0.9em; color:#666; margin-top:5px;">
                    * Hệ thống sẽ tự động tính giờ bắt đầu dựa trên các lịch hẹn đã có trong ca.
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Lưu Lịch</button></div>
        </form>
    </div>
</div>

<div id="adminWalkinModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Admin: Tiếp Nhận Khách Vãng Lai</h3><span class="close-btn" onclick="closeModal('adminWalkinModal')">Đóng</span></div>
        <form action="../controllers/admin_actions.php" method="POST">
            <input type="hidden" name="action" value="add_walkin_admin">
            <div class="modal-body">
                <div class="form-group"><label>Họ tên:</label><input type="text" name="ten_day_du" class="form-control" required></div>
                <div class="form-group"><label>SĐT:</label><input type="text" name="sdt" class="form-control" required></div>
                <div class="form-group"><label>Email (Để nhận TK):</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label>Chọn Bác sĩ:</label>
                    <select name="id_bacsi" class="form-control" required>
                        <?php foreach($doctors as $d): ?><option value="<?php echo $d['id_bacsi']; ?>"><?php echo $d['ten_day_du']; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Dịch vụ:</label>
                    <select name="id_dichvu" class="form-control" required>
                        <?php foreach($services as $s): ?><option value="<?php echo $s['id_dichvu']; ?>"><?php echo $s['ten_dich_vu']; ?></option><?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Ca Khám (Hôm nay):</label>
                    <select name="shift" class="form-control" required>
                        <option value="Sang">Sáng (08:00 - 12:00)</option>
                        <option value="Chieu">Chiều (13:00 - 17:00)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary" style="background:#ff9800;">Tạo & Xác Nhận Ngay</button></div>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
<script>
    // Hàm mở modal chuyển bác sĩ
    function openSwitchDoctorModal(id, name) {
        document.getElementById('switchApptId').value = id;
        document.getElementById('switchPatientName').innerText = name;
        openModal('switchDoctorModal');
    }
    function openServiceModal(mode, data = null) {
        const modal = document.getElementById('serviceModal');
        const title = document.getElementById('serviceModalTitle');
        const actionInput = document.getElementById('serviceAction');
        const idInput = document.getElementById('serviceId');
        
        // Reset form về rỗng trước khi gán
        document.getElementById('servName').value = '';
        document.getElementById('servDesc').value = '';
        document.getElementById('servPrice').value = '';
        document.getElementById('servTime').value = '';
        if (mode === 'edit_service' && data) {
            title.innerText = 'Cập Nhật Dịch Vụ';
            actionInput.value = 'edit_service'; // Action gửi lên PHP
            idInput.value = data.id_dichvu;
            
            // Đổ dữ liệu cũ vào form
            document.getElementById('servName').value = data.ten_dich_vu;
            document.getElementById('servDesc').value = data.mo_ta || ''; // Xử lý nếu mô tả null
            document.getElementById('servPrice').value = parseInt(data.gia_tien); // Đảm bảo là số
            document.getElementById('servTime').value = data.thoi_gian_phut;
        } else {
            // Trường hợp thêm mới (add_service)
            title.innerText = 'Thêm Dịch Vụ Mới';
            actionInput.value = 'add_service';
            idInput.value = '';
        }
        
        modal.style.display = 'block';
    }
    // Switch tab helper
    function switchTabContent(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.getElementById(tabId).style.display = 'block';
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.style.background = '#eee'; el.style.color = '#666';
        });
        btn.style.background = 'var(--primary)'; btn.style.color = 'white';
    }
    
    // Các hàm JS cũ
    function openEditDoctorModal(data) {
        document.getElementById('editDocId').value = data.id_bacsi;
        document.getElementById('editDocName').value = data.ten_day_du;
        document.getElementById('editDocPhone').value = data.sdt;
        document.getElementById('editDocSpec').value = data.chuyen_khoa;
        openModal('editDoctorModal');
    }

    function openResetPassDocModal(id, name) {
        document.getElementById('resetDocId').value = id;
        document.getElementById('resetDocName').innerText = name;
        openModal('resetPassDoctorModal');
    }

    // [BỔ SUNG QUAN TRỌNG] Hàm xem lịch sử bệnh nhân
    async function viewPatientHistory(id, name) {
        const modal = document.getElementById('patientHistoryModal');
        const title = document.getElementById('histPatName');
        const tbody = document.getElementById('histBody');
        
        if(modal) modal.style.display = 'block';
        if(title) title.innerText = name;
        if(tbody) tbody.innerHTML = '<tr><td colspan="4" style="text-align:center">Đang tải dữ liệu...</td></tr>';

        try {
            const res = await fetch(`../controllers/admin_actions.php?action=get_patient_history&id=${id}`);
            if (!res.ok) throw new Error("Server error");
            const data = await res.json();
            
            if(tbody) {
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#666;">Chưa có lịch sử khám.</td></tr>';
                } else {
                    data.forEach(row => {
                        let color = '#666';
                        let textStatus = row.trang_thai;
                        if(row.trang_thai === 'hoan_thanh') { color = 'green'; textStatus = 'Hoàn thành'; }
                        else if(row.trang_thai === 'huy') { color = 'red'; textStatus = 'Đã hủy'; }
                        else if(row.trang_thai === 'da_xac_nhan') { color = 'blue'; textStatus = 'Đã xác nhận'; }
                        else { color = 'orange'; textStatus = 'Chờ duyệt'; }

                        const dateObj = new Date(row.ngay_gio_hen);
                        const dateStr = dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + ' ' + dateObj.toLocaleDateString('vi-VN');

                        const tr = `<tr>
                                <td>${dateStr}</td>
                                <td>${row.ten_dich_vu || 'Chưa rõ'}</td>
                                <td>${row.ten_bs || '-'}</td>
                                <td><span style="color:${color}; font-weight:600;">${textStatus}</span></td>
                            </tr>`;
                        tbody.insertAdjacentHTML('beforeend', tr);
                    });
                }
            }
        } catch (e) {
            console.error(e);
            if(tbody) tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Lỗi tải dữ liệu! Vui lòng thử lại.</td></tr>';
        }
    }

    // Hàm chuyển đổi tab content
    function switchTabContent(tabId, btn) {
        const parent = btn.parentElement;
        const allTabs = parent.parentElement.querySelectorAll('.tab-content');
        const allBtns = parent.querySelectorAll('.tab-btn');
        
        allTabs.forEach(tab => tab.classList.remove('active'));
        allBtns.forEach(b => b.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        btn.classList.add('active');
    }

    // Hàm mở modal chuyển đổi bác sĩ
    function openSwitchDoctorModal(appointmentId, patientName) {
        const modal = document.getElementById('switchDoctorModal');
        if(modal) {
            modal.style.display = 'block';
            document.getElementById('switchApptId').value = appointmentId;
            document.getElementById('switchPatientName').innerText = patientName;
        }
    }

    // Hàm đóng modal
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if(modal) modal.style.display = 'none';
    }

    // Đóng modal khi click bên ngoài
    window.onclick = function(event) {
        const switchModal = document.getElementById('switchDoctorModal');
        if(event.target === switchModal) switchModal.style.display = 'none';
    }
</script>
</body>
</html>