<?php
// views/dangky.php
session_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản - iDental</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/styles.css">

    <style>
        
        .auth-page {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg);
            padding: 40px 20px;
        }
        .auth-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .auth-title {
            color: var(--primary);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .auth-desc {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .auth-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        .auth-link:hover {
            text-decoration: underline;
        }
       
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>

    <section class="auth-page">
        <div class="auth-box">
            <div style="margin-bottom: 20px;">
                <i class="fas fa-tooth" style="font-size: 40px; color: var(--primary);"></i>
            </div>
            <h1 class="auth-title">Đăng Ký Bệnh Nhân Mới</h1>
            <p class="auth-desc">Tạo tài khoản để đặt lịch và quản lý hồ sơ khám bệnh dễ dàng hơn.</p>

            <form action="../controllers/auth_register.php" method="POST" style="text-align: left;">
                <div class="form-group">
                    <label>Họ và Tên</label>
                    <input type="text" name="fullname" class="form-control" placeholder="VD: Nguyễn Văn A" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone" class="form-control" placeholder="09xxxxxxxx" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Nhập lại mật khẩu</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">Đăng Ký</button>
            </form>

            <div style="margin-top: 20px; font-size: 14px;">
                Bạn đã có tài khoản? <a href="dangnhap.php" class="auth-link">Đăng nhập ngay</a>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

   
    
    <script src="../../assets/js/file.js"></script>
</body>
</html>