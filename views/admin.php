<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: dangnhap.php"); exit(); }

// 1. FETCH DATA
$doctors = $conn->query("SELECT * FROM bacsi ORDER BY id_bacsi DESC")->fetchAll(PDO::FETCH_ASSOC);
$services = $conn->query("SELECT * FROM dichvu ORDER BY id_dichvu DESC")->fetchAll(PDO::FETCH_ASSOC);
$patients = $conn->query("SELECT * FROM benhnhan ORDER BY id_benhnhan DESC")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT * FROM quantrivien")->fetchAll(PDO::FETCH_ASSOC);
$beds = $conn->query("SELECT * FROM giuongbenh")->fetchAll(PDO::FETCH_ASSOC);

$pending_appts_count = $conn->query("SELECT COUNT(*) FROM lichhen WHERE trang_thai = 'cho_xac_nhan'")->fetchColumn();
$pending_leaves = $conn->query("SELECT y.*, b.ten_day_du FROM yeucaunghi y JOIN bacsi b ON y.id_bacsi = b.id_bacsi WHERE y.trang_thai = 'cho_duyet'")->fetchAll(PDO::FETCH_ASSOC);

// --- [FIX LOGIC] XUNG ĐỘT LỊCH (Chỉ báo lỗi nếu trùng Ca) ---
// Logic cũ của bạn sai ở chỗ nó JOIN theo ngày mà không check ca, dẫn đến nghỉ sáng mà chiều cũng bị dính conflict.
// Logic mới: Chỉ JOIN và báo lỗi nếu (Giờ hẹn < 12 VÀ Nghỉ Sáng) HOẶC (Giờ hẹn >= 12 VÀ Nghỉ Chiều)
$sql_conflict = "SELECT lh.*, bn.ten_day_du as ten_bn, bn.sdt, bs.ten_day_du as ten_bs, y.ca_nghi 
                 FROM lichhen lh 
                 JOIN yeucaunghi y ON lh.id_bacsi = y.id_bacsi AND DATE(lh.ngay_gio_hen) = y.ngay_nghi 
                 JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan 
                 JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi 
                 WHERE y.trang_thai = 'da_duyet' 
                 AND lh.trang_thai IN ('da_xac_nhan','cho_xac_nhan')
                 AND (
                    (HOUR(lh.ngay_gio_hen) < 12 AND y.ca_nghi = 'Sang') 
                    OR 
                    (HOUR(lh.ngay_gio_hen) >= 12 AND y.ca_nghi = 'Chieu')
                 )";
$conflicts = $conn->query($sql_conflict)->fetchAll(PDO::FETCH_ASSOC);

// 2. SCHEDULE LOGIC (Date Range 7 Days)
$start_date = $_GET['from'] ?? date('Y-m-d', strtotime('monday this week'));
$end_date = $_GET['to'] ?? date('Y-m-d', strtotime('sunday this week'));

// --- Generate Weeks for Dropdown ---
$weeks_options = [];
$current_monday = strtotime('monday this week');
// Mở rộng khoảng thời gian: 20 tuần trước và 20 tuần sau (khoảng 5 tháng)
for ($i = -20; $i <= 20; $i++) {
    $w_start = strtotime("$i weeks", $current_monday);
    $w_end = strtotime('+6 days', $w_start);
    $val_start = date('Y-m-d', $w_start);
    $val_end = date('Y-m-d', $w_end);
    
    // Hiển thị thêm năm để dễ quản lý
    $label = "Tuần " . date('d/m/Y', $w_start) . " - " . date('d/m/Y', $w_end);
    if ($i == 0) $label .= " (Hiện tại)";
    
    $weeks_options[] = ['start' => $val_start, 'end' => $val_end, 'label' => $label];
}

// Lấy lịch nghỉ phép đã duyệt để map vào lịch hiển thị
$leaves = $conn->prepare("SELECT * FROM yeucaunghi WHERE trang_thai='da_duyet' AND ngay_nghi BETWEEN ? AND ?");
$leaves->execute([$start_date, $end_date]);
$leave_data = $leaves->fetchAll(PDO::FETCH_ASSOC);
$leave_map = []; 
foreach($leave_data as $l) { 
    $leave_map[$l['ngay_nghi']][$l['ca_nghi']][] = $l['id_bacsi']; 
}

$schedule_data = $conn->prepare("SELECT llv.*, bs.ten_day_du, gb.ten_giuong FROM lichlamviec llv JOIN bacsi bs ON llv.id_bacsi = bs.id_bacsi JOIN giuongbenh gb ON llv.id_giuongbenh = gb.id_giuongbenh WHERE llv.ngay_hieu_luc BETWEEN ? AND ? ORDER BY llv.gio_bat_dau");
$schedule_data->execute([$start_date, $end_date]);
$sch_rows = $schedule_data->fetchAll(PDO::FETCH_ASSOC);

$schedule_map = ['Sang' => [], 'Chieu' => []];
$dates_arr = [];
// Tạo mảng ngày
$period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
foreach ($period as $dt) { $dates_arr[] = $dt->format('Y-m-d'); }

$day_names = ['Mon'=>'Thứ 2', 'Tue'=>'Thứ 3', 'Wed'=>'Thứ 4', 'Thu'=>'Thứ 5', 'Fri'=>'Thứ 6', 'Sat'=>'Thứ 7', 'Sun'=>'CN'];

foreach ($sch_rows as $r) {
    $d = $r['ngay_hieu_luc'];
    $shift = (date('H', strtotime($r['gio_bat_dau'])) < 12) ? 'Sang' : 'Chieu';
    
    // Format bed name
    $bed_name = $r['ten_giuong'];
    $bed_short = preg_replace('/Giường (số )?/', 'G', $bed_name);
    
    $doc_name = $r['ten_day_du'] . "<br><small>" . $bed_short . "</small>";
    $is_off = false;
    
    // Kiểm tra xem bác sĩ có nghỉ ca này không
    if (isset($leave_map[$d][$shift]) && in_array($r['id_bacsi'], $leave_map[$d][$shift])) {
        $doc_name .= " (Nghỉ)"; 
        $is_off = true;
    }
    $schedule_map[$shift][$d][] = ['name' => $doc_name, 'is_off' => $is_off];
}

// JSON Requests
$req_file = '../data/schedule_requests.json';
$schedule_requests = file_exists($req_file) ? json_decode(file_get_contents($req_file), true) : [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | iDental</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="logo"><i class="fas fa-tooth"></i> iDental Admin</div>
        <nav>
            <div class="nav-section">Tổng Quan</div>
            <a class="menu-link active" onclick="showSection('overview')"><i class="fas fa-th-large"></i> Dashboard</a>
            
            <div class="nav-section">Quản Lý</div>
            <a class="menu-link" onclick="showSection('appointments')">
                <i class="fas fa-calendar-check"></i> Lịch Hẹn
                <?php if($pending_appts_count > 0): ?><span class="badge bg-danger"><?php echo $pending_appts_count; ?></span><?php endif; ?>
            </a>
            <a class="menu-link" onclick="showSection('doctors')"><i class="fas fa-user-md"></i> Bác Sĩ</a>
            <a class="menu-link" onclick="showSection('patients')"><i class="fas fa-users"></i> Bệnh Nhân</a>
            <a class="menu-link" onclick="showSection('admins')"><i class="fas fa-user-shield"></i> Quản Trị Viên</a>
            <a class="menu-link" onclick="showSection('requests')">
                <i class="fas fa-exclamation-circle"></i> Xử Lý
                <?php if(count($pending_leaves) + count($conflicts) > 0): ?>
                    <span class="badge bg-danger"><?php echo count($pending_leaves) + count($conflicts); ?></span>
                <?php endif; ?>
            </a>
            
            <div class="nav-section">Vận Hành</div>
            <a class="menu-link" onclick="showSection('schedule')">
                <i class="fas fa-clock"></i> Lịch Làm Việc
                <?php if(!empty($schedule_requests)): ?><span class="badge bg-warning">New</span><?php endif; ?>
            </a>
            <a class="menu-link" onclick="showSection('services')"><i class="fas fa-list-ul"></i> Dịch Vụ</a>
        </nav>
    </div>
    
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <div class="main-content-wrapper">
        <header class="header">
            <div class="header-left">
                <button class="toggle-sidebar-btn"><i class="fas fa-bars"></i></button>
                <h3>Khu vực quản trị</h3>
            </div>
            <div class="user-profile" onclick="openModal('profileModal')">
                <img src="https://ui-avatars.com/api/?name=Admin&background=0D8ABC&color=fff" class="avatar">
                <div><strong><?php echo $_SESSION['fullname'] ?? 'Admin'; ?></strong></div>
            </div>
        </header>

        <div class="main-content">
            <div id="overview" class="content-section active">
                <h2>Tổng Quan</h2>
                
                <div class="search-container">
                    <div class="search-box-wrapper">
                        <input type="text" id="globalSearchPat" class="form-control" placeholder="Nhập SĐT hoặc Tên bệnh nhân...">
                        <div id="searchResult" class="search-result-dropdown"></div>
                    </div>
                    <button class="btn btn-primary" onclick="triggerSearch()"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-confirmed"><i class="fas fa-user-md" style="color:var(--primary)"></i></div>
                        <div><h1><?php echo count($doctors); ?></h1><small>Bác sĩ</small></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon bg-pending"><i class="fas fa-calendar-day" style="color:var(--warning)"></i></div>
                        <div><h1><?php echo $pending_appts_count; ?></h1><small>Lịch chờ duyệt</small></div>
                    </div>
                    <div class="stat-card" style="display:block; text-align:center;">
                         <button class="btn btn-primary" onclick="openModal('adminAddApptModal')" style="width:100%; margin-bottom:10px;"><i class="fas fa-plus"></i> Đặt lịch (BN cũ)</button>
                         <button class="btn btn-warning" onclick="openModal('adminWalkinModal')" style="width:100%;"><i class="fas fa-walking"></i> Khách vãng lai</button>
                    </div>
                </div>
            </div>

            <div id="appointments" class="content-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h2>Quản Lý Lịch Hẹn</h2>
                    <button class="btn btn-outline" onclick="toggleFilter()"><i class="fas fa-filter"></i> Bộ lọc nâng cao</button>
                </div>

                <div id="advFilter" class="filter-bar">
                    <div style="flex:1"><label>SĐT:</label><input type="text" id="filterPhone" class="form-control" placeholder="..."></div>
                    <div style="flex:1"><label>Bác sĩ:</label>
                        <select id="filterDoc" class="form-control">
                            <option value="">-- Tất cả --</option>
                            <?php foreach($doctors as $d): ?><option value="<?php echo $d['id_bacsi']; ?>"><?php echo $d['ten_day_du']; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1"><label>Từ ngày:</label><input type="date" id="filterDateFrom" class="form-control"></div>
                    <div style="flex:1"><label>Đến ngày:</label><input type="date" id="filterDateTo" class="form-control"></div>
                    <div style="width:100%; text-align:right;">
                        <button class="btn btn-primary" id="filterBtn">Áp dụng lọc</button>
                    </div>
                </div>

                <div class="tab-header">
                    <button class="tab-btn active" onclick="loadAppts('pending', this)">Chờ duyệt</button>
                    <button class="tab-btn" onclick="loadAppts('confirmed', this)">Đã xác nhận</button>
                    <button class="tab-btn" onclick="loadAppts('completed', this)">Hoàn thành</button>
                    <button class="tab-btn" onclick="loadAppts('cancelled', this)">Đã hủy</button>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Ngày giờ</th><th>Khách hàng</th><th>Dịch vụ</th><th>Bác sĩ</th><th>Trạng thái</th><th>Tác vụ</th></tr></thead>
                        <tbody id="apptTableBody">
                            <?php 
                            $pending = $conn->query("SELECT lh.*, bn.ten_day_du as ten_bn, bn.sdt, dv.ten_dich_vu, bs.ten_day_du as ten_bs FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan=bn.id_benhnhan JOIN dichvu dv ON lh.id_dichvu=dv.id_dichvu LEFT JOIN bacsi bs ON lh.id_bacsi=bs.id_bacsi WHERE lh.trang_thai='cho_xac_nhan' ORDER BY lh.ngay_gio_hen ASC")->fetchAll(PDO::FETCH_ASSOC);
                            foreach($pending as $p): ?>
                            <tr>
                                <td data-label="Ngày giờ"><?php echo date('H:i d/m/Y', strtotime($p['ngay_gio_hen'])); ?></td>
                                <td data-label="Khách hàng"><strong><?php echo $p['ten_bn']; ?></strong><br><small><?php echo $p['sdt']; ?></small></td>
                                <td data-label="Dịch vụ"><?php echo $p['ten_dich_vu']; ?></td>
                                <td data-label="Bác sĩ"><?php echo $p['ten_bs'] ?? '-'; ?></td>
                                <td data-label="Trạng thái"><span class="status-badge bg-pending">Chờ duyệt</span></td>
                                <td data-label="Tác vụ">
                                    <a href="../controllers/admin_actions.php?action=approve_appointment&id=<?php echo $p['id_lichhen']; ?>" class="btn-icon text-success"><i class="fas fa-check"></i></a>
                                    <a href="../controllers/admin_actions.php?action=reject_appointment&id=<?php echo $p['id_lichhen']; ?>" class="btn-icon text-danger"><i class="fas fa-times"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="doctors" class="content-section">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <h2>Đội Ngũ Bác Sĩ</h2>
                    <button class="btn btn-primary" onclick="openModal('doctorModal')"><i class="fas fa-plus"></i> Thêm</button>
                </div>
                <div class="doctor-card-grid">
                    <?php foreach($doctors as $doc): 
                        $isActive = isset($doc['trang_thai']) ? $doc['trang_thai'] : 1;
                        $cardClass = $isActive ? '' : 'disabled';
                    ?>
                    <div class="doctor-card <?php echo $cardClass; ?>">
                        <img src="<?php echo $doc['link_anh_dai_dien'] ?: 'https://ui-avatars.com/api/?name='.urlencode($doc['ten_day_du']); ?>" class="doc-avatar-lg">
                        <h3><?php echo htmlspecialchars($doc['ten_day_du']); ?></h3>
                        <p style="color:var(--text-light)"><?php echo htmlspecialchars($doc['chuyen_khoa']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doc['sdt']); ?></p>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:center;">
                            <button class="btn btn-outline" onclick='openEditDoctorModal(<?php echo json_encode($doc); ?>)'>Sửa</button>
                            <a href="../controllers/admin_actions.php?action=toggle_doctor_status&id=<?php echo $doc['id_bacsi']; ?>" 
                               class="btn <?php echo $isActive ? 'btn-danger' : 'btn-success'; ?>" 
                               onclick="return confirm('Bạn có chắc chắn muốn <?php echo $isActive?'vô hiệu hóa':'kích hoạt'; ?> tài khoản này?')">
                               <?php echo $isActive ? '<i class="fas fa-lock"></i> Khóa' : '<i class="fas fa-unlock"></i> Mở'; ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="patients" class="content-section">
                <h2>Quản Lý Bệnh Nhân</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Họ Tên</th><th>SĐT</th><th>Email</th><th>Trạng thái</th><th>Tác vụ</th></tr></thead>
                        <tbody>
                            <?php foreach($patients as $p): 
                                $status = isset($p['trang_thai']) ? $p['trang_thai'] : 1;
                            ?>
                            <tr>
                                <td>#<?php echo $p['id_benhnhan']; ?></td>
                                <td><?php echo htmlspecialchars($p['ten_day_du']); ?></td>
                                <td><?php echo htmlspecialchars($p['sdt']); ?></td>
                                <td><?php echo htmlspecialchars($p['email']); ?></td>
                                <td>
                                    <?php if($status == 1): ?>
                                        <span class="status-badge bg-confirmed">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="status-badge bg-cancelled">Đã khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-icon text-primary" onclick="viewPatientHistory(<?php echo $p['id_benhnhan']; ?>)" title="Xem lịch sử"><i class="fas fa-history"></i></button>
                                    <?php if($status == 1): ?>
                                        <button class="btn-icon text-danger" onclick="togglePatientStatus(<?php echo $p['id_benhnhan']; ?>, 0)" title="Khóa tài khoản"><i class="fas fa-lock"></i></button>
                                    <?php else: ?>
                                        <button class="btn-icon text-success" onclick="togglePatientStatus(<?php echo $p['id_benhnhan']; ?>, 1)" title="Mở khóa"><i class="fas fa-unlock"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="schedule" class="content-section">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <h2>Lịch Làm Việc</h2>
                    <button class="btn btn-primary" style="padding: 5px 10px; font-size: 0.9em;" onclick="openModal('addScheduleModal')"><i class="fas fa-plus"></i> Thêm Lịch</button>
                </div>
                <?php if(!empty($schedule_requests)): ?>
                <div style="margin-bottom:20px;">
                    <h4 class="text-warning">Yêu cầu đăng ký mới</h4>
                    <?php foreach($schedule_requests as $req): ?>
                    <div class="req-card">
                        <div>
                            <strong><?php echo htmlspecialchars($req['doctor_name']); ?></strong> 
                            <small>(<?php echo count($req['shifts']); ?> ca)</small>
                        </div>
                        <div>
                            <button class="btn btn-success" onclick="handleScheduleRequest('<?php echo $req['id']; ?>', 'approve')">Duyệt</button>
                            <button class="btn btn-danger" onclick="handleScheduleRequest('<?php echo $req['id']; ?>', 'reject')">Hủy</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form id="scheduleForm" method="GET" action="admin.php" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:15px;">
                    <input type="hidden" name="section" value="schedule">
                    <input type="hidden" name="from" id="schFrom" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="to" id="schTo" value="<?php echo $end_date; ?>">
                    
                    <div style="min-width: 250px;">
                        <label>Chọn tuần làm việc:</label>
                        <select id="weekSelector" class="form-control" onchange="onWeekChange(this)">
                            <?php foreach($weeks_options as $w): 
                                $sel = ($w['start'] == $start_date) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $w['start'].'|'.$w['end']; ?>" <?php echo $sel; ?>>
                                <?php echo $w['label']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <div id="scheduleTableWrap" class="table-container" style="overflow-x: auto;">
                    <table class="data-table" style="text-align:center; min-width: 800px;">
                        <thead>
                            <tr>
                                <th style="width:100px;">Ca</th>
                                <?php foreach($dates_arr as $d): ?>
                                <th><?php echo date('d/m', strtotime($d)); ?><br><small><?php echo $day_names[date('D', strtotime($d))]; ?></small></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>SÁNG</strong></td>
                                <?php foreach($dates_arr as $d): ?>
                                <td><?php if(isset($schedule_map['Sang'][$d])) foreach($schedule_map['Sang'][$d] as $item): 
                                    $style = $item['is_off'] ? 'background:#ffebee; color:#c62828;' : 'background:#e3f2fd; color:#1565c0;';
                                    echo "<div class='status-badge' style='display:block;margin-bottom:2px; $style'>{$item['name']}</div>"; 
                                endforeach; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>CHIỀU</strong></td>
                                <?php foreach($dates_arr as $d): ?>
                                <td><?php if(isset($schedule_map['Chieu'][$d])) foreach($schedule_map['Chieu'][$d] as $item): 
                                    $style = $item['is_off'] ? 'background:#ffebee; color:#c62828;' : 'background:#fff3e0; color:#ef6c00;';
                                    echo "<div class='status-badge' style='display:block;margin-bottom:2px; $style'>{$item['name']}</div>"; 
                                endforeach; ?></td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="admins" class="content-section">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <h2>Quản Trị Viên</h2>
                    <button class="btn btn-primary" onclick="openModal('addAdminModal')"><i class="fas fa-user-plus"></i> Thêm Admin</button>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Username</th><th>Họ tên</th><th>Tác vụ</th></tr></thead>
                        <tbody>
                            <?php foreach($admins as $ad): ?>
                            <tr>
                                <td data-label="ID"><?php echo $ad['id_quantrivien']; ?></td>
                                <td data-label="Username"><?php echo htmlspecialchars($ad['ten_dang_nhap']); ?></td>
                                <td data-label="Họ tên"><?php echo htmlspecialchars($ad['ten_day_du']); ?></td>
                                <td data-label="Tác vụ">
                                    <?php if($ad['id_quantrivien'] != 1): ?>
                                    <a href="../controllers/admin_actions.php?action=delete_admin&id=<?php echo $ad['id_quantrivien']; ?>" class="btn-icon text-danger" onclick="return confirm('Xóa admin này?')"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                    <small>(Super Admin)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="requests" class="content-section">
                <h2>Yêu Cầu & Xử Lý</h2>
                <div class="tab-header">
                    <button class="tab-btn active" onclick="switchInnerTab('req-leave', this)">Nghỉ Phép (<?php echo count($pending_leaves); ?>)</button>
                    <button class="tab-btn" onclick="switchInnerTab('req-conflict', this)">Xung Đột (<?php echo count($conflicts); ?>)</button>
                </div>
                <div id="req-leave" class="inner-tab active">
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Bác sĩ</th><th>Ngày nghỉ</th><th>Lý do</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                <?php foreach($pending_leaves as $l): ?>
                                <tr>
                                    <td data-label="Bác sĩ"><strong><?php echo $l['ten_day_du']; ?></strong></td>
                                    <td data-label="Ngày"><?php echo date('d/m/Y', strtotime($l['ngay_nghi'])) . ' (' . $l['ca_nghi'] . ')'; ?></td>
                                    <td data-label="Lý do"><?php echo $l['ly_do']; ?></td>
                                    <td data-label="Thao tác">
                                        <button onclick="checkLeaveConflicts(<?php echo $l['id_yeucau']; ?>, <?php echo $l['id_bacsi']; ?>, '<?php echo $l['ten_day_du']; ?>', '<?php echo $l['ngay_nghi']; ?>', '<?php echo $l['ca_nghi']; ?>')" class="btn btn-success">Duyệt</button>
                                        <a href="../controllers/admin_actions.php?action=reject_leave&id=<?php echo $l['id_yeucau']; ?>" class="btn btn-danger" onclick="return confirm('Từ chối yêu cầu này?')">Từ chối</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="req-conflict" class="inner-tab">
                    <div class="alert-info"><i class="fas fa-info-circle"></i> Danh sách các lịch hẹn bị trùng với lịch nghỉ của Bác sĩ (Cùng ngày và Cùng ca).</div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Khách hàng</th><th>Ngày giờ</th><th>Bác sĩ (Nghỉ)</th><th>Hành động</th></tr></thead>
                            <tbody>
                                <?php foreach($conflicts as $c): ?>
                                <tr>
                                    <td data-label="Khách hàng"><?php echo $c['ten_bn']; ?><br><small><?php echo $c['sdt']; ?></small></td>
                                    <td data-label="Ngày giờ" class="text-danger"><?php echo date('H:i d/m', strtotime($c['ngay_gio_hen'])); ?></td>
                                    <td data-label="Bác sĩ (Nghỉ)"><?php echo $c['ten_bs']; ?> (Ca: <?php echo $c['ca_nghi']; ?>)</td>
                                    <td data-label="Hành động">
                                        <button class="btn btn-primary" onclick="openSwitchDoctorModal(<?php echo $c['id_lichhen']; ?>, '<?php echo $c['ten_bn']; ?>', '<?php echo $c['ngay_gio_hen']; ?>', <?php echo $c['id_bacsi']; ?>)">Đổi BS</button>
                                        <a href="../controllers/admin_actions.php?action=cancel_conflict_appt&id=<?php echo $c['id_lichhen']; ?>" class="btn btn-danger" onclick="return confirm('Hủy lịch hẹn này?')">Hủy</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="services" class="content-section">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <h2>Dịch Vụ</h2>
                    <button class="btn btn-primary" onclick="openModal('serviceModal')"><i class="fas fa-plus"></i> Thêm</button>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Tên</th><th>Giá</th><th>Thời gian</th><th>Tác vụ</th></tr></thead>
                        <tbody>
                            <?php foreach($services as $s): ?>
                            <tr>
                                <td data-label="Tên"><?php echo $s['ten_dich_vu']; ?></td>
                                <td data-label="Giá" class="text-success"><?php echo number_format($s['gia_tien']); ?>đ</td>
                                <td data-label="Thời gian"><?php echo $s['thoi_gian_phut']; ?>p</td>
                                <td data-label="Tác vụ">
                                    <button class="btn-icon" onclick='openServiceModal("edit", <?php echo json_encode($s); ?>)'><i class="fas fa-pen"></i></button>
                                    <a href="../controllers/admin_actions.php?action=delete_service&id=<?php echo $s['id_dichvu']; ?>" class="btn-icon text-danger" onclick="return confirm('Xóa?')"><i class="fas fa-trash"></i></a>
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

<div id="doctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Thêm Bác Sĩ</h3><span class="close-btn" onclick="closeModal('doctorModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="add_doctor">
            <div class="form-group"><label>Họ tên:</label><input type="text" name="ten_day_du" class="form-control" required></div>
            <div class="form-group"><label>SĐT:</label><input type="text" name="sdt" class="form-control" required></div>
            <div class="form-group"><label>Email:</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>Chuyên khoa:</label><input type="text" name="chuyen_khoa" class="form-control"></div>
            <div class="form-group"><label>Mật khẩu:</label><input type="text" name="mat_khau" value="123456" class="form-control" required></div>
            <button class="btn btn-primary" style="width:100%">Lưu</button>
        </form>
    </div>
</div>

<div id="editDoctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Sửa Bác Sĩ</h3><span class="close-btn" onclick="closeModal('editDoctorModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="edit_doctor">
            <input type="hidden" name="id_bacsi" id="editDocId">
            <div class="form-group"><label>Họ tên:</label><input type="text" name="ten_day_du" id="editDocName" class="form-control" required></div>
            <div class="form-group"><label>SĐT:</label><input type="text" name="sdt" id="editDocPhone" class="form-control" required></div>
            <div class="form-group"><label>Email:</label><input type="email" name="email" id="editDocEmail" class="form-control" required></div>
            <div class="form-group"><label>Chuyên khoa:</label><input type="text" name="chuyen_khoa" id="editDocSpec" class="form-control"></div>
            <div class="form-group"><label>Mật khẩu mới (Để trống nếu không đổi):</label><input type="text" name="mat_khau" class="form-control" placeholder="******"></div>
            <button class="btn btn-primary" style="width:100%">Cập nhật</button>
        </form>
    </div>
</div>

<div id="adminAddApptModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Đặt Lịch (Khách Cũ)</h3><span class="close-btn" onclick="closeModal('adminAddApptModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="add_appointment_admin">
            <div class="form-group"><label>Bệnh nhân:</label>
                <select name="id_benhnhan" class="form-control" required>
                    <?php foreach($patients as $p): ?><option value="<?php echo $p['id_benhnhan']; ?>"><?php echo $p['ten_day_du'].' - '.$p['sdt']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Bác sĩ:</label>
                <select name="id_bacsi" class="form-control" required>
                    <?php foreach($doctors as $d): 
                        if(isset($d['trang_thai']) && $d['trang_thai']==0) continue;
                    ?><option value="<?php echo $d['id_bacsi']; ?>"><?php echo $d['ten_day_du']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Dịch vụ:</label>
                <select name="id_dichvu" class="form-control" required>
                    <?php foreach($services as $s): ?><option value="<?php echo $s['id_dichvu']; ?>"><?php echo $s['ten_dich_vu']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="display:flex; gap:10px;">
                <div style="flex:1"><label>Ngày:</label><input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>"></div>
                <div style="flex:1"><label>Ca:</label><select name="shift" class="form-control"><option value="Sang">Sáng</option><option value="Chieu">Chiều</option></select></div>
            </div>
            <button class="btn btn-primary" style="width:100%">Đặt lịch</button>
        </form>
    </div>
</div>

<div id="addScheduleModal" class="modal">
    <div class="modal-content" style="max-width:1000px;">
        <div class="modal-header"><h3>Thêm Lịch Làm Việc (Tuần)</h3><span class="close-btn" onclick="closeModal('addScheduleModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body" onsubmit="return validateAddSchedule()" style="max-height: 90vh;">
            <input type="hidden" name="action" value="add_schedule_week">
            <div class="form-group"><label>Bác sĩ:</label>
                <select name="id_bacsi" class="form-control" required>
                    <?php foreach($doctors as $d): if(isset($d['trang_thai']) && $d['trang_thai']==0) continue; ?>
                    <option value="<?php echo $d['id_bacsi']; ?>"><?php echo $d['ten_day_du']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Chọn tuần (Bắt đầu từ Thứ 2):</label>
                <input type="date" name="start_date" id="schStartDate" class="form-control" required min="<?php echo date('Y-m-d'); ?>" onchange="updateWeekDays()">
            </div>
            
            <div class="form-group">
                <label>Chọn các ca làm việc:</label>
                <table class="data-table" style="text-align:center;">
                    <thead><tr><th>Thứ</th><th>Ngày</th><th>Sáng (08:00-12:00)</th><th>Chiều (13:00-17:00)</th></tr></thead>
                    <tbody id="weekDaysBody">
                        <!-- JS will populate this -->
                    </tbody>
                </table>
            </div>

            <div class="form-group" style="display:flex; gap:10px; align-items:flex-end;">
                <div style="flex:1;">
                    <label>Chọn Giường/Ghế:</label>
                    <select name="id_giuongbenh" id="schBed" class="form-control" required disabled>
                        <option value="">-- Vui lòng chọn ca & tìm giường --</option>
                    </select>
                </div>
                <button type="button" class="btn btn-info" onclick="findAvailableBeds()">Tìm giường trống</button>
            </div>
            <small id="bedMsg" class="text-danger" style="display:none; margin-bottom:10px;"></small>

            <button id="btnAddSchedule" class="btn btn-primary" style="width:100%" disabled>Lưu Lịch</button>
        </form>
    </div>
</div>

<div id="adminWalkinModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Khách Vãng Lai</h3><span class="close-btn" onclick="closeModal('adminWalkinModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="add_walkin_admin">
            <div class="form-group"><label>Họ tên:</label><input type="text" name="ten_day_du" class="form-control" required></div>
            <div class="form-group"><label>SĐT:</label><input type="text" name="sdt" class="form-control" required></div>
            <div class="form-group"><label>Email (Tùy chọn):</label><input type="email" name="email" class="form-control"></div>
            <div class="form-group"><label>Bác sĩ:</label>
                <select name="id_bacsi" class="form-control" required><?php foreach($doctors as $d): if(isset($d['trang_thai']) && $d['trang_thai']==0) continue; ?><option value="<?php echo $d['id_bacsi']; ?>"><?php echo $d['ten_day_du']; ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Dịch vụ:</label>
                <select name="id_dichvu" class="form-control" required><?php foreach($services as $s): ?><option value="<?php echo $s['id_dichvu']; ?>"><?php echo $s['ten_dich_vu']; ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Ca (Hôm nay):</label>
                <select name="shift" class="form-control"><option value="Sang">Sáng</option><option value="Chieu">Chiều</option></select>
            </div>
            <button class="btn btn-warning" style="width:100%">Tiếp nhận ngay</button>
        </form>
    </div>
</div>

<div id="profileModal" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header"><h3>Đổi Mật Khẩu</h3><span class="close-btn" onclick="closeModal('profileModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="change_self_pass">
            <div class="form-group"><label>Mật khẩu cũ:</label><input type="password" name="old_pass" class="form-control" required></div>
            <div class="form-group"><label>Mật khẩu mới:</label><input type="password" name="new_pass" class="form-control" required></div>
            <button class="btn btn-primary" style="width:100%">Cập nhật</button>
            <div style="text-align:center; margin-top:15px;"><a href="../controllers/logout.php" class="text-danger">Đăng xuất</a></div>
        </form>
    </div>
</div>

<div id="addAdminModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Thêm Admin Mới</h3><span class="close-btn" onclick="closeModal('addAdminModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="add_admin">
            <div class="form-group"><label>Username:</label><input type="text" name="username" class="form-control" required></div>
            <div class="form-group"><label>Họ tên:</label><input type="text" name="fullname" class="form-control" required></div>
            <div class="form-group"><label>Mật khẩu:</label><input type="password" name="password" class="form-control" required></div>
            <button class="btn btn-primary" style="width:100%">Tạo</button>
        </form>
    </div>
</div>

<div id="switchDoctorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Chuyển Bác Sĩ</h3><span class="close-btn" onclick="closeModal('switchDoctorModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="switch_doctor">
            <input type="hidden" name="id_lichhen" id="switchApptId">
            <p>Khách hàng: <strong id="switchPatientName"></strong></p>
            <p>Thời gian: <strong id="switchApptTime"></strong></p>
            
            <div class="form-group">
                <label>Chọn Bác sĩ thay thế:</label>
                <select name="new_doctor_id" id="switchNewDoctor" class="form-control" required onchange="updateSwitchSummary()">
                    <option value="">-- Đang tải danh sách... --</option>
                </select>
                <small class="text-muted" id="switchDocMsg"></small>
            </div>
            <div id="switchSummary" style="margin-top: 15px;"></div>
            <button class="btn btn-primary" style="width:100%" id="btnSwitchDoc" disabled>Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<div id="approveLeaveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Cảnh Báo Xung Đột</h3><span class="close-btn" onclick="closeModal('approveLeaveModal')">&times;</span></div>
        <div class="modal-body">
            <p>Bạn đang duyệt yêu cầu nghỉ phép cho Bác sĩ <strong id="warnDocName"></strong>.</p>
            <p>Thời gian: <strong id="warnTime"></strong></p>
            
            <div id="conflictWarning" class="alert-danger" style="display:none; padding:10px; margin:10px 0; border-radius:5px;">
                <strong>CẢNH BÁO:</strong> Việc duyệt yêu cầu này sẽ gây ra <strong id="conflictCount" style="font-size:1.2em">0</strong> xung đột lịch hẹn!
                <p><small>Các lịch hẹn này sẽ được chuyển sang tab "Xung Đột" để bạn xử lý thủ công.</small></p>
            </div>
            
            <div id="noConflictMsg" class="alert-success" style="display:none; padding:10px; margin:10px 0; border-radius:5px;">
                <i class="fas fa-check-circle"></i> Không phát hiện xung đột nào. An toàn để duyệt.
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button class="btn btn-secondary" onclick="closeModal('approveLeaveModal')">Hủy bỏ</button>
                <a href="#" id="btnConfirmApprove" class="btn btn-success">Xác nhận Duyệt</a>
            </div>
        </div>
    </div>
</div>

<div id="serviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3 id="serviceModalTitle">Dịch Vụ</h3><span class="close-btn" onclick="closeModal('serviceModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" id="serviceAction" value="add_service">
            <input type="hidden" name="id" id="serviceId">
            <div class="form-group"><label>Tên:</label><input type="text" name="name" id="servName" class="form-control" required></div>
            <div class="form-group"><label>Giá:</label><input type="number" name="price" id="servPrice" class="form-control" required></div>
            <div class="form-group"><label>Thời gian (phút):</label><input type="number" name="time" id="servTime" class="form-control" required></div>
            <div class="form-group"><label>Mô tả:</label><input type="text" name="desc" id="servDesc" class="form-control"></div>
            <button class="btn btn-primary" style="width:100%">Lưu</button>
        </form>
    </div>
</div>



<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Đổi Lịch Hẹn</h3><span class="close-btn" onclick="closeModal('rescheduleModal')">&times;</span></div>
        <form action="../controllers/admin_actions.php" method="POST" class="modal-body">
            <input type="hidden" name="action" value="reschedule_appointment_admin">
            <input type="hidden" name="id_lichhen" id="reschApptId">
            <p>Bệnh nhân: <strong id="reschPatientName"></strong></p>
            
            <div class="form-group">
                <label>Ngày mới:</label>
                <input type="date" name="new_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Ca mới:</label>
                <select name="new_shift" class="form-control" required>
                    <option value="Sang">Sáng (08:00 - 12:00)</option>
                    <option value="Chieu">Chiều (13:00 - 17:00)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Lý do thay đổi (Gửi mail cho khách):</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Ví dụ: Bác sĩ có việc đột xuất..."></textarea>
            </div>
            <button class="btn btn-primary" style="width:100%">Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
<script>
    function toggleFilter() { document.getElementById('advFilter').classList.toggle('show'); }
    
    function validateScheduleDate() {
        const f = document.getElementById('schFrom').value;
        const t = document.getElementById('schTo').value;
        if(!f || !t) return true;
        const d1 = new Date(f); const d2 = new Date(t);
        const diff = (d2-d1)/(1000*60*60*24);
        
        if (d1 > d2) { alert('Ngày bắt đầu không được lớn hơn ngày kết thúc!'); return false; }
        if (diff !== 6) { // 7 ngày tính cả ngày đầu (0->6)
            alert('Vui lòng chọn khoảng thời gian đúng 1 tuần (7 ngày) để xem lịch chính xác.');
            return false;
        }
        return true;
    }

    function openServiceModal(mode, data = null) {
        const modal = document.getElementById('serviceModal');
        const title = document.getElementById('serviceModalTitle');
        const actionInput = document.getElementById('serviceAction');
        const idInput = document.getElementById('serviceId');
        document.getElementById('servName').value = ''; document.getElementById('servPrice').value = ''; document.getElementById('servTime').value = ''; document.getElementById('servDesc').value = '';
        if (mode === 'edit' && data) {
            title.innerText = 'Cập Nhật Dịch Vụ'; actionInput.value = 'edit_service'; idInput.value = data.id_dichvu;
            document.getElementById('servName').value = data.ten_dich_vu; document.getElementById('servPrice').value = data.gia_tien;
            document.getElementById('servTime').value = data.thoi_gian_phut; document.getElementById('servDesc').value = data.mo_ta;
        } else { title.innerText = 'Thêm Dịch Vụ Mới'; actionInput.value = 'add_service'; }
        modal.style.display = 'block';
    }

    function openSwitchDoctorModal(id, name) {
        document.getElementById('switchApptId').value = id;
        document.getElementById('switchPatientName').innerText = name;
        document.getElementById('switchDoctorModal').style.display = 'block';
    }

    function openEditDoctorModal(data) {
        document.getElementById('editDocId').value = data.id_bacsi;
        document.getElementById('editDocName').value = data.ten_day_du;
        document.getElementById('editDocPhone').value = data.sdt;
        document.getElementById('editDocEmail').value = data.email || '';
        document.getElementById('editDocSpec').value = data.chuyen_khoa;
        document.getElementById('editDoctorModal').style.display = 'block';
    }

    function updateWeekDays() {
        const startInput = document.getElementById('schStartDate');
        const tbody = document.getElementById('weekDaysBody');
        tbody.innerHTML = '';
        
        if (!startInput.value) return;

        let date = new Date(startInput.value);
        // Adjust to Monday if not already
        const day = date.getDay();
        const diff = date.getDate() - day + (day == 0 ? -6 : 1); 
        date.setDate(diff);
        
        // Update input to show Monday
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        startInput.value = `${yyyy}-${mm}-${dd}`;

        const days = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'];
        
        for (let i = 0; i < 7; i++) {
            const curDate = new Date(date);
            curDate.setDate(date.getDate() + i);
            const dateStr = curDate.toISOString().split('T')[0];
            const displayDate = `${curDate.getDate()}/${curDate.getMonth()+1}`;
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Thứ">${days[i]}</td>
                <td data-label="Ngày">${displayDate}</td>
                <td data-label="Sáng"><input type="checkbox" name="shifts[${dateStr}][Sang]" value="1" class="shift-check"></td>
                <td data-label="Chiều"><input type="checkbox" name="shifts[${dateStr}][Chieu]" value="1" class="shift-check"></td>
            `;
            tbody.appendChild(tr);
        }
        
        // Reset bed selection
        document.getElementById('schBed').innerHTML = '<option value="">-- Vui lòng chọn ca & tìm giường --</option>';
        document.getElementById('schBed').disabled = true;
        document.getElementById('btnAddSchedule').disabled = true;
    }

    function findAvailableBeds() {
        const checks = document.querySelectorAll('.shift-check:checked');
        if (checks.length === 0) {
            alert('Vui lòng chọn ít nhất một ca làm việc!');
            return;
        }

        const shifts = [];
        checks.forEach(c => {
            const name = c.name; // shifts[2025-01-01][Sang]
            const match = name.match(/shifts\[(.*?)\]\[(.*?)\]/);
            if (match) {
                shifts.push({date: match[1], shift: match[2]});
            }
        });

        const bedSelect = document.getElementById('schBed');
        const btn = document.getElementById('btnAddSchedule');
        const msg = document.getElementById('bedMsg');
        
        bedSelect.innerHTML = '<option>Đang tìm...</option>';
        bedSelect.disabled = true;
        btn.disabled = true;
        msg.style.display = 'none';

        fetch('../controllers/admin_actions.php?action=find_beds_bulk', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({shifts: shifts})
        })
        .then(res => res.json())
        .then(data => {
            bedSelect.innerHTML = '';
            if (data.length > 0) {
                data.forEach(bed => {
                    const opt = document.createElement('option');
                    opt.value = bed.id_giuongbenh;
                    opt.textContent = bed.ten_giuong;
                    bedSelect.appendChild(opt);
                });
                bedSelect.disabled = false;
                btn.disabled = false;
            } else {
                bedSelect.innerHTML = '<option value="">-- Hết giường trống --</option>';
                msg.textContent = 'Không có giường nào trống cho TẤT CẢ các ca đã chọn. Vui lòng bỏ bớt ca hoặc chọn tuần khác.';
                msg.style.display = 'block';
            }
        })
        .catch(err => {
            console.error(err);
            bedSelect.innerHTML = '<option value="">Lỗi</option>';
        });
    }

    function validateAddSchedule() {
        const bed = document.getElementById('schBed').value;
        if (!bed) {
            alert('Vui lòng chọn giường làm việc!');
            return false;
        }
        return true;
    }

    function checkLeaveConflicts(reqId, docId, docName, date, shift) {
        document.getElementById('warnDocName').innerText = docName;
        document.getElementById('warnTime').innerText = date + ' (' + shift + ')';
        
        const warnBox = document.getElementById('conflictWarning');
        const safeBox = document.getElementById('noConflictMsg');
        const btn = document.getElementById('btnConfirmApprove');
        const countSpan = document.getElementById('conflictCount');
        
        warnBox.style.display = 'none';
        safeBox.style.display = 'none';
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.5';
        btn.innerText = 'Đang kiểm tra...';
        
        document.getElementById('approveLeaveModal').style.display = 'block';
        
        fetch(`../controllers/admin_actions.php?action=check_leave_conflicts&id_bacsi=${docId}&date=${date}&shift=${shift}`)
        .then(res => res.json())
        .then(data => {
            btn.style.pointerEvents = 'auto';
            btn.style.opacity = '1';
            btn.innerText = 'Xác nhận Duyệt';
            btn.href = `../controllers/admin_actions.php?action=approve_leave&id=${reqId}`;
            
            if (data.count > 0) {
                countSpan.innerText = data.count;
                warnBox.style.display = 'block';
            } else {
                safeBox.style.display = 'block';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Lỗi khi kiểm tra xung đột!');
            closeModal('approveLeaveModal');
        });
    }

    function openSwitchDoctorModal(apptId, patientName, datetime, currentDocId) {
        document.getElementById('switchApptId').value = apptId;
        document.getElementById('switchPatientName').innerText = patientName;
        document.getElementById('switchSummary').innerHTML = ''; // Reset summary
        
        const dateObj = new Date(datetime);
        const dateStr = dateObj.toISOString().split('T')[0];
        const hour = dateObj.getHours();
        const shift = (hour < 12) ? 'Sang' : 'Chieu';
        const displayTime = `${hour}:${String(dateObj.getMinutes()).padStart(2,'0')} ${dateObj.getDate()}/${dateObj.getMonth()+1}/${dateObj.getFullYear()}`;
        
        document.getElementById('switchApptTime').innerText = displayTime;
        document.getElementById('switchDoctorModal').style.display = 'block';
        
        const select = document.getElementById('switchNewDoctor');
        const msg = document.getElementById('switchDocMsg');
        const btn = document.getElementById('btnSwitchDoc');
        
        select.innerHTML = '<option value="">-- Đang tải danh sách... --</option>';
        select.disabled = true;
        btn.disabled = true;
        msg.innerText = '';
        
        fetch(`../controllers/admin_actions.php?action=get_available_doctors_for_switch&date=${dateStr}&shift=${shift}&exclude_id=${currentDocId}`)
        .then(res => res.json())
        .then(data => {
            select.innerHTML = '<option value="">-- Chọn bác sĩ thay thế --</option>';
            if (data.length > 0) {
                data.forEach(d => {
                    let statusText = '';
                    if (d.has_schedule) {
                        statusText = ' (Đang làm việc)';
                    } else {
                        statusText = ' (Sẽ thêm lịch)';
                    }
                    
                    const opt = document.createElement('option');
                    opt.value = d.id_bacsi;
                    opt.textContent = `${d.ten_day_du} - ${d.chuyen_khoa}${statusText}`;
                    opt.setAttribute('data-name', d.ten_day_du); // Store name for summary
                    
                    // Highlight doctors who are already working
                    if (d.has_schedule) {
                        opt.style.fontWeight = 'bold';
                        opt.style.color = '#2e7d32'; // Green
                    }
                    
                    select.appendChild(opt);
                });
                select.disabled = false;
                btn.disabled = false;
            } else {
                select.innerHTML = '<option value="">-- Không có bác sĩ nào rảnh --</option>';
                msg.innerText = 'Tất cả bác sĩ khác đều đang nghỉ hoặc bị khóa.';
            }
        })
        .catch(err => {
            console.error(err);
            select.innerHTML = '<option value="">Lỗi tải danh sách</option>';
        });
    }

    function updateSwitchSummary() {
        const select = document.getElementById('switchNewDoctor');
        const summary = document.getElementById('switchSummary');
        const time = document.getElementById('switchApptTime').innerText;
        
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const docName = selectedOption.getAttribute('data-name') || selectedOption.text.split(' - ')[0];
            
            summary.innerHTML = `
                <div class="alert alert-info" style="background-color: #e3f2fd; color: #0d47a1; padding: 10px; border-radius: 5px; border: 1px solid #90caf9;">
                    <i class="fas fa-info-circle"></i> <strong>Xác nhận chuyển lịch:</strong><br>
                    Lịch hẹn này sẽ được chuyển sang cho <strong>Bác sĩ ${docName}</strong><br>
                    Thời gian: <strong>${time}</strong>
                </div>
            `;
        } else {
            summary.innerHTML = '';
        }
    }

    function onWeekChange(select) {
        const parts = select.value.split('|');
        if(parts.length === 2) {
            document.getElementById('schFrom').value = parts[0];
            document.getElementById('schTo').value = parts[1];
            document.getElementById('scheduleForm').submit();
        }
    }

    // Restore active section from URL
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const sec = urlParams.get('section');
        if(sec) {
            showSection(sec);
        }
    });
</script>
<!-- Patient History Modal -->
<div id="patientHistoryModal" class="modal">
    <div class="modal-content" style="width: auto; max-width: 95%; min-width: 600px;">
        <div class="modal-header">
            <h2>Lịch sử khám bệnh</h2>
            <span class="close" onclick="closeModal('patientHistoryModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="table-container" style="max-height: 70vh; overflow-y: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Ngày khám</th>
                            <th style="width: 140px;">Bác sĩ</th>
                            <th style="width: 140px;">Dịch vụ</th>
                            <th style="width: 100px;">Trạng thái</th>
                            <th>Chẩn đoán</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody id="patientHistoryBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function togglePatientStatus(id, status) {
    if(!confirm(status == 0 ? 'Bạn có chắc muốn khóa tài khoản này?' : 'Mở khóa tài khoản này?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', status);
    
    fetch('../controllers/admin_actions.php?action=toggle_patient_status', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Cập nhật thành công!');
            location.reload();
        } else {
            alert('Có lỗi xảy ra');
        }
    });
}

function viewPatientHistory(id) {
    // Add timestamp to prevent caching
    fetch(`../controllers/admin_actions.php?action=get_patient_history&id=${id}&t=${new Date().getTime()}`)
    .then(res => res.text()) // Read as text first to debug
    .then(text => {
        try {
            const data = JSON.parse(text);
            const tbody = document.getElementById('patientHistoryBody');
            tbody.innerHTML = '';
            
            if (!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px;">Chưa có lịch sử khám</td></tr>';
            } else {
                data.forEach(row => {
                    let statusBadge = '';
                    if(row.trang_thai == 'cho_xac_nhan') statusBadge = '<span class="status-badge bg-pending">Chờ duyệt</span>';
                    else if(row.trang_thai == 'da_xac_nhan') statusBadge = '<span class="status-badge bg-confirmed">Đã xác nhận</span>';
                    else if(row.trang_thai == 'hoan_thanh') statusBadge = '<span class="status-badge bg-success" style="background:#e8f5e9; color:#2e7d32">Hoàn thành</span>';
                    else if(row.trang_thai == 'huy') statusBadge = '<span class="status-badge bg-cancelled">Đã hủy</span>';
                    
                    // Safe date parsing
                    let dateStr = row.ngay_gio_hen;
                    try {
                        const d = new Date(row.ngay_gio_hen);
                        if (!isNaN(d.getTime())) {
                            dateStr = d.toLocaleString('vi-VN');
                        }
                    } catch(e) {}

                    tbody.innerHTML += `
                        <tr>
                            <td>${dateStr}</td>
                            <td>${row.ten_bs || '-'}</td>
                            <td>${row.ten_dich_vu || '-'}</td>
                            <td>${statusBadge}</td>
                            <td style="white-space: pre-wrap; max-width: 250px;">${row.chan_doan || '-'}</td>
                            <td style="white-space: pre-wrap; max-width: 250px;">${row.ghi_chu_bac_si || '-'}</td>
                        </tr>
                    `;
                });
            }
            openModal('patientHistoryModal');
        } catch (e) {
            console.error("JSON Parse Error:", e);
            console.log("Raw response:", text);
            alert("Lỗi tải dữ liệu: " + text.substring(0, 100));
        }
    })
    .catch(err => {
        console.error("Fetch Error:", err);
        alert("Lỗi kết nối server");
    });
}
</script>
</body>
</html>