# BÁO CÁO CHI TIẾT HỆ THỐNG QUẢN LÝ NHA KHOA

## 1. TỔNG QUAN KIẾN TRÚC HỆ THỐNG
Hệ thống được xây dựng theo mô hình **MVC (Model-View-Controller)** cổ điển trên nền tảng PHP thuần, đảm bảo sự phân tách rõ ràng giữa dữ liệu, giao diện và logic xử lý.

### 1.1. Công nghệ sử dụng
*   **Ngôn ngữ lập trình:** PHP 8.x (Backend), JavaScript (Frontend).
*   **Cơ sở dữ liệu:** MySQL (Sử dụng PDO để kết nối và bảo mật).
*   **Giao diện:** HTML5, CSS3, Bootstrap (tùy chỉnh).
*   **Thư viện hỗ trợ:**
    *   `PHPMailer`: Gửi email xác nhận lịch hẹn, lấy lại mật khẩu.
    *   `Composer`: Quản lý các gói phụ thuộc.
*   **Môi trường phát triển:** WAMP/XAMPP (Apache Web Server).

### 1.2. Cấu trúc thư mục
*   `config/`: Chứa file kết nối CSDL (`db_connect.php`).
*   `controllers/`: Chứa logic xử lý nghiệp vụ (Đăng nhập, Đặt lịch, Duyệt lịch...).
*   `views/`: Chứa giao diện người dùng (Admin, Bác sĩ, Khách hàng).
*   `includes/`: Các file tiện ích dùng chung (Header, Footer, SendMail).
*   `assets/`: Tài nguyên tĩnh (CSS, JS, Hình ảnh).
*   `data/`: Dữ liệu tạm hoặc file JSON cấu hình.

---

## 2. CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)
Hệ thống sử dụng CSDL quan hệ `nha_khoa` gồm 9 bảng chính, được thiết kế chuẩn hóa để đảm bảo tính toàn vẹn dữ liệu.

### 2.1. Nhóm Người dùng
1.  **`quantrivien`**: Quản trị viên hệ thống.
    *   `id_quantrivien` (PK), `ten_dang_nhap`, `mat_khau_hash`.
2.  **`bacsi`**: Thông tin bác sĩ.
    *   `id_bacsi` (PK), `ten_day_du`, `sdt`, `email`, `chuyen_khoa`, `trang_thai` (1: Hoạt động, 0: Khóa).
3.  **`benhnhan`**: Thông tin khách hàng.
    *   `id_benhnhan` (PK), `ten_day_du`, `sdt`, `email`, `mat_khau_hash`.

### 2.2. Nhóm Lịch & Hoạt động
4.  **`lichlamviec`**: Lịch trực của bác sĩ.
    *   `id_lichlamviec` (PK), `id_bacsi`, `id_giuongbenh`, `ngay_hieu_luc` (Ngày làm việc), `gio_bat_dau`, `gio_ket_thuc`.
    *   *Lưu ý:* Hệ thống lưu trữ từng ca làm việc cụ thể cho từng ngày (Explicit Daily Scheduling).
5.  **`lichhen`**: Lịch hẹn khám bệnh.
    *   `id_lichhen` (PK), `id_benhnhan`, `id_bacsi`, `id_dichvu`, `ngay_gio_hen`, `trang_thai` (cho_xac_nhan, da_xac_nhan, hoan_thanh, da_huy).
6.  **`yeucaunghi`**: Đơn xin nghỉ phép của bác sĩ.
    *   `id_yeucau` (PK), `id_bacsi`, `ngay_nghi`, `ca_nghi`, `trang_thai` (cho_duyet, da_duyet, tu_choi).

### 2.3. Nhóm Nghiệp vụ Y tế
7.  **`giuongbenh`**: Danh sách ghế nha khoa.
    *   `id_giuongbenh` (PK), `ten_giuong`.
8.  **`dichvu`**: Danh mục dịch vụ.
    *   `id_dichvu` (PK), `ten_dich_vu`, `gia`, `thoi_gian_phut` (Thời gian thực hiện dự kiến).
9.  **`benhan`**: Hồ sơ bệnh án.
    *   `id_benhan` (PK), `id_lichhen` (FK 1-1), `chan_doan`, `ghi_chu_bac_si`.
10. **`donthuoc`** & **`chitietdonthuoc`**: Quản lý kê đơn (nếu có mở rộng).

---

## 3. CHI TIẾT CHỨC NĂNG THEO PHÂN HỆ (MÔ TẢ QUY TRÌNH NGHIỆP VỤ)

### 3.1. Quy trình Xác thực & Đăng nhập
Đây là bước đầu tiên để đảm bảo an toàn cho hệ thống. Quy trình diễn ra như sau:
1.  **Nhập thông tin:** Người dùng (Khách, Bác sĩ hoặc Quản trị viên) nhập tên đăng nhập và mật khẩu.
2.  **Kiểm tra danh tính:** Hệ thống đối chiếu thông tin vừa nhập với dữ liệu đã lưu.
3.  **Kiểm tra trạng thái hoạt động:**
    *   Hệ thống kiểm tra xem tài khoản này có đang bị "Khóa" hay không.
    *   Nếu tài khoản bị khóa, người dùng sẽ không thể đăng nhập.
    *   *Đặc biệt:* Nếu người đăng nhập là Bác sĩ và tài khoản bị khóa, hệ thống sẽ tự động gửi một email thông báo lý do đến hộp thư của bác sĩ đó.
4.  **Cấp quyền truy cập:** Nếu mọi thứ hợp lệ, người dùng được chuyển đến màn hình làm việc tương ứng với vai trò của mình.

### 3.2. Quy trình Đặt lịch khám (Dành cho Khách hàng)
Hệ thống đóng vai trò như một lễ tân ảo thông minh, giúp khách hàng đặt lịch mà không cần gọi điện.
1.  **Chọn thông tin khám:** Khách hàng chọn Bác sĩ mong muốn, Dịch vụ cần làm (ví dụ: Nhổ răng, Cạo vôi) và Ngày muốn đi khám.
2.  **Hệ thống tự động kiểm tra (Rà soát thông minh):**
    *   **Kiểm tra lịch trực:** Bác sĩ có lịch làm việc vào ngày đó không?
    *   **Kiểm tra nghỉ phép:** Bác sĩ có đang trong kỳ nghỉ phép đã được duyệt không?
    *   Nếu bác sĩ vắng mặt, hệ thống sẽ báo ngay để khách chọn ngày khác.
3.  **Tính toán giờ hẹn dự kiến:**
    *   Thay vì để khách chọn giờ ngẫu nhiên (dễ gây trùng lặp), hệ thống tự động tính toán giờ bắt đầu dựa trên số lượng khách đang chờ.
    *   *Ví dụ:* Nếu ca sáng bắt đầu lúc 8:00 và đã có 2 người đặt (mỗi người 30 phút), hệ thống sẽ báo giờ dự kiến cho khách thứ 3 là 9:00.
4.  **Kiểm tra quá tải:** Nếu thời gian làm dịch vụ dự kiến vượt quá giờ kết thúc ca làm việc (ví dụ: làm răng sứ mất 2 tiếng mà 11:30 mới bắt đầu trong khi 12:00 nghỉ trưa), hệ thống sẽ từ chối và báo "Kín lịch".
5.  **Hoàn tất:** Nếu hợp lệ, yêu cầu đặt lịch được gửi đi và nằm ở trạng thái "Chờ xác nhận".

### 3.3. Quy trình Làm việc của Bác sĩ
Giúp bác sĩ quản lý bệnh nhân và hồ sơ y tế một cách khoa học.
1.  **Duyệt lịch hẹn:**
    *   Bác sĩ xem danh sách các yêu cầu đặt lịch mới.
    *   Khi bác sĩ bấm "Duyệt", hệ thống sẽ chốt giờ khám chính xác và tự động gửi email xác nhận (kèm giờ hẹn) đến cho khách hàng.
2.  **Tiếp nhận & Khám bệnh:**
    *   Khi khách đến, bác sĩ mở hồ sơ bệnh nhân trên phần mềm.
    *   Bác sĩ nhập các thông tin chẩn đoán, triệu chứng và phương pháp điều trị.
3.  **Kết thúc ca khám:**
    *   Sau khi lưu hồ sơ bệnh án, hệ thống tự động đánh dấu lịch hẹn này là "Hoàn thành". Hồ sơ này sẽ được lưu trữ vĩnh viễn để phục vụ cho các lần tái khám sau.

### 3.4. Quy trình Quản lý (Dành cho Quản trị viên)
Đảm bảo phòng khám vận hành trơn tru, không bị chồng chéo tài nguyên.
1.  **Xếp lịch làm việc & Kiểm soát giường bệnh:**
    *   Khi Admin xếp lịch cho một bác sĩ vào ngày cụ thể, hệ thống yêu cầu chọn Giường/Ghế nha khoa sẽ sử dụng.
    *   **Cơ chế ngăn chặn xung đột:** Hệ thống tự động quét toàn bộ lịch. Nếu phát hiện Giường số 1 đã được phân cho Bác sĩ A vào sáng thứ 2, hệ thống sẽ **không cho phép** xếp Bác sĩ B vào cùng Giường số 1 vào sáng thứ 2 nữa. Điều này đảm bảo không bao giờ xảy ra tình trạng "thừa bác sĩ, thiếu ghế".
2.  **Xử lý Nghỉ phép & Điều phối lịch:**
    *   Khi bác sĩ xin nghỉ đột xuất, Admin sẽ vào duyệt đơn.
    *   Hệ thống lập tức kiểm tra xem trong ngày nghỉ đó, bác sĩ có vướng lịch hẹn nào với khách không.
    *   Nếu có, hệ thống sẽ cảnh báo và hỗ trợ Admin chuyển những lịch hẹn đó sang cho một bác sĩ khác đang rảnh rỗi, đảm bảo khách hàng vẫn được phục vụ.

---

## 4. CÁC QUY TẮC NGHIỆP VỤ (BUSINESS RULES)

### 4.1. Quy tắc Xếp lịch & Giường bệnh
*   **Ràng buộc Giường:** Tại một thời điểm (Ca Sáng/Chiều của một Ngày), một `id_giuongbenh` chỉ được gán cho tối đa 1 `id_bacsi`.
*   **Ràng buộc Bác sĩ:** Một bác sĩ không thể có 2 dòng lịch làm việc trùng nhau về thời gian.

### 4.2. Quy tắc Đặt lịch & Hàng đợi (Queue)
*   **Giờ khám dự kiến:** Không cố định. Được tính bằng: `Giờ bắt đầu ca` + `Tổng thời gian dự kiến của các ca trước đó`.
*   **Overbooking:** Hệ thống ngăn chặn đặt lịch nếu `Giờ dự kiến` + `Thời gian dịch vụ` vượt quá `Giờ kết thúc ca`.
*   **Trạng thái Bác sĩ:** Không thể đặt lịch nếu Bác sĩ có trạng thái `trang_thai = 0` (Bị khóa) hoặc có đơn nghỉ phép `da_duyet`.

### 4.3. Quy tắc Nghỉ phép
*   Bác sĩ gửi yêu cầu -> Trạng thái `cho_duyet`.
*   Admin duyệt -> Trạng thái `da_duyet`.
*   Nếu đã duyệt nghỉ phép, hệ thống tự động chặn khách hàng đặt lịch vào khung giờ đó.

---

## 5. KẾT LUẬN
Hệ thống đã đáp ứng đầy đủ các quy trình cơ bản của một phòng khám nha khoa: từ khâu tiếp nhận khách (Đặt lịch online), vận hành (Xếp lịch, Khám bệnh) đến quản trị. Kiến trúc mã nguồn rõ ràng, tuân thủ MVC giúp dễ dàng bảo trì và mở rộng trong tương lai.
