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

// 1.1 Bác sĩ DUYỆT lịch hẹn (CẬP NHẬT LOGIC TÍNH GIỜ)
if (isset($_GET['action']) && $_GET['action'] == 'approve_appointment' && isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];

    // 1. Lấy thông tin lịch hẹn hiện tại
    $sql_get = "SELECT * FROM lichhen WHERE id_lichhen = ? AND id_bacsi = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->execute([$id_lichhen, $doctor_id]);
    $appt = $stmt_get->fetch(PDO::FETCH_ASSOC);
    
    if ($appt) {
        $date = date('Y-m-d', strtotime($appt['ngay_gio_hen']));
        $hour = date('H', strtotime($appt['ngay_gio_hen']));
        
        // Xác định khung giờ ca làm việc
        $start_shift_time = ($hour < 12) ? '08:00:00' : '13:00:00';
        $end_shift_time   = ($hour < 12) ? '12:00:00' : '17:00:00';
        
        // 2. Kiểm tra xem Bác sĩ có Ca trực (Lịch làm việc) ngày hôm đó chưa
        // (Bác sĩ KHÔNG được tự tạo lịch, phải có Admin tạo trước hoặc có sẵn)
        $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
        $check_work->execute([$doctor_id, $start_shift_time, $date]);
        
        if ($check_work->rowCount() == 0) {
            echo "<script>alert('LỖI: Bạn chưa có lịch làm việc (Ca trực) vào ngày này. Vui lòng liên hệ Admin để xếp lịch trước khi duyệt.'); window.location.href='../views/bacsi.php';</script>";
            exit();
        }

        // 3. TÍNH TOÁN GIỜ KHÁM DỰ KIẾN (QUEUE SYSTEM)
        // Lấy tổng phút của các lịch ĐÃ XÁC NHẬN trong cùng ca, cùng ngày (trừ lịch đang xét)
        $sql_calc = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                     FROM lichhen lh 
                     JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                     WHERE lh.id_bacsi = ? 
                     AND DATE(lh.ngay_gio_hen) = ? 
                     AND lh.trang_thai = 'da_xac_nhan'
                     AND lh.id_lichhen != ? 
                     AND (
                        (HOUR(lh.ngay_gio_hen) < 12 AND ? = '08:00:00') OR 
                        (HOUR(lh.ngay_gio_hen) >= 12 AND ? = '13:00:00')
                     )";
        $stmt_calc = $conn->prepare($sql_calc);
        $stmt_calc->execute([$doctor_id, $date, $id_lichhen, $start_shift_time, $start_shift_time]);
        $result_calc = $stmt_calc->fetch(PDO::FETCH_ASSOC);
        
        $accumulated_minutes = (int)$result_calc['total_minutes']; // Tổng phút đã xếp trước đó
        
        // Thời gian bắt đầu thực tế = Giờ đầu ca + Tổng phút đã xếp
        $start_timestamp = strtotime("$date $start_shift_time");
        $real_start_time = $start_timestamp + ($accumulated_minutes * 60);
        
        // Lấy thời gian của dịch vụ hiện tại
        $curr_service_time = $conn->query("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = " . $appt['id_dichvu'])->fetchColumn();
        
        // 4. Kiểm tra xem có vượt quá giờ kết thúc ca không
        if (($real_start_time + ($curr_service_time * 60)) > strtotime("$date $end_shift_time")) {
             echo "<script>alert('CẢNH BÁO: Ca làm việc này đã kín! Không đủ thời gian để nhận thêm khách.'); window.location.href='../views/bacsi.php';</script>"; 
             exit();
        }

        $final_datetime = date('Y-m-d H:i:s', $real_start_time);

        // 5. Cập nhật DB: Trạng thái -> da_xac_nhan & Giờ hẹn -> Giờ tính toán
        $update_stmt = $conn->prepare("UPDATE lichhen SET trang_thai = 'da_xac_nhan', ngay_gio_hen = ? WHERE id_lichhen = ?");
        $update_stmt->execute([$final_datetime, $id_lichhen]);

        // 6. Gửi mail thông báo
        $sql_mail = "SELECT bn.email, bn.ten_day_du, bs.ten_day_du AS ten_bacsi, dv.ten_dich_vu, lh.ngay_gio_hen 
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
        
        echo "<script>alert('Đã xác nhận lịch hẹn! Giờ khám dự kiến: " . date('H:i', $real_start_time) . "'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Lỗi: Bạn không có quyền duyệt lịch này!'); window.location.href='../views/bacsi.php';</script>";
    }
    exit();
}

// 1.2 Bác sĩ TỪ CHỐI lịch hẹn (Giữ nguyên)
if (isset($_GET['action']) && $_GET['action'] == 'reject_appointment' && isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];
    
    $check = $conn->prepare("SELECT id_lichhen FROM lichhen WHERE id_lichhen = ? AND id_bacsi = ?");
    $check->execute([$id_lichhen, $doctor_id]);
    
    if ($check->rowCount() > 0) {
        $conn->prepare("UPDATE lichhen SET trang_thai = 'huy' WHERE id_lichhen = ?")->execute([$id_lichhen]);
        echo "<script>alert('Đã từ chối lịch hẹn!'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Lỗi: Không tìm thấy lịch!'); window.location.href='../views/bacsi.php';</script>";
    }
    exit();
}

// ===========================================================================
// PHẦN 2: XỬ LÝ POST (Form submit)
// ===========================================================================

// 2.1 THÊM LỊCH HẸN (Logic Check lịch)
if (isset($_POST['add_appointment'])) {
    $id_benhnhan = $_POST['id_benhnhan'];
    $id_dichvu = $_POST['id_dichvu'];
    $date = $_POST['date'];
    $time = $_POST['time']; 
    $ngay_gio = $date . ' ' . $time;
    
    // Xác định ca để kiểm tra
    $hour = (int)substr($time, 0, 2);
    $shift_start = ($hour < 12) ? '08:00:00' : '13:00:00';

    // Kiểm tra xem bác sĩ có lịch làm việc vào ngày/ca này không
    $sql_check = "SELECT id_lichlamviec FROM lichlamviec 
                  WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$doctor_id, $shift_start, $date]);
    
    if ($stmt_check->rowCount() > 0) {
        // Có lịch làm -> Xác nhận luôn
        $status = 'da_xac_nhan';
        $msg = "Đã thêm lịch hẹn thành công (Đã xác nhận)!";
    } else {
        // Không có lịch làm -> Chờ Admin xử lý (hoặc chờ sắp xếp lịch)
        $status = 'cho_xac_nhan';
        $msg = "Bạn chưa có ca trực vào thời gian này. Lịch hẹn đang ở trạng thái Chờ duyệt.";
    }
    
    $sql = "INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) 
            VALUES (?, ?, ?, ?, ?, 'bac_si')";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$id_benhnhan, $doctor_id, $id_dichvu, $ngay_gio, $status])) {
        echo "<script>alert('$msg'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Lỗi thêm lịch!'); window.location.href='../views/bacsi.php';</script>";
    }
}

// 2.2 XỬ LÝ YÊU CẦU NGHỈ PHÉP
if (isset($_POST['request_leave'])) {
    $date = $_POST['leave_date'];
    $shift = $_POST['leave_shift']; 
    $reason = $_POST['leave_reason'];
    
    $start_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
    
    // Check xem có lịch làm việc để nghỉ không
    $check = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi=? AND ngay_hieu_luc=? AND gio_bat_dau=?");
    $check->execute([$doctor_id, $date, $start_time]);
    
    if ($check->rowCount() == 0) {
        echo "<script>alert('Lỗi: Bạn không có lịch làm việc ngày này, không cần xin nghỉ!'); window.location.href='../views/bacsi.php';</script>";
    } else {
        // Check trùng lặp yêu cầu
        $dup = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi=? AND ngay_nghi=? AND ca_nghi=?");
        $dup->execute([$doctor_id, $date, $shift]);
        
        if ($dup->rowCount() > 0) {
            echo "<script>alert('Bạn đã gửi yêu cầu cho ca này rồi!'); window.location.href='../views/bacsi.php';</script>";
        } else {
            $sql = "INSERT INTO yeucaunghi (id_bacsi, ngay_nghi, ca_nghi, ly_do, trang_thai) VALUES (?, ?, ?, ?, 'cho_duyet')";
            if ($conn->prepare($sql)->execute([$doctor_id, $date, $shift, $reason])) {
                echo "<script>alert('Đã gửi yêu cầu! Vui lòng chờ Admin duyệt.'); window.location.href='../views/bacsi.php';</script>";
            }
        }
    }
}

// 2.3 CẬP NHẬT HỒ SƠ & ẢNH ĐẠI DIỆN
if (isset($_POST['update_profile'])) {
    $ten_day_du = $_POST['ten_day_du'];
    $sdt = $_POST['sdt'];
    $chuyen_khoa = $_POST['chuyen_khoa'];
    
    $sql = "UPDATE bacsi SET ten_day_du = ?, sdt = ?, chuyen_khoa = ? WHERE id_bacsi = ?";
    $params = [$ten_day_du, $sdt, $chuyen_khoa, $doctor_id];
    
    // Xử lý Upload Ảnh
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = "doc_" . $doctor_id . "_" . time() . "." . $ext;
            $upload_path = "../assets/img/" . $new_filename;
            
            // Tạo thư mục nếu chưa có
            if (!file_exists("../assets/img")) mkdir("../assets/img", 0777, true);

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                $sql = "UPDATE bacsi SET ten_day_du = ?, sdt = ?, chuyen_khoa = ?, link_anh_dai_dien = ? WHERE id_bacsi = ?";
                $params = [$ten_day_du, $sdt, $chuyen_khoa, $upload_path, $doctor_id];
            }
        }
    }

    $stmt = $conn->prepare($sql);
    if ($stmt->execute($params)) {
        $_SESSION['fullname'] = $ten_day_du;
        echo "<script>alert('Cập nhật hồ sơ thành công!'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Lỗi cập nhật!'); window.location.href='../views/bacsi.php';</script>";
    }
}

// 2.4 ĐỔI MẬT KHẨU
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    if ($new_pass !== $confirm_pass) {
        echo "<script>alert('Mật khẩu mới không khớp!'); window.location.href='../views/bacsi.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("SELECT mat_khau_hash FROM bacsi WHERE id_bacsi = ?");
    $stmt->execute([$doctor_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($current_pass, $user['mat_khau_hash'])) {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->prepare("UPDATE bacsi SET mat_khau_hash = ? WHERE id_bacsi = ?")->execute([$new_hash, $doctor_id]);
        echo "<script>alert('Đổi mật khẩu thành công!'); window.location.href='../views/bacsi.php';</script>";
    } else {
        echo "<script>alert('Mật khẩu cũ không đúng!'); window.location.href='../views/bacsi.php';</script>";
    }
}

// 2.5 LƯU BỆNH ÁN (HOÀN THÀNH KHÁM)
if (isset($_POST['save_medical_record'])) {
    $id_lichhen = $_POST['id_lichhen'];
    $chuan_doan = $_POST['chuan_doan'];
    $ghi_chu = $_POST['ghi_chu'];
    
    try {
        $conn->beginTransaction();
        
        // Cập nhật trạng thái lịch hẹn -> Hoàn thành
        $conn->prepare("UPDATE lichhen SET trang_thai = 'hoan_thanh' WHERE id_lichhen = ?")->execute([$id_lichhen]);
        
        // Lưu bệnh án
        $check = $conn->prepare("SELECT id_benhan FROM benhan WHERE id_lichhen = ?");
        $check->execute([$id_lichhen]);
        
        if ($check->rowCount() > 0) {
            $conn->prepare("UPDATE benhan SET chan_doan = ?, ghi_chu_bac_si = ? WHERE id_lichhen = ?")->execute([$chuan_doan, $ghi_chu, $id_lichhen]);
        } else {
            $conn->prepare("INSERT INTO benhan (id_lichhen, chan_doan, ghi_chu_bac_si) VALUES (?, ?, ?)")->execute([$id_lichhen, $chuan_doan, $ghi_chu]);
        }
        
        $conn->commit();
        echo "<script>alert('Đã lưu bệnh án & Hoàn tất ca khám!'); window.location.href='../views/bacsi.php';</script>";
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Lỗi: " . $e->getMessage();
    }
}

// 2.6 TIẾP NHẬN KHÁCH VÃNG LAI
if (isset($_POST['add_walkin'])) {
    $name = $_POST['ten_day_du'];
    $phone = $_POST['sdt'];
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $id_dichvu = $_POST['id_dichvu'];
    
    try {
        $conn->beginTransaction();

        // Kiểm tra hoặc tạo bệnh nhân
        $stmt_check = $conn->prepare("SELECT id_benhnhan FROM benhnhan WHERE sdt = ?");
        $stmt_check->execute([$phone]);
        $pat = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($pat) {
            $id_benhnhan = $pat['id_benhnhan'];
        } else {
            $pass_hash = password_hash('123456', PASSWORD_DEFAULT);
            $conn->prepare("INSERT INTO benhnhan (ten_day_du, sdt, email, mat_khau_hash) VALUES (?, ?, ?, ?)")->execute([$name, $phone, $email, $pass_hash]);
            $id_benhnhan = $conn->lastInsertId();
        }
        
        // Tạo lịch hẹn (Xác nhận ngay)
        $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, NOW(), 'da_xac_nhan', 'bac_si')")->execute([$id_benhnhan, $doctor_id, $id_dichvu]);
        
        $conn->commit();
        echo "<script>alert('Đã tiếp nhận khách thành công!'); window.location.href='../views/bacsi.php';</script>";
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Lỗi: " . $e->getMessage();
    }
}
?>