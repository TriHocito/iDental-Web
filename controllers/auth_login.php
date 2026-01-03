<?php
// controllers/auth_login.php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php'; // Nạp file gửi mail để sử dụng hàm thông báo

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $table = ''; $col_user = ''; $col_id = ''; $redirect = '';

    switch ($role) {
        case 'admin':
            $table = 'quantrivien'; $col_user = 'ten_dang_nhap'; $col_id = 'id_quantrivien'; 
            $redirect = '../views/admin.php'; 
            break;
        case 'doctor':
            $table = 'bacsi'; $col_user = 'sdt'; $col_id = 'id_bacsi'; 
            $redirect = '../views/bacsi.php';
            break;
        case 'patient':
            $table = 'benhnhan'; $col_user = 'sdt'; $col_id = 'id_benhnhan'; 
            $redirect = '../views/khachhang.php';
            break;
        default: die("Vai trò không hợp lệ");
    }

    $stmt = $conn->prepare("SELECT * FROM $table WHERE $col_user = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['mat_khau_hash'])) {
        
        // --- KIỂM TRA TÀI KHOẢN BỊ KHÓA ---
        if (isset($user['trang_thai']) && $user['trang_thai'] == 0) {
            
            // Nếu là Bác sĩ và có email, thực hiện gửi mail thông báo
            if ($role === 'doctor' && !empty($user['email'])) {
                sendAccountLockNotification($user['email'], $user['ten_day_du']);
            }

            echo "<script>alert('Tài khoản của bạn hiện đang bị khóa. Vui lòng liên hệ Quản trị viên!'); window.location.href='../views/dangnhap.php';</script>";
            exit();
        }

        $_SESSION['user_id'] = $user[$col_id];
        $_SESSION['role'] = $role;
        $_SESSION['fullname'] = $user['ten_day_du'];
        header("Location: $redirect");
        exit();
    } else {
        echo "<script>alert('Sai thông tin đăng nhập hoặc mật khẩu!'); window.location.href='../views/dangnhap.php';</script>";
        exit();
    }
}
?>