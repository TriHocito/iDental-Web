<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../views/dangnhap.php"); exit();
}

$user_id = $_SESSION['user_id'];

// --- 1. HỦY LỊCH HẸN (Giữ nguyên) ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel_appointment' && isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];
    $check = $conn->prepare("SELECT id_lichhen FROM lichhen WHERE id_lichhen = ? AND id_benhnhan = ? AND trang_thai = 'cho_xac_nhan'");
    $check->execute([$id_lichhen, $user_id]);

    if ($check->rowCount() > 0) {
        $cancel = $conn->prepare("UPDATE lichhen SET trang_thai = 'huy' WHERE id_lichhen = ?");
        if ($cancel->execute([$id_lichhen])) {
            echo "<script>alert('Đã hủy yêu cầu đặt lịch!'); window.location.href='../views/khachhang.php';</script>";
        } else {
            echo "<script>alert('Lỗi hệ thống!'); window.location.href='../views/khachhang.php';</script>";
        }
    } else {
        echo "<script>alert('Không thể hủy! Lịch này đã được duyệt hoặc không tồn tại.'); window.location.href='../views/khachhang.php';</script>";
    }
    exit();
}

// --- 2. CẬP NHẬT HỒ SƠ (SỬA: Kiểm tra trùng lặp SĐT/Email) ---
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['ten_day_du']);
    $phone = trim($_POST['sdt']);
    $email = trim($_POST['email']);
    $address = $_POST['dia_chi'] ?? null;
    $dob = $_POST['ngay_sinh'] ?? null;

    // Kiểm tra trùng lặp
    $stmt_check = $conn->prepare("SELECT id_benhnhan FROM benhnhan WHERE (sdt = ? OR email = ?) AND id_benhnhan != ?");
    $stmt_check->execute([$phone, $email, $user_id]);
    
    if ($stmt_check->rowCount() > 0) {
        echo "<script>alert('Lỗi: Số điện thoại hoặc Email đã được sử dụng bởi tài khoản khác!'); window.history.back();</script>";
        exit();
    }

    $sql = "UPDATE benhnhan SET ten_day_du = ?, sdt = ?, email = ?, dia_chi = ?, ngay_sinh = ? WHERE id_benhnhan = ?";
    if ($conn->prepare($sql)->execute([$name, $phone, $email, $address, $dob, $user_id])) {
        $_SESSION['fullname'] = $name; 
        echo "<script>alert('Cập nhật hồ sơ thành công!'); window.location.href='../views/khachhang.php';</script>";
    } else {
        echo "<script>alert('Lỗi cập nhật!'); window.location.href='../views/khachhang.php';</script>";
    }
}

// --- 3. ĐỔI MẬT KHẨU (Giữ nguyên) ---
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    $stmt = $conn->prepare("SELECT mat_khau_hash FROM benhnhan WHERE id_benhnhan = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($old_pass, $user_data['mat_khau_hash'])) {
        if ($new_pass === $confirm_pass) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE benhnhan SET mat_khau_hash = ? WHERE id_benhnhan = ?")->execute([$new_hash, $user_id]);
            echo "<script>alert('Đổi mật khẩu thành công!'); window.location.href='../views/khachhang.php';</script>";
        } else {
            echo "<script>alert('Mật khẩu mới không khớp!'); window.location.href='../views/khachhang.php';</script>";
        }
    } else {
        echo "<script>alert('Mật khẩu cũ không đúng!'); window.location.href='../views/khachhang.php';</script>";
    }
}

// --- 4. ĐẶT LỊCH (Đồng bộ hóa toàn diện) ---
if (isset($_POST['book_appointment'])) {
    $id_bacsi = $_POST['id_bacsi'];
    $id_dichvu = $_POST['id_dichvu'];
    $date = $_POST['date'];
    $shift = $_POST['shift']; 

    try {
        // A. Kiểm tra trạng thái bác sĩ
        $stmt_status = $conn->prepare("SELECT trang_thai FROM bacsi WHERE id_bacsi = ?");
        $stmt_status->execute([$id_bacsi]);
        if ($stmt_status->fetchColumn() == 0) throw new Exception("Bác sĩ hiện đang tạm ngưng nhận lịch.");

        // B. KIỂM TRA NGHỈ PHÉP (Chi tiết theo ca)
        $sql_leave = "SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'";
        $stmt_leave = $conn->prepare($sql_leave);
        $stmt_leave->execute([$id_bacsi, $date, $shift]);
        
        if ($stmt_leave->rowCount() > 0) {
            echo "<script>alert('Lỗi: Bác sĩ đã nghỉ phép vào ca $shift ngày này!'); window.location.href='../views/khachhang.php';</script>";
            exit();
        }

        // Định nghĩa khung giờ
        $start_shift_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $end_shift_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';

        // C. Kiểm tra Lịch trực
        $sql_check_work = "SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?";
        $stmt_check = $conn->prepare($sql_check_work);
        $stmt_check->execute([$id_bacsi, $start_shift_time, $date]);
        
        $final_datetime = null;
        $status = 'cho_xac_nhan';
        $msg_success = "";

        if ($stmt_check->rowCount() > 0) {
            // === TRƯỜNG HỢP A: CÓ LỊCH LÀM (Queue System) ===
            
            // Tính tổng thời gian hàng đợi (Tính cả đã xác nhận và đã hoàn thành)
            $sql_queue = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                          FROM lichhen lh 
                          JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                          WHERE lh.id_bacsi = ? 
                          AND DATE(lh.ngay_gio_hen) = ? 
                          AND lh.trang_thai IN ('da_xac_nhan', 'hoan_thanh')
                          AND TIME(lh.ngay_gio_hen) >= ? AND TIME(lh.ngay_gio_hen) < ?";
            $stmt_queue = $conn->prepare($sql_queue);
            $stmt_queue->execute([$id_bacsi, $date, $start_shift_time, $end_shift_time]);
            $waiting_minutes = (int)$stmt_queue->fetch(PDO::FETCH_ASSOC)['total_minutes'];
            
            // Mốc khởi điểm: Nếu đặt hôm nay thì phải >= Thời điểm hiện tại + 15p
            $start_anchor = strtotime("$date $start_shift_time");
            if ($date == date('Y-m-d')) {
                $start_anchor = max($start_anchor, time() + 900);
            }
            
            $real_start_time = $start_anchor + ($waiting_minutes * 60);
            
            // Lấy thời gian của dịch vụ hiện tại
            $curr_svc_time = $conn->query("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = $id_dichvu")->fetchColumn();
            
            // Kiểm tra quá giờ ca làm
            if (($real_start_time + ($curr_svc_time * 60)) > strtotime("$date $end_shift_time")) {
                echo "<script>alert('Ca khám này đã kín lịch! Vui lòng chọn ca khác.'); window.location.href='../views/khachhang.php';</script>";
                exit();
            }

            $final_datetime = date('Y-m-d H:i:s', $real_start_time);
            $status = 'da_xac_nhan';
            $msg_success = "Đặt lịch thành công! Giờ dự kiến: " . date('H:i', $real_start_time);

        } else {
            // === TRƯỜNG HỢP B: KHÔNG CÓ LỊCH TRỰC ===
            $final_datetime = date('Y-m-d H:i:s', strtotime("$date $start_shift_time"));
            $status = 'cho_xac_nhan';
            $msg_success = "Đã gửi yêu cầu! Vui lòng chờ Bác sĩ xác nhận.";
        }

        $stmt_insert = $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, ?, 'benh_nhan')");
        if ($stmt_insert->execute([$user_id, $id_bacsi, $id_dichvu, $final_datetime, $status])) {
            echo "<script>alert('$msg_success'); window.location.href='../views/khachhang.php';</script>";
        }

    } catch (Exception $e) {
        echo "<script>alert('Lỗi: ".$e->getMessage()."'); window.location.href='../views/khachhang.php';</script>";
    }
}
?>