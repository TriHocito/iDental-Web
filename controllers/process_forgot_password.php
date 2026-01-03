<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $role = $_POST['role'] ?? 'patient'; // Nhận role từ form

    $user = null;
    $table = '';
    $id_col = '';
    $name_col = 'ten_day_du';
    
    // XÁC ĐỊNH BẢNG CẦN TRA CỨU
    if ($role == 'doctor') {
        $table = 'bacsi';
        $id_col = 'id_bacsi';
        // Lưu ý: Đảm bảo bảng bacsi đã có cột email như bạn đã thêm ở bước trước
        $stmt = $conn->prepare("SELECT id_bacsi, ten_day_du, sdt FROM bacsi WHERE email = ?");
    } else {
        $table = 'benhnhan';
        $id_col = 'id_benhnhan';
        $stmt = $conn->prepare("SELECT id_benhnhan, ten_day_du, sdt FROM benhnhan WHERE email = ?");
    }

    // Thực thi truy vấn
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // XỬ LÝ KẾT QUẢ
    if ($user) {
        // Tạo mật khẩu mới
        $new_pass = rand(100000, 999999); 
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $userId = $user[$id_col];

        // Cập nhật mật khẩu vào đúng bảng
        $update = $conn->prepare("UPDATE $table SET mat_khau_hash = ? WHERE $id_col = ?");
        
        if ($update->execute([$new_hash, $userId])) {
            $phone_db = $user['sdt'];
            $masked_phone = (strlen($phone_db) > 3) ? str_repeat('*', strlen($phone_db) - 3) . substr($phone_db, -3) : $phone_db;
            $roleDisplay = ($role == 'doctor') ? "Bác sĩ" : "Khách hàng";

            // Nội dung mail
            $subject = "Cấp lại mật khẩu mới - iDental";
            $body = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h3 style='color: #0046ad;'>Xin chào $roleDisplay {$user['ten_day_du']},</h3>
                    <p>Hệ thống iDental đã nhận được yêu cầu khôi phục mật khẩu.</p>
                    
                    <div style='background: #f0f7ff; padding: 15px; border-radius: 5px; border-left: 4px solid #0046ad; margin: 20px 0;'>
                        <p><strong>Thông tin đăng nhập mới:</strong></p>
                        <ul>
                            <li>Tài khoản (SĐT): <strong>$masked_phone</strong></li>
                            <li>Mật khẩu mới: <strong style='color: #d32f2f; font-size: 20px;'>$new_pass</strong></li>
                        </ul>
                    </div>
                    
                    <p>Vui lòng đăng nhập và đổi lại mật khẩu ngay để bảo mật tài khoản.</p>
                    <hr style='border:0; border-top:1px solid #eee;'>
                    <small>Email tự động từ Nha khoa iDental.</small>
                </div>
            ";

            if (sendMailGeneric($email, $subject, $body)) {
                echo "<script>
                    alert('Thành công! Mật khẩu mới đã được gửi vào Email {$email}.'); 
                    window.location.href='../views/dangnhap.php';
                </script>";
            } else {
                echo "<script>alert('Lỗi gửi mail! Vui lòng thử lại sau.'); window.history.back();</script>";
            }
        }
    } else {
        // Nếu không tìm thấy email
        if ($role == 'doctor') {
            echo "<script>
                alert('Email này không tồn tại trong hệ thống Bác sĩ! Vui lòng liên hệ Admin.');
                window.history.back();
            </script>";
        } else {
            echo "<script>
                let choice = confirm('Email này chưa đăng ký tài khoản Bệnh nhân!\\n\\nNhấn OK để Đăng ký mới hoặc Cancel để thử lại.');
                if (choice) window.location.href = '../views/dangky.php';
                else window.history.back();
            </script>";
        }
    }
}
?>