<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}require_once '../db.php';

// Kiểm tra đăng nhập bác sĩ
if (!isset($_SESSION['doctor_id'])) {
    error_log("Session doctor_id không tồn tại, chuyển hướng đến login");
    header('Location: ../login.php');
    exit;
}

// Kết nối cơ sở dữ liệu
try {
    $conn = new PDO("mysql:host=localhost;dbname=benhviensql", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    error_log("Kết nối database thành công");
} catch(PDOException $e) {
    error_log("Lỗi kết nối database: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage()]);
    exit;
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
    error_log("Lấy danh sách lịch khám thành công, doctor_id: $doctor_id");
} catch(PDOException $e) {
    error_log("Lỗi khi lấy danh sách lịch khám: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách lịch khám: ' . $e->getMessage()]);
    exit;
}

// Lấy danh sách đơn thuốc
try {
    $stmt = $conn->prepare("
        SELECT pr.prescription_id, pr.appointment_id, pr.diagnosis, pr.status, p.full_name, a.appointment_date,
               GROUP_CONCAT(CONCAT(m.name, ' (', pd.quantity, ' ', pd.unit, ')') SEPARATOR ', ') AS medications
        FROM prescriptions pr
        JOIN appointments a ON pr.appointment_id = a.appointment_id
        JOIN patients p ON pr.patient_id = p.patient_id
        LEFT JOIN prescription_details pd ON pr.prescription_id = pd.prescription_id
        LEFT JOIN medications m ON pd.medication_id = m.medication_id
        WHERE pr.doctor_id = :doctor_id
        GROUP BY pr.prescription_id
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([':doctor_id' => $doctor_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Lấy danh sách đơn thuốc thành công");
} catch(PDOException $e) {
    error_log("Lỗi khi lấy danh sách đơn thuốc: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách đơn thuốc: ' . $e->getMessage()]);
    exit;
}

// Lấy danh sách thuốc
try {
    $stmt = $conn->prepare("SELECT medication_id, name, unit FROM medications ORDER BY name");
    $stmt->execute();
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Lấy danh sách thuốc thành công, số lượng: " . count($medications));
} catch(PDOException $e) {
    error_log("Lỗi khi lấy danh sách thuốc: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách thuốc: ' . $e->getMessage()]);
    exit;
}

// Xử lý AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_prescription') {
        error_log("Nhận yêu cầu AJAX create_prescription: " . print_r($_POST, true));
        
        $appointment_id = $_POST['appointment_id'] ?? '';
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $medications_data = isset($_POST['medications']) ? json_decode($_POST['medications'], true) : [];

        // Kiểm tra dữ liệu đầu vào
        if (empty($appointment_id) || empty($diagnosis) || empty($medications_data)) {
            error_log("Dữ liệu đầu vào không hợp lệ: appointment_id=$appointment_id, diagnosis=$diagnosis, medications=" . print_r($medications_data, true));
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ chẩn đoán và ít nhất một loại thuốc.']);
            exit;
        }

        // Kiểm tra quyền bác sĩ
        try {
            $stmt = $conn->prepare("SELECT patient_id, doctor_id FROM appointments WHERE appointment_id = :appointment_id");
            $stmt->execute([':appointment_id' => $appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$appointment || $appointment['doctor_id'] != $doctor_id) {
                error_log("Quyền bác sĩ không hợp lệ: appointment_id=$appointment_id, doctor_id=$doctor_id");
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền kê đơn cho lịch khám này.']);
                exit;
            }

            // Kiểm tra medications_data
            foreach ($medications_data as $med) {
                if (empty($med['medication_id']) || empty($med['quantity']) || empty($med['unit']) || $med['quantity'] <= 0) {
                    error_log("Dữ liệu thuốc không hợp lệ: " . print_r($med, true));
                    echo json_encode(['success' => false, 'message' => 'Dữ liệu thuốc không hợp lệ.']);
                    exit;
                }
                $stmt = $conn->prepare("SELECT medication_id FROM medications WHERE medication_id = :medication_id");
                $stmt->execute([':medication_id' => $med['medication_id']]);
                if (!$stmt->fetch()) {
                    error_log("Thuốc không hợp lệ: medication_id=" . $med['medication_id']);
                    echo json_encode(['success' => false, 'message' => 'Thuốc không hợp lệ: ' . $med['medication_id']]);
                    exit;
                }
            }

            // Bắt đầu transaction
            $conn->beginTransaction();
            try {
                // Lưu đơn thuốc
                $stmt = $conn->prepare("
                    INSERT INTO prescriptions (appointment_id, doctor_id, patient_id, diagnosis, status)
                    VALUES (:appointment_id, :doctor_id, :patient_id, :diagnosis, 'Pending')
                ");
                $stmt->execute([
                    ':appointment_id' => $appointment_id,
                    ':doctor_id' => $doctor_id,
                    ':patient_id' => $appointment['patient_id'],
                    ':diagnosis' => $diagnosis
                ]);
                $prescription_id = $conn->lastInsertId();

                // Lưu chi tiết thuốc
                $stmt = $conn->prepare("
                    INSERT INTO prescription_details (prescription_id, medication_id, quantity, unit, instructions)
                    VALUES (:prescription_id, :medication_id, :quantity, :unit, :instructions)
                ");
                foreach ($medications_data as $med) {
                    $stmt->execute([
                        ':prescription_id' => $prescription_id,
                        ':medication_id' => $med['medication_id'],
                        ':quantity' => $med['quantity'],
                        ':unit' => $med['unit'],
                        ':instructions' => $med['instructions'] ?? null
                    ]);
                }

                // Lấy thông tin đơn thuốc vừa tạo
                $stmt = $conn->prepare("
                    SELECT pr.prescription_id, pr.diagnosis, pr.status, p.full_name, a.appointment_date,
                           GROUP_CONCAT(CONCAT(m.name, ' (', pd.quantity, ' ', pd.unit, ')') SEPARATOR ', ') AS medications
                    FROM prescriptions pr
                    JOIN appointments a ON pr.appointment_id = a.appointment_id
                    JOIN patients p ON pr.patient_id = p.patient_id
                    LEFT JOIN prescription_details pd ON pr.prescription_id = pd.prescription_id
                    LEFT JOIN medications m ON pd.medication_id = m.medication_id
                    WHERE pr.prescription_id = :prescription_id
                    GROUP BY pr.prescription_id
                ");
                $stmt->execute([':prescription_id' => $prescription_id]);
                $new_prescription = $stmt->fetch(PDO::FETCH_ASSOC);

                $conn->commit();
                error_log("Kê đơn thành công: prescription_id=$prescription_id");
                echo json_encode([
                    'success' => true,
                    'message' => 'Kê đơn thuốc thành công!',
                    'prescription' => $new_prescription
                ]);
                exit;
            } catch(PDOException $e) {
                $conn->rollBack();
                error_log("Lỗi khi lưu đơn thuốc: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Lỗi khi kê đơn thuốc: ' . $e->getMessage()]);
                exit;
            }
        } catch(PDOException $e) {
            error_log("Lỗi khi kiểm tra quyền: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi kiểm tra quyền: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'update_prescription') {
        error_log("Nhận yêu cầu AJAX update_prescription: " . print_r($_POST, true));
        
        $prescription_id = $_POST['prescription_id'] ?? '';
        $appointment_id = $_POST['appointment_id'] ?? '';
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $medications_data = isset($_POST['medications']) ? json_decode($_POST['medications'], true) : [];

        // Kiểm tra dữ liệu đầu vào
        if (empty($prescription_id) || empty($appointment_id) || empty($diagnosis) || empty($medications_data)) {
            error_log("Dữ liệu đầu vào không hợp lệ: prescription_id=$prescription_id, appointment_id=$appointment_id, diagnosis=$diagnosis, medications=" . print_r($medications_data, true));
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ chẩn đoán và ít nhất một loại thuốc.']);
            exit;
        }

        // Kiểm tra quyền bác sĩ và đơn thuốc
        try {
            $stmt = $conn->prepare("
                SELECT pr.prescription_id, pr.doctor_id, pr.appointment_id, a.patient_id
                FROM prescriptions pr
                JOIN appointments a ON pr.appointment_id = a.appointment_id
                WHERE pr.prescription_id = :prescription_id
            ");
            $stmt->execute([':prescription_id' => $prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prescription || $prescription['doctor_id'] != $doctor_id || $prescription['appointment_id'] != $appointment_id) {
                error_log("Quyền bác sĩ hoặc đơn thuốc không hợp lệ: prescription_id=$prescription_id, doctor_id=$doctor_id");
                echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa đơn thuốc này.']);
                exit;
            }

            // Kiểm tra medications_data
            foreach ($medications_data as $med) {
                if (empty($med['medication_id']) || empty($med['quantity']) || empty($med['unit']) || $med['quantity'] <= 0) {
                    error_log("Dữ liệu thuốc không hợp lệ: " . print_r($med, true));
                    echo json_encode(['success' => false, 'message' => 'Dữ liệu thuốc không hợp lệ.']);
                    exit;
                }
                $stmt = $conn->prepare("SELECT medication_id FROM medications WHERE medication_id = :medication_id");
                $stmt->execute([':medication_id' => $med['medication_id']]);
                if (!$stmt->fetch()) {
                    error_log("Thuốc không hợp lệ: medication_id=" . $med['medication_id']);
                    echo json_encode(['success' => false, 'message' => 'Thuốc không hợp lệ: ' . $med['medication_id']]);
                    exit;
                }
            }

            // Bắt đầu transaction
            $conn->beginTransaction();
            try {
                // Cập nhật chẩn đoán
                $stmt = $conn->prepare("
                    UPDATE prescriptions 
                    SET diagnosis = :diagnosis, status = 'Pending'
                    WHERE prescription_id = :prescription_id
                ");
                $stmt->execute([
                    ':prescription_id' => $prescription_id,
                    ':diagnosis' => $diagnosis
                ]);

                // Xóa chi tiết thuốc cũ
                $stmt = $conn->prepare("DELETE FROM prescription_details WHERE prescription_id = :prescription_id");
                $stmt->execute([':prescription_id' => $prescription_id]);

                // Lưu chi tiết thuốc mới
                $stmt = $conn->prepare("
                    INSERT INTO prescription_details (prescription_id, medication_id, quantity, unit, instructions)
                    VALUES (:prescription_id, :medication_id, :quantity, :unit, :instructions)
                ");
                foreach ($medications_data as $med) {
                    $stmt->execute([
                        ':prescription_id' => $prescription_id,
                        ':medication_id' => $med['medication_id'],
                        ':quantity' => $med['quantity'],
                        ':unit' => $med['unit'],
                        ':instructions' => $med['instructions'] ?? null
                    ]);
                }

                // Lấy thông tin đơn thuốc vừa cập nhật
                $stmt = $conn->prepare("
                    SELECT pr.prescription_id, pr.diagnosis, pr.status, p.full_name, a.appointment_date,
                           GROUP_CONCAT(CONCAT(m.name, ' (', pd.quantity, ' ', pd.unit, ')') SEPARATOR ', ') AS medications
                    FROM prescriptions pr
                    JOIN appointments a ON pr.appointment_id = a.appointment_id
                    JOIN patients p ON pr.patient_id = p.patient_id
                    LEFT JOIN prescription_details pd ON pr.prescription_id = pd.prescription_id
                    LEFT JOIN medications m ON pd.medication_id = m.medication_id
                    WHERE pr.prescription_id = :prescription_id
                    GROUP BY pr.prescription_id
                ");
                $stmt->execute([':prescription_id' => $prescription_id]);
                $updated_prescription = $stmt->fetch(PDO::FETCH_ASSOC);

                $conn->commit();
                error_log("Cập nhật đơn thuốc thành công: prescription_id=$prescription_id");
                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật đơn thuốc thành công!',
                    'prescription' => $updated_prescription
                ]);
                exit;
            } catch(PDOException $e) {
                $conn->rollBack();
                error_log("Lỗi khi cập nhật đơn thuốc: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật đơn thuốc: ' . $e->getMessage()]);
                exit;
            }
        } catch(PDOException $e) {
            error_log("Lỗi khi kiểm tra quyền: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi kiểm tra quyền: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'get_prescription') {
        $prescription_id = $_POST['prescription_id'] ?? '';
        if (empty($prescription_id)) {
            error_log("Thiếu prescription_id");
            echo json_encode(['success' => false, 'message' => 'Thiếu ID đơn thuốc.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("
                SELECT pr.prescription_id, pr.appointment_id, pr.diagnosis
                FROM prescriptions pr
                WHERE pr.prescription_id = :prescription_id AND pr.doctor_id = :doctor_id
            ");
            $stmt->execute([':prescription_id' => $prescription_id, ':doctor_id' => $doctor_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prescription) {
                error_log("Đơn thuốc không tồn tại hoặc không thuộc bác sĩ: prescription_id=$prescription_id, doctor_id=$doctor_id");
                echo json_encode(['success' => false, 'message' => 'Đơn thuốc không tồn tại hoặc bạn không có quyền truy cập.']);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT pd.medication_id, pd.quantity, pd.unit, pd.instructions, m.name
                FROM prescription_details pd
                JOIN medications m ON pd.medication_id = m.medication_id
                WHERE pd.prescription_id = :prescription_id
            ");
            $stmt->execute([':prescription_id' => $prescription_id]);
            $medication_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Lấy thông tin đơn thuốc thành công: prescription_id=$prescription_id");
            echo json_encode([
                'success' => true,
                'prescription' => [
                    'prescription_id' => $prescription['prescription_id'],
                    'appointment_id' => $prescription['appointment_id'],
                    'diagnosis' => $prescription['diagnosis'],
                    'medications' => $medication_details
                ]
            ]);
            exit;
        } catch(PDOException $e) {
            error_log("Lỗi khi lấy thông tin đơn thuốc: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy thông tin đơn thuốc: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'get_prescriptions') {
        try {
            $stmt = $conn->prepare("
                SELECT pr.prescription_id, pr.appointment_id, pr.diagnosis, pr.status, p.full_name, a.appointment_date,
                       GROUP_CONCAT(CONCAT(m.name, ' (', pd.quantity, ' ', pd.unit, ')') SEPARATOR ', ') AS medications
                FROM prescriptions pr
                JOIN appointments a ON pr.appointment_id = a.appointment_id
                JOIN patients p ON pr.patient_id = p.patient_id
                LEFT JOIN prescription_details pd ON pr.prescription_id = pd.prescription_id
                LEFT JOIN medications m ON pd.medication_id = m.medication_id
                WHERE pr.doctor_id = :doctor_id
                GROUP BY pr.prescription_id
                ORDER BY pr.created_at DESC
            ");
            $stmt->execute([':doctor_id' => $doctor_id]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Lấy danh sách đơn thuốc AJAX thành công");
            echo json_encode(['success' => true, 'prescriptions' => $prescriptions]);
            exit;
        } catch(PDOException $e) {
            error_log("Lỗi khi lấy danh sách đơn thuốc AJAX: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách đơn thuốc: ' . $e->getMessage()]);
            exit;
        }
    } else {
        error_log("Yêu cầu AJAX không hợp lệ: action=$action");
        echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kê Đơn Thuốc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .medication-row { margin-bottom: 1rem; }
        .medication-row .form-control, .medication-row .form-select { margin-bottom: 0.5rem; }
        .remove-medication { cursor: pointer; color: red; font-size: 1.2rem; line-height: 2.5rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-dark mb-4">Kê Đơn Thuốc</h3>

            <!-- Toast Container -->
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-dark alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Bảng lịch khám -->
            <h5 class="text-dark mb-3">Danh Sách Lịch Khám</h5>
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
                                        <button class="btn btn-dark btn-sm prescribe-btn" data-appointment-id="<?php echo htmlspecialchars($appointment['appointment_id']); ?>" data-bs-toggle="modal" data-bs-target="#prescriptionModal">Kê đơn</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bảng đơn thuốc -->
            <h5 class="text-dark mb-3 mt-4">Danh Sách Đơn Thuốc</h5>
            <div class="table-responsive">
                <table class="table table-striped table-bordered bg-white">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Bệnh nhân</th>
                            <th scope="col">Ngày khám</th>
                            <th scope="col">Chẩn đoán</th>
                            <th scope="col">Thuốc</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="prescriptionTableBody">
                        <?php if (empty($prescriptions)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Không có đơn thuốc nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $prescription): ?>
                                <tr data-prescription-id="<?php echo htmlspecialchars($prescription['prescription_id']); ?>">
                                    <td><?php echo htmlspecialchars($prescription['prescription_id']); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($prescription['appointment_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['diagnosis']); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['medications'] ?? 'Không có'); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch ($prescription['status']) {
                                                case 'Pending': echo 'bg-warning'; break;
                                                case 'Approved': echo 'bg-success'; break;
                                                case 'Cancelled': echo 'bg-danger'; break;
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($prescription['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-dark btn-sm edit-prescription-btn" data-prescription-id="<?php echo htmlspecialchars($prescription['prescription_id']); ?>" data-appointment-id="<?php echo htmlspecialchars($prescription['appointment_id']); ?>">Sửa</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal kê đơn thuốc -->
    <div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="prescriptionModalLabel">Kê Đơn Thuốc</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="prescriptionForm">
                        <input type="hidden" id="appointment_id" name="appointment_id">
                        <input type="hidden" id="prescription_id" name="prescription_id">
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label text-dark">Chẩn đoán <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
                        </div>
                        <div id="medicationsContainer"></div>
                        <button type="button" class="btn btn-outline-dark mt-2" id="addMedication">Thêm thuốc</button>
                        <button type="submit" class="btn btn-dark mt-3" id="submitButton">Lưu đơn thuốc</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastContainer = document.querySelector('.toast-container');
    const prescriptionForm = document.getElementById('prescriptionForm');
    const prescriptionModalEl = document.getElementById('prescriptionModal');
    const prescriptionModal = new bootstrap.Modal(prescriptionModalEl, { backdrop: 'static', keyboard: false });
    const prescribeButtons = document.querySelectorAll('.prescribe-btn');
    const prescriptionTableBody = document.getElementById('prescriptionTableBody');
    const medicationsContainer = document.getElementById('medicationsContainer');
    const addMedicationButton = document.getElementById('addMedication');
    const submitButton = document.getElementById('submitButton');
    let medicationIndex = 0;

    // Danh sách thuốc từ PHP
    const medications = <?php echo json_encode($medications); ?>;
    console.log('Medications:', medications);

    // Hàm xóa backdrop
    function removeModalBackdrop() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    // Hàm reset modal
    function resetModal() {
        document.getElementById('appointment_id').value = '';
        document.getElementById('prescription_id').value = '';
        document.getElementById('diagnosis').value = '';
        medicationsContainer.innerHTML = '';
        medicationIndex = 0;
        addMedicationRow(medicationIndex);
        submitButton.textContent = 'Lưu đơn thuốc';
        document.getElementById('prescriptionModalLabel').textContent = 'Kê Đơn Thuốc';
    }

    // Hàm thêm dòng thuốc mới
    function addMedicationRow(index, medication = null) {
        const newRow = document.createElement('div');
        newRow.className = 'medication-row';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label text-dark">Thuốc <span class="text-danger">*</span></label>
                    <select class="form-control medication-select" name="medications[${index}][medication_id]" required>
                        <option value="">Chọn thuốc</option>
                        ${medications.map(med => `
                            <option value="${med.medication_id}" data-unit="${med.unit}" ${medication && medication.medication_id == med.medication_id ? 'selected' : ''}>
                                ${med.name}
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-dark">Số lượng <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="medications[${index}][quantity]" min="1" value="${medication ? medication.quantity : ''}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-dark">Đơn vị <span class="text-danger">*</span></label>
                    <input type="text" class="form-control unit-input" name="medications[${index}][unit]" value="${medication ? medication.unit : ''}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-dark">Hướng dẫn</label>
                    <input type="text" class="form-control" name="medications[${index}][instructions]" value="${medication ? medication.instructions || '' : ''}">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <span class="remove-medication">×</span>
                </div>
            </div>
        `;
        medicationsContainer.appendChild(newRow);
        updateMedicationSelects();
    }

    // Hàm tải lại danh sách đơn thuốc
    function refreshPrescriptionTable() {
        fetch('/webkha/doctor/prescription.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'get_prescriptions'
            })
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                prescriptionTableBody.innerHTML = '';
                if (data.prescriptions.length === 0) {
                    prescriptionTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Không có đơn thuốc nào.</td></tr>';
                } else {
                    data.prescriptions.forEach(prescription => {
                        const row = document.createElement('tr');
                        row.setAttribute('data-prescription-id', prescription.prescription_id);
                        row.innerHTML = `
                            <td>${prescription.prescription_id}</td>
                            <td>${prescription.full_name}</td>
                            <td>${new Date(prescription.appointment_date).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                            <td>${prescription.diagnosis}</td>
                            <td>${prescription.medications || 'Không có'}</td>
                            <td><span class="badge ${prescription.status === 'Pending' ? 'bg-warning' : prescription.status === 'Approved' ? 'bg-success' : 'bg-danger'}">${prescription.status}</span></td>
                            <td><button class="btn btn-outline-dark btn-sm edit-prescription-btn" data-prescription-id="${prescription.prescription_id}" data-appointment-id="${prescription.appointment_id}">Sửa</button></td>
                        `;
                        prescriptionTableBody.appendChild(row);
                    });
                }
                console.log('Danh sách đơn thuốc đã được làm mới');
            } else {
                showToast(data.message, false);
            }
        })
        .catch(error => {
            console.error('Lỗi khi làm mới danh sách đơn thuốc:', error);
            showToast(`Lỗi khi làm mới danh sách đơn thuốc: ${error.message}`, false);
        });
    }

    // Xử lý nút Kê đơn
    prescribeButtons.forEach(button => {
        button.addEventListener('click', function() {
            console.log('Mở modal kê đơn, appointment_id:', this.dataset.appointmentId);
            resetModal();
            document.getElementById('appointment_id').value = this.dataset.appointmentId;
            prescriptionModal.show();
        });
    });

    // Xử lý nút Sửa đơn thuốc (Event Delegation)
    prescriptionTableBody.addEventListener('click', function(e) {
        if (e.target.classList.contains('edit-prescription-btn')) {
            const prescriptionId = e.target.dataset.prescriptionId;
            const appointmentId = e.target.dataset.appointmentId;
            console.log('Mở modal sửa đơn thuốc, prescription_id:', prescriptionId, 'appointment_id:', appointmentId);

            fetch('/webkha/doctor/prescription.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'get_prescription',
                    prescription_id: prescriptionId
                })
            })
            .then(response => {
                console.log('Phản hồi server:', response.status, response.statusText);
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                console.log('Dữ liệu JSON:', data);
                if (data.success) {
                    resetModal();
                    const { prescription_id, appointment_id, diagnosis, medications } = data.prescription;
                    document.getElementById('prescription_id').value = prescription_id;
                    document.getElementById('appointment_id').value = appointment_id;
                    document.getElementById('diagnosis').value = diagnosis;
                    medicationsContainer.innerHTML = '';
                    medicationIndex = 0;
                    medications.forEach((med, index) => {
                        addMedicationRow(index, med);
                        medicationIndex = index + 1;
                    });
                    submitButton.textContent = 'Cập nhật đơn thuốc';
                    document.getElementById('prescriptionModalLabel').textContent = 'Sửa Đơn Thuốc';
                    prescriptionModal.show();
                } else {
                    showToast(data.message, false);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showToast(`Lỗi kết nối: ${error.message}. Vui lòng kiểm tra mạng hoặc server.`, false);
            });
        }
    });

    // Thêm thuốc mới
    addMedicationButton.addEventListener('click', function() {
        console.log('Thêm thuốc mới, index:', medicationIndex + 1);
        medicationIndex++;
        addMedicationRow(medicationIndex);
    });

    // Xóa thuốc
    medicationsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-medication')) {
            console.log('Xóa dòng thuốc');
            if (medicationsContainer.querySelectorAll('.medication-row').length > 1) {
                e.target.closest('.medication-row').remove();
            } else {
                showToast('Không thể xóa dòng thuốc cuối cùng.', false);
            }
        }
    });

    // Cập nhật đơn vị khi chọn thuốc
    function updateMedicationSelects() {
        const selects = document.querySelectorAll('.medication-select');
        selects.forEach(select => {
            select.removeEventListener('change', updateUnit);
            select.addEventListener('change', updateUnit);
        });
    }

    function updateUnit(e) {
        const unitInput = e.target.closest('.row').querySelector('.unit-input');
        const selectedOption = e.target.options[e.target.selectedIndex];
        console.log('Cập nhật đơn vị:', selectedOption.dataset.unit);
        unitInput.value = selectedOption.dataset.unit || '';
    }

    // Hàm hiển thị toast
    function showToast(message, isSuccess) {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        setTimeout(() => toast.remove(), 3000);
    }

    // Xử lý form kê đơn
    prescriptionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const medicationsData = [...this.querySelectorAll('.medication-row')].map(row => ({
            medication_id: row.querySelector('.medication-select').value,
            quantity: row.querySelector('input[name$="[quantity]"]').value,
            unit: row.querySelector('.unit-input').value,
            instructions: row.querySelector('input[name$="[instructions]"]').value
        }));
        console.log('Dữ liệu gửi:', {
            prescription_id: this.querySelector('#prescription_id').value,
            appointment_id: this.querySelector('#appointment_id').value,
            diagnosis: this.querySelector('#diagnosis').value,
            medications: medicationsData
        });

        const formData = new FormData(this);
        formData.append('action', this.querySelector('#prescription_id').value ? 'update_prescription' : 'create_prescription');
        formData.append('medications', JSON.stringify(medicationsData));

        fetch('/webkha/doctor/prescription.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            console.log('Phản hồi server:', response.status, response.statusText);
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log('Dữ liệu JSON:', data);
            showToast(data.message, data.success);
            if (data.success) {
                prescriptionModal.hide();
                removeModalBackdrop();
                refreshPrescriptionTable();
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showToast(`Lỗi kết nối: ${error.message}. Vui lòng kiểm tra mạng hoặc server.`, false);
            prescriptionModal.hide();
            removeModalBackdrop();
        });
    });

    // Sự kiện khi modal đóng
    prescriptionModalEl.addEventListener('hidden.bs.modal', function () {
        console.log('Modal đóng, xóa backdrop');
        removeModalBackdrop();
        resetModal();
    });

    // Khởi tạo dòng thuốc đầu tiên khi mở modal
    addMedicationRow(0);

    // Tải danh sách đơn thuốc ban đầu
    refreshPrescriptionTable();
});
</script>
</body>
</html>