<?php
// controllers/get_doctor_info.php
require '../config/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id_bacsi = $_GET['id'];
    
    // 1. Lấy SĐT bác sĩ để hiển thị khi cần liên hệ
    $stmt_info = $conn->prepare("SELECT sdt FROM bacsi WHERE id_bacsi = ?");
    $stmt_info->execute([$id_bacsi]);
    $doc_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // 2. Lấy lịch làm việc chi tiết (Chỉ lấy lịch còn hiệu lực)
    $sql = "SELECT ngay_trong_tuan, gio_bat_dau 
            FROM lichlamviec 
            WHERE id_bacsi = ? 
            AND ngay_hieu_luc <= CURDATE() 
            AND (ngay_het_han IS NULL OR ngay_het_han >= CURDATE())
            ORDER BY ngay_trong_tuan ASC"; 
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_bacsi]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $days_map = [1=>'Thứ 2', 2=>'Thứ 3', 3=>'Thứ 4', 4=>'Thứ 5', 5=>'Thứ 6', 6=>'Thứ 7', 7=>'Chủ Nhật'];
    
    $text_arr = [];
    $valid_slots = []; // Mảng chứa các slot hợp lệ [ {day: 1, shift: 'Sang'}, ... ]

    foreach ($schedules as $sch) {
        $day_num = $sch['ngay_trong_tuan'];
        $hour = date('H', strtotime($sch['gio_bat_dau']));
        $shift = ($hour < 12) ? 'Sang' : 'Chieu';
        
        // Tạo chuỗi text hiển thị (VD: Thứ 2 (Sáng))
        $text_arr[] = $days_map[$day_num] . " (" . $shift . ")";
        
        // Lưu dữ liệu logic để JS so sánh
        $valid_slots[] = [
            'day' => $day_num,      
            'shift' => $shift       
        ];
    }
    
    echo json_encode([
        'phone' => $doc_info['sdt'] ?? '',
        'schedule_text' => !empty($text_arr) ? implode(', ', array_unique($text_arr)) : 'Chưa có lịch làm việc',
        'valid_slots' => $valid_slots
    ]);
}
?>