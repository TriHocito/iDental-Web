<?php
// src/controllers/book_appointment.php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $phone    = $_POST['phone'];
    $email    = $_POST['email'];
    $id_bacsi = $_POST['id_bacsi']; 
    $id_dichvu= $_POST['id_dichvu'];
    $date     = $_POST['date']; 
    $shift    = $_POST['shift']; 

    try {
        // 1. CHECK TÀI KHOẢN
        $check = $conn->prepare("SELECT id_benhnhan FROM benhnhan WHERE sdt = ? OR email = ?");
        $check->execute([$phone, $email]);
        if ($check->rowCount() > 0) {
            echo "<script>alert('Tài khoản đã tồn tại! Vui lòng đăng nhập.'); window.location.href = '../views/dangnhap.php';</script>";
            exit();
        }

        // 2. CHECK NGHỈ PHÉP (Chặn tuyệt đối)
        $ca_check = ($shift == 'Sang' || $shift == 'Chieu') ? $shift : 'Sang'; // Fallback
        $sql_leave = "SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND trang_thai = 'da_duyet'";
        $stmt_leave = $conn->prepare($sql_leave);
        $stmt_leave->execute([$id_bacsi, $date]);
        if ($stmt_leave->rowCount() > 0) {
            echo "<script>alert('Bác sĩ đã nghỉ phép vào ngày này. Vui lòng chọn ngày khác.'); window.history.back();</script>";
            exit();
        }

        // 3. CHECK LỊCH LÀM VIỆC & XÁC ĐỊNH TRẠNG THÁI
        $start_hour = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $sql_work = "SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?";
        $stmt_work = $conn->prepare($sql_work);
        $stmt_work->execute([$id_bacsi, $start_hour, $date]);

        if ($stmt_work->rowCount() > 0) {
            // Có lịch -> Đặt bình thường (Tính giờ)
            $status = 'cho_xac_nhan'; // Theo quy trình BN đặt luôn là chờ xác nhận (hoặc da_xac_nhan tùy bạn, nhưng an toàn là chờ)
            $msg = "Đặt lịch thành công! Vui lòng chờ xác nhận.";
            
            // Tính giờ dự kiến (Logic cũ)
            $end_hour = ($shift == 'Sang') ? '12:00:00' : '17:00:00';
            $sql_last = "SELECT MAX(ngay_gio_hen) as last_time FROM lichhen WHERE id_bacsi = ? AND DATE(ngay_gio_hen) = ? AND TIME(ngay_gio_hen) >= ? AND TIME(ngay_gio_hen) < ?";
            $stmt_last = $conn->prepare($sql_last);
            $stmt_last->execute([$id_bacsi, $date, $start_hour, $end_hour]);
            $last_appt = $stmt_last->fetch(PDO::FETCH_ASSOC);
            
            if ($last_appt['last_time']) $ts = strtotime($last_appt['last_time']) + (20 * 60);
            else $ts = strtotime("$date $start_hour");
            
            if ($ts + (20*60) > strtotime("$date $end_hour")) {
                echo "<script>alert('Ca này đã kín lịch.'); window.history.back();</script>"; exit();
            }
            $final_datetime = date('Y-m-d H:i:s', $ts);
            
        } else {
            // KHÔNG CÓ LỊCH -> Vẫn cho đặt nhưng là "Yêu cầu đặc biệt"
            $status = 'cho_xac_nhan';
            $msg = "Bạn đã gửi yêu cầu đặt lịch vào ngày bác sĩ chưa có lịch. Vui lòng chờ Admin/Bác sĩ duyệt.";
            // Giờ tạm tính là đầu ca
            $final_datetime = date('Y-m-d H:i:s', strtotime("$date $start_hour"));
        }

        // 4. INSERT DATA
        $conn->beginTransaction();
        
        $rand_pass = rand(100000, 999999);
        $pass_hash = password_hash($rand_pass, PASSWORD_DEFAULT);
        
        $stmt_u = $conn->prepare("INSERT INTO benhnhan (ten_day_du, sdt, email, mat_khau_hash) VALUES (?, ?, ?, ?)");
        $stmt_u->execute([$fullname, $phone, $email, $pass_hash]);
        $uid = $conn->lastInsertId();

        $stmt_a = $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, ?, 'benh_nhan')");
        $stmt_a->execute([$uid, $id_bacsi, $id_dichvu, $final_datetime, $status]);

        sendNewAccountInfo($email, $fullname, $phone, $rand_pass);

        $conn->commit();

        echo "<script>alert('$msg'); window.location.href = '../views/index.php';</script>";

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo "Lỗi hệ thống: " . $e->getMessage();
    }
}
?>