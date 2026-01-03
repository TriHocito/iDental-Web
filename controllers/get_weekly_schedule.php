<?php
// controllers/get_weekly_schedule.php
session_start();
require '../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$doctor_id = $_SESSION['user_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

if ($offset == 0) {
    $monday_timestamp = strtotime('monday this week');
} else {
    $sign = ($offset > 0) ? "+" : "";
    $monday_timestamp = strtotime("monday this week $sign$offset weeks");
}

$week_dates = [];
// Khởi tạo mảng lịch mặc định là null
$schedule_map = ['Sang' => array_fill(1, 7, null), 'Chieu' => array_fill(1, 7, null)];

for ($i = 0; $i < 7; $i++) {
    $ts = strtotime("+$i days", $monday_timestamp);
    $week_dates[$i+1] = [ 
        'full_date' => date('Y-m-d', $ts),
        'display' => date('d/m', $ts)
    ];
}

$start_date = date('Y-m-d', $monday_timestamp);
$end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));

// 1. Lấy Lịch Làm Việc (Work) -> set 'active'
$stmt = $conn->prepare("SELECT * FROM lichlamviec WHERE id_bacsi = ? AND ngay_hieu_luc BETWEEN ? AND ?");
$stmt->execute([$doctor_id, $start_date, $end_date]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($shifts as $sch) {
    $day = date('N', strtotime($sch['ngay_hieu_luc']));
    $shift = (date('H', strtotime($sch['gio_bat_dau'])) < 12) ? 'Sang' : 'Chieu';
    $schedule_map[$shift][$day] = 'active'; // Mặc định là đi làm
}

// 2. Lấy Lịch Nghỉ (Leave) -> set 'leave' (Ghi đè 'active' nếu trùng)
$stmt_l = $conn->prepare("SELECT * FROM yeucaunghi WHERE id_bacsi = ? AND trang_thai = 'da_duyet' AND ngay_nghi BETWEEN ? AND ?");
$stmt_l->execute([$doctor_id, $start_date, $end_date]);
$leaves = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

foreach ($leaves as $l) {
    $day = date('N', strtotime($l['ngay_nghi']));
    $shift = $l['ca_nghi']; // 'Sang' hoặc 'Chieu'
    
    // Chỉ ghi đè trạng thái NGHỈ nếu ngày đó thực sự có lịch làm (để tránh hiện nghỉ vào ngày không có ca)
    if (isset($schedule_map[$shift][$day])) {
        $schedule_map[$shift][$day] = 'leave';
    }
}

echo json_encode([
    'status' => 'success',
    'dates' => $week_dates,
    'schedule' => $schedule_map,
    'week_label' => "Tuần từ " . date('d/m', $monday_timestamp) . " đến " . date('d/m', strtotime($end_date))
]);
?>