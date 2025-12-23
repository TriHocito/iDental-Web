<?php
// views/verify_otp.php// Trang xác thực OTP sau khi đăng ký
session_start();
require '../config/db_connect.php';

// 1. Kiểm tra session, nếu không có thì đá về trang đăng ký
if (!isset($_SESSION['temp_register'])) {
    header("Location: dangky.php");
    exit();
}

$error = "";
$stored_data = $_SESSION['temp_register'];

// XỬ LÝ XÁC NHẬN OTP
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_otp = trim($_POST['otp']);
    
    // Kiểm tra thời gian (15 phút = 900s)
    if (time() - $stored_data['otp_time'] > 900) {
        $error = "Mã OTP đã hết hạn. Vui lòng nhấn 'Gửi lại mã'.";
    } 
    // Kiểm tra khớp mã
    else if ($user_otp == $stored_data['otp_code']) {
        try {
            // INSERT VÀO CSDL
            $sql = "INSERT INTO benhnhan (sdt, mat_khau_hash, ten_day_du, email) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([
                $stored_data['sdt'], 
                $stored_data['mat_khau_hash'], 
                $stored_data['ten_day_du'], 
                $stored_data['email']
            ])) {
                // Thành công -> Xóa session tạm -> Chuyển về đăng nhập
                unset($_SESSION['temp_register']);
                echo "<script>
                        alert('Đăng ký thành công! Vui lòng đăng nhập.'); 
                        window.location.href='dangnhap.php';
                      </script>";
                exit();
            }
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Email hoặc Số điện thoại này đã được sử dụng.";
            } else {
                $error = "Lỗi hệ thống: " . $e->getMessage();
            }
        }
    } else {
        $error = "Mã xác thực OTP không chính xác!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Thực OTP - iDental</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/styles.css">
    
    <style>
        /* CSS nội bộ cho form OTP đẹp hơn */
        :root { --primary: #0046AD; --bg: #F8FAFC; --shadow: 0 4px 15px rgba(0,0,0,0.05); }
        body { background-color: var(--bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Be Vietnam Pro', sans-serif; }
        .otp-box { background: white; padding: 40px; border-radius: 16px; box-shadow: var(--shadow); text-align: center; width: 100%; max-width: 420px; }
        .otp-box h2 { color: var(--primary); margin-bottom: 10px; }
        .form-control { width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: bold; }
        .btn-primary { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-primary:hover { opacity: 0.9; }
        .error-msg { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .footer-links { margin-top: 20px; font-size: 14px; color: #64748b; }
        .footer-links a { color: var(--primary); text-decoration: none; font-weight: 600; margin: 0 5px; }
    </style>
</head>
<body>
    <div class="otp-box">
        <div style="margin-bottom: 20px;">
            <i class="fas fa-shield-alt" style="font-size: 40px; color: var(--primary);"></i>
        </div>
        <h2>Xác Thực Email</h2>
        <p style="color: #666; margin-bottom: 20px;">
            Mã xác thực đã được gửi đến:<br>
            <strong><?php echo htmlspecialchars($stored_data['email']); ?></strong>
        </p>
        
        <?php if($error): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" class="form-control" placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus>
            <button type="submit" class="btn-primary">Xác Nhận Đăng Ký</button>
        </form>
        
        <div class="footer-links">
            <a href="../controllers/auth_register.php?resend=1">Gửi lại mã</a> | 
            <a href="dangky.php">Đăng ký lại</a>
        </div>
    </div>
</body>
</html>