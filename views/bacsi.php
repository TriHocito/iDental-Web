<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: dangnhap.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

$stmt_profile = $conn->prepare("SELECT * FROM bacsi WHERE id_bacsi = ?");
$stmt_profile->execute([$doctor_id]);
$my_profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);
$current_avatar = !empty($my_profile['link_anh_dai_dien']) ? $my_profile['link_anh_dai_dien'] : 'https://i.pravatar.cc/150?img=11';

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

$sql_my_sch = "SELECT * FROM lichlamviec 
               WHERE id_bacsi = ? AND ngay_hieu_luc BETWEEN ? AND ?";
$stmt_my_sch = $conn->prepare($sql_my_sch);
$stmt_my_sch->execute([$doctor_id, $monday_date, $sunday_date]);
$my_schedules = $stmt_my_sch->fetchAll(PDO::FETCH_ASSOC);

$my_map = ['Sang' => array_fill(1, 7, false), 'Chieu' => array_fill(1, 7, false)];
foreach ($my_schedules as $sch) {
    $day = date('N', strtotime($sch['ngay_hieu_luc']));
    $hour = date('H', strtotime($sch['gio_bat_dau']));
    $shift = ($hour < 12) ? 'Sang' : 'Chieu';
    $my_map[$shift][$day] = true;
}

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
                       AND (
                           (HOUR(lh.ngay_gio_hen) < 12 AND llv.gio_bat_dau = '08:00:00') OR 
                           (HOUR(lh.ngay_gio_hen) >= 12 AND llv.gio_bat_dau = '13:00:00')
                       )
                      ) as has_shift
                      FROM lichhen lh 
                      JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan 
                      JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                      WHERE lh.id_bacsi = $doctor_id 
                      AND lh.trang_thai = 'cho_xac_nhan' 
                      ORDER BY lh.ngay_gio_hen ASC";

$pending = $conn->query($sql_pending_check)->fetchAll(PDO::FETCH_ASSOC);
$upcoming = $conn->query("$base_sql AND lh.trang_thai = 'da_xac_nhan' AND lh.ngay_gio_hen >= NOW() ORDER BY lh.ngay_gio_hen ASC")->fetchAll(PDO::FETCH_ASSOC);
$completed = $conn->query("$base_sql AND lh.trang_thai = 'hoan_thanh' ORDER BY lh.ngay_gio_hen DESC")->fetchAll(PDO::FETCH_ASSOC);
$history = $conn->query("$base_sql ORDER BY lh.ngay_gio_hen DESC")->fetchAll(PDO::FETCH_ASSOC);
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
        .profile-container { display: flex; gap: 30px; }
        .profile-avatar { flex: 1; text-align: center; }
        .profile-avatar img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .profile-form { flex: 3; }
        .custom-file-upload { display: inline-block; padding: 6px 12px; cursor: pointer; background: #eee; border-radius: 4px; margin-top: 10px; font-size: 0.9em; }
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
                    <button id="sidebarToggle" style="display: none; background: none; border: none; font-size: 22px; cursor: pointer; color: #555;">
                        <i class="fas fa-bars"></i>
                    </button>
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
                    <h2>Xin chào, Dr. <?php echo htmlspecialchars($my_profile['ten_day_du']); ?>!</h2>
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
                                <?php if (count($appointments_today) > 0): ?>
                                    <?php foreach ($appointments_today as $appt): ?>
                                        <tr>
                                            <td><strong><?php echo date('H:i', strtotime($appt['ngay_gio_hen'])); ?></strong></td>
                                            <td><?php echo htmlspecialchars($appt['ten_bn']); ?></td>
                                            <td><?php echo htmlspecialchars($appt['ten_dichvu']); ?></td>
                                            <td><span class="badge bg-confirmed"><?php echo htmlspecialchars($appt['trang_thai']); ?></span></td>
                                            <td><button class="btn btn-primary" onclick="openMedicalModal(<?php echo $appt['id_lichhen']; ?>, '<?php echo $appt['ten_bn']; ?>')"><i class="fas fa-file-medical"></i> Bệnh án</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center;">Hôm nay không có lịch hẹn.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="my-schedule" class="content-section">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h2><i class="fas fa-calendar-alt"></i> Lịch Làm Việc Của Tôi</h2>
                        <button class="btn btn-warning" onclick="document.getElementById('leaveRequestModal').style.display='block'"><i class="fas fa-user-clock"></i> Xin nghỉ phép</button>
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
                                    <td class="shift-label"><strong>SÁNG</strong></td>
                                    <?php for ($i = 1; $i <= 7; $i++): ?>
                                        <td>
                                            <?php if ($my_map['Sang'][$i]): ?>
                                                <div class="work-slot-badge morning">TRỰC</div>
                                            <?php else: ?> 
                                                <div class="empty-slot">-</div> 
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="shift-label"><strong>CHIỀU</strong></td>
                                    <?php for ($i = 1; $i <= 7; $i++): ?>
                                        <td>
                                            <?php if ($my_map['Chieu'][$i]): ?>
                                                <div class="work-slot-badge afternoon">TRỰC</div>
                                            <?php else: ?> 
                                                <div class="empty-slot">-</div> 
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
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
                        <button class="tab-btn" onclick="switchTabContent('tab-all', this)">Tất cả</button>
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
                                                <a href="../controllers/reject_appointment.php?id=<?php echo $row['id_lichhen']; ?>" class="btn btn-danger" onclick="return confirm('Hủy lịch này?')">Hủy</a>
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
                                            <td>
                                                <?php if($row['has_shift'] > 0): ?>
                                                    <span class="badge bg-pending">Chờ xác nhận</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background:#607d8b; color:white;">Lịch Đặc Biệt</span>
                                                    <div style="font-size:0.8em; color:red; margin-top:2px;">Chờ Admin xếp lịch</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($row['has_shift'] > 0): ?>
                                                    <a href="../controllers/doctor_actions.php?action=approve_appointment&id=<?php echo $row['id_lichhen']; ?>"
                                                       class="btn btn-success" onclick="return confirm('Bạn có chắc chắn muốn nhận lịch này?')">
                                                        <i class="fas fa-check"></i> Duyệt
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn" style="background:#ccc; cursor:not-allowed; color:#666;" disabled title="Cần Admin tạo lịch làm việc trước"><i class="fas fa-lock"></i> Duyệt</button>
                                                <?php endif; ?>

                                                <a href="../controllers/doctor_actions.php?action=reject_appointment&id=<?php echo $row['id_lichhen']; ?>"
                                                   class="btn btn-danger" onclick="return confirm('Từ chối lịch hẹn này?')">
                                                    <i class="fas fa-times"></i> Hủy
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($pending)): ?><tr><td colspan="5" style="text-align:center; color:#999;">Không có lịch hẹn chờ duyệt.</td></tr><?php endif; ?>
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

                    <div id="tab-all" class="tab-content">
                        <div class="table-container">
                            <table class="data-table">
                                <thead><tr><th>Thời gian</th><th>Bệnh nhân</th><th>Trạng thái</th></tr></thead>
                                <tbody>
                                    <?php foreach ($history as $row): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['ngay_gio_hen'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['ten_bn']); ?></td>
                                            <td><?php echo $row['trang_thai']; ?></td>
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
                                <div style="flex:1"><label>Số điện thoại (Đăng nhập):</label><input type="text" name="sdt" class="form-control" value="<?php echo htmlspecialchars($my_profile['sdt']); ?>" required></div>
                                <div style="flex:1"><label>Chuyên khoa:</label><input type="text" name="chuyen_khoa" class="form-control" value="<?php echo htmlspecialchars($my_profile['chuyen_khoa']); ?>" required></div>
                            </div>
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

    <div id="addApptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Thêm Lịch Hẹn Mới</h3><span class="close-btn" onclick="document.getElementById('addApptModal').style.display='none'">&times;</span></div>
            <form action="../controllers/doctor_actions.php" method="POST">
                <div class="modal-body">
                    <div class="alert-info" style="background:#e3f2fd; color:#0d47a1; padding:10px; border-radius:5px; margin-bottom:15px; font-size:0.9em;">
                        <i class="fas fa-info-circle"></i> <strong>Lưu ý:</strong><br>
                        - Nếu đặt vào ngày <strong>có ca trực</strong>: Lịch được xác nhận ngay.<br>
                        - Nếu đặt vào ngày <strong>không có ca</strong>: Cần Admin duyệt (và bạn sẽ phải trực bổ sung).
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

        function toggleUserMenu() {
            document.getElementById("userMenuDropdown").classList.toggle("show");
        }

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
                                opt.value = shift.value;
                                opt.text = shift.label;
                                leaveShiftSelect.appendChild(opt);
                            });
                            leaveShiftSelect.disabled = false;
                            btnSubmitLeave.disabled = false;
                            
                            leaveAlert.innerHTML = '<i class="fas fa-check-circle"></i> Có thể gửi yêu cầu.';
                            leaveAlert.style.background = '#e8f5e9'; 
                            leaveAlert.style.color = '#2e7d32'; 
                            leaveAlert.style.display = 'block';
                            
                        } else if (data.status === 'on_leave') {
                            let opt = document.createElement('option'); opt.text = "Đã đăng ký nghỉ";
                            leaveShiftSelect.appendChild(opt);
                            
                            leaveAlert.innerHTML = '<i class="fas fa-info-circle"></i> Bạn đã đăng ký nghỉ ngày này rồi.';
                            leaveAlert.style.background = '#fff3e0'; 
                            leaveAlert.style.color = '#ef6c00'; 
                            leaveAlert.style.display = 'block';
                            
                        } else {
                            let opt = document.createElement('option'); opt.text = "Không có ca làm việc";
                            leaveShiftSelect.appendChild(opt);
                            
                            leaveAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Bạn không có lịch làm việc ngày này, không cần xin nghỉ!';
                            leaveAlert.style.background = '#ffebee'; 
                            leaveAlert.style.color = '#c62828'; 
                            leaveAlert.style.display = 'block';
                        }
                    } catch (e) {
                        console.error(e);
                        leaveAlert.innerText = "Lỗi kết nối!"; 
                        leaveAlert.style.display = 'block';
                    }
                });
            }
        });
    </script>
</body>
</html>