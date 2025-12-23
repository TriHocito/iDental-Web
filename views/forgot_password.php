<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên Mật Khẩu - iDental</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: var(--bg); }
        .auth-box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
    </style>
</head>
<body>
    <div class="auth-box">
        <h2 style="color: var(--primary); margin-bottom: 10px;">Khôi Phục Mật Khẩu</h2>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Nhập email đã đăng ký để nhận mật khẩu mới.</p>
        
        <form action="../controllers/process_forgot_password.php" method="POST" style="text-align: left;">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required placeholder="email@gmail.com">
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">Gửi Mật Khẩu Mới</button>
        </form>
        
        <div style="margin-top: 20px;">
            <a href="dangnhap.php" style="color: #666; text-decoration: none; font-size: 14px;">&larr; Quay lại Đăng nhập</a>
        </div>
    </div>
</body>
</html>