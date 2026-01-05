<?php
// includes/send_mail.php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    die("Lỗi: Không tìm thấy thư viện Composer. Vui lòng chạy lệnh 'composer install' trong thư mục dự án.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function sendMailGeneric($toEmail, $subject, $bodyContent) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                       
        $mail->SMTPAuth   = true;                                   
        
        $mail->Username   = 'tranhaitri92@gmail.com';
        $mail->Password   = 'jbzd iyew ohac msxw';
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';                                

        $mail->setFrom('tranhaitri92@gmail.com', 'iDental Clinic'); 
        $mail->addAddress($toEmail);                                

        $mail->isHTML(true);                                  
        $mail->Subject = $subject;
        $mail->Body    = $bodyContent;

        $mail->send();
        return true; 
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false; 
    }
}

function sendOTP($toEmail, $otpCode) {
    $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h3>Xin chào,</h3>
            <p>Cảm ơn bạn đã đăng ký tài khoản tại Nha khoa iDental.</p>
            <p>Mã xác thực OTP của bạn là:</p>
            <h1 style='color: #0056b3; letter-spacing: 5px;'>$otpCode</h1>
            <p>Vui lòng nhập mã này để hoàn tất đăng ký. Mã có hiệu lực trong 5 phút.</p>
            <hr>
            <small>Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email này.</small>
        </div>
    ";
    return sendMailGeneric($toEmail, 'Mã xác thực đăng ký tài khoản - iDental', $body);
}

function sendNewAccountInfo($toEmail, $name, $phone, $pass) {
    $body = "
        <h3>Xin chào $name,</h3>
        <p>Cảm ơn bạn đã đặt lịch tại iDental.</p>
        <p>Vì bạn chưa có tài khoản, hệ thống đã tự động tạo cho bạn:</p>
        <ul>
            <li><b>Tên đăng nhập:</b> $phone</li>
            <li><b>Mật khẩu:</b> <span style='color:red;font-weight:bold;'>$pass</span></li>
        </ul>
        <p>Vui lòng đăng nhập để theo dõi lịch hẹn và đổi mật khẩu.</p>
    ";
    return sendMailGeneric($toEmail, "Thông tin tài khoản & Đặt lịch thành công - iDental", $body);
}

function sendAppointmentConfirmation($toEmail, $name, $date, $doctorName, $serviceName) {
    $body = "
        <h3>Xin chào $name,</h3>
        <p>Lịch hẹn khám nha khoa của bạn đã được xác nhận thành công!</p>
        <div style='background:#e3f2fd; padding:15px; border-radius:5px;'>
            <p><b>Thời gian:</b> $date</p>
            <p><b>Dịch vụ:</b> $serviceName</p>
            <p><b>Bác sĩ phụ trách:</b> $doctorName</p>
            <p><b>Địa chỉ:</b> Phòng khám iDental - 180 Cao Lỗ, TP.HCM</p>
        </div>
        <p>Vui lòng đến đúng giờ để được phục vụ tốt nhất.</p>
    ";
    return sendMailGeneric($toEmail, "✅ Lịch hẹn đã được XÁC NHẬN - iDental", $body);
}

function sendAbsenceNotification($toEmail, $patientName, $dateStr, $doctorName) {
    $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h3 style='color: #d32f2f;'>Thông báo hủy lịch hẹn đột xuất</h3>
            <p>Xin chào <strong>$patientName</strong>,</p>
            <p>Chúng tôi rất tiếc phải thông báo rằng lịch hẹn khám của bạn vào lúc: <br>
            <b style='font-size: 16px;'>$dateStr</b></p>
            <p>Với bác sĩ: <strong>$doctorName</strong></p>
            <p>Hiện không thể thực hiện được do Bác sĩ có lịch công tác/nghỉ đột xuất.</p>
            
            <div style='background: #fff3e0; padding: 15px; border-radius: 5px; border-left: 4px solid #ff9800; margin: 20px 0;'>
                <strong>Hành động tiếp theo:</strong><br>
                Lịch hẹn này đã được hủy trên hệ thống. Vui lòng truy cập website để đặt lại lịch mới hoặc liên hệ hotline để được hỗ trợ.
            </div>
            
            <p>Nha khoa iDental thành thật xin lỗi bạn vì sự bất tiện này.</p>
        </div>
    ";
    return sendMailGeneric($toEmail, "⚠️ THÔNG BÁO: Thay đổi lịch hẹn khám tại iDental", $body);
}

function sendAccountLockNotification($toEmail, $name) {
    $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h2 style='color: #d32f2f;'>Thông báo về trạng thái tài khoản</h2>
            <p>Xin chào Bác sĩ <strong>$name</strong>,</p>
            <p>Chúng tôi xin thông báo rằng tài khoản bác sĩ của bạn trên hệ thống <strong>iDental</strong> hiện đã bị <strong>TẠM KHÓA</strong> bởi Quản trị viên.</p>
            <div style='background: #fff3e0; padding: 15px; border-radius: 5px; border-left: 4px solid #ff9800; margin: 20px 0;'>
                <strong>Lưu ý:</strong> Bạn sẽ không thể đăng nhập vào hệ thống hoặc thực hiện các thao tác chuyên môn cho đến khi tài khoản được mở khóa.
            </div>
            <p>Nếu bạn cho rằng đây là một sự nhầm lẫn, vui lòng liên hệ trực tiếp với bộ phận quản lý phòng khám.</p>
            <p>Trân trọng,<br>Ban quản trị iDental.</p>
        </div>
    ";
    return sendMailGeneric($toEmail, "⚠️ CẢNH BÁO: Tài khoản của bạn đã bị khóa - iDental", $body);
}

function sendLeaveStatusNotification($toEmail, $doctorName, $date, $shift, $status, $reason = '') {
    $statusText = ($status == 'da_duyet') ? "<span style='color:green;font-weight:bold;'>ĐÃ ĐƯỢC DUYỆT</span>" : "<span style='color:red;font-weight:bold;'>ĐÃ BỊ TỪ CHỐI</span>";
    $subject = ($status == 'da_duyet') ? "✅ Yêu cầu nghỉ phép được chấp thuận - iDental" : "❌ Yêu cầu nghỉ phép bị từ chối - iDental";
    
    $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h3>Xin chào Bác sĩ $doctorName,</h3>
            <p>Yêu cầu nghỉ phép của bạn cho ngày <strong>$date</strong> (Ca: <strong>$shift</strong>) đã được xử lý.</p>
            <p>Trạng thái: $statusText</p>
            " . ($reason ? "<p>Lý do/Ghi chú: $reason</p>" : "") . "
            <p>Vui lòng đăng nhập hệ thống để kiểm tra chi tiết.</p>
            <p>Trân trọng,<br>Ban quản trị iDental.</p>
        </div>
    ";
    return sendMailGeneric($toEmail, $subject, $body);
}

function sendSwitchDoctorNotification($toEmail, $patientName, $dateStr, $oldDoctorName, $newDoctorName) {
    $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h3>Xin chào $patientName,</h3>
            <p>Chúng tôi xin thông báo về sự thay đổi trong lịch hẹn khám của bạn vào lúc: <strong>$dateStr</strong>.</p>
            <div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p>Do lý do công tác đột xuất, bác sĩ <strong>$oldDoctorName</strong> sẽ không thể trực tiếp thăm khám.</p>
                <p>Lịch hẹn của bạn đã được chuyển sang cho bác sĩ: <strong style='color: #0d47a1; font-size: 1.1em;'>$newDoctorName</strong></p>
            </div>
            <p>Thời gian và địa điểm khám vẫn giữ nguyên. Bạn không cần phải thao tác gì thêm.</p>
            <p>Nha khoa iDental xin lỗi vì sự bất tiện này và cảm ơn sự thông cảm của bạn.</p>
        </div>
    ";
    return sendMailGeneric($toEmail, "ℹ️ THÔNG BÁO: Thay đổi bác sĩ phụ trách - iDental", $body);
}

function sendRescheduleNotification($toEmail, $patientName, $oldDate, $newDate, $doctorName, $reason) {
    $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h3>Xin chào $patientName,</h3>
            <p>Lịch hẹn khám của bạn đã được thay đổi theo yêu cầu hoặc do điều chỉnh từ phòng khám.</p>
            
            <div style='background: #fff3e0; padding: 15px; border-radius: 5px; border-left: 4px solid #ff9800; margin: 20px 0;'>
                <p><strong>Lịch cũ:</strong> <strike>$oldDate</strike></p>
                <p><strong>Lịch mới:</strong> <span style='color: #d32f2f; font-weight: bold; font-size: 1.1em;'>$newDate</span></p>
                <p><strong>Bác sĩ:</strong> $doctorName</p>
                " . ($reason ? "<p><strong>Lý do thay đổi:</strong> $reason</p>" : "") . "
            </div>
            
            <p>Vui lòng sắp xếp thời gian đến đúng giờ.</p>
            <p>Trân trọng,<br>Ban quản trị iDental.</p>
        </div>
    ";
    return sendMailGeneric($toEmail, "📅 THÔNG BÁO: Thay đổi thời gian lịch hẹn - iDental", $body);
}
