<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../views/dangnhap.php"); exit();
}

$user_id = $_SESSION['user_id'];

//  XỬ LÝ HỦY LỊCH (GET Request) ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel_appointment' && isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];

    // 1. Kiểm tra bảo mật: 
    // - Lịch phải của user này
    // - QUAN TRỌNG: Chỉ được hủy lịch có trạng thái 'cho_xac_nhan'
    $check = $conn->prepare("SELECT id_lichhen FROM lichhen WHERE id_lichhen = ? AND id_benhnhan = ? AND trang_thai = 'cho_xac_nhan'");
    $check->execute([$id_lichhen, $user_id]);

    if ($check->rowCount() > 0) {
        // 2. Thực hiện hủy
        $cancel = $conn->prepare("UPDATE lichhen SET trang_thai = 'huy' WHERE id_lichhen = ?");
        if ($cancel->execute([$id_lichhen])) {
            echo "<script>alert('Đã hủy yêu cầu đặt lịch!'); window.location.href='../views/khachhang.php';</script>";
        } else {
            echo "<script>alert('Lỗi hệ thống! Vui lòng thử lại.'); window.location.href='../views/khachhang.php';</script>";
        }
    } else {
        // Nếu không tìm thấy hoặc trạng thái không phải 'cho_xac_nhan'
        echo "<script>alert('Không thể hủy! Lịch hẹn này đã được Bác sĩ xác nhận hoặc không tồn tại.'); window.location.href='../views/khachhang.php';</script>";
    }
    exit();
}

// --- 1. CẬP NHẬT HỒ SƠ ---
if (isset($_POST['update_profile'])) {
    $name = $_POST['ten_day_du'];
    $phone = $_POST['sdt'];
    $email = $_POST['email'];
    $address = $_POST['dia_chi'] ?? null;
    $dob = $_POST['ngay_sinh'] ?? null;

    $sql = "UPDATE benhnhan SET ten_day_du = ?, sdt = ?, email = ?, dia_chi = ?, ngay_sinh = ? WHERE id_benhnhan = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$name, $phone, $email, $address, $dob, $user_id])) {
        $_SESSION['fullname'] = $name; 
        echo "<script>alert('Cập nhật hồ sơ thành công!'); window.location.href='../views/khachhang.php';</script>";
    } else {
        echo "<script>alert('Lỗi cập nhật!'); window.location.href='../views/khachhang.php';</script>";
    }
}

// --- 2. ĐỔI MẬT KHẨU ---
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    $stmt = $conn->prepare("SELECT mat_khau_hash FROM benhnhan WHERE id_benhnhan = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($old_pass, $user['mat_khau_hash'])) {
        if ($new_pass === $confirm_pass) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE benhnhan SET mat_khau_hash = ? WHERE id_benhnhan = ?");
            $update->execute([$new_hash, $user_id]);
            echo "<script>alert('Đổi mật khẩu thành công!'); window.location.href='../views/khachhang.php';</script>";
        } else {
            echo "<script>alert('Mật khẩu mới không khớp!'); window.location.href='../views/khachhang.php';</script>";
        }
    } else {
        echo "<script>alert('Mật khẩu cũ không đúng!'); window.location.href='../views/khachhang.php';</script>";
    }
}

// --- 3. ĐẶT LỊCH (ĐÃ SỬA LOGIC TÍNH GIỜ & QUEUE) ---
if (isset($_POST['book_appointment'])) {
    $id_bacsi = $_POST['id_bacsi'];
    $id_dichvu = $_POST['id_dichvu'];
    $date = $_POST['date'];
    $shift = $_POST['shift']; // 'Sang' hoặc 'Chieu'

    try {
        // 1. Kiểm tra nghỉ phép
        $sql_leave = "SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND trang_thai = 'da_duyet'";
        $stmt_leave = $conn->prepare($sql_leave);
        $stmt_leave->execute([$id_bacsi, $date]);
        if ($stmt_leave->rowCount() > 0) {
            echo "<script>alert('Lỗi: Bác sĩ đã nghỉ phép vào ngày này!'); window.location.href='../views/khachhang.php';</script>";
            exit();
        }

        // Định nghĩa khung giờ
        $start_shift_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $end_shift_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';

        // 2. Kiểm tra xem có Ca làm việc không
        $sql_check_work = "SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?";
        $stmt_check = $conn->prepare($sql_check_work);
        $stmt_check->execute([$id_bacsi, $start_shift_time, $date]);
        
        $final_datetime = null;
        $status = 'cho_xac_nhan';
        $msg_success = "";

        if ($stmt_check->rowCount() > 0) {
            // === TRƯỜNG HỢP A: CÓ LỊCH LÀM (Tính giờ chính xác theo Queue) ===
            
            // Tính tổng thời gian (phút) của các ca ĐÃ XÁC NHẬN trong buổi đó
            $sql_queue = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                          FROM lichhen lh 
                          JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                          WHERE lh.id_bacsi = ? 
                          AND DATE(lh.ngay_gio_hen) = ? 
                          AND lh.trang_thai = 'da_xac_nhan'
                          AND (
                             (HOUR(lh.ngay_gio_hen) < 12 AND ? = '08:00:00') OR 
                             (HOUR(lh.ngay_gio_hen) >= 12 AND ? = '13:00:00')
                          )";
            $stmt_queue = $conn->prepare($sql_queue);
            $stmt_queue->execute([$id_bacsi, $date, $start_shift_time, $start_shift_time]);
            $res_queue = $stmt_queue->fetch(PDO::FETCH_ASSOC);
            
            $waiting_minutes = (int)$res_queue['total_minutes'];
            
            // Tính timestamp bắt đầu = Đầu ca + Thời gian chờ
            $start_timestamp = strtotime("$date $start_shift_time");
            $real_start_time = $start_timestamp + ($waiting_minutes * 60);
            
            // Lấy thời gian của dịch vụ khách đang chọn
            $curr_service_time = $conn->query("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = $id_dichvu")->fetchColumn();
            
            // Kiểm tra quá giờ kết thúc ca
            if (($real_start_time + ($curr_service_time * 60)) > strtotime("$date $end_shift_time")) {
                echo "<script>alert('Ca khám này đã kín lịch (Không đủ thời gian)! Vui lòng chọn ngày khác.'); window.location.href='../views/khachhang.php';</script>";
                exit();
            }

            $final_datetime = date('Y-m-d H:i:s', $real_start_time);
            $msg_success = "Đặt lịch thành công! Giờ khám dự kiến: " . date('H:i', $real_start_time);

        } else {
            // === TRƯỜNG HỢP B: KHÔNG CÓ LỊCH (Lịch đặc biệt) ===
            // Vẫn cho đặt, giờ tạm tính là đầu ca. Admin sẽ xếp lại sau.
            $final_datetime = date('Y-m-d H:i:s', strtotime("$date $start_shift_time"));
            $msg_success = "Đã gửi yêu cầu! (Lịch hẹn đặc biệt đang chờ Admin/Bác sĩ xác nhận).";
        }

        // 3. Lưu vào Database
        $stmt_insert = $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, ?, 'benh_nhan')");
        
        if ($stmt_insert->execute([$user_id, $id_bacsi, $id_dichvu, $final_datetime, $status])) {
            echo "<script>alert('$msg_success'); window.location.href='../views/khachhang.php';</script>";
        } else {
            echo "<script>alert('Lỗi hệ thống!'); window.location.href='../views/khachhang.php';</script>";
        }

    } catch (Exception $e) {
        echo "<script>alert('Lỗi: ".$e->getMessage()."'); window.location.href='../views/khachhang.php';</script>";
    }
}
?>