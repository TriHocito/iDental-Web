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