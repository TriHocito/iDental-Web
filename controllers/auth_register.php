<?php
session_start();

require '../config/db_connect.php';
require '../includes/send_mail.php'; 

if (isset($_GET['resend']) && isset($_SESSION['temp_register'])) {
    $email = $_SESSION['temp_register']['email'];
    $new_otp = rand(100000, 999999);
    
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        echo "<script>alert('Mật khẩu nhập lại không khớp!'); window.history.back();</script>";
        exit();
    }
    $stmt = $conn->prepare("SELECT id_benhnhan FROM BENHNHAN WHERE sdt = ?");
    $stmt->execute([$phone]);
    if ($stmt->rowCount() > 0) {
        echo "<script>alert('Số điện thoại này đã được đăng ký!'); window.history.back();</script>";
        exit();
    }

    $otp = rand(100000, 999999);
    $pass_hash = password_hash($pass, PASSWORD_DEFAULT);

    if (sendOTP($email, $otp)) {
        $_SESSION['temp_register'] = [
            'sdt' => $phone,
            'mat_khau_hash' => $pass_hash,
            'ten_day_du' => $fullname,
            'email' => $email,
            'otp_code' => $otp,
            'otp_time' => time()
        ];
        header("Location: ../views/verify_otp.php");
        exit();
    } else {
        echo "<script>alert('Không thể gửi Email. Vui lòng kiểm tra lại địa chỉ Email!'); window.history.back();</script>";
    }
}
?>