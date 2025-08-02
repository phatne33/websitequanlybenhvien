<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra đăng nhập và trả về JSON nếu không đăng nhập
if (!isset($_SESSION['admin_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.', 'redirect' => '../login.php']);
        exit();
    }
    header('Location: ../login.php');
    exit;
}

// Kết nối tới cơ sở dữ liệu
try {
    $conn = new PDO("mysql:host=localhost;dbname=benhviensql", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage()]);
    exit();
}

// Ánh xạ trạng thái tiếng Việt sang tiếng Anh và ngược lại
$status_map = [
    'Chờ xử lý' => 'Pending',
    'Đã duyệt' => 'Approved',
    'Đã hủy' => 'Cancelled'
];
$status_map_reverse = array_flip($status_map);
$valid_statuses = array_keys($status_map);

// Xử lý AJAX request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add') {
        $appointment_id = $_POST['appointment_id'] ?? '';
        $patient_id = $_POST['patient_id'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? '';
        $created_at = $_POST['created_at'] ?? '';
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $status = in_array($_POST['status'] ?? '', $valid_statuses) ? $status_map[$_POST['status']] : 'Pending';
        $admin_note = trim($_POST['admin_note'] ?? '');
        $medication_ids = $_POST['medication_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $units = $_POST['units'] ?? [];
        $instructions = $_POST['instructions'] ?? [];

        if (empty($appointment_id) || empty($patient_id) || empty($doctor_id) || empty($created_at) || empty($medication_ids)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Vui lòng điền đầy đủ các trường bắt buộc']);
            exit();
        }

        try {
            // Lấy patient_name và doctor_name
            $stmt = $conn->prepare("SELECT full_name FROM patients WHERE patient_id = :patient_id AND is_deleted = 0");
            $stmt->execute([':patient_id' => $patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Bệnh nhân không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            $stmt = $conn->prepare("SELECT full_name FROM doctors WHERE doctor_id = :doctor_id AND is_deleted = 0");
            $stmt->execute([':doctor_id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doctor) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Bác sĩ không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            // Kiểm tra appointment_id
            $stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = :appointment_id AND status != 'Đã hủy'");
            $stmt->execute([':appointment_id' => $appointment_id]);
            if (!$stmt->fetch()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Lịch hẹn không hợp lệ hoặc đã bị hủy']);
                exit();
            }

            // Kiểm tra medication_ids
            $medication_names = [];
            if (!empty($medication_ids)) {
                $placeholders = implode(',', array_fill(0, count($medication_ids), '?'));
                $stmt = $conn->prepare("SELECT medication_id, name FROM medications WHERE medication_id IN ($placeholders)");
                $stmt->execute($medication_ids);
                $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $medication_names = array_column($medications, 'name', 'medication_id');
                if (count($medications) != count($medication_ids)) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Một hoặc nhiều ID thuốc không hợp lệ']);
                    exit();
                }
            }

            // Bắt đầu transaction
            $conn->beginTransaction();

            // Thêm đơn thuốc
            $sql = "INSERT INTO prescriptions (appointment_id, patient_id, doctor_id, diagnosis, status, admin_note, created_at, patient_name, doctor_name)
                    VALUES (:appointment_id, :patient_id, :doctor_id, :diagnosis, :status, :admin_note, :created_at, :patient_name, :doctor_name)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':appointment_id' => $appointment_id,
                ':patient_id' => $patient_id,
                ':doctor_id' => $doctor_id,
                ':diagnosis' => $diagnosis,
                ':status' => $status,
                ':admin_note' => $admin_note ?: NULL,
                ':created_at' => $created_at,
                ':patient_name' => $patient['full_name'],
                ':doctor_name' => $doctor['full_name']
            ]);
            $prescription_id = $conn->lastInsertId();

            // Thêm chi tiết thuốc
            $stmt = $conn->prepare("INSERT INTO prescription_details (prescription_id, medication_id, quantity, unit, instructions) 
                                    VALUES (:prescription_id, :medication_id, :quantity, :unit, :instructions)");
            foreach ($medication_ids as $index => $medication_id) {
                $stmt->execute([
                    ':prescription_id' => $prescription_id,
                    ':medication_id' => $medication_id,
                    ':quantity' => $quantities[$index] ?? 1,
                    ':unit' => $units[$index] ?? '',
                    ':instructions' => $instructions[$index] ?? ''
                ]);
            }

            $conn->commit();

            // Chuẩn bị danh sách thuốc để trả về
            $medication_list = [];
            foreach ($medication_ids as $index => $med_id) {
                $medication_list[] = [
                    'name' => $medication_names[$med_id] ?? 'Unknown Medication',
                    'quantity' => $quantities[$index] ?? 1,
                    'unit' => $units[$index] ?? '',
                    'instructions' => $instructions[$index] ?? ''
                ];
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Thêm đơn thuốc thành công!',
                'prescription_id' => $prescription_id,
                'appointment_id' => $appointment_id,
                'patient_name' => $patient['full_name'],
                'doctor_name' => $doctor['full_name'],
                'created_at' => $created_at,
                'diagnosis' => $diagnosis,
                'status' => $status_map_reverse[$status] ?? 'Chờ xử lý',
                'admin_note' => $admin_note ?: 'Không có',
                'medications' => $medication_list
            ]);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => "Lỗi khi thêm đơn thuốc: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'edit') {
        $prescription_id = $_POST['prescription_id'] ?? '';
        $appointment_id = $_POST['appointment_id'] ?? '';
        $patient_id = $_POST['patient_id'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? '';
        $created_at = $_POST['created_at'] ?? '';
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $status = in_array($_POST['status'] ?? '', $valid_statuses) ? $status_map[$_POST['status']] : 'Pending';
        $admin_note = trim($_POST['admin_note'] ?? '');
        $medication_ids = $_POST['medication_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $units = $_POST['units'] ?? [];
        $instructions = $_POST['instructions'] ?? [];

        // Kiểm tra dữ liệu đầu vào
        if (empty($prescription_id) || empty($appointment_id) || empty($patient_id) || empty($doctor_id) || empty($created_at) || empty($medication_ids)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Vui lòng điền đầy đủ các trường bắt buộc']);
            exit();
        }

        try {
            // Kiểm tra prescription_id có tồn tại
            $stmt = $conn->prepare("SELECT prescription_id FROM prescriptions WHERE prescription_id = :prescription_id");
            $stmt->execute([':prescription_id' => $prescription_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Đơn thuốc không tồn tại']);
                exit();
            }

            // Lấy patient_name và doctor_name
            $stmt = $conn->prepare("SELECT full_name FROM patients WHERE patient_id = :patient_id AND is_deleted = 0");
            $stmt->execute([':patient_id' => $patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$patient) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Bệnh nhân không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            $stmt = $conn->prepare("SELECT full_name FROM doctors WHERE doctor_id = :doctor_id AND is_deleted = 0");
            $stmt->execute([':doctor_id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doctor) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Bác sĩ không hợp lệ hoặc đã bị xóa']);
                exit();
            }

            // Kiểm tra appointment_id
            $stmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = :appointment_id AND status != 'Đã hủy'");
            $stmt->execute([':appointment_id' => $appointment_id]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Lịch hẹn không hợp lệ hoặc đã bị hủy']);
                exit();
            }

            // Kiểm tra medication_ids
            $medication_names = [];
            if (!empty($medication_ids)) {
                $placeholders = implode(',', array_fill(0, count($medication_ids), '?'));
                $stmt = $conn->prepare("SELECT medication_id, name FROM medications WHERE medication_id IN ($placeholders)");
                $stmt->execute($medication_ids);
                $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $medication_names = array_column($medications, 'name', 'medication_id');
                if (count($medications) != count($medication_ids)) {
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Một hoặc nhiều ID thuốc không hợp lệ']);
                    exit();
                }
            }

            // Bắt đầu transaction
            $conn->beginTransaction();

            // Cập nhật đơn thuốc
            $sql = "UPDATE prescriptions SET appointment_id = :appointment_id, patient_id = :patient_id, doctor_id = :doctor_id, 
                    diagnosis = :diagnosis, status = :status, admin_note = :admin_note, created_at = :created_at, 
                    patient_name = :patient_name, doctor_name = :doctor_name 
                    WHERE prescription_id = :prescription_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':prescription_id' => $prescription_id,
                ':appointment_id' => $appointment_id,
                ':patient_id' => $patient_id,
                ':doctor_id' => $doctor_id,
                ':diagnosis' => $diagnosis,
                ':status' => $status,
                ':admin_note' => $admin_note ?: NULL,
                ':created_at' => $created_at,
                ':patient_name' => $patient['full_name'],
                ':doctor_name' => $doctor['full_name']
            ]);

            // Xóa chi tiết thuốc cũ
            $stmt = $conn->prepare("DELETE FROM prescription_details WHERE prescription_id = :prescription_id");
            $stmt->execute([':prescription_id' => $prescription_id]);

            // Thêm chi tiết thuốc mới
            $stmt = $conn->prepare("INSERT INTO prescription_details (prescription_id, medication_id, quantity, unit, instructions) 
                                    VALUES (:prescription_id, :medication_id, :quantity, :unit, :instructions)");
            foreach ($medication_ids as $index => $medication_id) {
                if (!isset($medication_names[$medication_id])) {
                    $conn->rollBack();
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => "ID thuốc không hợp lệ: $medication_id"]);
                    exit();
                }
                $stmt->execute([
                    ':prescription_id' => $prescription_id,
                    ':medication_id' => $medication_id,
                    ':quantity' => $quantities[$index] ?? 1,
                    ':unit' => $units[$index] ?? '',
                    ':instructions' => $instructions[$index] ?? ''
                ]);
            }

            $conn->commit();

            // Chuẩn bị danh sách thuốc để trả về
            $medication_list = [];
            foreach ($medication_ids as $index => $med_id) {
                $medication_list[] = [
                    'name' => $medication_names[$med_id] ?? 'Unknown Medication',
                    'quantity' => $quantities[$index] ?? 1,
                    'unit' => $units[$index] ?? '',
                    'instructions' => $instructions[$index] ?? ''
                ];
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Sửa đơn thuốc thành công!',
                'prescription_id' => $prescription_id,
                'appointment_id' => $appointment_id,
                'patient_name' => $patient['full_name'],
                'doctor_name' => $doctor['full_name'],
                'created_at' => $created_at,
                'diagnosis' => $diagnosis,
                'status' => $status_map_reverse[$status] ?? 'Chờ xử lý',
                'admin_note' => $admin_note ?: 'Không có',
                'medications' => $medication_list
            ]);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => "Lỗi khi sửa đơn thuốc: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'delete') {
        $prescription_id = $_POST['prescription_id'] ?? '';
        try {
            // Kiểm tra xem đơn thuốc có tồn tại không
            $stmt = $conn->prepare("SELECT prescription_id FROM prescriptions WHERE prescription_id = :prescription_id");
            $stmt->execute([':prescription_id' => $prescription_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Đơn thuốc không tồn tại']);
                exit();
            }

            // Bắt đầu transaction
            $conn->beginTransaction();

            // Xóa đơn thuốc
            $stmt = $conn->prepare("DELETE FROM prescriptions WHERE prescription_id = :prescription_id");
            $stmt->execute([':prescription_id' => $prescription_id]);

            $conn->commit();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Xóa đơn thuốc thành công!']);
            exit();
        } catch(PDOException $e) {
            $conn->rollBack();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => "Lỗi khi xóa đơn thuốc: " . $e->getMessage()]);
            exit();
        }
    }
}

// Xử lý yêu cầu GET cho get_details
if (isset($_GET['action']) && $_GET['action'] == 'get_details' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $prescription_id = $_GET['prescription_id'] ?? '';
    try {
        // Kiểm tra prescription_id hợp lệ
        if (empty($prescription_id) || !is_numeric($prescription_id)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID đơn thuốc không hợp lệ']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT p.*, 
                   COALESCE(pt.full_name, p.patient_name) AS patient_name, 
                   COALESCE(d.full_name, p.doctor_name) AS doctor_name
            FROM prescriptions p
            LEFT JOIN patients pt ON p.patient_id = pt.patient_id AND pt.is_deleted = 0
            LEFT JOIN doctors d ON p.doctor_id = d.doctor_id AND d.is_deleted = 0
            WHERE p.prescription_id = :prescription_id
        ");
        $stmt->execute([':prescription_id' => $prescription_id]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Đơn thuốc không tồn tại']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT pd.*, m.name
            FROM prescription_details pd
            JOIN medications m ON pd.medication_id = m.medication_id
            WHERE pd.prescription_id = :prescription_id
        ");
        $stmt->execute([':prescription_id' => $prescription_id]);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ánh xạ status sang tiếng Việt
        $display_status = isset($status_map_reverse[$prescription['status']]) ? $status_map_reverse[$prescription['status']] : 'Chờ xử lý';

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'prescription' => [
                'prescription_id' => $prescription['prescription_id'],
                'appointment_id' => $prescription['appointment_id'],
                'patient_id' => $prescription['patient_id'],
                'doctor_id' => $prescription['doctor_id'],
                'diagnosis' => $prescription['diagnosis'],
                'status' => $display_status,
                'admin_note' => $prescription['admin_note'] ?: 'Không có',
                'created_at' => $prescription['created_at'],
                'patient_name' => $prescription['patient_name'] ?: 'Unknown Patient',
                'doctor_name' => $prescription['doctor_name'] ?: 'Unknown Doctor'
            ],
            'medications' => $medications
        ]);
        exit();
    } catch(PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => "Lỗi khi lấy chi tiết đơn thuốc: " . $e->getMessage()]);
        exit();
    }
}

// Lấy danh sách đơn thuốc, bệnh nhân, bác sĩ, thuốc, lịch hẹn
try {
    $prescriptions = $conn->query("
        SELECT p.*, 
               COALESCE(pt.full_name, p.patient_name) AS patient_name, 
               COALESCE(d.full_name, p.doctor_name) AS doctor_name,
               CASE 
                   WHEN p.status = 'Pending' THEN 'Chờ xử lý'
                   WHEN p.status = 'Approved' THEN 'Đã duyệt'
                   WHEN p.status = 'Cancelled' THEN 'Đã hủy'
                   ELSE 'Chờ xử lý'
               END AS display_status,
               GROUP_CONCAT(CONCAT(m.name, ' (Số lượng: ', pd.quantity, ' ', pd.unit, ', Hướng dẫn: ', COALESCE(pd.instructions, '')) SEPARATOR '; ') AS medications
        FROM prescriptions p
        LEFT JOIN prescription_details pd ON p.prescription_id = pd.prescription_id
        LEFT JOIN medications m ON pd.medication_id = m.medication_id
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id AND pt.is_deleted = 0
        LEFT JOIN doctors d ON p.doctor_id = d.doctor_id AND d.is_deleted = 0
        GROUP BY p.prescription_id
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $patients = $conn->query("SELECT patient_id, full_name FROM patients WHERE is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC);
    $doctors = $conn->query("SELECT doctor_id, full_name FROM doctors WHERE is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC);
    $medications = $conn->query("SELECT medication_id, name, unit FROM medications")->fetchAll(PDO::FETCH_ASSOC);
    $appointments = $conn->query("SELECT appointment_id, CONCAT('Lịch hẹn #', appointment_id, ' - ', patient_name, ' - ', appointment_date) AS appointment_info FROM appointments WHERE status != 'Đã hủy'")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4 text-gray-800">Quản Lý Đơn Thuốc</h3>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-[9999]"></div>

    <!-- Modal xác nhận xóa -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-[1000]">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4 text-gray-800" id="deleteModalTitle">Xác nhận xóa đơn thuốc</h3>
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

    <!-- Form thêm/sửa đơn thuốc -->
    <div class="mb-6">
        <h4 id="formTitle" class="text-base font-semibold mb-3 text-gray-800">Thêm Đơn Thuốc</h4>
        <form id="prescriptionForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" id="action" value="add">
            <input type="hidden" name="prescription_id" id="prescription_id">
            <div>
                <label for="appointment_id" class="block text-sm font-medium text-gray-700">Lịch hẹn</label>
                <select name="appointment_id" id="appointment_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Chọn lịch hẹn</option>
                    <?php foreach ($appointments as $appointment): ?>
                        <option value="<?php echo $appointment['appointment_id']; ?>"><?php echo htmlspecialchars($appointment['appointment_info']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="patient_id" class="block text-sm font-medium text-gray-700">Bệnh nhân</label>
                <select name="patient_id" id="patient_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Chọn bệnh nhân</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['patient_id']; ?>"><?php echo htmlspecialchars($patient['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="doctor_id" class="block text-sm font-medium text-gray-700">Bác sĩ</label>
                <select name="doctor_id" id="doctor_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Chọn bác sĩ</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['doctor_id']; ?>"><?php echo htmlspecialchars($doctor['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="created_at" class="block text-sm font-medium text-gray-700">Ngày kê đơn</label>
                <input type="datetime-local" name="created_at" id="created_at" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Trạng thái</label>
                <select name="status" id="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="Chờ xử lý">Chờ xử lý</option>
                    <option value="Đã duyệt">Đã duyệt</option>
                    <option value="Đã hủy">Đã hủy</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Thuốc</label>
                <div id="medicationContainer" class="mt-1">
                    <div class="medication-row grid grid-cols-4 gap-2 mb-2">
                        <select name="medication_ids[]" class="medication-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Chọn thuốc</option>
                            <?php foreach ($medications as $medication): ?>
                                <option value="<?php echo $medication['medication_id']; ?>" data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                                    <?php echo htmlspecialchars($medication['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="quantities[]" placeholder="Số lượng" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <input type="text" name="units[]" placeholder="Đơn vị" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <input type="text" name="instructions[]" placeholder="Hướng dẫn sử dụng" class="col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" class="remove-medication text-red-600 hover:text-red-800 col-span-1"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <button type="button" id="addMedication" class="mt-2 text-blue-600 hover:text-blue-800"><i class="fas fa-plus"></i> Thêm thuốc</button>
            </div>
            <div class="md:col-span-2">
                <label for="diagnosis" class="block text-sm font-medium text-gray-700">Chẩn đoán</label>
                <textarea name="diagnosis" id="diagnosis" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
            </div>
            <div class="md:col-span-2">
                <label for="admin_note" class="block text-sm font-medium text-gray-700">Ghi chú quản trị</label>
                <textarea name="admin_note" id="admin_note" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="md:col-span-2 flex justify-center space-x-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Lưu</button>
                <button type="button" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition" id="cancelEdit" style="display: none;">Hủy sửa</button>
            </div>
        </form>
    </div>

    <!-- Danh sách đơn thuốc -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Lịch hẹn</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Bệnh nhân</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Bác sĩ</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ngày kê đơn</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thuốc</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Chẩn đoán</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Trạng thái</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ghi chú</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao tác</th>
                </tr>
            </thead>
            <tbody id="prescriptionTable">
                <?php if (empty($prescriptions)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-gray-600">Không có đơn thuốc nào</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <tr id="prescription-<?php echo htmlspecialchars($prescription['prescription_id']); ?>">
                            <td class="px-4 py-2 text-gray-700">#<?php echo htmlspecialchars($prescription['prescription_id']); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['appointment_id'] ? 'Lịch hẹn #' . $prescription['appointment_id'] : 'Không có'); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['patient_name'] ?: 'Unknown Patient'); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['doctor_name'] ?: 'Unknown Doctor'); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['created_at'] ? date('d/m/Y H:i', strtotime($prescription['created_at'])) : 'Không có'); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['medications'] ?? 'Không có'); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['diagnosis'] ?? 'Không có'); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['display_status']); ?></td>
                            <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($prescription['admin_note'] ?: 'Không có'); ?></td>
                            <td class="px-4 py-2">
                                <button class="text-blue-600 hover:text-blue-800 mr-2 edit-btn" data-id="<?php echo htmlspecialchars($prescription['prescription_id']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-800 delete-btn" data-id="<?php echo htmlspecialchars($prescription['prescription_id']); ?>" data-name="<?php echo htmlspecialchars($prescription['patient_name'] ?: 'Unknown Patient') . ' - ' . date('d/m/Y H:i', strtotime($prescription['created_at'])); ?>">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('prescriptionForm');
        const cancelEditBtn = document.getElementById('cancelEdit');
        const formTitle = document.getElementById('formTitle');
        const deleteModal = document.getElementById('deleteModal');
        const deleteModalMessage = document.getElementById('deleteModalMessage');
        const confirmDelete = document.getElementById('confirmDelete');
        const cancelDelete = document.getElementById('cancelDelete');
        const medicationContainer = document.getElementById('medicationContainer');
        const addMedicationBtn = document.getElementById('addMedication');
        let deleteId = null;

        // Thêm hàng thuốc mới
        addMedicationBtn.addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.className = 'medication-row grid grid-cols-4 gap-2 mb-2';
            newRow.innerHTML = `
                <select name="medication_ids[]" class="medication-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Chọn thuốc</option>
                    <?php foreach ($medications as $medication): ?>
                        <option value="<?php echo $medication['medication_id']; ?>" data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                            <?php echo htmlspecialchars($medication['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="quantities[]" placeholder="Số lượng" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <input type="text" name="units[]" placeholder="Đơn vị" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <input type="text" name="instructions[]" placeholder="Hướng dẫn sử dụng" class="col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="button" class="remove-medication text-red-600 hover:text-red-800 col-span-1"><i class="fas fa-trash"></i></button>
            `;
            medicationContainer.appendChild(newRow);
        });

        // Xóa hàng thuốc
        medicationContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-medication')) {
                const row = e.target.closest('.medication-row');
                if (medicationContainer.children.length > 1) {
                    row.remove();
                }
            }
        });

        // Tự động điền đơn vị khi chọn thuốc
        medicationContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('medication-select')) {
                const row = e.target.closest('.medication-row');
                const unitInput = row.querySelector('input[name="units[]"]');
                const selectedOption = e.target.options[e.target.selectedIndex];
                unitInput.value = selectedOption.getAttribute('data-unit') || '';
            }
        });

        // Xử lý submit form
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            console.log('Form Data:', Object.fromEntries(formData));
            fetch('prescriptions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => {
                console.log('Response Status:', response.status);
                console.log('Response Headers:', response.headers.get('Content-Type'));
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response Data:', data);
                showToast(data.message || data.error, data.success ? 'success' : 'error');
                if (data.success) {
                    if (formData.get('action') === 'add') {
                        const medicationText = data.medications.map(m => `${m.name} (Số lượng: ${m.quantity} ${m.unit}, Hướng dẫn: ${m.instructions || ''})`).join('; ');
                        const newRow = `
                            <tr id="prescription-${data.prescription_id}">
                                <td class="px-4 py-2 text-gray-700">#${data.prescription_id}</td>
                                <td class="px-4 py-2 text-gray-700">Lịch hẹn #${data.appointment_id}</td>
                                <td class="px-4 py-2 text-gray-700">${data.patient_name || 'Unknown Patient'}</td>
                                <td class="px-4 py-2 text-gray-700">${data.doctor_name || 'Unknown Doctor'}</td>
                                <td class="px-4 py-2 text-gray-700">${new Date(data.created_at).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                                <td class="px-4 py-2 text-gray-700">${medicationText || 'Không có'}</td>
                                <td class="px-4 py-2 text-gray-700">${data.diagnosis || 'Không có'}</td>
                                <td class="px-4 py-2 text-gray-700">${data.status}</td>
                                <td class="px-4 py-2 text-gray-700">${data.admin_note || 'Không có'}</td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2 edit-btn" data-id="${data.prescription_id}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-btn" data-id="${data.prescription_id}" data-name="${data.patient_name || 'Unknown Patient'} - ${new Date(data.created_at).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                        const tableBody = document.getElementById('prescriptionTable');
                        if (tableBody.querySelector('tr td[colspan="10"]')) {
                            tableBody.innerHTML = newRow;
                        } else {
                            tableBody.insertAdjacentHTML('beforeend', newRow);
                        }
                    } else {
                        const row = document.getElementById(`prescription-${data.prescription_id}`);
                        const medicationText = data.medications.map(m => `${m.name} (Số lượng: ${m.quantity} ${m.unit}, Hướng dẫn: ${m.instructions || ''})`).join('; ');
                        row.children[1].textContent = `Lịch hẹn #${data.appointment_id}`;
                        row.children[2].textContent = data.patient_name || 'Unknown Patient';
                        row.children[3].textContent = data.doctor_name || 'Unknown Doctor';
                        row.children[4].textContent = new Date(data.created_at).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                        row.children[5].textContent = medicationText || 'Không có';
                        row.children[6].textContent = data.diagnosis || 'Không có';
                        row.children[7].textContent = data.status;
                        row.children[8].textContent = data.admin_note || 'Không có';
                    }
                    form.reset();
                    document.getElementById('action').value = 'add';
                    document.getElementById('prescription_id').value = '';
                    formTitle.textContent = 'Thêm Đơn Thuốc';
                    cancelEditBtn.style.display = 'none';
                    medicationContainer.innerHTML = `
                        <div class="medication-row grid grid-cols-4 gap-2 mb-2">
                            <select name="medication_ids[]" class="medication-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Chọn thuốc</option>
                                <?php foreach ($medications as $medication): ?>
                                    <option value="<?php echo $medication['medication_id']; ?>" data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                                        <?php echo htmlspecialchars($medication['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="quantities[]" placeholder="Số lượng" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <input type="text" name="units[]" placeholder="Đơn vị" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <input type="text" name="instructions[]" placeholder="Hướng dẫn sử dụng" class="col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" class="remove-medication text-red-600 hover:text-red-800 col-span-1"><i class="fas fa-trash"></i></button>
                        </div>`;
                } else {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showToast(`Lỗi kết nối: ${error.message}`, 'error');
            });
        });

        // Xử lý nút sửa và xóa
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-btn')) {
                const btn = e.target.closest('.edit-btn');
                const row = btn.closest('tr');
                const prescriptionId = btn.getAttribute('data-id');

                console.log('Edit Prescription ID:', prescriptionId);

                // Kiểm tra kết nối mạng trước khi gửi yêu cầu
                if (!navigator.onLine) {
                    showToast('Không có kết nối mạng. Vui lòng kiểm tra mạng và thử lại.', 'error');
                    return;
                }

                // Lấy dữ liệu chi tiết đơn thuốc
                fetch(`prescriptions.php?action=get_details&prescription_id=${prescriptionId}`, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => {
                    console.log('Get Details Response Status:', response.status);
                    console.log('Get Details Response Headers:', response.headers.get('Content-Type'));
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Get Details Response Data:', data);
                    if (data.success) {
                        document.getElementById('action').value = 'edit';
                        document.getElementById('prescription_id').value = data.prescription.prescription_id;
                        document.getElementById('appointment_id').value = data.prescription.appointment_id || '';
                        document.getElementById('patient_id').value = data.prescription.patient_id || '';
                        document.getElementById('doctor_id').value = data.prescription.doctor_id || '';
                        document.getElementById('created_at').value = data.prescription.created_at ? new Date(data.prescription.created_at).toISOString().slice(0, 16) : '';
                        document.getElementById('diagnosis').value = data.prescription.diagnosis || '';
                        document.getElementById('status').value = data.prescription.status || 'Chờ xử lý';
                        document.getElementById('admin_note').value = data.prescription.admin_note === 'Không có' ? '' : data.prescription.admin_note;

                        // Cập nhật danh sách thuốc
                        medicationContainer.innerHTML = '';
                        if (data.medications.length === 0) {
                            const newRow = document.createElement('div');
                            newRow.className = 'medication-row grid grid-cols-4 gap-2 mb-2';
                            newRow.innerHTML = `
                                <select name="medication_ids[]" class="medication-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Chọn thuốc</option>
                                    <?php foreach ($medications as $m): ?>
                                        <option value="<?php echo $m['medication_id']; ?>" data-unit="<?php echo htmlspecialchars($m['unit']); ?>">
                                            <?php echo htmlspecialchars($m['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="quantities[]" placeholder="Số lượng" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <input type="text" name="units[]" placeholder="Đơn vị" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                <input type="text" name="instructions[]" placeholder="Hướng dẫn sử dụng" class="col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="button" class="remove-medication text-red-600 hover:text-red-800 col-span-1"><i class="fas fa-trash"></i></button>
                            `;
                            medicationContainer.appendChild(newRow);
                        } else {
                            data.medications.forEach(med => {
                                const newRow = document.createElement('div');
                                newRow.className = 'medication-row grid grid-cols-4 gap-2 mb-2';
                                newRow.innerHTML = `
                                    <select name="medication_ids[]" class="medication-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Chọn thuốc</option>
                                        <?php foreach ($medications as $m): ?>
                                            <option value="<?php echo $m['medication_id']; ?>" data-unit="<?php echo htmlspecialchars($m['unit']); ?>" ${med.medication_id == <?php echo $m['medication_id']; ?> ? 'selected' : ''}>
                                                <?php echo htmlspecialchars($m['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="quantities[]" value="${med.quantity || 1}" placeholder="Số lượng" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <input type="text" name="units[]" value="${med.unit || ''}" placeholder="Đơn vị" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <input type="text" name="instructions[]" value="${med.instructions || ''}" placeholder="Hướng dẫn sử dụng" class="col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button" class="remove-medication text-red-600 hover:text-red-800 col-span-1"><i class="fas fa-trash"></i></button>
                                `;
                                medicationContainer.appendChild(newRow);
                            });
                        }

                        formTitle.textContent = 'Sửa Đơn Thuốc';
                        cancelEditBtn.style.display = 'inline-block';
                    } else {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            showToast(data.error || 'Lỗi khi lấy chi tiết đơn thuốc', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    if (error.message.includes('Unexpected token')) {
                        showToast('Lỗi phản hồi từ server: Dữ liệu không hợp lệ. Vui lòng thử lại.', 'error');
                    } else if (!navigator.onLine) {
                        showToast('Không có kết nối mạng. Vui lòng kiểm tra mạng và thử lại.', 'error');
                    } else {
                        showToast(`Lỗi kết nối: ${error.message}`, 'error');
                    }
                });
            }

            if (e.target.closest('.delete-btn')) {
                const btn = e.target.closest('.delete-btn');
                deleteId = btn.getAttribute('data-id');
                deleteModalMessage.textContent = `Bạn có chắc chắn muốn xóa đơn thuốc của "${btn.getAttribute('data-name')}"?`;
                deleteModal.classList.remove('hidden');
            }
        });

        // Xử lý xác nhận xóa
        confirmDelete.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('prescription_id', deleteId);
            console.log('Delete Prescription ID:', deleteId);
            fetch('prescriptions.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => {
                console.log('Delete Response Status:', response.status);
                console.log('Delete Response Headers:', response.headers.get('Content-Type'));
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Delete Response Data:', data);
                showToast(data.message || data.error, data.success ? 'success' : 'error');
                if (data.success) {
                    document.getElementById(`prescription-${deleteId}`)?.remove();
                    if (!document.getElementById('prescriptionTable').children.length) {
                        document.getElementById('prescriptionTable').innerHTML = '<tr><td colspan="10" class="text-center py-4 text-gray-600">Không có đơn thuốc nào</td></tr>';
                    }
                }
                deleteModal.classList.add('hidden');
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showToast(`Lỗi kết nối: ${error.message}`, 'error');
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
            document.getElementById('prescription_id').value = '';
            formTitle.textContent = 'Thêm Đơn Thuốc';
            cancelEditBtn.style.display = 'none';
            medicationContainer.innerHTML = `
                <div class="medication-row grid grid-cols-4 gap-2 mb-2">
                    <select name="medication_ids[]" class="medication-select col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Chọn thuốc</option>
                        <?php foreach ($medications as $medication): ?>
                            <option value="<?php echo $medication['medication_id']; ?>" data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                                <?php echo htmlspecialchars($medication['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantities[]" placeholder="Số lượng" min="1" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <input type="text" name="units[]" placeholder="Đơn vị" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <input type="text" name="instructions[]" placeholder="Hướng dẫn sử dụng" class="col-span-2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="button" class="remove-medication text-red-600 hover:text-red-800 col-span-1"><i class="fas fa-trash"></i></button>
                </div>`;
        });

        // Hàm hiển thị toast
        function showToast(message, type) {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'} flex items-center space-x-2`;
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
    });
</script>