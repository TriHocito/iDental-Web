<?php
// controllers/approve_appointment.php
session_start();

// CẬP NHẬT ĐƯỜNG DẪN CONFIG & INCLUDES
require '../config/db_connect.php';
require '../includes/send_mail.php';

// Chỉ Admin hoặc Bác sĩ mới được duyệt
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'doctor')) {
    die("Không có quyền truy cập.");
}

if (isset($_GET['id'])) {
    $id_lichhen = $_GET['id'];

    try {
        // [BỔ SUNG] Kiểm tra và tự động thêm lịch làm việc nếu chưa có
        $stmt_check = $conn->prepare("SELECT id_bacsi, ngay_gio_hen FROM lichhen WHERE id_lichhen = ?");
        $stmt_check->execute([$id_lichhen]);
        $appt = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($appt) {
            $id_bacsi_appt = $appt['id_bacsi'];
            $ngay_hen = date('Y-m-d', strtotime($appt['ngay_gio_hen']));
            $gio_hen = date('H:i:s', strtotime($appt['ngay_gio_hen']));
            
            // Xác định ca
            if ($gio_hen < '12:00:00') {
                $gio_bat_dau = '08:00:00';
                $gio_ket_thuc = '12:00:00';
            } else {
                $gio_bat_dau = '13:00:00';
                $gio_ket_thuc = '17:00:00';
            }

            // Kiểm tra lịch làm việc
            $sql_check_schedule = "SELECT id_lichlamviec FROM lichlamviec 
                                   WHERE id_bacsi = ? AND ngay_hieu_luc = ? AND gio_bat_dau = ?";
            $stmt_schedule = $conn->prepare($sql_check_schedule);
            $stmt_schedule->execute([$id_bacsi_appt, $ngay_hen, $gio_bat_dau]);

            if ($stmt_schedule->rowCount() == 0) {
                // Chưa có lịch -> Cần tạo lịch mới
                
                // [RÀNG BUỘC] Chỉ Admin mới được duyệt trường hợp này
                if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
                    echo "<script>alert('Lịch hẹn này nằm trong ngày bác sĩ chưa có lịch làm việc. Chỉ Admin mới có quyền duyệt và tạo lịch làm việc mới.'); window.history.back();</script>";
                    exit();
                }

                // Tự động thêm lịch
                // Lấy ghế đầu tiên
                $stmt_chair = $conn->query("SELECT id_giuongbenh FROM giuongbenh LIMIT 1");
                $id_giuong = $stmt_chair->fetchColumn();

                if ($id_giuong) {
                    $ngay_trong_tuan = date('N', strtotime($ngay_hen)); // 1 (Mon) - 7 (Sun)
                    $id_admin = $_SESSION['user_id']; 

                    $sql_insert_schedule = "INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert_schedule);
                    if (!$stmt_insert->execute([$id_bacsi_appt, $id_giuong, $id_admin, $ngay_trong_tuan, $gio_bat_dau, $gio_ket_thuc, $ngay_hen])) {
                         echo "<script>alert('Lỗi khi tạo lịch làm việc mới. Vui lòng thử lại.'); window.history.back();</script>";
                         exit();
                    }
                } else {
                     echo "<script>alert('Không tìm thấy giường bệnh khả dụng để tạo lịch.'); window.history.back();</script>";
                     exit();
                }
            }
        }

        // 1. Cập nhật trạng thái
        $stmt = $conn->prepare("UPDATE lichhen SET trang_thai = 'da_xac_nhan' WHERE id_lichhen = ?");
        if ($stmt->execute([$id_lichhen])) {
            
            // 2. Lấy thông tin để gửi mail
            $sql_info = "SELECT lh.ngay_gio_hen, bn.ten_day_du, bn.email, bs.ten_day_du AS ten_bacsi, dv.ten_dich_vu 
                         FROM lichhen lh
                         JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan
                         JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
                         JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                         WHERE lh.id_lichhen = ?";
            $info = $conn->prepare($sql_info);
            $info->execute([$id_lichhen]);
            $data = $info->fetch(PDO::FETCH_ASSOC);

            if ($data && !empty($data['email'])) {
                // Gửi mail xác nhận
                $dateStr = date('H:i d/m/Y', strtotime($data['ngay_gio_hen']));
                sendAppointmentConfirmation(
                    $data['email'], 
                    $data['ten_day_du'], 
                    $dateStr, 
                    $data['ten_bacsi'], 
                    $data['ten_dich_vu']
                );
            }
            $back_url = ($_SESSION['role'] == 'admin') ? '../views/admin.php' : '../views/bacsi.php';
            echo "<script>alert('Đã duyệt lịch và gửi mail thông báo!'); window.location.href='$back_url';</script>";
        }
    } catch (Exception $e) {
        echo "Lỗi: " . $e->getMessage();
    }
}
?>