<?php
session_start();
require '../config/db_connect.php';

// Kiểm tra quyền truy cập (Bệnh nhân hoặc Bác sĩ)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['patient', 'doctor'])) {
    echo json_encode(['error' => 'Bạn không có quyền xem dữ liệu này.']);
    exit();
}

if (isset($_GET['id'])) {
    $id_benhan = $_GET['id'];
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    $sql = "SELECT ba.*, bs.ten_day_du AS ten_bacsi, dv.ten_dich_vu, bn.ten_day_du AS ten_benhnhan
            FROM benhan ba
            JOIN lichhen lh ON ba.id_lichhen = lh.id_lichhen
            JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
            JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan
            JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
            WHERE ba.id_benhan = ?";
    
    // Nếu là bệnh nhân, chỉ xem được bệnh án của mình
    if ($role === 'patient') {
        $sql .= " AND lh.id_benhnhan = $user_id";
    }
    // Nếu là bác sĩ, có thể xem bệnh án (có thể thêm điều kiện chỉ xem bệnh án do mình tạo hoặc của bệnh nhân mình khám nếu cần)
    // Hiện tại cho phép bác sĩ xem chi tiết bệnh án nếu có ID (vì họ click từ danh sách lịch hẹn của họ)

    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_benhan]);
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