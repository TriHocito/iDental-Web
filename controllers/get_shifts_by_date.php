<?php
require '../config/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['id']) && isset($_GET['date'])) {
    $id_bacsi = $_GET['id'];
    $date = $_GET['date']; // YYYY-MM-DD

    try {
        // 1. Lấy thông tin cơ bản
        $stmt_info = $conn->prepare("SELECT sdt FROM bacsi WHERE id_bacsi = ?");
        $stmt_info->execute([$id_bacsi]);
        $doc = $stmt_info->fetch(PDO::FETCH_ASSOC);
        $phone = $doc['sdt'] ?? 'Tổng đài';
        
        $day_of_week = date('N', strtotime($date));

        // Hàm hỗ trợ lấy tên thứ (Định nghĩa ngay trong luồng hoặc function riêng)
        $days_map = [1=>'Thứ 2', 2=>'Thứ 3', 3=>'Thứ 4', 4=>'Thứ 5', 5=>'Thứ 6', 6=>'Thứ 7', 7=>'Chủ Nhật'];
        $day_name = $days_map[$day_of_week] ?? '';

        // 2. [LOGIC 1] Kiểm tra ngày nghỉ (Ưu tiên cao nhất)
        $sql_leave = "SELECT ca_nghi FROM yeucaunghi 
                      WHERE id_bacsi = ? AND ngay_nghi = ? AND trang_thai = 'da_duyet'";
        $stmt_leave = $conn->prepare($sql_leave);
        $stmt_leave->execute([$id_bacsi, $date]);
        
        if ($stmt_leave->rowCount() > 0) {
            echo json_encode([
                'status' => 'on_leave',
                'phone' => $phone,
                'day_name' => $day_name // [Cite: 1] Trả về tên thứ
            ]);
            exit;
        }

        // 3. [LOGIC 2] Kiểm tra ca làm việc
        // Chỉ lấy lịch của đúng ngày đó (ngay_hieu_luc = ?)
        $sql_schedule = "SELECT DISTINCT gio_bat_dau 
                         FROM lichlamviec 
                         WHERE id_bacsi = ? 
                         AND ngay_hieu_luc = ? 
                         ORDER BY gio_bat_dau ASC";
        
        $stmt = $conn->prepare($sql_schedule);
        $stmt->execute([$id_bacsi, $date]);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $available_shifts = [];
        if (!empty($shifts)) {
            foreach ($shifts as $sch) {
                $hour = date('H', strtotime($sch['gio_bat_dau']));
                $shift_code = ($hour < 12) ? 'Sang' : 'Chieu';
                $shift_label = ($hour < 12) ? 'Sáng (08:00 - 12:00)' : 'Chiều (13:00 - 17:00)';
                
                $available_shifts[] = [
                    'value' => $shift_code,
                    'label' => $shift_label
                ];
            }
        }

        // 4. Trả kết quả
        if (count($available_shifts) > 0) {
            // Có ca làm
            echo json_encode([
                'status' => 'has_schedule', // [Quan trọng] JS bên bacsi.php phải check 'has_schedule'
                'shifts' => $available_shifts,
                'phone'  => $phone,
                'day_name' => $day_name 
            ]);
        } else {
            // Không có ca
            echo json_encode([
                'status' => 'no_schedule',
                'phone'  => $phone,
                'day_name' => $day_name
            ]);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>