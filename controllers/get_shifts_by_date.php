<?php
require '../config/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['id']) && isset($_GET['date'])) {
    $id_bacsi = $_GET['id'];
    $date = $_GET['date']; // YYYY-MM-DD

    try {
        // [SỬA] 1. Lấy thông tin cơ bản & Kiểm tra trạng thái bác sĩ
        $stmt_info = $conn->prepare("SELECT sdt, trang_thai FROM bacsi WHERE id_bacsi = ?");
        $stmt_info->execute([$id_bacsi]);
        $doc = $stmt_info->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc || $doc['trang_thai'] == 0) {
            echo json_encode(['status' => 'locked', 'message' => 'Bác sĩ này hiện không còn hoạt động.']);
            exit();
        }

        $phone = $doc['sdt'] ?? 'Tổng đài';
        $day_of_week = date('N', strtotime($date));
        $days_map = [1=>'Thứ 2', 2=>'Thứ 3', 3=>'Thứ 4', 4=>'Thứ 5', 5=>'Thứ 6', 6=>'Thứ 7', 7=>'Chủ Nhật'];
        $day_name = $days_map[$day_of_week] ?? '';

        // 2. Lấy danh sách các ca ĐÃ ĐƯỢC DUYỆT NGHỈ
        $sql_leave = "SELECT ca_nghi FROM yeucaunghi 
                      WHERE id_bacsi = ? AND ngay_nghi = ? AND trang_thai = 'da_duyet'";
        $stmt_leave = $conn->prepare($sql_leave);
        $stmt_leave->execute([$id_bacsi, $date]);
        $leaves = $stmt_leave->fetchAll(PDO::FETCH_COLUMN);

        // 3. Lấy lịch làm việc của bác sĩ ngày hôm đó
        $sql_schedule = "SELECT DISTINCT gio_bat_dau 
                         FROM lichlamviec 
                         WHERE id_bacsi = ? AND ngay_hieu_luc = ? 
                         ORDER BY gio_bat_dau ASC";
        
        $stmt = $conn->prepare($sql_schedule);
        $stmt->execute([$id_bacsi, $date]);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $available_shifts = [];
        $has_work_schedule = count($shifts) > 0;

        if ($has_work_schedule) {
            foreach ($shifts as $sch) {
                $hour = date('H', strtotime($sch['gio_bat_dau']));
                $shift_code = ($hour < 12) ? 'Sang' : 'Chieu';
                $shift_label = ($hour < 12) ? 'Sáng (08:00 - 12:00)' : 'Chiều (13:00 - 17:00)';
                
                if (!in_array($shift_code, $leaves)) {
                    $available_shifts[] = [
                        'value' => $shift_code,
                        'label' => $shift_label
                    ];
                }
            }
        }

        // 4. Trả kết quả
        if (count($available_shifts) > 0) {
            echo json_encode([
                'status' => 'has_schedule',
                'shifts' => $available_shifts,
                'phone'  => $phone,
                'day_name' => $day_name 
            ]);
        } elseif ($has_work_schedule && count($available_shifts) == 0) {
            echo json_encode([
                'status' => 'on_leave',
                'phone'  => $phone,
                'day_name' => $day_name
            ]);
        } else {
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