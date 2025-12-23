<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDental Clinic</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/style.css"> 
</head>
<body>

<header class="navbar">
    <div class="container"> 
        <a href="index.php" class="logo-link">
            <i class="fas fa-tooth logo-icon"></i>
            <span class="logo-text">iDental</span>
        </a>
        
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>

        <nav class="nav-menu" id="navMenu">
            <a href="index.php" class="nav-link">Trang chủ</a>
            <a href="vechungtoi.php" class="nav-link">Về chúng tôi</a>
            <a href="banggia.php" class="nav-link">Bảng giá</a> 
            <a href="datlich.php" class="nav-link">Đặt lịch phòng khám</a>
        </nav>

        <div class="header-actions" id="headerActions">
            <a href="dangnhap.php" class="btn btn-secondary">Đăng Nhập</a>
            <a href="dangky.php" class="btn btn-primary">Đăng Ký</a>
        </div>
    </div>
</header>