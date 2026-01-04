<?php
session_start();
require '../config/db_connect.php'; 

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: dangnhap.php"); exit();
}
$user_id = $_SESSION['user_id'];

// 2. Lấy thông tin Bệnh nhân
$stmt = $conn->prepare("SELECT * FROM benhnhan WHERE id_benhnhan = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { 
    header("Location: ../controllers/logout.php"); exit(); 
}

// 3. Lấy dữ liệu cho Modal Đặt lịch
$services = $conn->query("SELECT * FROM dichvu")->fetchAll(PDO::FETCH_ASSOC);
// [CẬP NHẬT] Chỉ lấy bác sĩ đang hoạt động
$doctors = $conn->query("SELECT * FROM bacsi WHERE trang_thai = 1")->fetchAll(PDO::FETCH_ASSOC);

// 4. Lấy Lịch Hẹn Sắp Tới
$sql_upcoming = "SELECT t1.*, t2.ten_day_du AS ten_bacsi, t2.sdt AS sdt_bacsi, t3.ten_dich_vu 
                 FROM lichhen AS t1
                 JOIN bacsi AS t2 ON t1.id_bacsi = t2.id_bacsi
                 JOIN dichvu AS t3 ON t1.id_dichvu = t3.id_dichvu
                 WHERE t1.id_benhnhan = ? 
                 AND (t1.trang_thai = 'cho_xac_nhan' OR t1.trang_thai = 'da_xac_nhan')
                 AND t1.ngay_gio_hen >= CURDATE()
                 ORDER BY t1.ngay_gio_hen ASC";
$upcoming_appts = $conn->prepare($sql_upcoming);
$upcoming_appts->execute([$user_id]);

// 5. Lấy Lịch sử Khám (Kèm ID Bệnh án nếu có)
$sql_history = "SELECT t1.*, t2.ten_day_du AS ten_bacsi, t3.ten_dich_vu, ba.id_benhan 
                FROM lichhen AS t1
                JOIN bacsi AS t2 ON t1.id_bacsi = t2.id_bacsi
                JOIN dichvu AS t3 ON t1.id_dichvu = t3.id_dichvu
                LEFT JOIN benhan AS ba ON t1.id_lichhen = ba.id_lichhen
                WHERE t1.id_benhnhan = ? 
                AND (t1.trang_thai = 'hoan_thanh' OR t1.trang_thai = 'huy' OR t1.ngay_gio_hen < CURDATE())
                ORDER BY t1.ngay_gio_hen DESC";
$history_appts = $conn->prepare($sql_history);
$history_appts->execute([$user_id]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng Thông Tin Bệnh Nhân</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/khachhang.css">
    <style>
        .password-section { margin-top: 40px; border-top: 1px solid #eee; padding-top: 30px; }
        .mr-group { margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .mr-label { font-weight: bold; color: var(--primary); display: block; margin-bottom: 5px; }
        .mr-value { color: #333; line-height: 1.6; }
        
        /* CSS cho Modal đặt lịch giống datlich.php */
        .alert-box { padding: 10px; margin-top: 10px; border-radius: 5px; display: none; font-size: 0.9em; }
        .alert-error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .contact-phone { font-weight: bold; color: #d32f2f; }
        select:disabled, input:disabled { background-color: #f9f9f9; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="sidebar">
        <div class="logo"><i class="fas fa-tooth"></i> iDental Patient</div>
        <nav>
            <div class="menu-link active" onclick="switchTab('dashboard', this)"><i class="fas fa-home"></i> Trang Chủ</div>
            <div class="menu-link" onclick="switchTab('history', this)"><i class="fas fa-history"></i> Lịch Sử Khám</div>
            <div class="menu-link" onclick="switchTab('profile', this)"><i class="fas fa-user-circle"></i> Hồ Sơ & Cài Đặt</div>
        </nav>
        <div class="sidebar-logout">
            <a href="../controllers/logout.php" class="btn" style="color: #666;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </div>
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>
    <div class="main-content-wrapper">
        <header class="header">
    <div style="display: flex; align-items: center; gap: 15px;">
        <button id="sidebarToggle" style="display: none; background: none; border: none; font-size: 22px; cursor: pointer; color: #555;">
            <i class="fas fa-bars"></i>
        </button>
        <div style="color: #666;">Hôm nay, <?php echo date('d/m/Y'); ?></div>
    </div>

    <div class="user-profile" onclick="toggleUserMenu()">
        <div style="text-align:right;">
            <div style="font-weight:700;"><?php echo htmlspecialchars($user['ten_day_du']); ?></div>
            <div style="font-size:0.8em; color:#888;">Khách hàng</div>
        </div>
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['ten_day_du']); ?>&background=random&color=fff" class="avatar" alt="Avatar">
        <div id="userMenuDropdown" class="user-dropdown">
            <a href="../controllers/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </div>
    </div>
</header>

        <div class="main-content">

            <div id="dashboard" class="content-section active">
                <div class="card-box" style="background: linear-gradient(135deg, var(--primary), #5a85e0); color: white; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin: 0 0 10px 0;">Xin chào, <?php echo htmlspecialchars($user['ten_day_du']); ?>! 👋</h2>
                        <p style="margin: 0; opacity: 0.9;">Bạn có <strong><?php echo $upcoming_appts->rowCount(); ?> lịch hẹn</strong> sắp tới.</p>
                    </div>
                    <button class="btn" style="background: white; color: var(--primary);" onclick="openModal('bookingModal')"><i class="fas fa-plus-circle"></i> Đặt Lịch Mới</button>
                </div>

                <div class="card-box">
                    <div class="section-title"><h3><i class="fas fa-calendar-alt"></i> Lịch Hẹn Sắp Tới</h3></div>
                    <?php if ($upcoming_appts->rowCount() > 0): ?>
                    <table class="data-table">
                        <thead><tr><th>Thời gian</th><th>Dịch vụ</th><th>Bác sĩ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                        <tbody>
                            <?php while ($app = $upcoming_appts->fetch(PDO::FETCH_ASSOC)): 
                                $statusClass = ($app['trang_thai'] == 'da_xac_nhan') ? 'bg-confirmed' : 'bg-pending';
                                $statusText = ($app['trang_thai'] == 'da_xac_nhan') ? 'Đã xác nhận' : 'Chờ duyệt';
                            ?>
                            <tr>
                                <td data-label="Thời gian"><div style="font-weight:bold; color: var(--primary);"><?php echo date('H:i d/m/Y', strtotime($app['ngay_gio_hen'])); ?></div></td>
                                <td data-label="Dịch vụ"><?php echo htmlspecialchars($app['ten_dich_vu']); ?></td>
                                <td data-label="Bác sĩ">
                                    Dr. <?php echo htmlspecialchars($app['ten_bacsi']); ?>
                                    <div style="font-size: 0.85em; color: #666; margin-top: 4px;">
                                        <i class="fas fa-phone-alt" style="font-size: 0.8em;"></i> <?php echo htmlspecialchars($app['sdt_bacsi']); ?>
                                    </div>
                                </td>
                                <td data-label="Trạng thái"><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td data-label="Thao tác">
                                    <?php if($app['trang_thai'] == 'cho_xac_nhan'): ?>
                                        <a href="../controllers/patient_actions.php?action=cancel_appointment&id=<?php echo $app['id_lichhen']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Bạn chắc chắn muốn hủy yêu cầu đặt lịch này?')">
                                           <i class="fas fa-times"></i> Hủy
                                        </a>

                                    <?php elseif($app['trang_thai'] == 'da_xac_nhan'): ?>
                                        <button class="btn" style="background:#eee; color:#999; cursor:not-allowed;" disabled title="Lịch đã được chốt, vui lòng liên hệ phòng khám để đổi/hủy">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                        <div style="font-size:0.8em; color:#888; margin-top:2px;">Liên hệ hotline</div>

                                    <?php else: ?>
                                        <small style="color:#888">Không thể hủy</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="text-align:center; color:#888; padding: 20px;">Không có lịch hẹn sắp tới.</p>
                    <?php endif; ?>

                    <div style="margin-top: 20px; padding: 15px; background-color: #e3f2fd; border-left: 5px solid #2196f3; border-radius: 4px; font-size: 0.95em; color: #333;">
                        <i class="fas fa-info-circle" style="color: #2196f3; margin-right: 5px;"></i> 
                        <strong>Ghi chú:</strong> Nếu bạn cần thay đổi hoặc hủy lịch hẹn đã chốt, vui lòng liên hệ trực tiếp vào số điện thoại của bác sĩ (hiển thị ở trên) hoặc gọi vào hotline phòng khám: <strong style="color: #d32f2f;">1900 1234</strong> để được hỗ trợ.
                    </div>
                </div>
            </div>

            <div id="history" class="content-section">
                <div class="card-box">
                    <div class="section-title"><h3><i class="fas fa-history"></i> Lịch Sử Khám Bệnh</h3></div>
                    <table class="data-table">
                        <thead><tr><th>Thời gian</th><th>Dịch vụ</th><th>Bác sĩ</th><th>Kết quả</th><th>Chi tiết</th></tr></thead>
                        <tbody>
                            <?php while ($hist = $history_appts->fetch(PDO::FETCH_ASSOC)): 
                                $stt = $hist['trang_thai'];
                                $cls = ($stt == 'hoan_thanh') ? 'bg-confirmed' : 'bg-danger';
                                $txt = ($stt == 'hoan_thanh') ? 'Hoàn thành' : 'Đã hủy';
                            ?>
                            <tr>
                                <td data-label="Thời gian"><?php echo date('d/m/Y H:i', strtotime($hist['ngay_gio_hen'])); ?></td>
                                <td data-label="Dịch vụ"><?php echo htmlspecialchars($hist['ten_dich_vu']); ?></td>
                                <td data-label="Bác sĩ">Dr. <?php echo htmlspecialchars($hist['ten_bacsi']); ?></td>
                                <td data-label="Kết quả"><span class="badge <?php echo $cls; ?>"><?php echo $txt; ?></span></td>
                                <td data-label="Chi tiết">
                                    <?php if($stt == 'hoan_thanh' && !empty($hist['id_benhan'])): ?>
                                        <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.85em;" onclick="viewMedicalRecord(<?php echo $hist['id_benhan']; ?>)">
                                            <i class="fas fa-file-medical"></i> Xem
                                        </button>
                                    <?php elseif($stt == 'hoan_thanh'): ?>
                                        <small style="color:#999">Đang cập nhật...</small>
                                    <?php else: ?> - <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="profile" class="content-section">
                <div class="card-box">
                    <div class="section-title"><h3><i class="fas fa-user-edit"></i> Thông Tin Cá Nhân</h3></div>
                    <form action="../controllers/patient_actions.php" method="POST" id="profileForm">
                        <div class="form-grid">
                            <div class="form-group"><label>Họ và Tên:</label><input type="text" name="ten_day_du" class="form-control" value="<?php echo htmlspecialchars($user['ten_day_du']); ?>" disabled></div>
                            <div class="form-group"><label>Số điện thoại:</label><input type="text" name="sdt" class="form-control" value="<?php echo htmlspecialchars($user['sdt']); ?>" disabled></div>
                            <div class="form-group"><label>Email:</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled></div>
                            <div class="form-group"><label>Địa chỉ:</label><input type="text" name="dia_chi" class="form-control" value="<?php echo htmlspecialchars($user['dia_chi'] ?? ''); ?>" disabled></div>
                            <div class="form-group"><label>Ngày sinh:</label><input type="date" name="ngay_sinh" class="form-control" value="<?php echo htmlspecialchars($user['ngay_sinh'] ?? ''); ?>" disabled></div>
                        </div>
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="button" id="btnEditProfile" class="btn btn-secondary" onclick="enableEditMode()">Chỉnh sửa</button>
                            <div id="editActions" style="display:none;">
                                <button type="button" class="btn btn-secondary" onclick="cancelEditMode()">Hủy</button>
                                <button type="submit" name="update_profile" class="btn btn-primary">Lưu Thay Đổi</button>
                            </div>
                        </div>
                    </form>

                    <div class="password-section">
                        <div class="section-title"><h3><i class="fas fa-key"></i> Đổi Mật Khẩu</h3></div>
                        <form action="../controllers/patient_actions.php" method="POST">
                            <div class="form-grid">
                                <div class="form-group"><label>Mật khẩu hiện tại</label><input type="password" name="old_pass" class="form-control" required></div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group"><label>Mật khẩu mới</label><input type="password" name="new_pass" class="form-control" required></div>
                                <div class="form-group"><label>Nhập lại mật khẩu mới</label><input type="password" name="confirm_pass" class="form-control" required></div>
                            </div>
                            <div style="text-align: right; margin-top: 15px;">
                                <button type="submit" name="change_password" class="btn btn-danger">Cập Nhật Mật Khẩu</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="bookingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>Đặt Lịch Mới</h3></div>
        <form action="../controllers/patient_actions.php" method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Dịch vụ:</label>
                    <select name="id_dichvu" class="form-control" required>
                        <?php foreach($services as $sv): ?>
                            <option value="<?php echo $sv['id_dichvu']; ?>"><?php echo htmlspecialchars($sv['ten_dich_vu']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Bác sĩ:</label>
                    <select name="id_bacsi" id="modalDocSelect" class="form-control" required>
                        <option value="">-- Chọn Bác sĩ --</option>
                        <?php foreach($doctors as $doc): ?>
                            <option value="<?php echo $doc['id_bacsi']; ?>">Dr. <?php echo htmlspecialchars($doc['ten_day_du']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ngày khám:</label>
                    <input type="date" name="date" id="modalDateInput" class="form-control" required min="<?php echo date('Y-m-d'); ?>" disabled>
                    <small id="modalDateHint" style="color:#666; font-size:0.85em; display:block; margin-top:5px;">* Vui lòng chọn Bác sĩ trước</small>
                </div>

                <div class="form-group">
                    <label>Ca khám khả dụng:</label>
                    <select name="shift" id="modalShiftSelect" class="form-control" required disabled>
                        <option value="">-- Vui lòng chọn ngày --</option>
                    </select>
                </div>

                <div id="modalAlertBox" class="alert-box alert-error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span id="modalAlertMsg"></span><br>
                    Liên hệ Hotline: <span id="modalContactPhone" class="contact-phone"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('bookingModal')">Hủy</button>
                <button type="submit" name="book_appointment" id="modalSubmitBtn" class="btn btn-primary" disabled>Xác Nhận</button>
            </div>
        </form>
    </div>
</div>

<div id="medicalRecordModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Chi Tiết Bệnh Án</h3>
        </div>
        <div class="modal-body" id="medicalRecordContent">
            <div style="text-align:center; color:#666;">Đang tải dữ liệu...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('medicalRecordModal')">Đóng</button>
        </div>
    </div>
</div>

<script src="../assets/js/khachhang.js"></script>
<script>
    // Hàm xem bệnh án (Đã cập nhật giao diện đẹp hơn)
    async function viewMedicalRecord(recordId) {
        openModal('medicalRecordModal');
        const contentDiv = document.getElementById('medicalRecordContent');
        contentDiv.innerHTML = '<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';
        try {
            const res = await fetch(`../controllers/get_medical_record.php?id=${recordId}`);
            const data = await res.json();
            if (data.error) contentDiv.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
            else {
                contentDiv.innerHTML = `
                    <div class="record-detail">
                        <div class="mr-group"><span class="mr-label">Ngày khám:</span> <span class="mr-value">${data.ngay_tao}</span></div>
                        <div class="mr-group"><span class="mr-label">Bác sĩ khám:</span> <span class="mr-value">${data.ten_bacsi}</span></div>
                        <div class="mr-group"><span class="mr-label">Dịch vụ:</span> <span class="mr-value">${data.ten_dich_vu}</span></div>
                        
                        <div class="mr-group" style="background:#e8f5e9; padding:10px; border-radius:5px; margin-top:10px; border: 1px solid #c8e6c9;">
                            <span class="mr-label" style="color:#2e7d32;"><i class="fas fa-stethoscope"></i> Chẩn đoán / Kết quả:</span> 
                            <div class="mr-value" style="font-weight:500; margin-top:5px;">${data.chan_doan}</div>
                        </div>
                        
                        <div class="mr-group" style="background:#fff3e0; padding:10px; border-radius:5px; margin-top:10px; border: 1px solid #ffe0b2;">
                            <span class="mr-label" style="color:#ef6c00;"><i class="fas fa-notes-medical"></i> Ghi chú / Đơn thuốc:</span> 
                            <div class="mr-value" style="margin-top:5px;">${data.ghi_chu_bac_si || 'Không có ghi chú'}</div>
                        </div>
                    </div>`;
            }
        } catch (e) { contentDiv.innerHTML = '<div class="alert alert-error">Lỗi kết nối!</div>'; }
    }

    // --- LOGIC ĐẶT LỊCH ĐÃ SỬA ---
    document.addEventListener("DOMContentLoaded", function() {
        const docSelect = document.getElementById('modalDocSelect');
        const dateInput = document.getElementById('modalDateInput');
        const shiftSelect = document.getElementById('modalShiftSelect');
        const dateHint = document.getElementById('modalDateHint');
        const submitBtn = document.getElementById('modalSubmitBtn');
        const alertBox = document.getElementById('modalAlertBox');
        const alertMsg = document.getElementById('modalAlertMsg');

        
        docSelect.addEventListener('change', function() {
            if (this.value) {
                dateInput.disabled = false;
                dateHint.style.display = 'none';
                resetUI();
                if(dateInput.value) checkModalSchedule();
            } else {
                dateInput.disabled = true;
                dateHint.style.display = 'block';
                resetUI();
            }
        });

        
        dateInput.addEventListener('change', checkModalSchedule);

       
        shiftSelect.addEventListener('change', function() {
            submitBtn.disabled = (this.value === "");
        });

        async function checkModalSchedule() {
            const docId = docSelect.value;
            const dateVal = dateInput.value;
            if (!docId || !dateVal) return;

            
            shiftSelect.innerHTML = '<option>Đang kiểm tra...</option>';
            shiftSelect.disabled = true;
            submitBtn.disabled = true;
            alertBox.style.display = 'none';

            try {
                const res = await fetch(`../controllers/get_shifts_by_date.php?id=${docId}&date=${dateVal}`);
                const data = await res.json();
                
               
                shiftSelect.innerHTML = '<option value="">-- Chọn ca khám --</option>';

                // TRƯỜNG HỢP 1: BÁC SĨ BỊ KHÓA
                if (data.status === 'locked') {
                    alertBox.className = 'alert-box alert-error';
                    alertBox.style.display = 'block';
                    alertMsg.innerText = data.message;
                    shiftSelect.disabled = true;
                }
                // TRƯỜNG HỢP 2: NGHỈ PHÉP
                else if (data.status === 'on_leave') {
                    alertBox.className = 'alert-box alert-error';
                    alertBox.style.display = 'block';
                    alertMsg.innerHTML = `Bác sĩ <strong>nghỉ phép</strong> ngày ${formatDate(dateVal)}. Vui lòng chọn ngày khác.`;
                    
                    let opt = document.createElement('option'); opt.text = "Bác sĩ nghỉ";
                    shiftSelect.appendChild(opt);
                    shiftSelect.disabled = true;
                } 
                // TRƯỜNG HỢP 3: CÓ CA LÀM
                else if (data.status === 'has_schedule') {
                    data.shifts.forEach(shift => {
                        let opt = document.createElement('option');
                        opt.value = shift.value; opt.text = shift.label;
                        shiftSelect.appendChild(opt);
                    });
                    shiftSelect.disabled = false;
                } 
                // TRƯỜNG HỢP 4: LỊCH ĐẶC BIỆT
                else {
                    alertBox.className = 'alert-box alert-warning';
                    
                    alertBox.style.backgroundColor = '#fff3e0';
                    alertBox.style.color = '#e65100';
                    alertBox.style.border = '1px solid #ffb74d';
                    
                    alertMsg.innerHTML = `Bác sĩ <strong>không có lịch</strong> ngày ${formatDate(dateVal)}. <br>Bạn vẫn có thể đặt (Chờ duyệt).`;
                    alertBox.style.display = 'block';

                    let opt1 = document.createElement('option'); 
                    opt1.value = "Sang"; opt1.text = "Sáng (Dự kiến 08:00)";
                    
                    let opt2 = document.createElement('option'); 
                    opt2.value = "Chieu"; opt2.text = "Chiều (Dự kiến 13:00)";
                    
                    shiftSelect.appendChild(opt1); 
                    shiftSelect.appendChild(opt2);
                    
                    shiftSelect.disabled = false;
                }
            } catch (e) {
                console.error(e);
                shiftSelect.innerHTML = '<option>Lỗi kết nối</option>';
            }
        }

        function resetUI() {
            shiftSelect.innerHTML = '<option value="">-- Vui lòng chọn ngày --</option>';
            shiftSelect.disabled = true;
            submitBtn.disabled = true;
            alertBox.style.display = 'none';
        }

        function formatDate(str) {
            const p = str.split('-');
            return `${p[2]}/${p[1]}/${p[0]}`;
        }
    });
</script>
</body>
</html>