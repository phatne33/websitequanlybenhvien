# 🏥 Hệ Thống Quản Lý Bệnh Viện

Đây là hệ thống quản lý bệnh viện được xây dựng để quản lý các hoạt động như thông tin bệnh nhân, bác sĩ, lịch hẹn, đơn thuốc và danh mục thuốc.  
Hệ thống sử dụng cơ sở dữ liệu **MySQL** và được thiết kế để chạy trên máy chủ cục bộ thông qua **XAMPP**.

---

## 📋 Yêu Cầu

- **XAMPP**: Cài đặt XAMPP với các module Apache và MySQL được kích hoạt.
- **PHP**: Phiên bản 7.4 hoặc tương thích (đã được tích hợp sẵn trong XAMPP).
- **Trình duyệt**: Bất kỳ trình duyệt hiện đại nào (Chrome, Firefox, Edge,...).

---

## 🛠️ Hướng Dẫn Cài Đặt

### 1. Cài đặt XAMPP

- Tải và cài đặt XAMPP tại: [https://www.apachefriends.org](https://www.apachefriends.org/)
- Mở XAMPP Control Panel và **khởi động Apache + MySQL**

### 2. Thiết lập dự án

- Sao chép thư mục dự án (chứa mã nguồn website) vào thư mục `htdocs` trong XAMPP  
  _Ví dụ:_ `C:\xampp\htdocs\hospital-management`
- Đảm bảo file SQL `benhviensql.sql` nằm trong thư mục dự án

### 3. Nhập cơ sở dữ liệu

1. Truy cập: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Tạo cơ sở dữ liệu mới tên là `benhviensql`
3. Chọn cơ sở dữ liệu `benhviensql` → tab **Nhập (Import)** → tải lên file `benhviensql.sql`
4. Nhấn **Thực hiện (Go)** để import

### 4. Truy cập
 - Mở đường dẫn localhost/webkha/admin để đăng nhập admin với tài khoản : admin1 và mật khẩu admin123
 -  localhost/webkha/doctor để đăng nhập của bác sĩ.




