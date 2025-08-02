<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}require_once '../db.php';

// Kiểm tra đăng nhập bác sĩ
if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../login.php');
    exit;
}

// Kết nối cơ sở dữ liệu
try {
    $conn = new PDO("mysql:host=localhost;dbname=benhviensql", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
} catch(PDOException $e) {
    $error_message = "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
    error_log($error_message);
}

// Lấy danh sách lịch khám
$doctor_id = $_SESSION['doctor_id'];
try {
    $stmt = $conn->prepare("
        SELECT a.appointment_id, a.appointment_date, a.symptoms, a.status, p.full_name, d.department_name, r.room_name
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.patient_id 
        LEFT JOIN departments d ON a.department_id = d.department_id
        LEFT JOIN rooms r ON a.room_id = r.room_id
        WHERE a.doctor_id = :doctor_id 
        ORDER BY a.appointment_date DESC
    ");
    $stmt->execute([':doctor_id' => $doctor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy danh sách lịch khám: " . $e->getMessage();
    error_log($error_message);
}

// Xử lý AJAX cập nhật trạng thái
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $appointment_id = $_POST['appointment_id'] ?? '';
    $status = $_POST['status'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    $valid_statuses = ['Chờ xác nhận', 'Đã xác nhận', 'Hoàn thành', 'Đã hủy'];
    if (empty($appointment_id) || !in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE appointments SET status = :status WHERE appointment_id = :appointment_id");
        $stmt->execute([
            ':status' => $status,
            ':appointment_id' => $appointment_id
        ]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lịch khám.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công!']);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Lịch Khám</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-dark mb-4">Danh Sách Lịch Khám</h3>

            <!-- Toast Container -->
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-dark alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Bảng lịch khám -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered bg-white">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Bệnh nhân</th>
                            <th scope="col">Ngày giờ</th>
                            <th scope="col">Khoa</th>
                            <th scope="col">Phòng</th>
                            <th scope="col">Triệu chứng</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Không có lịch khám nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appointment['appointment_id']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($appointment['appointment_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['department_name'] ?? 'Chưa xác định'); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['room_name'] ?? 'Chưa xác định'); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['symptoms'] ?? 'Không có triệu chứng'); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch ($appointment['status']) {
                                                case 'Chờ xác nhận': echo 'bg-warning'; break;
                                                case 'Đã xác nhận': echo 'bg-success'; break;
                                                case 'Đã hủy': echo 'bg-danger'; break;
                                                case 'Hoàn thành': echo 'bg-primary'; break;
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <select class="form-select status-select" data-appointment-id="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                            <option value="Chờ xác nhận" <?php echo $appointment['status'] === 'Chờ xác nhận' ? 'selected' : ''; ?>>Chờ xác nhận</option>
                                            <option value="Đã xác nhận" <?php echo $appointment['status'] === 'Đã xác nhận' ? 'selected' : ''; ?>>Đã xác nhận</option>
                                            <option value="Đã hủy" <?php echo $appointment['status'] === 'Đã hủy' ? 'selected' : ''; ?>>Đã hủy</option>
                                            <option value="Hoàn thành" <?php echo $appointment['status'] === 'Hoàn thành' ? 'selected' : ''; ?>>Hoàn thành</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastContainer = document.querySelector('.toast-container');
    const statusSelects = document.querySelectorAll('.status-select');

    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const appointmentId = this.dataset.appointmentId;
            const newStatus = this.value;

            fetch('appointments.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `appointment_id=${encodeURIComponent(appointmentId)}&status=${encodeURIComponent(newStatus)}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white ${data.success ? 'bg-success' : 'bg-danger'} border-0`;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${data.message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                toastContainer.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);

                // Cập nhật badge trạng thái
                if (data.success) {
                    const badge = this.closest('tr').querySelector('.badge');
                    badge.textContent = newStatus;
                    badge.className = 'badge';
                    switch (newStatus) {
                        case 'Chờ xác nhận': badge.classList.add('bg-warning'); break;
                        case 'Đã xác nhận': badge.classList.add('bg-success'); break;
                        case 'Đã hủy': badge.classList.add('bg-danger'); break;
                        case 'Hoàn thành': badge.classList.add('bg-primary'); break;
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-danger border-0';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            Lỗi kết nối: ${error.message}. Vui lòng kiểm tra mạng hoặc server.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                toastContainer.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);
            });
        });
    });
});
</script>
</body>
</html>