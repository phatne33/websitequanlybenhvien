<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Kết nối tới cơ sở dữ liệu
try {
    $conn = new PDO("mysql:host=localhost;dbname=benhviensql", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => "Lỗi kết nối: " . $e->getMessage()]);
    exit();
}

// Ánh xạ trạng thái từ tiếng Anh sang tiếng Việt
$status_map_to_db = [
    'Pending' => 'Chờ xác nhận',
    'Confirmed' => 'Đã xác nhận',
    'Completed' => 'Hoàn thành',
    'Cancelled' => 'Đã hủy'
];

// Ánh xạ trạng thái từ tiếng Việt sang tiếng Anh
$status_map_from_db = array_flip($status_map_to_db);

// Danh sách trạng thái hợp lệ
$valid_statuses = array_keys($status_map_to_db);

// Xử lý AJAX request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add') {
        $patient_id = $_POST['patient_id'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? '';
        $department_id = $_POST['department_id'] ?? '';
        $room_id = $_POST['room_id'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_type = trim($_POST['appointment_type'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $status = $_POST['status'] ?? '';

        if (empty($patient_id) || empty($doctor_id) || empty($department_id) || empty($appointment_date) || empty($appointment_type) || empty($status)) {
            echo json_encode(['error' => 'Vui lòng điền đầy đủ các trường bắt buộc']);
            exit();
        }

        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['error' => 'Trạng thái không hợp lệ']);
            exit();
        }

        try {
            // Lấy patient_name và doctor_name
            $stmt = $conn->prepare("SELECT full_name FROM patients WHERE patient_id = :patient_id AND is_deleted = 0");
            $stmt->execute([':patient_id' => $patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                echo json_encode(['error' => 'Bệnh nhân không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            $stmt = $conn->prepare("SELECT full_name FROM doctors WHERE doctor_id = :doctor_id AND is_deleted = 0");
            $stmt->execute([':doctor_id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doctor) {
                echo json_encode(['error' => 'Bác sĩ không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            $sql = "INSERT INTO appointments (patient_id, doctor_id, department_id, room_id, appointment_date, appointment_type, symptoms, status, patient_name, doctor_name)
                    VALUES (:patient_id, :doctor_id, :department_id, :room_id, :appointment_date, :appointment_type, :symptoms, :status, :patient_name, :doctor_name)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':patient_id' => $patient_id,
                ':doctor_id' => $doctor_id,
                ':department_id' => $department_id,
                ':room_id' => $room_id ?: null,
                ':appointment_date' => $appointment_date,
                ':appointment_type' => $appointment_type,
                ':symptoms' => $symptoms,
                ':status' => $status_map_to_db[$status], // Ánh xạ sang tiếng Việt
                ':patient_name' => $patient['full_name'] ?? 'Unknown Patient',
                ':doctor_name' => $doctor['full_name'] ?? 'Unknown Doctor'
            ]);

            // Lấy thông tin để trả về
            $appointment_id = $conn->lastInsertId();
            $stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = :department_id");
            $stmt->execute([':department_id' => $department_id]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("SELECT room_name FROM rooms WHERE room_id = :room_id");
            $stmt->execute([':room_id' => $room_id ?: null]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Thêm lịch khám thành công!',
                'appointment_id' => $appointment_id,
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'department_id' => $department_id,
                'room_id' => $room_id ?: null,
                'patient_name' => $patient['full_name'] ?? 'Unknown Patient',
                'doctor_name' => $doctor['full_name'] ?? 'Unknown Doctor',
                'department_name' => $dept['department_name'] ?? 'N/A',
                'room_name' => $room['room_name'] ?? 'N/A',
                'appointment_date' => $appointment_date,
                'appointment_type' => $appointment_type,
                'symptoms' => $symptoms,
                'status' => $status, // Trả về trạng thái tiếng Anh
                'created_at' => date('Y-m-d H:i:s')
            ]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi thêm lịch khám: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'edit') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        if (empty($appointment_id)) {
            echo json_encode(['error' => 'Thiếu ID lịch khám']);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                SELECT a.*, d.department_name, r.room_name
                FROM appointments a
                LEFT JOIN departments d ON a.department_id = d.department_id
                LEFT JOIN rooms r ON a.room_id = r.room_id
                WHERE a.appointment_id = :appointment_id
            ");
            $stmt->execute([':appointment_id' => $appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                echo json_encode(['error' => 'Lịch khám không tồn tại']);
                exit();
            }

            // Ánh xạ trạng thái từ tiếng Việt sang tiếng Anh
            $appointment['status'] = $status_map_from_db[$appointment['status']] ?? 'Pending';

            echo json_encode([
                'success' => true,
                'appointment' => $appointment
            ]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi lấy thông tin lịch khám: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'update') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        $patient_id = $_POST['patient_id'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? '';
        $department_id = $_POST['department_id'] ?? '';
        $room_id = $_POST['room_id'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_type = trim($_POST['appointment_type'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $status = $_POST['status'] ?? '';

        if (empty($appointment_id) || empty($patient_id) || empty($doctor_id) || empty($department_id) || empty($appointment_date) || empty($appointment_type) || empty($status)) {
            echo json_encode(['error' => 'Vui lòng điền đầy đủ các trường bắt buộc']);
            exit();
        }

        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['error' => 'Trạng thái không hợp lệ']);
            exit();
        }

        try {
            // Lấy patient_name và doctor_name
            $stmt = $conn->prepare("SELECT full_name FROM patients WHERE patient_id = :patient_id AND is_deleted = 0");
            $stmt->execute([':patient_id' => $patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                echo json_encode(['error' => 'Bệnh nhân không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            $stmt = $conn->prepare("SELECT full_name FROM doctors WHERE doctor_id = :doctor_id AND is_deleted = 0");
            $stmt->execute([':doctor_id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doctor) {
                echo json_encode(['error' => 'Bác sĩ không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            $sql = "UPDATE appointments SET patient_id = :patient_id, doctor_id = :doctor_id, department_id = :department_id, 
                    room_id = :room_id, appointment_date = :appointment_date, appointment_type = :appointment_type, 
                    symptoms = :symptoms, status = :status, patient_name = :patient_name, doctor_name = :doctor_name 
                    WHERE appointment_id = :appointment_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':appointment_id' => $appointment_id,
                ':patient_id' => $patient_id,
                ':doctor_id' => $doctor_id,
                ':department_id' => $department_id,
                ':room_id' => $room_id ?: null,
                ':appointment_date' => $appointment_date,
                ':appointment_type' => $appointment_type,
                ':symptoms' => $symptoms,
                ':status' => $status_map_to_db[$status], // Ánh xạ sang tiếng Việt
                ':patient_name' => $patient['full_name'] ?? 'Unknown Patient',
                ':doctor_name' => $doctor['full_name'] ?? 'Unknown Doctor'
            ]);

            // Lấy thông tin để trả về
            $stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = :department_id");
            $stmt->execute([':department_id' => $department_id]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("SELECT room_name FROM rooms WHERE room_id = :room_id");
            $stmt->execute([':room_id' => $room_id ?: null]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Sửa lịch khám thành công!',
                'appointment_id' => $appointment_id,
                'patient_id' => $patient_id,
                'doctor_id' => $doctor_id,
                'department_id' => $department_id,
                'room_id' => $room_id ?: null,
                'patient_name' => $patient['full_name'] ?? 'Unknown Patient',
                'doctor_name' => $doctor['full_name'] ?? 'Unknown Doctor',
                'department_name' => $dept['department_name'] ?? 'N/A',
                'room_name' => $room['room_name'] ?? 'N/A',
                'appointment_date' => $appointment_date,
                'appointment_type' => $appointment_type,
                'symptoms' => $symptoms,
                'status' => $status, // Trả về trạng thái tiếng Anh
                'created_at' => date('Y-m-d H:i:s')
            ]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi sửa lịch khám: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'delete') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        if (empty($appointment_id)) {
            echo json_encode(['error' => 'Thiếu ID lịch khám']);
            exit();
        }

        try {
            // Kiểm tra xem lịch khám có tồn tại không
            $stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = :appointment_id");
            $stmt->execute([':appointment_id' => $appointment_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Lịch khám không tồn tại']);
                exit();
            }

            $sql = "DELETE FROM appointments WHERE appointment_id = :appointment_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':appointment_id' => $appointment_id]);
            echo json_encode(['success' => true, 'message' => 'Xóa lịch khám thành công!']);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi xóa lịch khám: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'get_appointments') {
        try {
            $stmt = $conn->query("
                SELECT a.*, d.department_name, r.room_name 
                FROM appointments a
                LEFT JOIN departments d ON a.department_id = d.department_id
                LEFT JOIN rooms r ON a.room_id = r.room_id
                ORDER BY a.appointment_date DESC
            ");
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Ánh xạ trạng thái sang tiếng Anh cho client
            foreach ($appointments as &$appointment) {
                $appointment['status'] = $status_map_from_db[$appointment['status']] ?? 'Pending';
            }
            echo json_encode(['success' => true, 'appointments' => $appointments]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi lấy danh sách lịch khám: " . $e->getMessage()]);
            exit();
        }
    }
}

// Lấy danh sách lịch khám, bệnh nhân, bác sĩ, khoa, phòng
try {
    $appointments = $conn->query("
        SELECT a.*, d.department_name, r.room_name 
        FROM appointments a
        LEFT JOIN departments d ON a.department_id = d.department_id
        LEFT JOIN rooms r ON a.room_id = r.room_id
        ORDER BY a.appointment_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $patients = $conn->query("SELECT patient_id, full_name FROM patients WHERE is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC);
    $doctors = $conn->query("SELECT doctor_id, full_name FROM doctors WHERE is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC);
    $departments = $conn->query("SELECT department_id, department_name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
    $rooms = $conn->query("SELECT room_id, room_name FROM rooms")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Lịch Khám</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .toast {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 10px;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toast-success {
            background-color: #10b981;
            color: white;
        }
        .toast-error {
            background-color: #ef4444;
            color: white;
        }
        .toast.show {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4 text-gray-800">Quản Lý Lịch Khám</h3>

            <!-- Toast Container -->
            <div id="toast-container" class="fixed top-5 right-5 z-[9999]"></div>

            <!-- Modal xác nhận xóa -->
            <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-[1000]">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h3 class="text-lg font-bold mb-4 text-gray-800" id="deleteModalTitle">Xác nhận xóa lịch khám</h3>
                    <p class="text-gray-600 mb-6" id="deleteModalMessage"></p>
                    <div class="flex justify-end space-x-4">
                        <button id="cancelDelete" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition">Hủy</button>
                        <button id="confirmDelete" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition">Xóa</button>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg class="fill-current h-6 w-6 text-red-500" role="button" onclick="this.parentElement.parentElement.style.display='none'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M14.348 14.849a1 1 0 01-1.414 0L10 11.414l-2.934 2.935a1 1 0 01-1.414-1.414L8.586 10 5.652 7.065a1 1 0 011.414-1.414L10 8.586l2.934-2.935a1 1 0 011.414 1.414L11.414 10l2.934 2.935a1 0 010 1.414z"/>
                        </svg>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Form thêm/sửa lịch khám -->
            <div class="mb-6">
                <h4 id="formTitle" class="text-base font-semibold mb-3 text-gray-700">Thêm Lịch Khám</h4>
                <form id="appointmentForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="appointment_id" id="appointment_id">
                    <div>
                        <label for="patient_id" class="block text-sm font-medium text-gray-700">Bệnh nhân</label>
                        <select name="patient_id" id="patient_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Chọn bệnh nhân</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo htmlspecialchars($patient['patient_id']); ?>"><?php echo htmlspecialchars($patient['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="doctor_id" class="block text-sm font-medium text-gray-700">Bác sĩ</label>
                        <select name="doctor_id" id="doctor_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Chọn bác sĩ</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo htmlspecialchars($doctor['doctor_id']); ?>"><?php echo htmlspecialchars($doctor['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700">Khoa</label>
                        <select name="department_id" id="department_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Chọn khoa</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo htmlspecialchars($department['department_id']); ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="room_id" class="block text-sm font-medium text-gray-700">Phòng</label>
                        <select name="room_id" id="room_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Chọn phòng</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo htmlspecialchars($room['room_id']); ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="appointment_date" class="block text-sm font-medium text-gray-700">Ngày khám</label>
                        <input type="datetime-local" name="appointment_date" id="appointment_date" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label for="appointment_type" class="block text-sm font-medium text-gray-700">Loại lịch khám</label>
                        <input type="text" name="appointment_type" id="appointment_type" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Trạng thái</label>
                        <select name="status" id="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="Pending">Chờ xác nhận</option>
                            <option value="Confirmed">Đã xác nhận</option>
                            <option value="Completed">Hoàn thành</option>
                            <option value="Cancelled">Đã hủy</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="symptoms" class="block text-sm font-medium text-gray-700">Triệu chứng</label>
                        <textarea name="symptoms" id="symptoms" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="md:col-span-2 flex justify-center space-x-4">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Lưu</button>
                        <button type="button" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition" id="cancelEdit" style="display: none;">Hủy sửa</button>
                    </div>
                </form>
            </div>

            <!-- Danh sách lịch khám -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Bệnh nhân</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Bác sĩ</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Khoa</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Phòng</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ngày</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Loại</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Triệu chứng</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Trạng thái</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ngày tạo</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentTable">
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4 text-gray-600">Không có lịch hẹn nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr id="appointment-<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                    <td class="px-4 py-2 text-gray-700">#<?php echo htmlspecialchars($appointment['appointment_id']); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['patient_name'] ?: 'Unknown Patient'); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['doctor_name'] ?: 'Unknown Doctor'); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['department_name'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['room_name'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['appointment_date'] ? date('d/m/Y H:i', strtotime($appointment['appointment_date'])) : 'N/A'); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['appointment_type'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['symptoms'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-2">
                                        <span class="inline-block px-2 py-1 rounded text-sm <?php
                                            $status = $appointment['status'];
                                            if ($status === 'Đã xác nhận') {
                                                echo 'bg-green-100 text-green-800';
                                            } elseif ($status === 'Chờ xác nhận') {
                                                echo 'bg-yellow-100 text-yellow-800';
                                            } elseif ($status === 'Đã hủy') {
                                                echo 'bg-red-100 text-red-800';
                                            } elseif ($status === 'Hoàn thành') {
                                                echo 'bg-gray-100 text-gray-800';
                                            } else {
                                                echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($status ?: 'Không xác định'); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($appointment['created_at'] ? date('d/m/Y H:i', strtotime($appointment['created_at'])) : 'N/A'); ?></td>
                                    <td class="px-4 py-2">
                                        <button class="text-blue-600 hover:text-blue-800 mr-2 edit-btn" data-id="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-800 delete-btn" data-id="<?php echo htmlspecialchars($appointment['appointment_id']); ?>" data-name="<?php echo htmlspecialchars($appointment['patient_name'] ?: 'Unknown Patient') . ' - ' . date('d/m/Y H:i', strtotime($appointment['appointment_date'])); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('appointmentForm');
            const cancelEditBtn = document.getElementById('cancelEdit');
            const formTitle = document.getElementById('formTitle');
            const deleteModal = document.getElementById('deleteModal');
            const deleteModalMessage = document.getElementById('deleteModalMessage');
            const confirmDelete = document.getElementById('confirmDelete');
            const cancelDelete = document.getElementById('cancelDelete');
            let deleteId = null;

            // Hàm hiển thị toast
            function showToast(message, type) {
                const toastContainer = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'}`;
                toast.innerHTML = `
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                `;
                toastContainer.appendChild(toast);
                setTimeout(() => toast.classList.add('show'), 100);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            // Hàm làm mới bảng lịch khám
            function refreshAppointmentTable() {
                fetch('appointments.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ action: 'get_appointments' })
                })
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('appointmentTable');
                    tableBody.innerHTML = '';
                    if (data.success && data.appointments.length > 0) {
                        data.appointments.forEach(appointment => {
                            const statusText = {
                                'Pending': 'Chờ xác nhận',
                                'Confirmed': 'Đã xác nhận',
                                'Completed': 'Hoàn thành',
                                'Cancelled': 'Đã hủy'
                            }[appointment.status] || 'Không xác định';
                            const statusClass = {
                                'Pending': 'bg-yellow-100 text-yellow-800',
                                'Confirmed': 'bg-green-100 text-green-800',
                                'Completed': 'bg-gray-100 text-gray-800',
                                'Cancelled': 'bg-red-100 text-red-800'
                            }[appointment.status] || 'bg-gray-100 text-gray-800';
                            const row = document.createElement('tr');
                            row.id = `appointment-${appointment.appointment_id}`;
                            row.innerHTML = `
                                <td class="px-4 py-2 text-gray-700">#${appointment.appointment_id}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.patient_name || 'Unknown Patient'}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.doctor_name || 'Unknown Doctor'}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.department_name || 'N/A'}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.room_name || 'N/A'}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.appointment_date ? new Date(appointment.appointment_date).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.appointment_type || 'N/A'}</td>
                                <td class="px-4 py-2 text-gray-700">${appointment.symptoms || 'N/A'}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-block px-2 py-1 rounded text-sm ${statusClass}">${statusText}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-700">${appointment.created_at ? new Date(appointment.created_at).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}</td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2 edit-btn" data-id="${appointment.appointment_id}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-btn" data-id="${appointment.appointment_id}" data-name="${appointment.patient_name || 'Unknown Patient'} - ${appointment.appointment_date ? new Date(appointment.appointment_date).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-gray-600">Không có lịch hẹn nào</td></tr>';
                    }
                })
                .catch(error => {
                    showToast('Lỗi khi tải danh sách lịch khám: ' + error.message, 'error');
                });
            }

            // Xử lý submit form
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const action = formData.get('action') === 'add' ? 'add' : 'update';
                formData.set('action', action);
                fetch('appointments.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message || data.error, data.success ? 'success' : 'error');
                    if (data.success) {
                        form.reset();
                        document.getElementById('action').value = 'add';
                        document.getElementById('appointment_id').value = '';
                        formTitle.textContent = 'Thêm Lịch Khám';
                        cancelEditBtn.style.display = 'none';
                        refreshAppointmentTable();
                    }
                })
                .catch(() => showToast('Lỗi kết nối. Vui lòng kiểm tra mạng.', 'error'));
            });

            // Xử lý nút sửa
            document.addEventListener('click', function(e) {
                if (e.target.closest('.edit-btn')) {
                    const btn = e.target.closest('.edit-btn');
                    const appointmentId = btn.getAttribute('data-id');
                    fetch('appointments.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: new URLSearchParams({
                            action: 'edit',
                            appointment_id: appointmentId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const appointment = data.appointment;
                            document.getElementById('action').value = 'update';
                            document.getElementById('appointment_id').value = appointment.appointment_id;
                            document.getElementById('patient_id').value = appointment.patient_id || '';
                            document.getElementById('doctor_id').value = appointment.doctor_id || '';
                            document.getElementById('department_id').value = appointment.department_id || '';
                            document.getElementById('room_id').value = appointment.room_id || '';
                            document.getElementById('appointment_date').value = appointment.appointment_date ? new Date(appointment.appointment_date).toISOString().slice(0, 16) : '';
                            document.getElementById('appointment_type').value = appointment.appointment_type || '';
                            document.getElementById('symptoms').value = appointment.symptoms || '';
                            document.getElementById('status').value = appointment.status || 'Pending';
                            formTitle.textContent = 'Sửa Lịch Khám';
                            cancelEditBtn.style.display = 'inline-block';
                        } else {
                            showToast(data.error, 'error');
                        }
                    })
                    .catch(() => showToast('Lỗi khi tải dữ liệu lịch khám', 'error'));
                }

                if (e.target.closest('.delete-btn')) {
                    const btn = e.target.closest('.delete-btn');
                    deleteId = btn.getAttribute('data-id');
                    deleteModalMessage.textContent = `Bạn có chắc chắn muốn xóa lịch khám của "${btn.getAttribute('data-name')}"?`;
                    deleteModal.classList.remove('hidden');
                }
            });

            // Xử lý xác nhận xóa
            confirmDelete.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('appointment_id', deleteId);
                fetch('appointments.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message || data.error, data.success ? 'success' : 'error');
                    if (data.success) {
                        refreshAppointmentTable();
                    }
                    deleteModal.classList.add('hidden');
                })
                .catch(() => {
                    showToast('Lỗi kết nối. Vui lòng kiểm tra mạng.', 'error');
                    deleteModal.classList.add('hidden');
                });
            });

            // Xử lý hủy xóa
            cancelDelete.addEventListener('click', function() {
                deleteModal.classList.add('hidden');
            });

            // Xử lý hủy sửa
            cancelEditBtn.addEventListener('click', function() {
                form.reset();
                document.getElementById('action').value = 'add';
                document.getElementById('appointment_id').value = '';
                formTitle.textContent = 'Thêm Lịch Khám';
                cancelEditBtn.style.display = 'none';
            });

            // Tải danh sách lịch khám ban đầu
            refreshAppointmentTable();
        });
    </script>
</body>
</html>