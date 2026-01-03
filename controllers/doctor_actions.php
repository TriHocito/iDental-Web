<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php'; 

// Kiểm tra quyền Bác sĩ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../views/dangnhap.php"); 
    exit();
}

$doctor_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// ===========================================================================
// PHẦN 1: XỬ LÝ GET (Duyệt/Hủy lịch hẹn từ danh sách)
// ===========================================================================

// 1.1 Bác sĩ DUYỆT lịch hẹn
if (isset($_GET['action']) && $_GET['action'] == 'approve_appointment' && isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];

    // 1. Lấy thông tin lịch hẹn hiện tại + Bảo mật quyền sở hữu
    $sql_get = "SELECT lh.*, dv.thoi_gian_phut FROM lichhen lh 
                JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                WHERE lh.id_lichhen = ? AND lh.id_bacsi = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->execute([$id_lichhen, $doctor_id]);
    $appt = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if ($appt) {
        $date = date('Y-m-d', strtotime($appt['ngay_gio_hen']));
        $hour = date('H', strtotime($appt['ngay_gio_hen']));
        
        // Xác định khung giờ ca làm việc
        $shift = ($hour < 12) ? 'Sang' : 'Chieu';
        $start_shift_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $end_shift_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';
        
        // 2. Thắt chặt kiểm tra: Có Ca trực và KHÔNG nghỉ phép
        $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
        $check_work->execute([$doctor_id, $start_shift_time, $date]);

        $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'");
        $check_leave->execute([$doctor_id, $date, $shift]);
        
        if ($check_work->rowCount() == 0 || $check_leave->rowCount() > 0) {
            $err_msg = ($check_leave->rowCount() > 0) ? "Bạn đã nghỉ phép ca này." : "Bạn không có lịch trực ca này.";
            echo "<script>alert('LỖI: $err_msg Không thể duyệt lịch.'); window.location.href='../views/bacsi.php';</script>";
            exit();
        }

        // 3. ĐỒNG BỘ CÔNG THỨC HÀNG ĐỢI (QUEUE): Đã xác nhận + Hoàn thành
        $sql_calc = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                     FROM lichhen lh 
                     JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                     WHERE lh.id_bacsi = ? 
                     AND DATE(lh.ngay_gio_hen) = ? 
                     AND lh.trang_thai IN ('da_xac_nhan', 'hoan_thanh')
                     AND lh.id_lichhen != ? 
                     AND (
                        (HOUR(lh.ngay_gio_hen) < 12 AND ? = '08:00:00') OR 
                        (HOUR(lh.ngay_gio_hen) >= 12 AND ? = '13:00:00')
                     )";
        $stmt_calc = $conn->prepare($sql_calc);
        $stmt_calc->execute([$doctor_id, $date, $id_lichhen, $start_shift_time, $start_shift_time]);
        $accumulated_minutes = (int)$stmt_calc->fetch(PDO::FETCH_ASSOC)['total_minutes'];
        
        $start_timestamp = strtotime("$date $start_shift_time");
        // Giờ khám = Giờ bắt đầu ca + Tổng thời gian đã đặt/khám
        $real_start_time = $start_timestamp + ($accumulated_minutes * 60);
        
        if (($real_start_time + ($appt['thoi_gian_phut'] * 60)) > strtotime("$date $end_shift_time")) {
             echo "<script>alert('LỖI: Ca trực đã đầy lịch, không đủ thời gian duyệt thêm.'); window.location.href='../views/bacsi.php';</script>"; 
             exit();
        }

        $final_datetime = date('Y-m-d H:i:s', $real_start_time);

        // 4. Cập nhật DB
        $update_stmt = $conn->prepare("UPDATE lichhen SET trang_thai = 'da_xac_nhan', ngay_gio_hen = ? WHERE id_lichhen = ? AND id_bacsi = ?");
        $update_stmt->execute([$final_datetime, $id_lichhen, $doctor_id]);

        // 5. TỰ ĐỘNG GỬI MAIL THÔNG BÁO
        $sql_mail = "SELECT bn.email, bn.ten_day_du, bs.ten_day_du AS ten_bacsi, dv.ten_dich_vu 
                     FROM lichhen lh
                     JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan
                     JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
                     JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                     WHERE lh.id_lichhen = ?";
        $stmt_m = $conn->prepare($sql_mail);
        $stmt_m->execute([$id_lichhen]);
        $data_mail = $stmt_m->fetch(PDO::FETCH_ASSOC);

        if ($data_mail && !empty($data_mail['email'])) {
            $dateStr = date('H:i d/m/Y', strtotime($final_datetime));
            sendAppointmentConfirmation($data_mail['email'], $data_mail['ten_day_du'], $dateStr, $data_mail['ten_bacsi'], $data_mail['ten_dich_vu']);
        }
        
        echo "<script>alert('Đã duyệt lịch hẹn! Giờ khám dự kiến: " . date('H:i', $real_start_time) . "'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Lỗi: Không tìm thấy lịch hoặc không có quyền!'); window.location.href='../views/bacsi.php';</script>";
    }
    exit();
}

// 1.2 Bác sĩ TỪ CHỐI lịch hẹn (Giữ nguyên cấu trúc)
if (isset($_GET['action']) && $_GET['action'] == 'reject_appointment' && isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];
    $check = $conn->prepare("SELECT id_lichhen FROM lichhen WHERE id_lichhen = ? AND id_bacsi = ?");
    $check->execute([$id_lichhen, $doctor_id]);
    if ($check->rowCount() > 0) {
        $conn->prepare("UPDATE lichhen SET trang_thai = 'huy' WHERE id_lichhen = ?")->execute([$id_lichhen]);
        echo "<script>alert('Đã từ chối lịch hẹn!'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Lỗi: Không tìm thấy lịch hoặc không có quyền!'); window.location.href='../views/bacsi.php';</script>";
    }
    exit();
}

// ===========================================================================
// PHẦN 2: XỬ LÝ POST (Form submit)
// ===========================================================================

// 2.1 ĐĂNG KÝ LỊCH LÀM VIỆC (Giữ nguyên cấu trúc JSON)
if (isset($_POST['submit_schedule_request'])) {
    $doctor_name = $_SESSION['fullname'] ?? 'Bác sĩ #' . $doctor_id;
    $requests = $_POST['reg'] ?? [];
    if (empty($requests)) {
        echo "<script>alert('Bạn chưa chọn ca làm việc nào!'); window.history.back();</script>";
        exit();
    }
    $new_request_data = [
        'id' => uniqid(),
        'doctor_id' => $doctor_id,
        'doctor_name' => $doctor_name,
        'created_at' => date('Y-m-d H:i:s'),
        'shifts' => []
    ];
    foreach ($requests as $date => $shifts) {
        foreach ($shifts as $shift_name => $val) {
            if ($val == 1) {
                $new_request_data['shifts'][] = ['date' => $date, 'shift' => $shift_name, 'status' => 'pending'];
            }
        }
    }
    $file_path = '../data/schedule_requests.json';
    if (!file_exists('../data')) { mkdir('../data', 0777, true); }
    $current_data = (file_exists($file_path)) ? json_decode(file_get_contents($file_path), true) ?? [] : [];
    $current_data[] = $new_request_data;
    if (file_put_contents($file_path, json_encode($current_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo "<script>alert('Đã gửi yêu cầu đăng ký lịch!'); window.location.href='../views/bacsi.php?section=my-schedule';</script>";
    }
}

// 2.2 THÊM LỊCH HẸN (Dành cho BN cũ - Áp dụng QUEUE)
if (isset($_POST['add_appointment'])) {
    $id_benhnhan = $_POST['id_benhnhan'];
    $id_dichvu = $_POST['id_dichvu'];
    $date = $_POST['date'];
    $time = $_POST['time']; 
    
    $hour = (int)substr($time, 0, 2);
    $shift = ($hour < 12) ? 'Sang' : 'Chieu';
    $start_shift_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
    $end_shift_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';

    // 1. Thắt chặt kiểm tra ca trực & nghỉ phép
    $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
    $check_work->execute([$doctor_id, $start_shift_time, $date]);
    
    $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'");
    $check_leave->execute([$doctor_id, $date, $shift]);

    if ($check_leave->rowCount() > 0) {
        echo "<script>alert('LỖI: Bạn đã nghỉ phép vào ca này!'); window.history.back();</script>";
        exit();
    }

    $status = ($check_work->rowCount() > 0) ? 'da_xac_nhan' : 'cho_xac_nhan';
    $final_dt = $date . ' ' . $time;

    // 2. Nếu có lịch trực -> Tự động tính slot theo Queue
    if ($status == 'da_xac_nhan') {
        $sql_calc = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                     FROM lichhen lh JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                     WHERE lh.id_bacsi = ? AND DATE(lh.ngay_gio_hen) = ? AND lh.trang_thai IN ('da_xac_nhan', 'hoan_thanh')
                     AND ((HOUR(lh.ngay_gio_hen) < 12 AND ? = '08:00:00') OR (HOUR(lh.ngay_gio_hen) >= 12 AND ? = '13:00:00'))";
        $st_c = $conn->prepare($sql_calc);
        $st_c->execute([$doctor_id, $date, $start_shift_time, $start_shift_time]);
        $wait = (int)$st_c->fetchColumn();
        
        $calc_ts = strtotime("$date $start_shift_time") + ($wait * 60);
        $final_dt = date('Y-m-d H:i:s', $calc_ts);
        
        $svc_time = $conn->query("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu=$id_dichvu")->fetchColumn();
        if (($calc_ts + ($svc_time * 60)) > strtotime("$date $end_shift_time")) {
            echo "<script>alert('LỖI: Ca trực đã đầy lịch!'); window.history.back();</script>"; exit();
        }
    }
    
    $sql = "INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, ?, 'bac_si')";
    if ($conn->prepare($sql)->execute([$id_benhnhan, $doctor_id, $id_dichvu, $final_dt, $status])) {
        echo "<script>alert('Thêm lịch thành công!'); window.location.href='../views/bacsi.php';</script>";
    }
}

// 2.3 XỬ LÝ YÊU CẦU NGHỈ PHÉP (Giữ nguyên cấu trúc)
if (isset($_POST['request_leave'])) {
    $date = $_POST['leave_date']; $shift = $_POST['leave_shift']; $reason = $_POST['leave_reason'];
    $start_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
    $check = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi=? AND ngay_hieu_luc=? AND gio_bat_dau=?");
    $check->execute([$doctor_id, $date, $start_time]);
    if ($check->rowCount() == 0) {
        echo "<script>alert('Lỗi: Bạn không có lịch trực ngày này!'); window.history.back();</script>";
    } else {
        $sql = "INSERT INTO yeucaunghi (id_bacsi, ngay_nghi, ca_nghi, ly_do, trang_thai) VALUES (?, ?, ?, ?, 'cho_duyet')";
        if ($conn->prepare($sql)->execute([$doctor_id, $date, $shift, $reason])) {
            echo "<script>alert('Đã gửi yêu cầu nghỉ phép!'); window.location.href='../views/bacsi.php';</script>";
        }
    }
}

// 2.4 CẬP NHẬT HỒ SƠ & ẢNH ĐẠI DIỆN (Giữ nguyên cấu trúc)
if (isset($_POST['update_profile'])) {
    $ten_day_du = $_POST['ten_day_du']; $sdt = $_POST['sdt']; $email = $_POST['email']; $chuyen_khoa = $_POST['chuyen_khoa'];
    $sql = "UPDATE bacsi SET ten_day_du = ?, sdt = ?, email = ?, chuyen_khoa = ? WHERE id_bacsi = ?";
    $params = [$ten_day_du, $sdt, $email, $chuyen_khoa, $doctor_id];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $upload_path = "../assets/img/doc_" . $doctor_id . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            $sql = "UPDATE bacsi SET ten_day_du = ?, sdt = ?, email = ?, chuyen_khoa = ?, link_anh_dai_dien = ? WHERE id_bacsi = ?";
            $params = [$ten_day_du, $sdt, $email, $chuyen_khoa, $upload_path, $doctor_id];
        }
    }
    if ($conn->prepare($sql)->execute($params)) {
        $_SESSION['fullname'] = $ten_day_du;
        echo "<script>alert('Cập nhật thành công!'); window.location.href='../views/bacsi.php?section=profile';</script>";
    }
}

// 2.5 ĐỔI MẬT KHẨU (Giữ nguyên)
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_pass']; $new_pass = $_POST['new_pass']; $confirm_pass = $_POST['confirm_pass'];
    if ($new_pass !== $confirm_pass) { echo "<script>alert('Mật khẩu không khớp!'); window.history.back();</script>"; exit(); }
    $stmt = $conn->prepare("SELECT mat_khau_hash FROM bacsi WHERE id_bacsi = ?");
    $stmt->execute([$doctor_id]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($current_pass, $user['mat_khau_hash'])) {
        $conn->prepare("UPDATE bacsi SET mat_khau_hash = ? WHERE id_bacsi = ?")->execute([password_hash($new_pass, PASSWORD_DEFAULT), $doctor_id]);
        echo "<script>alert('Đã đổi mật khẩu!'); window.location.href='../views/bacsi.php';</script>";
    } else { echo "<script>alert('Mật khẩu cũ sai!'); window.history.back();</script>"; }
}

// 2.6 LƯU BỆNH ÁN (Bảo mật theo quyền sở hữu)
if (isset($_POST['save_medical_record'])) {
    $id_lichhen = $_POST['id_lichhen']; $chuan_doan = $_POST['chuan_doan']; $ghi_chu = $_POST['ghi_chu'];
    // Kiểm tra quyền sở hữu lịch hẹn
    $check_owner = $conn->prepare("SELECT id_lichhen FROM lichhen WHERE id_lichhen = ? AND id_bacsi = ?");
    $check_owner->execute([$id_lichhen, $doctor_id]);
    if ($check_owner->rowCount() == 0) {
        echo "<script>alert('LỖI: Bạn không có quyền xử lý bệnh án này!'); window.history.back();</script>"; exit();
    }
    try {
        $conn->beginTransaction();
        $conn->prepare("UPDATE lichhen SET trang_thai = 'hoan_thanh' WHERE id_lichhen = ?")->execute([$id_lichhen]);
        $conn->prepare("INSERT INTO benhan (id_lichhen, chan_doan, ghi_chu_bac_si) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE chan_doan=?, ghi_chu_bac_si=?")->execute([$id_lichhen, $chuan_doan, $ghi_chu, $chuan_doan, $ghi_chu]);
        $conn->commit();
        echo "<script>alert('Đã lưu bệnh án!'); window.location.href='../views/bacsi.php';</script>";
    } catch (Exception $e) { $conn->rollBack(); echo "Lỗi: " . $e->getMessage(); }
}

// 2.7 TIẾP NHẬN KHÁCH VÃNG LAI (Áp dụng QUEUE & EMAIL)
if (isset($_POST['add_walkin'])) {
    $name = $_POST['ten_day_du']; $phone = $_POST['sdt']; $email = !empty($_POST['email']) ? $_POST['email'] : null; $id_dichvu = $_POST['id_dichvu'];
    
    // 1. Thắt chặt kiểm tra ca trực & nghỉ phép tại thời điểm hiện tại
    $current_h = (int)date('H');
    $shift = ($current_h < 12) ? 'Sang' : 'Chieu';
    $date = date('Y-m-d');
    $start_shift_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
    $end_shift_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';

    $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
    $check_work->execute([$doctor_id, $start_shift_time, $date]);
    
    $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'");
    $check_leave->execute([$doctor_id, $date, $shift]);

    if ($check_work->rowCount() == 0 || $check_leave->rowCount() > 0) {
        $err = ($check_leave->rowCount() > 0) ? "Bạn đang trong ca nghỉ phép." : "Bạn không có lịch trực lúc này.";
        echo "<script>alert('LỖI: $err Không thể tiếp nhận khách vãng lai.'); window.history.back();</script>"; exit();
    }

    try {
        $conn->beginTransaction();
        $stmt_check = $conn->prepare("SELECT id_benhnhan FROM benhnhan WHERE sdt = ?");
        $stmt_check->execute([$phone]);
        $pat = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($pat) { $id_benhnhan = $pat['id_benhnhan']; } 
        else {
            $pass_hash = password_hash('123456', PASSWORD_DEFAULT);
            $conn->prepare("INSERT INTO benhnhan (ten_day_du, sdt, email, mat_khau_hash) VALUES (?, ?, ?, ?)")->execute([$name, $phone, $email, $pass_hash]);
            $id_benhnhan = $conn->lastInsertId();
        }
        
        // 2. Tính toán Slot Queue
        $sql_q = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) FROM lichhen lh JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                  WHERE lh.id_bacsi = ? AND DATE(lh.ngay_gio_hen) = ? AND lh.trang_thai IN ('da_xac_nhan', 'hoan_thanh')
                  AND TIME(lh.ngay_gio_hen) >= ? AND TIME(lh.ngay_gio_hen) < ?";
        $st_q = $conn->prepare($sql_q); $st_q->execute([$doctor_id, $date, $start_shift_time, $end_shift_time]);
        $wait = (int)$st_q->fetchColumn();
        
        $anchor = max(strtotime("$date $start_shift_time"), time() + 900); // Tối thiểu là 15p sau hiện tại
        $real_start = $anchor + ($wait * 60);
        $final_dt = date('Y-m-d H:i:s', $real_start);

        $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, 'da_xac_nhan', 'bac_si')")->execute([$id_benhnhan, $doctor_id, $id_dichvu, $final_dt]);
        
        // 3. Gửi Mail xác nhận
        if (!empty($email)) {
            $svc_name = $conn->query("SELECT ten_dich_vu FROM dichvu WHERE id_dichvu=$id_dichvu")->fetchColumn();
            sendAppointmentConfirmation($email, $name, date('H:i d/m/Y', $real_start), $_SESSION['fullname'], $svc_name);
        }

        $conn->commit();
        echo "<script>alert('Đã tiếp nhận! Giờ khám dự kiến: ".date('H:i', $real_start)."'); window.location.href='../views/bacsi.php';</script>";
    } catch (Exception $e) { $conn->rollBack(); echo "Lỗi: " . $e->getMessage(); }
}
?>