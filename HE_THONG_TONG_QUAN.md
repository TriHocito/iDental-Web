# 📋 Hệ Thống Quản Lý Phòng Khám Nha Khoa - Tổng Quan Toàn Bộ Hệ Thống

---

## 🎯 I. GIỚI THIỆU CHUNG

**Tên hệ thống:** iDental Clinic Management System (Hệ Thống Quản Lý Phòng Khám Nha Khoa)

**Mục đích:** Cung cấp giải pháp quản lý toàn diện cho phòng khám nha khoa, bao gồm:
- 👥 Quản lý bệnh nhân
- 📅 Quản lý lịch hẹn
- 👨‍⚕️ Quản lý nhân sự (bác sĩ, admin)
- 💊 Quản lý dịch vụ & giá cả
- 📊 Báo cáo & thống kê
- 📧 Thông báo & giao tiếp

**Ngôn ngữ & Công nghệ:**
- Backend: PHP 7.0+ (Procedural)
- Database: MySQL/MariaDB
- Frontend: HTML5, CSS3, JavaScript/jQuery
- Email: PHPMailer
- Pattern: MVC (Model-View-Controller)

---

## 👥 II. CÁC VAI TRÒ NGƯỜI DÙNG

### 1. **Bệnh Nhân (Patient)**
**Quyền hạn:**
- ✅ Đăng ký tài khoản
- ✅ Đặt lịch khám
- ✅ Hủy lịch khám (chỉ khi `chờ xác nhận`)
- ✅ Xem lịch sử khám
- ✅ Cập nhật hồ sơ cá nhân
- ✅ Đổi mật khẩu
- ✅ Xem bảng giá dịch vụ
- ✅ Tìm kiếm bác sĩ

**File chính:** `views/khachhang.php`, `controllers/patient_actions.php`

---

### 2. **Bác Sĩ (Doctor)**
**Quyền hạn:**
- ✅ Xem lịch làm việc
- ✅ Phê duyệt/từ chối lịch hẹn
- ✅ Xin nghỉ phép (với thông báo cho admin)
- ✅ Thêm lịch hẹn cho bệnh nhân
- ✅ Tiếp nhận khách vãng lai
- ✅ Cập nhật bệnh án
- ✅ Cập nhật hồ sơ cá nhân
- ✅ Đổi mật khẩu

**File chính:** `views/bacsi.php`, `controllers/doctor_actions.php`

---

### 3. **Quản Trị Viên (Admin)**
**Quyền hạn:**
- ✅ Quản lý bác sĩ (thêm, sửa, xóa, reset mật khẩu)
- ✅ Quản lý bệnh nhân (xem, xóa)
- ✅ Quản lý lịch hẹn (duyệt, từ chối, thêm)
- ✅ Quản lý lịch làm việc (tạo ca trực)
- ✅ Quản lý dịch vụ (thêm, sửa, xóa)
- ✅ Quản lý admin (thêm, xóa)
- ✅ Duyệt yêu cầu nghỉ phép
- ✅ Xử lý lịch hẹn xung đột
- ✅ Chuyển bác sĩ / Hủy lịch hẹn

**File chính:** `views/admin.php`, `controllers/admin_actions.php`

---

## 🏗️ III. KIẾN TRÚC HỆ THỐNG

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONT-END (Client)                       │
├─────────────────────────────────────────────────────────────┤
│  HTML5 / CSS3 / JavaScript / jQuery                          │
│  Views: khachhang.php, bacsi.php, admin.php, ... (10+ file) │
└─────────────────┬───────────────────────────────────────────┘
                  │ HTTP Request
                  ▼
┌─────────────────────────────────────────────────────────────┐
│              BACK-END (Server) - Controllers                │
├─────────────────────────────────────────────────────────────┤
│  PHP Procedural + OOP                                       │
│  - auth_login.php (xác thực)                                │
│  - patient_actions.php (hành động bệnh nhân)                │
│  - doctor_actions.php (hành động bác sĩ)                    │
│  - admin_actions.php (hành động admin)                      │
│  - book_appointment.php (đặt lịch)                          │
│  - get_shifts_by_date.php (lấy ca trực)                     │
│  - send_mail.php (gửi email thông báo)                      │
│  - ... (15+ file controller)                                │
└─────────────────┬───────────────────────────────────────────┘
                  │ SQL Query
                  ▼
┌─────────────────────────────────────────────────────────────┐
│                 DATA-BASE (MySQL)                           │
├─────────────────────────────────────────────────────────────┤
│  - benhnhan (bệnh nhân)                                      │
│  - bacsi (bác sĩ)                                            │
│  - quantrivien (admin)                                       │
│  - lichhen (lịch hẹn)                                        │
│  - dichvu (dịch vụ)                                          │
│  - lichlamviec (lịch làm việc)                               │
│  - yeucaunghi (yêu cầu nghỉ phép)                            │
│  - giuongbenh (giường/phòng)                                 │
│  - benhan (bệnh án)                                          │
└─────────────────────────────────────────────────────────────┘
```

---

## 📱 IV. CÁC MODULE CHỨC NĂNG CHÍNH

### **1. Module Xác Thực (Authentication)**
**Chức năng:** Đăng nhập / Đăng ký cho 3 vai trò

**Workflow:**
```
1. Người dùng chọn vai trò (bệnh nhân, bác sĩ, admin)
2. Nhập tên đăng nhập & mật khẩu
3. Hệ thống kiểm tra trong DB
4. Nếu đúng → Lưu SESSION → Chuyển hướng tới dashboard
5. Nếu sai → Thông báo lỗi
```

**File liên quan:**
- `views/dangnhap.php` - Giao diện đăng nhập
- `views/dangky.php` - Giao diện đăng ký (bệnh nhân)
- `controllers/auth_login.php` - Xử lý đăng nhập
- `controllers/auth_register.php` - Xử lý đăng ký

---

### **2. Module Đặt Lịch Hẹn (Appointment Booking)**
**Chức năng:** Bệnh nhân đặt lịch khám nha

**Workflow:**
```
1. Bệnh nhân chọn:
   - Ngày khám
   - Ca (Sáng/Chiều)
   - Bác sĩ (nếu có yêu cầu)
   - Dịch vụ

2. Hệ thống kiểm tra:
   - ✓ Bác sĩ có lịch làm việc không?
   - ✓ Bác sĩ có đang nghỉ phép không?
   - ✓ Ca này còn chỗ không?

3. Tính toán thời gian (Queue System):
   - Giờ khám = Giờ bắt đầu ca + Tổng thời gian các lịch đã xác nhận
   - Kiểm tra: Giờ khám + Thời gian dịch vụ <= Giờ kết thúc ca?
   - Nếu vượt quá → Từ chối

4. Lưu vào DB:
   - Trạng thái = 'cho_xac_nhan' (nếu không có ca trực)
   - Trạng thái = 'da_xac_nhan' (nếu admin/bác sĩ tạo)

5. Gửi email thông báo cho bệnh nhân
```

**File liên quan:**
- `views/datlich.php` - Giao diện đặt lịch
- `controllers/book_appointment.php` - Xử lý đặt lịch
- `controllers/patient_actions.php` - Hành động bệnh nhân

**Quan trọng:** Queue System (tính toán thời gian động)
```php
// Formula:
$accumulated_minutes = SUM(dịch vụ của lịch đã xác nhận cùng ngày)
$real_start_time = shift_start_time + ($accumulated_minutes * 60)
$end_time = $real_start_time + ($service_duration * 60)

// Kiểm tra:
if ($end_time > shift_end_time) {
    return "Lỗi: Không đủ thời gian";
}
```

---

### **3. Module Quản Lý Lịch Hẹn (Appointment Management)**

#### **3.1 Phê Duyệt Lịch Hẹn (Doctor/Admin)**
**Workflow:**
```
1. Bác sĩ/Admin xem danh sách lịch chờ duyệt

2. Duyệt lịch:
   - Kiểm tra có ca trực không
   - Tính giờ khám (Queue System)
   - Kiểm tra không vượt quá giờ kết thúc
   - Cập nhật trạng thái = 'da_xac_nhan'
   - Gửi email xác nhận cho bệnh nhân

3. Từ chối lịch:
   - Cập nhật trạng thái = 'huy'
   - Gửi email thông báo lý do từ chối
```

#### **3.2 Hủy Lịch Hẹn (Patient)**
**Workflow:**
```
1. Bệnh nhân xem lịch hẹn
2. Nếu trạng thái = 'cho_xac_nhan':
   - Hiển thị nút "Hủy"
   - Bệnh nhân click → Yêu cầu xác nhận
   - Cập nhật trạng thái = 'huy'
   - Gửi email thông báo

3. Nếu trạng thái = 'da_xac_nhan':
   - Hiển thị nút "Đã chốt" (khóa)
   - Hướng dẫn liên hệ hotline để hủy
```

#### **3.3 Hoàn Thành Lịch Hẹn (Doctor)**
**Workflow:**
```
1. Bác sĩ khám bệnh nhân
2. Click "Khám" → Mở modal nhập bệnh án
3. Nhập:
   - Chẩn đoán
   - Ghi chú / Đơn thuốc / Hẹn tái khám
4. Lưu → Cập nhật trạng thái = 'hoan_thanh'
```

---

### **4. Module Quản Lý Lịch Làm Việc (Work Schedule)**

**Chức năng:** Quản lý ca trực của bác sĩ

**Workflow:**
```
1. Admin tạo lịch làm việc (hàng loạt):
   - Chọn bác sĩ
   - Chọn giường/phòng
   - Chọn ngày từ-đến
   - Chọn những ngày trong tuần
   - Chọn ca (Sáng/Chiều)

2. Hệ thống tạo:
   - Bản ghi cho mỗi ngày x ca
   - Ví dụ: 5 ngày x 2 ca = 10 bản ghi

3. Bác sĩ xem lịch làm việc:
   - Bảng hiển thị 7 ngày x 2 ca
   - Màu xanh = Có trực
   - Màu xám = Không trực

4. Bác sĩ có thể chuyển tuần:
   - Xem tuần trước/sau
```

---

### **5. Module Yêu Cầu Nghỉ Phép (Leave Request)**

**Chức năng:** Bác sĩ xin nghỉ, Admin duyệt

**Workflow - Bác Sĩ:**
```
1. Bác sĩ click "Xin Nghỉ Phép"
2. Chọn ngày + ca nghỉ
3. Nhập lý do
4. Hệ thống kiểm tra:
   - Ngày này có ca trực không?
   - Đã xin nghỉ rồi không?
5. Nếu OK → Gửi yêu cầu (trạng thái = 'cho_duyet')
6. Gửi email thông báo cho Admin
```

**Workflow - Admin:**
```
1. Admin xem danh sách yêu cầu chờ duyệt
2. Click "Duyệt":
   - Tự động phát hiện lịch hẹn bị xung đột
   - Gửi email thông báo hoãn lịch cho bệnh nhân
   - Cập nhật trạng thái = 'da_duyet'
3. Click "Từ chối":
   - Cập nhật trạng thái = 'tu_choi'
```

---

### **6. Module Xử Lý Xung Đột Lịch (Conflict Management)**

**Chức năng:** Xử lý lịch hẹn khi bác sĩ nghỉ phép

**Workflow:**
```
1. Admin duyệt yêu cầu nghỉ phép
2. Hệ thống tự động tìm xung đột:
   - Tìm lịch hẹn có:
     * Bác sĩ = người xin nghỉ
     * Ngày = ngày nghỉ
     * Ca = ca nghỉ
     * Trạng thái = 'da_xac_nhan' hoặc 'cho_xac_nhan'
3. Hiển thị trên dashboard "Lịch Cần Xử Lý"

4. Admin có 2 lựa chọn:
   a) Chuyển Bác Sĩ:
      - Chọn bác sĩ thay thế
      - (Tùy chọn) Thay đổi thời gian
      - Gửi email xác nhận cho bệnh nhân
   
   b) Hủy Lịch:
      - Cập nhật trạng thái = 'huy'
      - Gửi email xin lỗi cho bệnh nhân
      - Ghi lại vào lịch sử
```

---

### **7. Module Quản Lý Dịch Vụ (Service Management)**

**Chức năng:** Quản lý danh sách dịch vụ & giá cả

**Workflow:**
```
1. Admin quản lý dịch vụ:
   - Thêm dịch vụ mới (tên, mô tả, giá, thời gian)
   - Sửa dịch vụ
   - Xóa dịch vụ (kiểm tra không được dùng)

2. Bệnh nhân xem:
   - Bảng giá chi tiết
   - Tên dịch vụ, mô tả, giá tiền
   - Thời gian (phút)

3. Bác sĩ/Admin xem:
   - Danh sách để chọn khi đặt lịch
```

---

### **8. Module Quản Lý Bác Sĩ (Doctor Management)**

**Chức năng:** Admin quản lý thông tin bác sĩ

**Workflow:**
```
1. Admin thêm bác sĩ mới:
   - Họ tên, SĐT, chuyên khoa
   - Hệ thống tạo tài khoản (SĐT = username)
   - Tạo mật khẩu random
   - Gửi email thông báo

2. Admin sửa thông tin bác sĩ:
   - Cập nhật họ tên, chuyên khoa
   - Cập nhật SĐT

3. Admin reset mật khẩu bác sĩ:
   - Tạo mật khẩu mới
   - Gửi email

4. Bác sĩ tự quản lý hồ sơ:
   - Cập nhật hồ sơ
   - Thay avatar
   - Đổi mật khẩu
```

---

### **9. Module Quản Lý Bệnh Nhân (Patient Management)**

**Chức năng:** Xem & quản lý bệnh nhân

**Workflow:**
```
1. Bệnh nhân đăng ký:
   - Nhập SĐT, mật khẩu, họ tên, email
   - OTP xác thực
   - Lưu vào DB

2. Admin xem danh sách bệnh nhân:
   - Xem lịch sử khám
   - Xóa bệnh nhân (nếu cần)

3. Bệnh nhân cập nhật hồ sơ:
   - Họ tên, SĐT, email, địa chỉ, ngày sinh
   - Thay avatar
   - Đổi mật khẩu
```

---

### **10. Module Gửi Email & Thông Báo (Email & Notification)**

**Chức năng:** Gửi email tự động cho người dùng

**Các loại email:**
```
1. Đăng ký thành công:
   - Gửi mã OTP
   - Gửi thông tin tài khoản

2. Lịch hẹn:
   - Lịch hẹn được tạo
   - Lịch hẹn được duyệt
   - Lịch hẹn bị từ chối
   - Lịch hẹn bị hủy

3. Yêu cầu nghỉ phép:
   - Yêu cầu được duyệt
   - Email thông báo hoãn lịch cho bệnh nhân

4. Xung đột lịch:
   - Email thông báo chuyển bác sĩ
   - Email xin lỗi khi hủy lịch

5. Quên mật khẩu:
   - Email reset mật khẩu
```

**File liên quan:** `includes/send_mail.php`

---

## 🗄️ V. CƠ SỞ DỮ LIỆU (Database Schema)

### **1. Bảng benhnhan (Bệnh Nhân)**
```sql
id_benhnhan      INT         Primary Key
sdt              VARCHAR     Số điện thoại (username)
mat_khau_hash    VARCHAR     Mật khẩu (hashed)
ten_day_du       VARCHAR     Họ và tên
email            VARCHAR     Email
dia_chi          VARCHAR     Địa chỉ
ngay_sinh        DATE        Ngày sinh
link_anh_dai_dien VARCHAR    Avatar URL
id_quantrivien_tao INT       Admin tạo
ngay_tao         TIMESTAMP   Ngày tạo
```

### **2. Bảng bacsi (Bác Sĩ)**
```sql
id_bacsi         INT         Primary Key
sdt              VARCHAR     Số điện thoại (username)
mat_khau_hash    VARCHAR     Mật khẩu
ten_day_du       VARCHAR     Họ và tên
chuyen_khoa      VARCHAR     Chuyên khoa
link_anh_dai_dien VARCHAR    Avatar
id_quantrivien_tao INT       Admin tạo
ngay_tao         TIMESTAMP   Ngày tạo
```

### **3. Bảng quantrivien (Admin)**
```sql
id_quantrivien   INT         Primary Key
ten_dang_nhap    VARCHAR     Tên đăng nhập
mat_khau_hash    VARCHAR     Mật khẩu
ten_day_du       VARCHAR     Họ và tên
id_quantrivien_tao INT       Admin tạo (admin cấp trên)
ngay_tao         TIMESTAMP   Ngày tạo
```

### **4. Bảng lichhen (Lịch Hẹn)**
```sql
id_lichhen       INT         Primary Key
id_benhnhan      INT         FK → benhnhan
id_bacsi         INT         FK → bacsi
id_dichvu        INT         FK → dichvu
ngay_gio_hen     DATETIME    Ngày giờ hẹn
trang_thai       ENUM        'cho_xac_nhan', 'da_xac_nhan', 'hoan_thanh', 'huy'
nguoi_tao_lich   VARCHAR     'benh_nhan', 'bac_si', 'quan_tri_vien'
ngay_tao         TIMESTAMP   Ngày tạo
```

### **5. Bảng dichvu (Dịch Vụ)**
```sql
id_dichvu        INT         Primary Key
ten_dich_vu      VARCHAR     Tên dịch vụ
mo_ta            TEXT        Mô tả
gia_tien         INT         Giá tiền (VND)
thoi_gian_phut   INT         Thời gian (phút)
ngay_tao         TIMESTAMP   Ngày tạo
```

### **6. Bảng lichlamviec (Lịch Làm Việc)**
```sql
id_lichlamviec   INT         Primary Key
id_bacsi         INT         FK → bacsi
id_giuongbenh    INT         FK → giuongbenh
id_quantrivien_tao INT       FK → quantrivien
ngay_trong_tuan  INT         Thứ (1-7)
gio_bat_dau      TIME        Giờ bắt đầu (08:00 hoặc 13:00)
gio_ket_thuc     TIME        Giờ kết thúc (12:00 hoặc 17:00)
ngay_hieu_luc    DATE        Ngày hữu lực
ngay_tao         TIMESTAMP   Ngày tạo
```

### **7. Bảng yeucaunghi (Yêu Cầu Nghỉ Phép)**
```sql
id_yeucau        INT         Primary Key
id_bacsi         INT         FK → bacsi
ngay_nghi        DATE        Ngày xin nghỉ
ca_nghi          VARCHAR     'Sang' hoặc 'Chieu'
ly_do            TEXT        Lý do xin nghỉ
trang_thai       ENUM        'cho_duyet', 'da_duyet', 'tu_choi'
id_quantrivien_duyet INT     FK → quantrivien (người duyệt)
ngay_tao         TIMESTAMP   Ngày tạo
```

### **8. Bảng giuongbenh (Giường/Phòng)**
```sql
id_giuongbenh    INT         Primary Key
so_giuong        VARCHAR     Số giường/phòng
mo_ta            VARCHAR     Mô tả
ngay_tao         TIMESTAMP   Ngày tạo
```

### **9. Bảng benhan (Bệnh Án)**
```sql
id_benhan        INT         Primary Key
id_lichhen       INT         FK → lichhen
chuan_doan       TEXT        Chẩn đoán
ghi_chu          TEXT        Ghi chú / Đơn thuốc
ngay_tao         TIMESTAMP   Ngày tạo
```

---

## 🔄 VI. LUỒNG CÔNG VIỆC CHÍNH

### **Luồng Đặt Lịch (Patient Booking Flow)**
```
┌─────────────────────────────────────────────────────────────┐
│                    1. BỆNH NHÂN ĐẶT LỊCH                    │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Chọn ngày, ca, BS, │
         │  dịch vụ           │
         └────────┬───────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Kiểm tra:          │
         │ - Có ca trực?      │
         │ - BS đang nghỉ?    │
         │ - Còn chỗ không?   │
         └────────┬───────────┘
                  │
                  ▼ OK / KHÔNG OK
         ┌────────────────────┐     ┌──────────────┐
         │ Tính thời gian     │     │ Báo lỗi      │
         │ (Queue System)     │     │ Hủy          │
         └────────┬───────────┘     └──────────────┘
                  │
                  ▼
         ┌────────────────────┐
         │ Kiểm tra vượt quá  │
         │ giờ kết thúc?      │
         └────────┬───────────┘
                  │
         ┌────────┴────────┐
         │                 │
         ▼ CÓ              ▼ KHÔNG
    ┌─────────┐       ┌──────────┐
    │ Báo lỗi │       │ Lưu lịch │
    │ Hủy     │       │ Email BN │
    └─────────┘       └──────────┘
```

### **Luồng Duyệt Yêu Cầu Nghỉ & Xử Lý Xung Đột (Leave & Conflict Flow)**
```
┌──────────────────────────────────────────────────────────┐
│         1. BÁC SĨ XIN NGHỈ PHÉP                         │
└──────────────┬───────────────────────────────────────────┘
               │
               ▼
    ┌──────────────────────┐
    │ Chọn ngày + ca       │
    │ Trạng thái = 'cho_duyet'
    └──────────┬───────────┘
               │
               ▼
┌──────────────────────────────────────────────────────────┐
│         2. ADMIN DUYỆT YÊU CẦU                          │
└──────────────┬───────────────────────────────────────────┘
               │
         ┌─────┴──────┐
         │            │
         ▼ DUYỆT      ▼ TỪ CHỐI
    ┌─────────┐   ┌─────────┐
    │ Tìm XD  │   │ Cập     │
    │ Gửi     │   │ nhật    │
    │ email   │   │ status  │
    └────┬────┘   └─────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────┐
│    3. HIỂN THỊ LỊCH XUNG ĐỘT TRÊN DASHBOARD ADMIN       │
└──────────────┬───────────────────────────────────────────┘
               │
         ┌─────┴──────────┐
         │                │
         ▼ CHUYỂN BS      ▼ HỦY LỊCH
    ┌─────────────┐  ┌──────────┐
    │ Chọn BS mới │  │ Cập nhật │
    │ Email BN    │  │ Email BN │
    └─────────────┘  └──────────┘
```

---

## 🔐 VII. AN TOÀN & BẢO MẬT

### **Xác Thực & Phân Quyền**
- ✅ Session-based authentication
- ✅ Role-based access control (3 vai trò)
- ✅ Password hashing (password_hash)
- ✅ SQL Injection prevention (Prepared Statements)
- ✅ Input validation & sanitization

### **Dữ Liệu Nhạy Cảm**
- ✅ Mật khẩu → Hash (không lưu plaintext)
- ✅ Email → Gửi qua SMTP secure
- ✅ SĐT → Không hiển thị công khai

---

## 📊 VIII. THỐNG KÊ & BÁNG ĐIỀU KHIỂN

### **Dashboard Bệnh Nhân**
- 📅 Lịch hẹn sắp tới
- ✅ Lịch hẹn đã hoàn thành
- 📋 Lịch sử khám

### **Dashboard Bác Sĩ**
- 👥 Số bệnh nhân đã khám
- ⏳ Số lịch chờ duyệt
- 📅 Lịch hẹn hôm nay
- 🗓️ Lịch làm việc tuần này
- 📋 Lịch hẹn sắp tới

### **Dashboard Admin**
- 👥 Tổng bác sĩ / bệnh nhân
- 📅 Tổng lịch hẹn (tháng này)
- ⏳ Yêu cầu nghỉ chờ duyệt
- ⚠️ Lịch hẹn xung đột
- 📋 Các biểu đồ thống kê

---

## 🚀 IX. CÔNG NGHỆ SỬ DỤNG

### **Backend**
```
PHP 7.0+
├── Procedural OOP
├── PDO (Database Access)
├── PHPMailer (Email)
└── Prepared Statements (SQL Security)
```

### **Frontend**
```
HTML5 / CSS3 / JavaScript
├── jQuery (DOM Manipulation)
├── AJAX (Async Requests)
├── Font Awesome (Icons)
└── Bootstrap-like Grid System
```

### **Database**
```
MySQL / MariaDB
├── 9 Main Tables
├── Foreign Keys
├── Indexes (Performance)
└── ENUM Types (Constraints)
```

### **Email**
```
PHPMailer
├── SMTP (Gmail)
├── UTF-8 Encoding
├── HTML Templates
└── Auto-responder
```

---

## ⚙️ X. CẤU HÌNH & TRIỂN KHAI

### **Cài Đặt Ban Đầu**
1. **Database Setup:**
   ```bash
   - Import nha_khoa.sql
   - Tạo database: idental_clinic
   - Tạo user MySQL
   ```

2. **PHP Configuration:**
   ```php
   // config/db_connect.php
   $host = 'localhost';
   $dbname = 'nha_khoa';
   $user = 'root';
   $pass = '';
   ```

3. **Email Configuration:**
   ```php
   // includes/send_mail.php
   $mail->Username = 'your-email@gmail.com';
   $mail->Password = 'app-password';
   ```

### **Yêu Cầu Hệ Thống**
- PHP 7.0+
- MySQL 5.7+
- Apache / Nginx
- cURL Extension
- OpenSSL Extension

---

## 📝 XI. HƯỚNG DẪN SỬ DỤNG

### **Bệnh Nhân**
1. Đăng ký tài khoản (SĐT, mật khẩu)
2. Xác thực OTP
3. Đặt lịch hẹn
4. Xem lịch sử khám
5. Hủy lịch (nếu cần)

### **Bác Sĩ**
1. Đăng nhập bằng SĐT
2. Xem lịch làm việc
3. Duyệt lịch hẹn chờ
4. Xin nghỉ phép (nếu cần)
5. Nhập bệnh án sau khám

### **Admin**
1. Đăng nhập với tài khoản admin
2. Quản lý bác sĩ (thêm/sửa/xóa)
3. Quản lý dịch vụ
4. Duyệt yêu cầu nghỉ
5. Xử lý xung đột lịch

---

## 🆘 XII. KHẮC PHỤC SỰ CỐ PHỔ BIẾN

| Sự Cố | Nguyên Nhân | Giải Pháp |
|-------|-----------|----------|
| Email không gửi được | SMTP sai | Kiểm tra config, cấp quyền ứng dụng |
| Lỗi database | Kết nối sai | Kiểm tra host, user, pass |
| Lịch không được duyệt | Không có ca trực | Admin tạo lịch làm việc trước |
| Không thể đặt lịch | Bác sĩ đang nghỉ | Chọn bác sĩ khác hoặc ngày khác |
| Avatar không thay đổi | Lỗi upload | Kiểm tra folder uploads, quyền file |

---

## 📞 XIII. LIÊN HỆ & HỖ TRỢ

**Phòng Khám Nha Khoa iDental**
- 📞 Hotline: [Số điện thoại]
- 📧 Email: support@idental.com
- 🕐 Giờ làm việc: 8:00 - 17:00 (Thứ 2 - Thứ 6)
- 📍 Địa chỉ: [Địa chỉ phòng khám]

---

## 📚 XIV. TÀI LIỆU THAM KHẢO

- **PHP Documentation:** https://www.php.net/docs.php
- **MySQL Documentation:** https://dev.mysql.com/doc/
- **PHPMailer:** https://github.com/PHPMailer/PHPMailer
- **JavaScript Reference:** https://developer.mozilla.org/en-US/docs/Web/JavaScript

---

**Phiên bản:** 1.0  
**Cập nhật lần cuối:** Tháng 12, 2025  
**Trạng thái:** ✅ Hoàn Chỉnh

---

*Tài liệu này mô tả chi tiết toàn bộ chức năng, kiến trúc, và luồng hoạt động của hệ thống iDental Clinic Management System.*
