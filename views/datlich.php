<?php
require '../config/db_connect.php';

$services = $conn->query("SELECT * FROM dichvu")->fetchAll(PDO::FETCH_ASSOC);
$doctors = $conn->query("SELECT * FROM bacsi")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Lịch Khám</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .booking-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto; }
        .section-heading { color: var(--primary); font-size: 20px; border-bottom: 2px solid var(--primary-light); padding-bottom: 10px; margin-bottom: 25px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: 1 / -1; }
        
        .alert-box { padding: 15px; margin-top: 15px; border-radius: 5px; display: none; font-weight: 500; font-size: 0.95em; }
        .alert-error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert-warning { background-color: #fff3e0; color: #e65100; border: 1px solid #ffb74d; }
        .contact-phone { font-weight: bold; font-size: 1.1em; }
        
        select:disabled, input:disabled { background-color: #f2f2f2; cursor: not-allowed; }
    </style>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <section class="section bg-light">
        <div class="container">
            <div class="text-center" style="margin-bottom: 40px;">
                <h1 class="section-title">Đặt Lịch Hẹn Khám</h1>
                <p class="section-subtitle">Vui lòng chọn Bác sĩ và Ngày khám để kiểm tra lịch.</p>
            </div>

            <div class="booking-container">
                <form action="../controllers/book_appointment.php" method="POST" id="bookingForm">
                    
                    <h2 class="section-heading"><i class="fas fa-user-circle"></i> Thông Tin Cá Nhân</h2>
                    <div class="form-grid">
                        <div class="form-group"><label>Họ và Tên *</label><input type="text" name="fullname" class="form-control" required></div>
                        <div class="form-group"><label>Số Điện Thoại *</label><input type="tel" name="phone" class="form-control" required placeholder="Nhập SĐT của bạn"></div>
                        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                    </div>

                    <h2 class="section-heading" style="margin-top: 30px;"><i class="fas fa-calendar-alt"></i> Chọn Bác Sĩ & Thời Gian</h2>
                    <div class="form-grid">
                        
                        <div class="form-group">
                            <label>Dịch Vụ *</label>
                            <select name="id_dichvu" class="form-control" required>
                                <?php foreach($services as $sv): ?>
                                    <option value="<?php echo $sv['id_dichvu']; ?>"><?php echo htmlspecialchars($sv['ten_dich_vu']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Bác Sĩ Ưu Tiên *</label>
                            <select name="id_bacsi" id="docSelect" class="form-control" required>
                                <option value="">-- Chọn Bác sĩ --</option>
                                <?php foreach($doctors as $doc): ?>
                                    <option value="<?php echo $doc['id_bacsi']; ?>">Dr. <?php echo htmlspecialchars($doc['ten_day_du']); ?> - <?php echo htmlspecialchars($doc['chuyen_khoa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Ngày Khám *</label>
                            <input type="date" name="date" id="dateInput" class="form-control" required min="<?php echo date('Y-m-d'); ?>" disabled>
                            <small id="dateHint" style="color:#666; font-size:0.85em;">* Vui lòng chọn Bác sĩ trước</small>
                        </div>

                        <div class="form-group">
                            <label>Ca Khám *</label>
                            <select name="shift" id="shiftSelect" class="form-control" required disabled>
                                <option value="">-- Vui lòng chọn ngày --</option>
                            </select>
                        </div>
                        
                        <div id="availabilityAlert" class="alert-box full-width">
                            <i class="fas fa-info-circle"></i> 
                            <span id="alertMessage"></span>
                        </div>
                    </div>

                    <h2 class="section-heading" style="margin-top: 30px;"><i class="fas fa-heartbeat"></i> Thông Tin Y Tế</h2>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="note">Ghi Chú Thêm</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Triệu chứng, tiền sử bệnh..."></textarea>
                        </div>
                    </div>

                    <div class="text-center" style="margin-top: 30px;">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg" disabled>Gửi Yêu Cầu</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/file.js"></script>
    
   <script>
    document.addEventListener("DOMContentLoaded", function() {
        const docSelect = document.getElementById('docSelect');
        const dateInput = document.getElementById('dateInput');
        const shiftSelect = document.getElementById('shiftSelect');
        const dateHint = document.getElementById('dateHint');
        const submitBtn = document.getElementById('submitBtn');
        const alertBox = document.getElementById('availabilityAlert');
        const alertMsg = document.getElementById('alertMessage');

        // 1. Chọn Bác sĩ
        docSelect.addEventListener('change', function() {
            if (this.value) {
                dateInput.disabled = false;
                dateHint.style.display = 'none';
                resetUI();
                if(dateInput.value) checkSchedule();
            } else {
                dateInput.disabled = true;
                dateHint.style.display = 'block';
                resetUI();
            }
        });

        // 2. Chọn Ngày
        dateInput.addEventListener('change', checkSchedule);

        // 3. Chọn Ca -> Mở nút Submit
        shiftSelect.addEventListener('change', function() {
            if(this.value) submitBtn.disabled = false;
            else submitBtn.disabled = true;
        });

        async function checkSchedule() {
            const docId = docSelect.value;
            const dateVal = dateInput.value;
            if (!docId || !dateVal) return;

            
            shiftSelect.innerHTML = '<option>Đang kiểm tra...</option>';
            shiftSelect.disabled = true;
            alertBox.style.display = 'none';
            submitBtn.disabled = true;

            try {
                const res = await fetch(`../controllers/get_shifts_by_date.php?id=${docId}&date=${dateVal}`);
                const data = await res.json();
                
                
                shiftSelect.innerHTML = '<option value="">-- Chọn ca khám --</option>';

                // --- TRƯỜNG HỢP 1: BÁC SĨ NGHỈ ---
                if (data.status === 'on_leave') {
                    alertBox.className = 'alert-box alert-error full-width';
                    alertBox.style.display = 'block';
                    alertMsg.innerHTML = `Bác sĩ <strong>đã xin nghỉ</strong> ngày ${formatDate(dateVal)}. Vui lòng chọn ngày khác.`;
                    
                    let opt = document.createElement('option');
                    opt.text = "Bác sĩ nghỉ phép";
                    shiftSelect.appendChild(opt);
                    shiftSelect.disabled = true;
                } 
                
                // --- TRƯỜNG HỢP 2: CÓ CA LÀM (Hiển thị đúng ca đó) ---
                else if (data.status === 'has_schedule') {
                    data.shifts.forEach(shift => {
                        let opt = document.createElement('option');
                        opt.value = shift.value;
                        opt.text = shift.label;
                        shiftSelect.appendChild(opt);
                    });
                    shiftSelect.disabled = false;
                } 
                
                // --- TRƯỜNG HỢP 3: KHÔNG CÓ LỊCH (Cho phép đặt Đặc biệt) ---
                else {
                    alertBox.className = 'alert-box alert-warning full-width';
                   
                    alertBox.style.backgroundColor = '#fff3e0';
                    alertBox.style.color = '#e65100';
                    alertBox.style.border = '1px solid #ffb74d';
                    
                    alertMsg.innerHTML = `Bác sĩ <strong>không có lịch</strong> ngày ${formatDate(dateVal)}. <br>Bạn vẫn có thể đặt, lịch sẽ ở trạng thái <strong>Chờ duyệt</strong>.`;
                    alertBox.style.display = 'block';

                   
                    let opt1 = document.createElement('option'); 
                    opt1.value = "Sang"; opt1.text = "Sáng (Dự kiến 08:00)";
                    
                    let opt2 = document.createElement('option'); 
                    opt2.value = "Chieu"; opt2.text = "Chiều (Dự kiến 13:00)";
                    
                    shiftSelect.appendChild(opt1);
                    shiftSelect.appendChild(opt2);
                    
                    shiftSelect.disabled = false;
                }

            } catch (e) {
                console.error(e);
                shiftSelect.innerHTML = '<option>Lỗi kết nối</option>';
            }
        }

        function resetUI() {
            shiftSelect.innerHTML = '<option value="">-- Vui lòng chọn ngày --</option>';
            shiftSelect.disabled = true;
            submitBtn.disabled = true;
            alertBox.style.display = 'none';
        }

        function formatDate(str) {
            const p = str.split('-');
            return `${p[2]}/${p[1]}/${p[0]}`;
        }
    });
</script>
</body>
</html>