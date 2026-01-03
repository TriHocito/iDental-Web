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

        // [BỔ SUNG] KIỂM TRA TRẠNG THÁI BÁC SĨ (CHẶN NẾU BỊ KHÓA)
        $stmt_doc_status = $conn->prepare("SELECT trang_thai FROM bacsi WHERE id_bacsi = ?");
        $stmt_doc_status->execute([$id_bacsi]);
        if ($stmt_doc_status->fetchColumn() == 0) {
            echo "<script>alert('Bác sĩ này hiện đang tạm ngưng nhận lịch. Vui lòng chọn bác sĩ khác.'); window.history.back();</script>";
            exit();
        }

        // 2. CHECK NGHỈ PHÉP (SỬA: Kiểm tra chi tiết theo Ca để tránh chặn cả ngày)
        $sql_leave = "SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'";
        $stmt_leave = $conn->prepare($sql_leave);
        $stmt_leave->execute([$id_bacsi, $date, $shift]);
        if ($stmt_leave->rowCount() > 0) {
            echo "<script>alert('Bác sĩ đã nghỉ phép vào ca này. Vui lòng chọn ca khám hoặc ngày khác.'); window.history.back();</script>";
            exit();
        }

        // 3. CHECK LỊCH LÀM VIỆC & XÁC ĐỊNH TRẠNG THÁI
        $start_hour = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $sql_work = "SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?";
        $stmt_work = $conn->prepare($sql_work);
        $stmt_work->execute([$id_bacsi, $start_hour, $date]);

        if ($stmt_work->rowCount() > 0) {
            // Có lịch -> Đặt bình thường
            $status = 'cho_xac_nhan'; 
            $msg = "Đặt lịch thành công! Vui lòng chờ xác nhận.";
            
            // [SỬA] Tính giờ dự kiến dựa trên THỜI GIAN DỊCH VỤ CỤ THỂ
            $end_hour = ($shift == 'Sang') ? '12:00:00' : '17:00:00';
            
            // Lấy thời gian dịch vụ hiện tại
            $stmt_dur = $conn->prepare("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = ?");
            $stmt_dur->execute([$id_dichvu]);
            $current_duration = (int)$stmt_dur->fetchColumn();

            // Tính tổng thời gian đã được đặt trước đó trong ca
            $sql_queue = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_wait 
                          FROM lichhen lh 
                          JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                          WHERE lh.id_bacsi = ? AND DATE(lh.ngay_gio_hen) = ? 
                          AND lh.trang_thai = 'da_xac_nhan'
                          AND TIME(lh.ngay_gio_hen) >= ? AND TIME(lh.ngay_gio_hen) < ?";
            $stmt_queue = $conn->prepare($sql_queue);
            $stmt_queue->execute([$id_bacsi, $date, $start_hour, $end_hour]);
            $wait_minutes = (int)$stmt_queue->fetchColumn();
            
            $ts = strtotime("$date $start_hour") + ($wait_minutes * 60);
            
            // Kiểm tra xem giờ dự kiến + thời gian khám có vượt quá ca trực không
            if ($ts + ($current_duration * 60) > strtotime("$date $end_hour")) {
                echo "<script>alert('Ca này đã kín lịch, không đủ thời gian thực hiện dịch vụ này.'); window.history.back();</script>"; 
                exit();
            }
            $final_datetime = date('Y-m-d H:i:s', $ts);
            
        } else {
            // KHÔNG CÓ LỊCH -> Chờ duyệt
            $status = 'cho_xac_nhan';
            $msg = "Bạn đã gửi yêu cầu đặt lịch vào ngày bác sĩ chưa có lịch. Vui lòng chờ Admin/Bác sĩ duyệt.";
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

        echo "<script>alert('$msg'); window.location.href = '../views/dangnhap.php';</script>";

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo "Lỗi hệ thống: " . $e->getMessage();
    }
}
?>