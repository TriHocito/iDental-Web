<?php
// controllers/auth_register.php
session_start();

// SỬA ĐƯỜNG DẪN: Lùi 1 cấp ra khỏi controllers
require '../config/db_connect.php';
require '../includes/send_mail.php'; 

// --- LOGIC 1: XỬ LÝ YÊU CẦU GỬI LẠI MÃ (GET) ---
if (isset($_GET['resend']) && isset($_SESSION['temp_register'])) {
    $email = $_SESSION['temp_register']['email'];
    $new_otp = rand(100000, 999999);
    
    // Cập nhật lại OTP mới và thời gian mới vào session
    $_SESSION['temp_register']['otp_code'] = $new_otp;
    $_SESSION['temp_register']['otp_time'] = time();
    
    if (sendOTP($email, $new_otp)) {
        echo "<script>
                alert('Mã OTP mới đã được gửi lại vào email của bạn!'); 
                window.location.href='../views/verify_otp.php';
              </script>";
    } else {
        echo "<script>
                alert('Gửi lại thất bại. Vui lòng thử lại sau.'); 
                window.location.href='../views/verify_otp.php';
              </script>";
    }
    exit();
}

// --- LOGIC 2: XỬ LÝ ĐĂNG KÝ MỚI (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // 1. Kiểm tra mật khẩu nhập lại
    if ($pass !== $confirm) {
        echo "<script>alert('Mật khẩu nhập lại không khớp!'); window.history.back();</script>";
        exit();
    }

    // 2. Kiểm tra SĐT đã tồn tại chưa
    $stmt = $conn->prepare("SELECT id_benhnhan FROM BENHNHAN WHERE sdt = ?");
    $stmt->execute([$phone]);
    if ($stmt->rowCount() > 0) {
        echo "<script>alert('Số điện thoại này đã được đăng ký!'); window.history.back();</script>";
        exit();
    }

    // 3. Tạo OTP và Hash mật khẩu
    $otp = rand(100000, 999999);
    $pass_hash = password_hash($pass, PASSWORD_DEFAULT);

    // 4. Gửi Email OTP
    if (sendOTP($email, $otp)) {
        // 5. Lưu thông tin tạm vào SESSION
        $_SESSION['temp_register'] = [
            'sdt' => $phone,
            'mat_khau_hash' => $pass_hash,
            'ten_day_du' => $fullname,
            'email' => $email,
            'otp_code' => $otp,
            'otp_time' => time()
        ];

        // SỬA ĐƯỜNG DẪN: Chuyển hướng sang views/verify_otp.php
        header("Location: ../views/verify_otp.php");
        exit();
    } else {
        echo "<script>alert('Không thể gửi Email. Vui lòng kiểm tra lại địa chỉ Email!'); window.history.back();</script>";
    }
}
?>