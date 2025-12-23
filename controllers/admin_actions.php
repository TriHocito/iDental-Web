<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php'; 

// =================================================================
// 0. KIỂM TRA QUYỀN HẠN
// =================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         header('Content-Type: application/json'); 
         echo json_encode(['status'=>'error', 'message'=>'Bạn chưa đăng nhập hoặc không có quyền!']); 
         exit();
    }
    header("Location: ../views/dangnhap.php"); exit();
}

$id_quantrivien = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// =================================================================
// 1. HÀM HỖ TRỢ (HELPER FUNCTIONS)
// =================================================================

/**
 * Tìm ID Giường bệnh TRỐNG trong một khung giờ cụ thể
 * Trả về ID giường hoặc false nếu hết giường.
 */
function getAvailableBed($conn, $date, $start_time, $end_time) {
    // Logic: Tìm id_giuongbenh KHÔNG nằm trong danh sách các giường đang bận (overlap thời gian)
    // Công thức trùng giờ: (StartA < EndB) AND (EndA > StartB)
    $sql = "SELECT id_giuongbenh FROM giuongbenh 
            WHERE id_giuongbenh NOT IN (
                SELECT id_giuongbenh FROM lichlamviec 
                WHERE ngay_hieu_luc = :date 
                AND (
                    (gio_bat_dau < :end_time) AND (gio_ket_thuc > :start_time)
                )
            )
            LIMIT 1"; // Lấy cái đầu tiên tìm được
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':date' => $date, 
        ':start_time' => $start_time, 
        ':end_time' => $end_time
    ]);
    return $stmt->fetchColumn(); 
}

// =================================================================
// 2. XỬ LÝ CHÍNH
// =================================================================
try {

    // -------------------------------------------------------------
    // GROUP A: API TRẢ VỀ JSON (Cho AJAX)
    // -------------------------------------------------------------

    // A1. Lấy lịch sử khám bệnh nhân
    if ($action == 'get_patient_history' && isset($_GET['id'])) { 
        ob_clean(); // Xóa buffer để đảm bảo JSON sạch
        header('Content-Type: application/json'); 
        try { 
            $sql = "SELECT lh.ngay_gio_hen, lh.trang_thai, dv.ten_dich_vu, bs.ten_day_du AS ten_bs 
                    FROM lichhen lh 
                    LEFT JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                    LEFT JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi 
                    WHERE lh.id_benhnhan = ? 
                    ORDER BY lh.ngay_gio_hen DESC"; 
            $stmt = $conn->prepare($sql); 
            $stmt->execute([$_GET['id']]); 
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); 
        } catch (Exception $e) { 
            echo json_encode(['error' => $e->getMessage()]); 
        } 
        exit(); 
    }

    // A2. Thêm lịch làm việc
    if ($action == 'add_schedule_bulk') { 
        header('Content-Type: application/json'); 
        $json = file_get_contents('php://input'); 
        $data = json_decode($json, true); 
        
        if (!$data) { echo json_encode(['status'=>'error', 'message'=>'No Data']); exit(); }
        
        $id_bacsi = $data['id_bacsi']; 
        $id_giuong = $data['id_giuongbenh']; // Admin chủ động chọn giường ở chức năng này
        $fromDate = strtotime($data['fromDate']); 
        $toDate = strtotime($data['toDate']); 
        $days = $data['days']; 
        $shifts = $data['shifts']; 
        $count = 0; 
        
        for ($i = $fromDate; $i <= $toDate; $i += 86400) { 
            $currentDate = date('Y-m-d', $i); 
            $dayOfWeek = date('N', $i); 
            
            if (in_array($dayOfWeek, $days)) { 
                foreach ($shifts as $shift) { 
                    $start = ($shift == 'Sang') ? '08:00:00' : '13:00:00'; 
                    $end = ($shift == 'Sang') ? '12:00:00' : '17:00:00'; 
                    
                    // Check xem bác sĩ đã có lịch chưa
                    $checkBS = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi=? AND ngay_hieu_luc=? AND gio_bat_dau=?");
                    $checkBS->execute([$id_bacsi, $currentDate, $start]);
                    
                    // Check xem giường đó có ai ngồi chưa
                    $checkBed = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_giuongbenh=? AND ngay_hieu_luc=? AND gio_bat_dau=?");
                    $checkBed->execute([$id_giuong, $currentDate, $start]);

                    if ($checkBS->rowCount() == 0 && $checkBed->rowCount() == 0) { 
                        $conn->prepare("INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?, ?, ?, ?, ?, ?, ?)")
                             ->execute([$id_bacsi, $id_giuong, $id_quantrivien, $dayOfWeek, $start, $end, $currentDate]); 
                        $count++; 
                    } 
                } 
            } 
        } 
        echo json_encode(['status' => 'success', 'message' => "Đã thêm thành công $count ca làm việc!"]); 
        exit(); 
    }

    // -------------------------------------------------------------
    // GROUP B: QUẢN LÝ LỊCH HẸN (PHỨC TẠP NHẤT)
    // -------------------------------------------------------------

    // B1. Admin đặt lịch cho Bệnh nhân cũ
    if ($action == 'add_appointment_admin') {
        $id_benhnhan = $_POST['id_benhnhan'];
        $id_bacsi    = $_POST['id_bacsi'];
        $id_dichvu   = $_POST['id_dichvu'];
        $date        = $_POST['date'];
        $shift       = $_POST['shift']; 
        
        $start_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $end_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';
        $day_of_week = date('N', strtotime($date));

        // 1. Check xem bác sĩ có nghỉ phép không
        $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'");
        $check_leave->execute([$id_bacsi, $date, $shift]);
        if ($check_leave->rowCount() > 0) {
            echo "<script>alert('LỖI: Bác sĩ đã xin nghỉ phép vào ca này!'); window.location.href='../views/admin.php';</script>"; exit();
        }

        // 2. Tự động thêm lịch làm việc nếu chưa có
        $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
        $check_work->execute([$id_bacsi, $start_time, $date]);
        
        if ($check_work->rowCount() == 0) {
            // Tìm giường trống (Logic mới)
            $available_bed_id = getAvailableBed($conn, $date, $start_time, $end_time);
            
            if (!$available_bed_id) {
                echo "<script>alert('LỖI HỆ THỐNG: Không còn ghế/giường trống nào trong khung giờ này! Vui lòng chọn ca khác.'); window.location.href='../views/admin.php';</script>"; exit();
            }

            // Tạo lịch làm việc mới với giường vừa tìm được
            $sql_add_work = "INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $conn->prepare($sql_add_work)->execute([$id_bacsi, $available_bed_id, $id_quantrivien, $day_of_week, $start_time, $end_time, $date]);
        }

        // 3. Tính toán thời gian hẹn (Nối đuôi)
        $stmt_dur = $conn->prepare("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = ?");
        $stmt_dur->execute([$id_dichvu]);
        $current_service_duration = $stmt_dur->fetchColumn();

        $sql_last = "SELECT lh.ngay_gio_hen, dv.thoi_gian_phut FROM lichhen lh JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu WHERE lh.id_bacsi = ? AND DATE(lh.ngay_gio_hen) = ? AND TIME(lh.ngay_gio_hen) >= ? AND TIME(lh.ngay_gio_hen) < ? ORDER BY lh.ngay_gio_hen DESC LIMIT 1";
        $stmt_last = $conn->prepare($sql_last);
        $stmt_last->execute([$id_bacsi, $date, $start_time, $end_time]);
        $last_appt = $stmt_last->fetch(PDO::FETCH_ASSOC);

        if ($last_appt) {
            $new_timestamp = strtotime($last_appt['ngay_gio_hen']) + ($last_appt['thoi_gian_phut'] * 60);
        } else {
            $new_timestamp = strtotime("$date $start_time");
        }

        if ($new_timestamp + ($current_service_duration * 60) > strtotime("$date $end_time")) {
            echo "<script>alert('Ca này đã kín lịch! Không đủ thời gian thực hiện dịch vụ.'); window.location.href='../views/admin.php';</script>"; exit();
        }

        $ngay_gio_final = date('Y-m-d H:i:s', $new_timestamp);

        // 4. Insert Lịch hẹn
        $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, 'da_xac_nhan', 'quan_tri_vien')")->execute([$id_benhnhan, $id_bacsi, $id_dichvu, $ngay_gio_final]);
        
        // 5. Gửi mail thông báo
        $info = $conn->query("SELECT bn.email, bn.ten_day_du, bs.ten_day_du as ten_bs, dv.ten_dich_vu FROM benhnhan bn, bacsi bs, dichvu dv WHERE bn.id_benhnhan=$id_benhnhan AND bs.id_bacsi=$id_bacsi AND dv.id_dichvu=$id_dichvu")->fetch(PDO::FETCH_ASSOC);
        if ($info && !empty($info['email'])) {
             sendAppointmentConfirmation($info['email'], $info['ten_day_du'], date('H:i d/m/Y', $new_timestamp), $info['ten_bs'], $info['ten_dich_vu']);
        }

        echo "<script>alert('Đã đặt lịch thành công!'); window.location.href='../views/admin.php';</script>";
    }

    // B2. Admin tiếp nhận Khách vãng lai (Tạo tài khoản + Đặt lịch)
    elseif ($action == 'add_walkin_admin') {
        $name      = $_POST['ten_day_du'];
        $phone     = $_POST['sdt'];
        $email     = !empty($_POST['email']) ? $_POST['email'] : null;
        $id_dichvu = $_POST['id_dichvu'];
        $id_bacsi  = $_POST['id_bacsi'];
        $shift     = $_POST['shift']; 

        $date = date('Y-m-d'); // Hôm nay
        $start_time = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
        $end_time   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';
        $day_of_week = date('N');
        
        $conn->beginTransaction(); // Bắt đầu giao dịch để đảm bảo toàn vẹn

        try {
            // 1. Tạo/Tìm user
            $stmt_check = $conn->prepare("SELECT id_benhnhan, email FROM benhnhan WHERE sdt = ?");
            $stmt_check->execute([$phone]);
            $pat = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($pat) {
                $id_benhnhan = $pat['id_benhnhan'];
                if(empty($email) && !empty($pat['email'])) $email = $pat['email']; 
            } else {
                $random_pass = (string)rand(100000, 999999);
                $pass_hash = password_hash($random_pass, PASSWORD_DEFAULT);
                $conn->prepare("INSERT INTO benhnhan (ten_day_du, sdt, email, mat_khau_hash) VALUES (?, ?, ?, ?)")->execute([$name, $phone, $email, $pass_hash]);
                $id_benhnhan = $conn->lastInsertId();
                if (!empty($email)) { sendNewAccountInfo($email, $name, $phone, $random_pass); }
            }

            // 2. Check nghỉ phép
            $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'");
            $check_leave->execute([$id_bacsi, $date, $shift]);
            if ($check_leave->rowCount() > 0) {
                 $conn->rollBack();
                 echo "<script>alert('LỖI: Bác sĩ đang nghỉ phép!'); window.location.href='../views/admin.php';</script>"; exit();
            }

            // 3. Tự động thêm lịch làm việc + Tìm giường trống
            $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
            $check_work->execute([$id_bacsi, $start_time, $date]);
            
            if ($check_work->rowCount() == 0) {
                $available_bed_id = getAvailableBed($conn, $date, $start_time, $end_time);
                
                if (!$available_bed_id) {
                     $conn->rollBack();
                     echo "<script>alert('LỖI: Không còn giường trống!'); window.location.href='../views/admin.php';</script>"; exit();
                }

                $conn->prepare("INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?, ?, ?, ?, ?, ?, ?)")
                     ->execute([$id_bacsi, $available_bed_id, $id_quantrivien, $day_of_week, $start_time, $end_time, $date]);
            }

            // 4. Tính giờ
            $stmt_dur = $conn->prepare("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = ?");
            $stmt_dur->execute([$id_dichvu]);
            $current_service_duration = $stmt_dur->fetchColumn();

            $sql_last = "SELECT lh.ngay_gio_hen, dv.thoi_gian_phut FROM lichhen lh JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu WHERE lh.id_bacsi = ? AND DATE(lh.ngay_gio_hen) = ? AND TIME(lh.ngay_gio_hen) >= ? AND TIME(lh.ngay_gio_hen) < ? ORDER BY lh.ngay_gio_hen DESC LIMIT 1";
            $stmt_last = $conn->prepare($sql_last);
            $stmt_last->execute([$id_bacsi, $date, $start_time, $end_time]);
            $last_appt = $stmt_last->fetch(PDO::FETCH_ASSOC);

            if ($last_appt) {
                $new_timestamp = strtotime($last_appt['ngay_gio_hen']) + ($last_appt['thoi_gian_phut'] * 60);
            } else {
                $new_timestamp = strtotime("$date $start_time");
            }

            // Nếu là vãng lai (ngay bây giờ), đừng để giờ hẹn trong quá khứ quá xa
            if ($new_timestamp < time()) {
                 $new_timestamp = time(); 
                 $new_timestamp = ceil($new_timestamp / 300) * 300; // Làm tròn lên 5 phút
            }

            if ($new_timestamp + ($current_service_duration * 60) > strtotime("$date $end_time")) {
                 $conn->rollBack();
                 echo "<script>alert('Ca này đã kín!'); window.location.href='../views/admin.php';</script>"; exit();
            }

            $ngay_gio_final = date('Y-m-d H:i:s', $new_timestamp);

            // 5. Insert Lịch hẹn
            $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?, ?, ?, ?, 'da_xac_nhan', 'quan_tri_vien')")->execute([$id_benhnhan, $id_bacsi, $id_dichvu, $ngay_gio_final]);

            if (!empty($email)) {
                $doc_name = $conn->query("SELECT ten_day_du FROM bacsi WHERE id_bacsi=$id_bacsi")->fetchColumn();
                $ser_name = $conn->query("SELECT ten_dich_vu FROM dichvu WHERE id_dichvu=$id_dichvu")->fetchColumn();
                sendAppointmentConfirmation($email, $name, date('H:i d/m/Y', $new_timestamp), $doc_name, $ser_name);
            }

            $conn->commit();
            echo "<script>alert('Tiếp nhận thành công! Giờ khám: ".date('H:i', $new_timestamp)."'); window.location.href='../views/admin.php';</script>";

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // B3. Duyệt lịch hẹn (Approve) & Xếp ca tự động (CẬP NHẬT LOGIC TÍNH GIỜ)
    elseif ($action == 'approve_appointment' && isset($_GET['id'])) {
        $id_lichhen = $_GET['id'];
        
        // Lấy thông tin lịch hẹn
        $stmt_get = $conn->prepare("SELECT * FROM lichhen WHERE id_lichhen = ?");
        $stmt_get->execute([$id_lichhen]);
        $appt = $stmt_get->fetch(PDO::FETCH_ASSOC);
        
        if ($appt) {
            $id_bacsi = $appt['id_bacsi'];
            $date = date('Y-m-d', strtotime($appt['ngay_gio_hen']));
            $hour = date('H', strtotime($appt['ngay_gio_hen']));
            
            // Xác định ca dựa trên giờ khách chọn ban đầu
            $start_shift_time = ($hour < 12) ? '08:00:00' : '13:00:00';
            $end_shift_time   = ($hour < 12) ? '12:00:00' : '17:00:00';
            $shift_code       = ($hour < 12) ? 'Sang' : 'Chieu';
            $day_of_week      = date('N', strtotime($date));

            // 1. Chặn nếu Bác sĩ đang nghỉ phép
            $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi = ? AND ngay_nghi = ? AND ca_nghi = ? AND trang_thai = 'da_duyet'");
            $check_leave->execute([$id_bacsi, $date, $shift_code]);
            if ($check_leave->rowCount() > 0) {
                echo "<script>alert('CẢNH BÁO: Bác sĩ đã nghỉ phép! Vui lòng Hủy hoặc Chuyển bác sĩ.'); window.location.href='../views/admin.php#conflict-appts';</script>"; exit();
            }

            // 2. Tạo lịch làm việc nếu chưa có (Quyền đặc biệt của Admin)
            $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi = ? AND gio_bat_dau = ? AND ngay_hieu_luc = ?");
            $check_work->execute([$id_bacsi, $start_shift_time, $date]);
            
            if ($check_work->rowCount() == 0) {
                // Tìm giường trống
                $available_bed_id = getAvailableBed($conn, $date, $start_shift_time, $end_shift_time);
                if (!$available_bed_id) {
                    echo "<script>alert('KHÔNG THỂ DUYỆT: Không còn ghế/giường trống!'); window.location.href='../views/admin.php';</script>"; exit();
                }
                // Tạo lịch
                $conn->prepare("INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?, ?, ?, ?, ?, ?, ?)")
                     ->execute([$id_bacsi, $available_bed_id, $id_quantrivien, $day_of_week, $start_shift_time, $end_shift_time, $date]);
            }
            
            // 3. TÍNH TOÁN GIỜ KHÁM CHÍNH XÁC (QUEUE SYSTEM)
            // Lấy tổng phút của các lịch ĐÃ XÁC NHẬN trong cùng ca (trừ lịch hiện tại)
            $sql_calc = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                         FROM lichhen lh 
                         JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                         WHERE lh.id_bacsi = ? 
                         AND DATE(lh.ngay_gio_hen) = ? 
                         AND lh.trang_thai = 'da_xac_nhan'
                         AND lh.id_lichhen != ? 
                         AND (
                            (HOUR(lh.ngay_gio_hen) < 12 AND ? = '08:00:00') OR 
                            (HOUR(lh.ngay_gio_hen) >= 12 AND ? = '13:00:00')
                         )";
            $stmt_calc = $conn->prepare($sql_calc);
            $stmt_calc->execute([$id_bacsi, $date, $id_lichhen, $start_shift_time, $start_shift_time]);
            $result_calc = $stmt_calc->fetch(PDO::FETCH_ASSOC);
            
            $accumulated_minutes = (int)$result_calc['total_minutes']; // Tổng phút đã xếp
            
            // Thời gian bắt đầu dự kiến = Đầu ca + Tổng thời gian khách trước
            $start_timestamp = strtotime("$date $start_shift_time");
            $real_start_time = $start_timestamp + ($accumulated_minutes * 60);
            
            // Lấy thời gian dịch vụ hiện tại
            $curr_service_time = $conn->query("SELECT thoi_gian_phut FROM dichvu WHERE id_dichvu = " . $appt['id_dichvu'])->fetchColumn();

            // Kiểm tra quá giờ ca không
            if (($real_start_time + ($curr_service_time * 60)) > strtotime("$date $end_shift_time")) {
                 echo "<script>alert('CẢNH BÁO: Ca làm việc này đã quá tải! Không đủ thời gian.'); window.location.href='../views/admin.php';</script>"; exit();
            }

            $final_datetime = date('Y-m-d H:i:s', $real_start_time);

            // 4. Update DB
            $update_stmt = $conn->prepare("UPDATE lichhen SET trang_thai = 'da_xac_nhan', ngay_gio_hen = ? WHERE id_lichhen = ?");
            $update_stmt->execute([$final_datetime, $id_lichhen]);
            
            // 5. Gửi Mail
            $sql_mail = "SELECT bn.email, bn.ten_day_du, bs.ten_day_du AS ten_bacsi, dv.ten_dich_vu 
                         FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan 
                         JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi 
                         JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu 
                         WHERE lh.id_lichhen = ?";
            $stmt_m = $conn->prepare($sql_mail); $stmt_m->execute([$id_lichhen]); $data_mail = $stmt_m->fetch(PDO::FETCH_ASSOC);

            if ($data_mail && !empty($data_mail['email'])) {
                $dateStr = date('H:i d/m/Y', strtotime($final_datetime));
                sendAppointmentConfirmation($data_mail['email'], $data_mail['ten_day_du'], $dateStr, $data_mail['ten_bacsi'], $data_mail['ten_dich_vu']);
            }
            
            echo "<script>alert('Đã duyệt và xếp lịch thành công! Giờ khám dự kiến: " . date('H:i', $real_start_time) . "'); window.location.href='../views/admin.php';</script>";
        }
    }

    // B4. Hủy/Từ chối lịch hẹn
    elseif ($action == 'reject_appointment' && isset($_GET['id'])) {
        $conn->prepare("UPDATE lichhen SET trang_thai='huy' WHERE id_lichhen=?")->execute([$_GET['id']]);
        echo "<script>alert('Đã hủy lịch!'); window.location.href='../views/admin.php';</script>";
    }

    // -------------------------------------------------------------
    // GROUP C: QUẢN LÝ BÁC SĨ (Đã sửa lỗi thêm bác sĩ)
    // -------------------------------------------------------------
    
    // C1. Thêm Bác sĩ
    elseif ($action == 'add_doctor') {
        $ten_bacsi = $_POST['ten_day_du']; 
        $sdt = $_POST['sdt']; 
        $mat_khau_tho = $_POST['mat_khau']; 
        $chuyen_khoa = $_POST['chuyen_khoa'];
        
        // Check trùng SĐT
        $check = $conn->prepare("SELECT id_bacsi FROM bacsi WHERE sdt = ?"); 
        $check->execute([$sdt]);
        if ($check->rowCount() > 0) { 
            echo "<script>alert('SĐT đã tồn tại!'); window.location.href='../views/admin.php';</script>"; exit(); 
        }
        
        $mat_khau_hash = password_hash($mat_khau_tho, PASSWORD_DEFAULT);
        
        // Insert (Không còn cột id_giuongbenh nữa)
        $sql = "INSERT INTO bacsi (ten_day_du, sdt, mat_khau_hash, chuyen_khoa, id_quantrivien_tao) VALUES (?, ?, ?, ?, ?)";
        $conn->prepare($sql)->execute([$ten_bacsi, $sdt, $mat_khau_hash, $chuyen_khoa, $id_quantrivien]);
        
        echo "<script>alert('Thêm Bác sĩ thành công!'); window.location.href='../views/admin.php';</script>";
    }

    // C2. Sửa Bác sĩ
    elseif ($action == 'edit_doctor') {
        $conn->prepare("UPDATE bacsi SET ten_day_du=?, sdt=?, chuyen_khoa=? WHERE id_bacsi=?")
             ->execute([$_POST['ten_day_du'], $_POST['sdt'], $_POST['chuyen_khoa'], $_POST['id_bacsi']]);
        echo "<script>alert('Cập nhật thành công!'); window.location.href='../views/admin.php';</script>";
    }

    // C3. Xóa Bác sĩ
    elseif ($action == 'delete_doctor' && isset($_GET['id'])) {
         try { 
             $conn->prepare("DELETE FROM bacsi WHERE id_bacsi=?")->execute([$_GET['id']]); 
             echo "<script>alert('Đã xóa bác sĩ!'); window.location.href='../views/admin.php';</script>"; 
         } catch (Exception $e) { 
             echo "<script>alert('Không thể xóa bác sĩ này vì đang có dữ liệu liên quan (lịch khám, lịch hẹn)!'); window.location.href='../views/admin.php';</script>"; 
         }
    }

    // C4. Reset mật khẩu Bác sĩ
    elseif ($action == 'reset_pass_doctor' && isset($_POST['id'])) {
        $hash = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
        $conn->prepare("UPDATE bacsi SET mat_khau_hash=? WHERE id_bacsi=?")->execute([$hash, $_POST['id']]);
        echo "<script>alert('Đổi mật khẩu thành công!'); window.location.href='../views/admin.php';</script>";
    }

    // -------------------------------------------------------------
    // GROUP D: QUẢN LÝ DỊCH VỤ (Sửa lỗi mapping form)
    // -------------------------------------------------------------

    // D1. Thêm Dịch vụ
    elseif ($action == 'add_service') {
        $name = $_POST['name']; 
        $desc = $_POST['desc']; 
        $price = $_POST['price']; 
        $time = $_POST['time'];
        
        $conn->prepare("INSERT INTO dichvu (ten_dich_vu, mo_ta, gia_tien, thoi_gian_phut) VALUES (?, ?, ?, ?)")
             ->execute([$name, $desc, $price, $time]);
        
        echo "<script>alert('Thêm dịch vụ thành công!'); window.location.href='../views/admin.php';</script>";
    }
    
    // D2. Sửa Dịch vụ
    elseif ($action == 'edit_service') {
        $id = $_POST['id']; 
        $name = $_POST['name']; 
        $desc = $_POST['desc']; 
        $price = $_POST['price']; 
        $time = $_POST['time'];
        
        $conn->prepare("UPDATE dichvu SET ten_dich_vu=?, mo_ta=?, gia_tien=?, thoi_gian_phut=? WHERE id_dichvu=?")
             ->execute([$name, $desc, $price, $time, $id]);
             
        echo "<script>alert('Cập nhật dịch vụ thành công!'); window.location.href='../views/admin.php';</script>";
    }

    // D3. Xóa Dịch vụ
    elseif ($action == 'delete_service' && isset($_GET['id'])) {
        // Kiểm tra xem dịch vụ có đang được dùng trong lịch hẹn nào không
        $check = $conn->prepare("SELECT id_lichhen FROM lichhen WHERE id_dichvu = ?"); 
        $check->execute([$_GET['id']]);
        
        if ($check->rowCount() > 0) { 
            echo "<script>alert('Không thể xóa! Dịch vụ này đang có lịch hẹn gắn kết.'); window.location.href='../views/admin.php';</script>"; 
        } else { 
            $conn->prepare("DELETE FROM dichvu WHERE id_dichvu = ?")->execute([$_GET['id']]); 
            echo "<script>alert('Đã xóa dịch vụ!'); window.location.href='../views/admin.php';</script>"; 
        }
    }

    // -------------------------------------------------------------
    // GROUP E: QUẢN LÝ NGHỈ PHÉP & XUNG ĐỘT
    // -------------------------------------------------------------

    // E1. Duyệt nghỉ
    elseif ($action == 'approve_leave' && isset($_GET['id'])) {
        $id_yeucau = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM yeucaunghi WHERE id_yeucau = ?"); $stmt->execute([$id_yeucau]); $req = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($req) {
            $id_bacsi = $req['id_bacsi']; $date = $req['ngay_nghi']; $ca = $req['ca_nghi'];
            $conn->prepare("UPDATE yeucaunghi SET trang_thai='da_duyet', id_quantrivien_duyet=? WHERE id_yeucau=?")->execute([$id_quantrivien, $id_yeucau]);
            
            // Tìm các lịch hẹn bị trùng để gửi mail báo hoãn
            $time_condition = ($ca == 'Sang') ? "HOUR(ngay_gio_hen) < 12" : "HOUR(ngay_gio_hen) >= 12";
            $sql_find = "SELECT lh.id_lichhen, bn.email, bn.ten_day_du FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan WHERE lh.id_bacsi = ? AND DATE(lh.ngay_gio_hen) = ? AND $time_condition AND lh.trang_thai IN ('da_xac_nhan', 'cho_xac_nhan')";
            $stmt_find = $conn->prepare($sql_find); $stmt_find->execute([$id_bacsi, $date]); $affected = $stmt_find->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($affected as $appt) {
                if (!empty($appt['email'])) {
                    $subject = "Thông báo hoãn lịch khám - iDental";
                    $body = "<h3>Xin chào {$appt['ten_day_du']},</h3><p>Bác sĩ của bạn có việc bận đột xuất vào ngày đã hẹn. Nhân viên phòng khám sẽ liên hệ lại sớm nhất để dời lịch. Mong bạn thông cảm.</p>";
                    sendMailGeneric($appt['email'], $subject, $body);
                }
            }
            echo "<script>alert('Đã duyệt yêu cầu nghỉ! Đã gửi mail thông báo cho ".count($affected)." khách hàng.'); window.location.href='../views/admin.php#leave-requests';</script>";
        }
    }

    // E2. Từ chối nghỉ
    elseif ($action == 'reject_leave' && isset($_GET['id'])) {
        $conn->prepare("UPDATE yeucaunghi SET trang_thai='tu_choi', id_quantrivien_duyet=? WHERE id_yeucau=?")->execute([$id_quantrivien, $_GET['id']]);
        echo "<script>alert('Đã từ chối yêu cầu.'); window.location.href='../views/admin.php#leave-requests';</script>";
    }

    // E3. Chuyển Bác sĩ (Xử lý xung đột)
    elseif ($action == 'switch_doctor') {
        $id_lichhen = $_POST['id_lichhen']; 
        $new_doc = $_POST['new_doctor_id']; 
        $new_time = $_POST['new_datetime'];
        
        $sql = "UPDATE lichhen SET id_bacsi = ?"; $params = [$new_doc];
        if (!empty($new_time)) { $sql .= ", ngay_gio_hen = ?"; $params[] = $new_time; }
        $sql .= ", trang_thai = 'da_xac_nhan' WHERE id_lichhen = ?"; $params[] = $id_lichhen;
        
        $conn->prepare($sql)->execute($params);
        
        $info = $conn->query("SELECT bn.email, bn.ten_day_du, bs.ten_day_du as ten_bs, dv.ten_dich_vu FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan=bn.id_benhnhan JOIN bacsi bs ON lh.id_bacsi=bs.id_bacsi JOIN dichvu dv ON lh.id_dichvu=dv.id_dichvu WHERE lh.id_lichhen=$id_lichhen")->fetch();
        if ($info && !empty($info['email'])) {
             $dateStr = !empty($new_time) ? date('H:i d/m/Y', strtotime($new_time)) : "Giờ đã hẹn (nhưng thay đổi bác sĩ)";
             sendAppointmentConfirmation($info['email'], $info['ten_day_du'], $dateStr, $info['ten_bs'], $info['ten_dich_vu']);
        }
        echo "<script>alert('Đã chuyển bác sĩ thành công!'); window.location.href='../views/admin.php#conflict-appts';</script>";
    }

    // E4. Hủy lịch xung đột
    elseif ($action == 'cancel_conflict_appt' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $info = $conn->query("SELECT bn.email, bn.ten_day_du FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan=bn.id_benhnhan WHERE lh.id_lichhen=$id")->fetch();
        $conn->prepare("UPDATE lichhen SET trang_thai = 'huy' WHERE id_lichhen = ?")->execute([$id]);
        
        if ($info && !empty($info['email'])) {
            sendMailGeneric($info['email'], "Hủy lịch hẹn - iDental", "<h3>Xin lỗi {$info['ten_day_du']}, lịch hẹn đã bị hủy do bác sĩ bận đột xuất và bạn chưa đồng ý dời lịch.</h3>");
        }
        echo "<script>alert('Đã hủy lịch!'); window.location.href='../views/admin.php#conflict-appts';</script>";
    }

    // -------------------------------------------------------------
    // GROUP F: CÁC CHỨC NĂNG KHÁC (Admin, Patient)
    // -------------------------------------------------------------

    elseif ($action == 'add_admin') {
        $check = $conn->prepare("SELECT id_quantrivien FROM quantrivien WHERE ten_dang_nhap = ?"); $check->execute([$_POST['username']]);
        if ($check->rowCount() > 0) { echo "<script>alert('Tên đăng nhập đã tồn tại!'); window.location.href='../views/admin.php';</script>"; } 
        else {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $conn->prepare("INSERT INTO QUANTRIVIEN (ten_dang_nhap, mat_khau_hash, ten_day_du, id_quantrivien_tao) VALUES (?, ?, ?, ?)")->execute([$_POST['username'], $hash, $_POST['fullname'], $id_quantrivien]);
            echo "<script>alert('Thêm Admin mới thành công!'); window.location.href='../views/admin.php';</script>";
        }
    }
    
    elseif ($action == 'delete_admin' && isset($_GET['id'])) {
        if ($_GET['id'] == 1) { echo "<script>alert('Không thể xóa Admin mặc định!'); window.location.href='../views/admin.php';</script>"; } 
        else { $conn->prepare("DELETE FROM quantrivien WHERE id_quantrivien = ?")->execute([$_GET['id']]); echo "<script>alert('Đã xóa Admin!'); window.location.href='../views/admin.php';</script>"; }
    }

    elseif ($action == 'change_self_pass') {
        $hash = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
        $conn->prepare("UPDATE quantrivien SET mat_khau_hash = ? WHERE id_quantrivien = ?")->execute([$hash, $id_quantrivien]);
        echo "<script>alert('Đổi mật khẩu thành công!'); window.location.href='../views/admin.php';</script>";
    }

    elseif ($action == 'delete_patient' && isset($_GET['id'])) {
        $conn->prepare("DELETE FROM benhnhan WHERE id_benhnhan = ?")->execute([$_GET['id']]);
        echo "<script>alert('Đã xóa bệnh nhân!'); window.location.href='../views/admin.php';</script>";
    }

    else {
        // Fallback
        header("Location: ../views/admin.php");
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "<h1>Đã xảy ra lỗi hệ thống</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<a href='../views/admin.php'>Quay lại</a>";
}
?>