<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'doctor')) {
    die("Access Denied");
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // 1. Lấy thông tin trước khi hủy để gửi mail
    $stmt = $conn->prepare("SELECT bn.email, bn.ten_day_du FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan WHERE lh.id_lichhen = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Cập nhật trạng thái Hủy (Thay vì xóa hẳn, để lưu lịch sử)
    $update = $conn->prepare("UPDATE lichhen SET trang_thai = 'huy' WHERE id_lichhen = ?");
    
    if ($update->execute([$id])) {
        // 3. Gửi mail thông báo hủy
        if ($data && !empty($data['email'])) {
            $subject = "Thông báo hủy lịch hẹn - iDental";
            $body = "<h3>Xin chào {$data['ten_day_du']},</h3>
                     <p>Rất tiếc, lịch hẹn của bạn tại iDental đã bị hủy do trùng lịch hoặc lý do đột xuất từ phòng khám.</p>
                     <p>Vui lòng liên hệ lại hotline hoặc đặt lịch mới trên website.</p>
                     <p>Xin lỗi vì sự bất tiện này.</p>";
            sendMailGeneric($data['email'], $subject, $body);
        }
        
        $back = ($_SESSION['role'] == 'admin') ? '../views/admin.php' : '../views/bacsi.php';
        echo "<script>alert('Đã hủy lịch hẹn và gửi thông báo cho khách hàng.'); window.location.href='$back';</script>";
    }
}
?>