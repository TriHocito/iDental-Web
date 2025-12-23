<?php
session_start();

// 1. Kết nối CSDL & Thư viện Mail (Lùi ra 1 cấp để vào config/includes)
require '../config/db_connect.php';
require '../includes/send_mail.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // 2. Kiểm tra thông tin trong DB
    // Chỉ tìm theo Email
    $stmt = $conn->prepare("SELECT id_benhnhan, ten_day_du, sdt FROM benhnhan WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // TRƯỜNG HỢP 1: TÀI KHOẢN CÓ TỒN TẠI -> Gửi mật khẩu mới
    if ($user) {
        // Tạo mật khẩu mới (6 số)
        $new_pass = rand(100000, 999999); 
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

        // Cập nhật vào DB
        $update = $conn->prepare("UPDATE benhnhan SET mat_khau_hash = ? WHERE id_benhnhan = ?");
        
        if ($update->execute([$new_hash, $user['id_benhnhan']])) {
            // Xử lý ẩn số điện thoại để hiển thị trong mail cho bảo mật
            $phone_db = $user['sdt'];
            $masked_phone = (strlen($phone_db) > 3) ? str_repeat('*', strlen($phone_db) - 3) . substr($phone_db, -3) : $phone_db;

            // Nội dung mail
            $subject = "Cấp lại mật khẩu mới - iDental";
            $body = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h3 style='color: #007bff;'>Xin chào {$user['ten_day_du']},</h3>
                    <p>Hệ thống iDental đã nhận được yêu cầu khôi phục mật khẩu qua Email.</p>
                    
                    <div style='background: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #eee; margin: 20px 0;'>
                        <p><strong>Thông tin tài khoản:</strong></p>
                        <ul>
                            <li>Tài khoản (SĐT): <strong>$masked_phone</strong></li>
                            <li>Mật khẩu mới: <strong style='color: #d9534f; font-size: 20px;'>$new_pass</strong></li>
                        </ul>
                    </div>
                    
                    <p>Vui lòng đăng nhập và đổi lại mật khẩu ngay.</p>
                    <hr style='border:0; border-top:1px solid #eee;'>
                    <small>Email tự động từ Nha khoa iDental.</small>
                </div>
            ";

            if (sendMailGeneric($email, $subject, $body)) {
                echo "<script>
                    alert('Thành công! Mật khẩu mới đã được gửi vào Email của bạn.'); 
                    window.location.href='../views/dangnhap.php';
                </script>";
            } else {
                echo "<script>alert('Lỗi gửi mail! Vui lòng thử lại sau.'); window.history.back();</script>";
            }
        }
    } 
    // TRƯỜNG HỢP 2: EMAIL CHƯA CÓ TRONG HỆ THỐNG -> Gợi ý Đăng ký hoặc Đặt lịch
    else {
        echo "<script>
            // Sử dụng confirm của JS để cho người dùng chọn
            let userChoice = confirm('Email này chưa được đăng ký tại iDental!\\n\\n- Nhấn OK để đến trang ĐĂNG KÝ tài khoản mới.\\n- Nhấn CANCEL (Hủy) để đến trang ĐẶT LỊCH khám ngay.');
            
            if (userChoice) {
                // Nếu chọn OK -> Chuyển sang trang Đăng ký
                window.location.href = '../views/dangky.php';
            } else {
                // Nếu chọn Cancel -> Chuyển sang trang Đặt lịch
                window.location.href = '../views/datlich.php';
            }
        </script>";
    }
}
?>