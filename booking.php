<?php
// Kết nối tới cơ sở dữ liệu
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "benhviensql";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    echo "Lỗi kết nối: " . $e->getMessage();
    exit();
}

// Xử lý form khi được submit
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_SESSION['form_submitted'])) {
    $full_name = trim($_POST['full_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department_id = $_POST['department_id'];
    $appointment_date = $_POST['appointment_date'];
    $symptoms = trim($_POST['symptoms']);

    // Chuẩn bị và thực thi câu lệnh SQL
    try {
        $sql = "INSERT INTO patients (full_name, date_of_birth, gender, address, email, phone, source, appointment_date, department_id, symptoms)
                VALUES (:full_name, :date_of_birth, :gender, :address, :email, :phone, 'form', :appointment_date, :department_id, :symptoms)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':date_of_birth', $date_of_birth);
        $stmt->bindParam(':gender', $gender);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':appointment_date', $appointment_date);
        $stmt->bindParam(':department_id', $department_id);
        $stmt->bindParam(':symptoms', $symptoms);
        
        $stmt->execute();
        // Đặt session để ngăn gửi lại form
        session_start();
        $_SESSION['form_submitted'] = true;
        $_SESSION['success_message'] = "Đăng ký lịch khám thành công! Bệnh viện sẽ sớm liên hệ với bạn qua email hoặc số điện thoại, mong bạn chú ý.";
        // Redirect về index.php để reload toàn bộ trang
        header("Location: index.php#booking?success=1");
        exit();
    } catch(PDOException $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Lấy danh sách các khoa từ bảng departments
try {
    $stmt = $conn->query("SELECT department_id, department_name FROM departments");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy danh sách khoa: " . $e->getMessage();
}

// Kiểm tra và hiển thị thông báo từ session
session_start();
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
    unset($_SESSION['form_submitted']);
}
?>

<section id="booking" class="booking-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Đặt Lịch Khám Trực Tuyến</h2>
            <p class="section-subtitle">Điền thông tin để đặt lịch khám nhanh chóng và tiện lợi</p>
        </div>

        <!-- Toast Notification -->
        <?php if (isset($_GET['success']) && $success_message): ?>
            <div class="toast-container position-fixed bottom-0 end-0 p-3">
                <div id="successToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <?php echo $success_message; ?>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-4 max-w-2xl mx-auto bg-white p-8 rounded-xl shadow-lg">
            <div class="col-md-6">
                <label for="full_name" class="form-label fw-medium">Họ và tên</label>
                <input type="text" name="full_name" id="full_name" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
            </div>

            <div class="col-md-6">
                <label for="date_of_birth" class="form-label fw-medium">Ngày sinh</label>
                <input type="date" name="date_of_birth" id="date_of_birth" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
            </div>

            <div class="col-md-6">
                <label for="gender" class="form-label fw-medium">Giới tính</label>
                <select name="gender" id="gender" required class="form-select bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                    <option value="">Chọn giới tính</option>
                    <option value="Male">Nam</option>
                    <option value="Female">Nữ</option>
                    <option value="Other">Khác</option>
                </select>
            </div>

            <div class="col-md-6">
                <label for="address" class="form-label fw-medium">Địa chỉ</label>
                <input type="text" name="address" id="address" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label fw-medium">Email</label>
                <input type="email" name="email" id="email" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
            </div>

            <div class="col-md-6">
                <label for="phone" class="form-label fw-medium">Số điện thoại</label>
                <input type="tel" name="phone" id="phone" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
            </div>

            <div class="col-md-6">
                <label for="department_id" class="form-label fw-medium">Khoa khám</label>
                <select name="department_id" id="department_id" required class="form-select bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                    <option value="">Chọn khoa</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department['department_id']; ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="appointment_date" class="form-label fw-medium">Ngày khám</label>
                <input type="datetime-local" name="appointment_date" id="appointment_date" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
            </div>

            <div class="col-12">
                <label for="symptoms" class="form-label fw-medium">Triệu chứng</label>
                <textarea name="symptoms" id="symptoms" rows="5" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200"></textarea>
            </div>

            <div class="col-12 text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-6 py-3 rounded-lg">Đăng ký</button>
            </div>
        </form>
    </div>
</section>

<script>
    // Auto-show toast on page load if success parameter is present
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['success'])): ?>
            var toastEl = document.getElementById('successToast');
            var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
        <?php endif; ?>
    });
</script>