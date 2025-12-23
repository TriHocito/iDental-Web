<?php
require '../config/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['id']) && isset($_GET['date'])) {
    $id_bacsi = $_GET['id'];
    $date = $_GET['date'];

    try {
        // Kiểm tra xem ngày đó có lịch làm việc không (Dựa trên ngay_hieu_luc)
        $sql = "SELECT gio_bat_dau FROM lichlamviec 
                WHERE id_bacsi = ? AND ngay_hieu_luc = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_bacsi, $date]);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($shifts) > 0) {
            // Xác định các ca làm việc
            $found_shifts = [];
            foreach($shifts as $row) {
                $hour = date('H', strtotime($row['gio_bat_dau']));
                $found_shifts[] = ($hour < 12) ? 'Sang' : 'Chieu';
            }

            echo json_encode([
                'status' => 'has_schedule',
                'shifts' => array_unique($found_shifts) // Trả về mảng các ca: ['Sang'] hoặc ['Chieu'] hoặc cả 2
            ]);
        } else {
            echo json_encode(['status' => 'no_schedule']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>