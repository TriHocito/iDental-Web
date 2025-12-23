<?php
// controllers/get_medical_record.php
session_start();
require '../config/db_connect.php';

// Kiểm tra quyền truy cập (Chỉ bệnh nhân đã đăng nhập mới xem được)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['error' => 'Bạn không có quyền xem dữ liệu này.']);
    exit();
}

if (isset($_GET['id'])) {
    $id_benhan = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Truy vấn thông tin bệnh án, kết nối với bác sĩ, dịch vụ
    // QUAN TRỌNG: Phải check xem bệnh án này có thuộc về bệnh nhân đang đăng nhập không (bảo mật)
    $sql = "SELECT ba.*, bs.ten_day_du AS ten_bacsi, dv.ten_dich_vu 
            FROM benhan ba
            JOIN lichhen lh ON ba.id_lichhen = lh.id_lichhen
            JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
            JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
            WHERE ba.id_benhan = ? AND lh.id_benhnhan = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_benhan, $user_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        $record['ngay_tao'] = date('H:i - d/m/Y', strtotime($record['ngay_tao']));
        echo json_encode($record);
    } else {
        echo json_encode(['error' => 'Không tìm thấy bệnh án hoặc bạn không có quyền truy cập.']);
    }
} else {
    echo json_encode(['error' => 'Thiếu ID bệnh án.']);
}
?>