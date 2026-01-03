<?php
session_start();
require '../config/db_connect.php';
require '../includes/send_mail.php'; 

// 0. CHECK PERMISSION
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         header('Content-Type: application/json'); 
         echo json_encode(['status'=>'error', 'message'=>'Unauthorized']); 
         exit();
    }
    header("Location: ../views/dangnhap.php"); exit();
}

$id_quantrivien = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// 1. HELPER: Tìm giường trống
function getAvailableBed($conn, $date, $start_time, $end_time) {
    $sql = "SELECT id_giuongbenh FROM giuongbenh 
            WHERE id_giuongbenh NOT IN (
                SELECT id_giuongbenh FROM lichlamviec 
                WHERE ngay_hieu_luc = :date 
                AND ((gio_bat_dau < :end_time) AND (gio_ket_thuc > :start_time))
            ) LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date, ':start_time' => $start_time, ':end_time' => $end_time]);
    return $stmt->fetchColumn(); 
}

try {
    // =============================================================
    // GROUP A: AJAX JSON RESPONSES
    // =============================================================

    if ($action == 'search_patient') {
        header('Content-Type: application/json');
        $keyword = $_GET['keyword'] ?? '';
        $sql = "SELECT id_benhnhan, ten_day_du, sdt, email FROM benhnhan WHERE sdt LIKE ? OR ten_day_du LIKE ? LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute(["%$keyword%", "%$keyword%"]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    if ($action == 'filter_appointments') {
        header('Content-Type: application/json');
        $tab_status = $_GET['tab_status'] ?? '';
        $phone = $_GET['phone'] ?? '';
        $doctor_id = $_GET['doctor_id'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';

        $sql = "SELECT lh.*, bn.ten_day_du as ten_bn, bn.sdt, bs.ten_day_du as ten_bs, dv.ten_dich_vu 
                FROM lichhen lh 
                JOIN benhnhan bn ON lh.id_benhnhan = bn.id_benhnhan
                LEFT JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi
                JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                WHERE 1=1";
        $params = [];

        if ($tab_status == 'pending') $sql .= " AND lh.trang_thai = 'cho_xac_nhan'";
        elseif ($tab_status == 'confirmed') $sql .= " AND lh.trang_thai = 'da_xac_nhan'";
        elseif ($tab_status == 'completed') $sql .= " AND lh.trang_thai = 'hoan_thanh'";
        elseif ($tab_status == 'cancelled') $sql .= " AND lh.trang_thai = 'huy'";

        if ($phone) { $sql .= " AND bn.sdt LIKE ?"; $params[] = "%$phone%"; }
        if ($doctor_id) { $sql .= " AND lh.id_bacsi = ?"; $params[] = $doctor_id; }
        if ($date_from) { $sql .= " AND DATE(lh.ngay_gio_hen) >= ?"; $params[] = $date_from; }
        if ($date_to) { $sql .= " AND DATE(lh.ngay_gio_hen) <= ?"; $params[] = $date_to; }
        
        $sql .= " ORDER BY lh.ngay_gio_hen DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    // A3b. Lấy lịch làm việc (AJAX cho admin)
    if ($action == 'get_schedule_admin') {
        header('Content-Type: application/json');
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';

        if (!$from || !$to) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu khoảng ngày']);
            exit();
        }

        try {
            $period = new DatePeriod(new DateTime($from), new DateInterval('P1D'), (new DateTime($to))->modify('+1 day'));
            $dates = [];
            foreach ($period as $dt) {
                $dates[] = $dt->format('Y-m-d');
            }

            // Map ngày nghỉ để đánh dấu ca nghỉ
            $leaves = $conn->prepare("SELECT id_bacsi, ngay_nghi, ca_nghi FROM yeucaunghi WHERE trang_thai='da_duyet' AND ngay_nghi BETWEEN ? AND ?");
            $leaves->execute([$from, $to]);
            $leave_map = [];
            foreach ($leaves->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $leave_map[$l['ngay_nghi']][$l['ca_nghi']][] = $l['id_bacsi'];
            }

            $schedule_stmt = $conn->prepare("SELECT llv.*, bs.ten_day_du FROM lichlamviec llv JOIN bacsi bs ON llv.id_bacsi = bs.id_bacsi WHERE llv.ngay_hieu_luc BETWEEN ? AND ? ORDER BY llv.gio_bat_dau");
            $schedule_stmt->execute([$from, $to]);
            $sch_rows = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);

            $schedule_map = ['Sang' => [], 'Chieu' => []];
            foreach ($dates as $d) { $schedule_map['Sang'][$d] = []; $schedule_map['Chieu'][$d] = []; }

            foreach ($sch_rows as $r) {
                $d = $r['ngay_hieu_luc'];
                $shift = (date('H', strtotime($r['gio_bat_dau'])) < 12) ? 'Sang' : 'Chieu';
                $is_off = (isset($leave_map[$d][$shift]) && in_array($r['id_bacsi'], $leave_map[$d][$shift]));
                $schedule_map[$shift][$d][] = [
                    'name' => $r['ten_day_du'] . ($is_off ? ' (Nghỉ)' : ''),
                    'is_off' => $is_off
                ];
            }

            $resp_dates = [];
            foreach ($dates as $d) {
                $resp_dates[] = [
                    'date' => $d,
                    'display' => date('d/m', strtotime($d)),
                    'dow' => date('D', strtotime($d))
                ];
            }

            echo json_encode([
                'status' => 'success',
                'dates' => $resp_dates,
                'schedule' => $schedule_map
            ]);
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($action == 'approve_schedule_request' || $action == 'reject_schedule_request') {
        header('Content-Type: application/json');
        $req_id = $_POST['request_id'];
        $json_file = '../data/schedule_requests.json';
        
        if (!file_exists($json_file)) { echo json_encode(['status'=>'error', 'msg'=>'File not found']); exit(); }
        $data = json_decode(file_get_contents($json_file), true);
        $found_index = -1; $count = 0;
        
        foreach ($data as $index => $req) {
            if ($req['id'] === $req_id) {
                $found_index = $index;
                if ($action == 'approve_schedule_request') {
                    $doctor_id = $req['doctor_id'];
                    foreach ($req['shifts'] as $shift) {
                        $date = $shift['date']; $ca = $shift['shift'];
                        $start = ($ca == 'Sang') ? '08:00:00' : '13:00:00'; $end = ($ca == 'Sang') ? '12:00:00' : '17:00:00';
                        $day = date('N', strtotime($date));
                        
                        $chk = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi=? AND ngay_hieu_luc=? AND gio_bat_dau=?");
                        $chk->execute([$doctor_id, $date, $start]);
                        if($chk->rowCount() == 0) {
                            $bed = getAvailableBed($conn, $date, $start, $end);
                            if ($bed) {
                                $conn->prepare("INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?,?,?,?,?,?,?)")
                                     ->execute([$doctor_id, $bed, $id_quantrivien, $day, $start, $end, $date]);
                                $count++;
                            }
                        }
                    }
                }
                break;
            }
        }
        
        if ($found_index > -1) {
            array_splice($data, $found_index, 1);
            file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $msg = ($action == 'approve_schedule_request') ? "Đã xử lý." : "Đã từ chối.";
            if ($action == 'approve_schedule_request') {
                if ($count > 0) {
                    $msg = "Đã duyệt và thêm thành công $count ca làm việc.";
                } else {
                    $msg = "Yêu cầu đã được xử lý, nhưng không có ca mới nào được thêm (do trùng lịch đã có).";
                }
            }
            echo json_encode(['status'=>'success', 'msg'=> $msg]);
        } else { echo json_encode(['status'=>'error', 'msg'=>'Request not found']); }
        exit();
    }

    if ($action == 'get_patient_history') { 
        header('Content-Type: application/json'); 
        $stmt = $conn->prepare("SELECT lh.ngay_gio_hen, lh.trang_thai, dv.ten_dich_vu, bs.ten_day_du AS ten_bs FROM lichhen lh LEFT JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu LEFT JOIN bacsi bs ON lh.id_bacsi = bs.id_bacsi WHERE lh.id_benhnhan = ? ORDER BY lh.ngay_gio_hen DESC"); 
        $stmt->execute([$_GET['id']]); 
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); 
        exit(); 
    }

    // =============================================================
    // GROUP B: FORM ACTIONS & LOGIC
    // =============================================================

    if ($action == 'add_doctor') {
        $check = $conn->prepare("SELECT id_bacsi FROM bacsi WHERE sdt=? OR email=?"); 
        $check->execute([$_POST['sdt'], $_POST['email']]);
        if($check->rowCount()>0) { echo "<script>alert('Lỗi: SĐT hoặc Email đã tồn tại!'); window.history.back();</script>"; exit(); }
        
        $conn->prepare("INSERT INTO bacsi (ten_day_du, sdt, email, mat_khau_hash, chuyen_khoa, id_quantrivien_tao, trang_thai) VALUES (?,?,?,?,?,?,1)")
             ->execute([$_POST['ten_day_du'], $_POST['sdt'], $_POST['email'], password_hash($_POST['mat_khau'], PASSWORD_DEFAULT), $_POST['chuyen_khoa'], $id_quantrivien]);
        echo "<script>alert('Thêm bác sĩ thành công!'); location.href='../views/admin.php#doctors';</script>";
    }

    elseif ($action == 'edit_doctor') {
        $check = $conn->prepare("SELECT id_bacsi FROM bacsi WHERE (sdt=? OR email=?) AND id_bacsi != ?"); 
        $check->execute([$_POST['sdt'], $_POST['email'], $_POST['id_bacsi']]);
        if($check->rowCount()>0) { echo "<script>alert('Lỗi: SĐT hoặc Email bị trùng với bác sĩ khác!'); window.history.back();</script>"; exit(); }

        $sql = "UPDATE bacsi SET ten_day_du=?, sdt=?, email=?, chuyen_khoa=? WHERE id_bacsi=?";
        $params = [$_POST['ten_day_du'], $_POST['sdt'], $_POST['email'], $_POST['chuyen_khoa'], $_POST['id_bacsi']];
        if(!empty($_POST['mat_khau'])) { 
            $sql = str_replace("WHERE", ", mat_khau_hash=? WHERE", $sql); 
            array_splice($params, 4, 0, password_hash($_POST['mat_khau'], PASSWORD_DEFAULT)); 
        }
        $conn->prepare($sql)->execute($params);
        echo "<script>alert('Cập nhật thành công!'); location.href='../views/admin.php#doctors';</script>";
    }

    elseif ($action == 'toggle_doctor_status') {
        $id = $_GET['id'];
        $doctor = $conn->query("SELECT ten_day_du, email, trang_thai FROM bacsi WHERE id_bacsi=$id")->fetch();
        if ($doctor) {
            $new_status = ($doctor['trang_thai'] == 1) ? 0 : 1;
            $conn->prepare("UPDATE bacsi SET trang_thai=? WHERE id_bacsi=?")->execute([$new_status, $id]);
            if ($new_status == 0 && !empty($doctor['email'])) sendAccountLockNotification($doctor['email'], $doctor['ten_day_du']);
            echo "<script>alert('Đã thay đổi trạng thái tài khoản!'); location.href='../views/admin.php#doctors';</script>";
        }
    }

    // B2. BOOKING LOGIC (Admin & Walk-in)
    elseif ($action == 'add_appointment_admin' || $action == 'add_walkin_admin') {
        try {
            $conn->beginTransaction();
            $id_bs = $_POST['id_bacsi'];
            $id_dv = $_POST['id_dichvu'];
            $shift = $_POST['shift'];
            $date = ($action == 'add_walkin_admin') ? date('Y-m-d') : $_POST['date'];
            
            $stmt_stat = $conn->prepare("SELECT ten_day_du, trang_thai FROM bacsi WHERE id_bacsi = ?");
            $stmt_stat->execute([$id_bs]); $row_bs = $stmt_stat->fetch();
            if($row_bs['trang_thai'] == 0) throw new Exception("Bác sĩ đang bị khóa!");

            $chkL = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi=? AND ngay_nghi=? AND ca_nghi=? AND trang_thai='da_duyet'");
            $chkL->execute([$id_bs, $date, $shift]);
            if($chkL->rowCount() > 0) throw new Exception("Bác sĩ đã nghỉ phép ca $shift ngày $date!");

            $start = ($shift == 'Sang') ? '08:00:00' : '13:00:00';
            $end   = ($shift == 'Sang') ? '12:00:00' : '17:00:00';
            
            $chkW = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi=? AND gio_bat_dau=? AND ngay_hieu_luc=?");
            $chkW->execute([$id_bs, $start, $date]);
            
            if($chkW->rowCount() == 0) {
                $bed = getAvailableBed($conn, $date, $start, $end);
                if(!$bed) throw new Exception("Hết giường/ghế trống trong ca này!");
                
                $day_of_week = date('N', strtotime($date));
                $conn->prepare("INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?,?,?,?,?,?,?)")
                     ->execute([$id_bs, $bed, $id_quantrivien, $day_of_week, $start, $end, $date]);
            }

            if ($action == 'add_walkin_admin') {
                $phone = $_POST['sdt']; 
                $name = $_POST['ten_day_du']; 
                $email = $_POST['email'] ?? null;
                
                $stmt_pat = $conn->prepare("SELECT id_benhnhan FROM benhnhan WHERE sdt=?");
                $stmt_pat->execute([$phone]);
                $pat = $stmt_pat->fetch(PDO::FETCH_ASSOC);

                if ($pat) {
                    $id_bn = $pat['id_benhnhan'];
                } else {
                    $rand_pass = rand(100000, 999999);
                    $conn->prepare("INSERT INTO benhnhan (ten_day_du, sdt, email, mat_khau_hash) VALUES (?,?,?,?)")->execute([$name, $phone, $email, password_hash($rand_pass, PASSWORD_DEFAULT)]);
                    $id_bn = $conn->lastInsertId();
                    if(!empty($_POST['email'])) sendNewAccountInfo($_POST['email'], $name, $phone, $rand_pass);
                }
            } else { 
                $id_bn = $_POST['id_benhnhan']; 
            }

            $stmt_dur = $conn->prepare("SELECT ten_dich_vu, thoi_gian_phut FROM dichvu WHERE id_dichvu=?");
            $stmt_dur->execute([$id_dv]); 
            $dv_info = $stmt_dur->fetch();
            
            $sql_queue = "SELECT COALESCE(SUM(dv.thoi_gian_phut), 0) as total_minutes 
                          FROM lichhen lh 
                          JOIN dichvu dv ON lh.id_dichvu = dv.id_dichvu
                          WHERE lh.id_bacsi = ? 
                          AND DATE(lh.ngay_gio_hen) = ? 
                          AND lh.trang_thai IN ('da_xac_nhan', 'hoan_thanh')
                          AND TIME(lh.ngay_gio_hen) >= ? AND TIME(lh.ngay_gio_hen) < ?";
            
            $stmt_queue = $conn->prepare($sql_queue);
            $stmt_queue->execute([$id_bs, $date, $start, $end]);
            $waiting_minutes = (int)$stmt_queue->fetch(PDO::FETCH_ASSOC)['total_minutes'];
            
            $start_anchor = strtotime("$date $start");
            if ($date == date('Y-m-d')) {
                $start_anchor = max($start_anchor, time() + 900); 
            }
            
            $real_start_time = $start_anchor + ($waiting_minutes * 60);
            
            if (($real_start_time + ($dv_info['thoi_gian_phut'] * 60)) > strtotime("$date $end")) {
                throw new Exception("Ca làm việc đã kín, không đủ thời gian thực hiện dịch vụ này!");
            }

            $final_datetime = date('Y-m-d H:i:s', $real_start_time);

            $stmt_insert = $conn->prepare("INSERT INTO lichhen (id_benhnhan, id_bacsi, id_dichvu, ngay_gio_hen, trang_thai, nguoi_tao_lich) VALUES (?,?,?,?, 'da_xac_nhan', 'quan_tri_vien')");
            $stmt_insert->execute([$id_bn, $id_bs, $id_dv, $final_datetime]);
            
            $stmt_p = $conn->prepare("SELECT email, ten_day_du FROM benhnhan WHERE id_benhnhan=?"); 
            $stmt_p->execute([$id_bn]); 
            $p_info = $stmt_p->fetch();
            if($p_info['email']) sendAppointmentConfirmation($p_info['email'], $p_info['ten_day_du'], date('H:i d/m/Y', $real_start_time), $row_bs['ten_day_du'], $dv_info['ten_dich_vu']);

            $conn->commit();
            echo "<script>alert('Thành công! Giờ khám dự kiến: ".date('H:i d/m', $real_start_time)."'); location.href='../views/admin.php';</script>";

        } catch (Throwable $e) { 
            if($conn->inTransaction()) $conn->rollBack();
            echo "<script>alert('Lỗi: ".$e->getMessage()."'); window.history.back();</script>"; 
        }
    }

    elseif ($action == 'approve_appointment') {
        $conn->prepare("UPDATE lichhen SET trang_thai='da_xac_nhan' WHERE id_lichhen=?")->execute([$_GET['id']]);
        $info = $conn->query("SELECT bn.email, bn.ten_day_du, bs.ten_day_du as ten_bs, dv.ten_dich_vu, lh.ngay_gio_hen FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan=bn.id_benhnhan JOIN bacsi bs ON lh.id_bacsi=bs.id_bacsi JOIN dichvu dv ON lh.id_dichvu=dv.id_dichvu WHERE id_lichhen=".$_GET['id'])->fetch();
        if($info['email']) sendAppointmentConfirmation($info['email'], $info['ten_day_du'], date('H:i d/m/Y', strtotime($info['ngay_gio_hen'])), $info['ten_bs'], $info['ten_dich_vu']);
        echo "<script>alert('Đã xác nhận!'); location.href='../views/admin.php#appointments';</script>";
    }
    elseif ($action == 'reject_appointment') {
        $id = $_GET['id'];
        $info = $conn->query("SELECT bn.email, bn.ten_day_du, bs.ten_day_du as ten_bs, lh.ngay_gio_hen FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan=bn.id_benhnhan JOIN bacsi bs ON lh.id_bacsi=bs.id_bacsi WHERE id_lichhen=$id")->fetch();
        $conn->prepare("UPDATE lichhen SET trang_thai='huy' WHERE id_lichhen=?")->execute([$id]);
        if($info['email']) sendAbsenceNotification($info['email'], $info['ten_day_du'], date('H:i d/m/Y', strtotime($info['ngay_gio_hen'])), $info['ten_bs']);
        echo "<script>alert('Đã hủy lịch!'); location.href='../views/admin.php#appointments';</script>";
    }
    
    // B4. SWITCH DOCTOR
    elseif ($action == 'switch_doctor') {
        $id_lichhen = $_POST['id_lichhen'];
        $new_doc_id = $_POST['new_doctor_id'];

        try {
            if ($conn->query("SELECT trang_thai FROM bacsi WHERE id_bacsi=$new_doc_id")->fetchColumn() == 0) {
                throw new Exception("Bác sĩ được chọn đang bị khóa, không thể chuyển lịch!");
            }

            $conn->beginTransaction();

            $appt = $conn->query("SELECT ngay_gio_hen FROM lichhen WHERE id_lichhen = $id_lichhen")->fetch();
            $date = date('Y-m-d', strtotime($appt['ngay_gio_hen']));
            $hour = (int)date('H', strtotime($appt['ngay_gio_hen']));
            
            $shift_start = ($hour < 12) ? '08:00:00' : '13:00:00';
            $shift_end   = ($hour < 12) ? '12:00:00' : '17:00:00';
            $shift_code  = ($hour < 12) ? 'Sang' : 'Chieu';

            $check_leave = $conn->prepare("SELECT id_yeucau FROM yeucaunghi WHERE id_bacsi=? AND ngay_nghi=? AND ca_nghi=? AND trang_thai='da_duyet'");
            $check_leave->execute([$new_doc_id, $date, $shift_code]);
            if ($check_leave->rowCount() > 0) throw new Exception("Bác sĩ được chọn đang nghỉ phép vào ca này!");

            $check_work = $conn->prepare("SELECT id_lichlamviec FROM lichlamviec WHERE id_bacsi=? AND ngay_hieu_luc=? AND gio_bat_dau=?");
            $check_work->execute([$new_doc_id, $date, $shift_start]);
            
            if ($check_work->rowCount() == 0) {
                $bed = getAvailableBed($conn, $date, $shift_start, $shift_end);
                if (!$bed) throw new Exception("Hết giường trống trong ca này, không thể chuyển bác sĩ!");
                
                $day_of_week = date('N', strtotime($date));
                $conn->prepare("INSERT INTO lichlamviec (id_bacsi, id_giuongbenh, id_quantrivien_tao, ngay_trong_tuan, gio_bat_dau, gio_ket_thuc, ngay_hieu_luc) VALUES (?,?,?,?,?,?,?)")
                     ->execute([$new_doc_id, $bed, $id_quantrivien, $day_of_week, $shift_start, $shift_end, $date]);
            }

            $conn->prepare("UPDATE lichhen SET id_bacsi=?, trang_thai='da_xac_nhan' WHERE id_lichhen=?")->execute([$new_doc_id, $id_lichhen]);
            
            $conn->commit();
            echo "<script>alert('Đã chuyển bác sĩ thành công!'); location.href='../views/admin.php#requests';</script>";

        } catch (Throwable $e) {
            $conn->rollBack();
            echo "<script>alert('Lỗi: ".$e->getMessage()."'); window.history.back();</script>";
        }
    }

    elseif ($action == 'add_admin') {
        $check = $conn->prepare("SELECT id_quantrivien FROM quantrivien WHERE ten_dang_nhap=?"); $check->execute([$_POST['username']]);
        if($check->rowCount()>0) { echo "<script>alert('Username đã tồn tại'); location.href='../views/admin.php#admins';</script>"; exit(); }
        $conn->prepare("INSERT INTO quantrivien (ten_dang_nhap, ten_day_du, mat_khau_hash, id_quantrivien_tao) VALUES (?,?,?,?)")->execute([$_POST['username'], $_POST['fullname'], password_hash($_POST['password'], PASSWORD_DEFAULT), $id_quantrivien]);
        echo "<script>alert('Thêm Admin thành công'); location.href='../views/admin.php#admins';</script>";
    }
    
    // [CẬP NHẬT] XỬ LÝ LỖI KHÔNG THỂ XÓA ADMIN CÓ RÀNG BUỘC KHÓA NGOẠI
    elseif ($action == 'delete_admin') {
        $del_id = $_GET['id'];
        if($del_id == 1 || $del_id == $id_quantrivien) { 
            echo "<script>alert('Không thể xóa Super Admin hoặc chính mình!'); location.href='../views/admin.php#admins';</script>"; 
            exit(); 
        }
        
        try {
            $conn->prepare("DELETE FROM quantrivien WHERE id_quantrivien=?")->execute([$del_id]);
            echo "<script>alert('Đã xóa Admin!'); location.href='../views/admin.php#admins';</script>";
        } catch (PDOException $e) {
            // Ràng buộc khóa ngoại (Integrity constraint violation: 1451)
            if ($e->getCode() == '23000') {
                echo "<script>
                    alert('KHÔNG THỂ XÓA: Tài khoản Admin này đã tham gia tạo dữ liệu (Lịch làm việc, Bác sĩ, v.v...) trong hệ thống.\\n\\nĐể bảo toàn dữ liệu, bạn không thể xóa tài khoản này.');
                    window.location.href='../views/admin.php#admins';
                </script>";
            } else {
                throw $e; // Ném lỗi khác để catch tổng bên dưới xử lý
            }
        }
    }

    elseif ($action == 'change_self_pass') {
        $conn->prepare("UPDATE quantrivien SET mat_khau_hash=? WHERE id_quantrivien=?")->execute([password_hash($_POST['new_pass'], PASSWORD_DEFAULT), $id_quantrivien]);
        echo "<script>alert('Đổi mật khẩu thành công'); location.href='../views/admin.php';</script>";
    }
    elseif ($action == 'add_service') { $conn->prepare("INSERT INTO dichvu (ten_dich_vu, mo_ta, gia_tien, thoi_gian_phut) VALUES (?,?,?,?)")->execute([$_POST['name'], $_POST['desc'], $_POST['price'], $_POST['time']]); echo "<script>alert('Thêm dịch vụ thành công'); location.href='../views/admin.php#services';</script>"; }
    elseif ($action == 'edit_service') { $conn->prepare("UPDATE dichvu SET ten_dich_vu=?, mo_ta=?, gia_tien=?, thoi_gian_phut=? WHERE id_dichvu=?")->execute([$_POST['name'], $_POST['desc'], $_POST['price'], $_POST['time'], $_POST['id']]); echo "<script>alert('Sửa dịch vụ thành công'); location.href='../views/admin.php#services';</script>"; }
    elseif ($action == 'delete_service') { try { $conn->prepare("DELETE FROM dichvu WHERE id_dichvu=?")->execute([$_GET['id']]); echo "<script>alert('Đã xóa'); location.href='../views/admin.php#services';</script>"; } catch(Exception $e) { echo "<script>alert('Dịch vụ đang được sử dụng!'); location.href='../views/admin.php#services';</script>"; } }
    
    elseif ($action == 'approve_leave') {
        $conn->prepare("UPDATE yeucaunghi SET trang_thai='da_duyet', id_quantrivien_duyet=? WHERE id_yeucau=?")->execute([$id_quantrivien, $_GET['id']]);
        echo "<script>alert('Đã duyệt nghỉ phép!'); location.href='../views/admin.php#requests';</script>";
    }
    elseif ($action == 'reject_leave') {
        $conn->prepare("UPDATE yeucaunghi SET trang_thai='tu_choi', id_quantrivien_duyet=? WHERE id_yeucau=?")->execute([$id_quantrivien, $_GET['id']]);
        echo "<script>alert('Đã từ chối nghỉ phép!'); location.href='../views/admin.php#requests';</script>";
    }
    elseif ($action == 'cancel_conflict_appt') {
        $id = $_GET['id'];
        $info = $conn->query("SELECT bn.email, bn.ten_day_du, bs.ten_day_du as ten_bs, lh.ngay_gio_hen FROM lichhen lh JOIN benhnhan bn ON lh.id_benhnhan=bn.id_benhnhan JOIN bacsi bs ON lh.id_bacsi=bs.id_bacsi WHERE id_lichhen=$id")->fetch();
        $conn->prepare("UPDATE lichhen SET trang_thai='huy' WHERE id_lichhen=?")->execute([$id]);
        if($info['email']) sendAbsenceNotification($info['email'], $info['ten_day_du'], date('H:i d/m/Y', strtotime($info['ngay_gio_hen'])), $info['ten_bs']);
        echo "<script>alert('Đã hủy lịch xung đột!'); location.href='../views/admin.php#requests';</script>";
    }

    else { header("Location: ../views/admin.php"); }

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "<script>alert('LỖI HỆ THỐNG: ".$e->getMessage()."'); window.history.back();</script>";
}
?>