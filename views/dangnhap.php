<?php
// views/dangnhap.php
session_start();


if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') header("Location: admin.php");
    else if ($_SESSION['role'] == 'doctor') header("Location: bacsi.php");
    else header("Location: khachhang.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - iDental</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">

    <style>
        .auth-page { min-height: 80vh; display: flex; align-items: center; justify-content: center; background-color: var(--bg); padding: 40px 20px; }
        .auth-box { background: white; padding: 40px; border-radius: 12px; box-shadow: var(--shadow); width: 100%; max-width: 450px; text-align: center; }
        .role-selector { display: flex; justify-content: space-between; margin-bottom: 25px; background: #f5f5f5; padding: 5px; border-radius: 8px; }
        .role-btn { flex: 1; padding: 10px; border: none; background: none; cursor: pointer; border-radius: 6px; font-weight: 600; color: #666; transition: 0.2s; font-size: 13px; }
        .role-btn.active { background: white; color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .role-btn i { display: block; font-size: 18px; margin-bottom: 5px; }
        
      
        .register-link { margin-top: 20px; font-size: 14px; color: #666; }
        .register-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>

    <section class="auth-page">
        <div class="auth-box">
            <h1 style="color: var(--primary); margin-bottom: 5px;">Chào mừng trở lại!</h1>
            <p style="color: #666; margin-bottom: 20px;">Vui lòng chọn vai trò để đăng nhập</p>

            <div class="role-selector">
                <button type="button" class="role-btn active" onclick="selectRole('patient', this)">
                    <i class="fas fa-user-injured"></i> Bệnh nhân
                </button>
                <button type="button" class="role-btn" onclick="selectRole('doctor', this)">
                    <i class="fas fa-user-md"></i> Bác sĩ
                </button>
                <button type="button" class="role-btn" onclick="selectRole('admin', this)">
                    <i class="fas fa-user-cog"></i> Quản trị
                </button>
            </div>

            <form id="loginForm" action="../controllers/auth_login.php" method="POST" style="text-align: left;">
                <input type="hidden" id="selectedRole" name="role" value="patient">
                
                <div class="form-group">
                    <label id="loginLabel">Số điện thoại</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Nhập SĐT..." required>
                </div>

                <div class="form-group">
                    <label>Mật khẩu</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <div style="text-align: right; margin-top: 5px;">
                        <a href="forgot_password.php" style="font-size: 13px; color: var(--primary); text-decoration: none;">Quên mật khẩu?</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">Đăng Nhập</button>
            </form>

            <div id="registerSection" class="register-link">
                Bạn chưa có tài khoản? <a href="dangky.php">Đăng ký ngay</a>
            </div>

        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script>
        function selectRole(role, btnElement) {
            
            document.getElementById('selectedRole').value = role;
            
           
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            if(btnElement) {
                btnElement.classList.add('active');
            } else {
                
                document.querySelector(`.role-btn[onclick*="${role}"]`).classList.add('active');
            }
            
            
            const label = document.getElementById('loginLabel');
            const input = document.getElementById('username');
            const regSection = document.getElementById('registerSection');
            
            if(role === 'patient') {
                label.innerText = "Số điện thoại"; 
                input.placeholder = "Nhập SĐT...";
                regSection.style.display = 'block'; 
            } else if (role === 'doctor') {
                label.innerText = "SĐT Bác sĩ"; 
                input.placeholder = "Nhập SĐT đăng nhập...";
                regSection.style.display = 'none';  
            } else {
                label.innerText = "Tên đăng nhập"; 
                input.placeholder = "Nhập tên đăng nhập...";
                regSection.style.display = 'none'; 
            }
        }
    </script>
    
    <script src="../../assets/js/file.js"></script>

</body>
</html>