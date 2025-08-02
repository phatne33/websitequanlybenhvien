<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Xử lý AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        $response = ['success' => false, 'message' => ''];

        if ($_GET['action'] === 'add') {
            $full_name = trim($_POST['full_name'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?: null;
            $gender = $_POST['gender'] ?: null;
            $address = $_POST['address'] ?: null;
            $email = $_POST['email'] ?: null;
            $phone = trim($_POST['phone'] ?? '');

            // Kiểm tra dữ liệu bắt buộc
            if (!$full_name || !$phone) {
                $response['message'] = 'Họ tên và số điện thoại là bắt buộc!';
                echo json_encode($response);
                exit;
            }

            // Kiểm tra trùng email hoặc phone
            $stmt = $conn->prepare("SELECT COUNT(*) FROM patients WHERE ((email = :email AND email IS NOT NULL) OR (phone = :phone AND phone IS NOT NULL)) AND is_deleted = 0");
            $stmt->execute(['email' => $email, 'phone' => $phone]);
            if ($stmt->fetchColumn() > 0) {
                $response['message'] = 'Email hoặc số điện thoại đã tồn tại!';
                echo json_encode($response);
                exit;
            }

            // Thêm bệnh nhân mới
            $stmt = $conn->prepare("
                INSERT INTO patients (full_name, date_of_birth, gender, address, email, phone, source, created_at, is_deleted)
                VALUES (:full_name, :date_of_birth, :gender, :address, :email, :phone, 'admin', NOW(), 0)
            ");
            $stmt->execute([
                'full_name' => $full_name,
                'date_of_birth' => $date_of_birth,
                'gender' => $gender,
                'address' => $address,
                'email' => $email,
                'phone' => $phone
            ]);

            $patient_id = $conn->lastInsertId();
            error_log("New patient added by admin: ID=$patient_id, full_name=$full_name");
            $response['success'] = true;
            $response['message'] = 'Thêm bệnh nhân thành công!';
            $response['patient'] = [
                'patient_id' => $patient_id,
                'full_name' => $full_name,
                'date_of_birth' => $date_of_birth,
                'gender' => $gender,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'source' => 'admin'
            ];
        } elseif ($_GET['action'] === 'edit') {
            $patient_id = $_POST['patient_id'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?: null;
            $gender = $_POST['gender'] ?: null;
            $address = $_POST['address'] ?: null;
            $email = $_POST['email'] ?: null;
            $phone = trim($_POST['phone'] ?? '');

            // Kiểm tra dữ liệu bắt buộc
            if (!$patient_id || !$full_name || !$phone) {
                $response['message'] = 'ID, họ tên và số điện thoại là bắt buộc!';
                echo json_encode($response);
                exit;
            }

            // Kiểm tra trùng email hoặc phone
            $stmt = $conn->prepare("SELECT COUNT(*) FROM patients WHERE ((email = :email AND email IS NOT NULL) OR (phone = :phone AND phone IS NOT NULL)) AND patient_id != :patient_id AND is_deleted = 0");
            $stmt->execute(['email' => $email, 'phone' => $phone, 'patient_id' => $patient_id]);
            if ($stmt->fetchColumn() > 0) {
                $response['message'] = 'Email hoặc số điện thoại đã tồn tại!';
                echo json_encode($response);
                exit;
            }

            // Cập nhật thông tin bệnh nhân
            $stmt = $conn->prepare("
                UPDATE patients SET full_name = :full_name, date_of_birth = :date_of_birth, gender = :gender, 
                address = :address, email = :email, phone = :phone
                WHERE patient_id = :patient_id AND is_deleted = 0
            ");
            $stmt->execute([
                'patient_id' => $patient_id,
                'full_name' => $full_name,
                'date_of_birth' => $date_of_birth,
                'gender' => $gender,
                'address' => $address,
                'email' => $email,
                'phone' => $phone
            ]);

            error_log("Patient updated: ID=$patient_id, full_name=$full_name");
            $response['success'] = true;
            $response['message'] = 'Cập nhật bệnh nhân thành công!';
            $response['patient'] = [
                'patient_id' => $patient_id,
                'full_name' => $full_name,
                'date_of_birth' => $date_of_birth,
                'gender' => $gender,
                'address' => $address,
                'email' => $email,
                'phone' => $phone
            ];
        } elseif ($_GET['action'] === 'delete') {
            $patient_id = $_POST['patient_id'] ?? '';
            if (!$patient_id) {
                $response['message'] = 'ID bệnh nhân không hợp lệ!';
                echo json_encode($response);
                exit;
            }

            // Lấy tên bệnh nhân để cập nhật patient_name
            $stmt = $conn->prepare("SELECT full_name FROM patients WHERE patient_id = :patient_id AND is_deleted = 0");
            $stmt->execute(['patient_id' => $patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                $response['message'] = 'Bệnh nhân không tồn tại hoặc đã bị xóa!';
                echo json_encode($response);
                exit;
            }

            // Cập nhật patient_name trong appointments và prescriptions
            $stmt = $conn->prepare("UPDATE appointments SET patient_name = :full_name WHERE patient_id = :patient_id AND patient_name IS NULL");
            $stmt->execute(['full_name' => $patient['full_name'], 'patient_id' => $patient_id]);

            $stmt = $conn->prepare("UPDATE prescriptions SET patient_name = :full_name WHERE patient_id = :patient_id AND patient_name IS NULL");
            $stmt->execute(['full_name' => $patient['full_name'], 'patient_id' => $patient_id]);

            // Đánh dấu bệnh nhân là đã xóa
            $stmt = $conn->prepare("UPDATE patients SET is_deleted = 1 WHERE patient_id = :patient_id");
            $stmt->execute(['patient_id' => $patient_id]);

            error_log("Patient marked as deleted: ID=$patient_id, full_name={$patient['full_name']}");
            $response['success'] = true;
            $response['message'] = 'Đã đánh dấu bệnh nhân là xóa. Lịch khám và đơn thuốc được giữ lại.';
        } elseif ($_GET['action'] === 'book') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = $_POST['email'] ?: null;
            $gender = $_POST['gender'] ?: null;
            $department_id = $_POST['department_id'] ?? '';
            $appointment_date = $_POST['appointment_date'] ?? '';
            $symptoms = $_POST['symptoms'] ?: null;

            // Debug: Ghi log dữ liệu nhận được
            error_log("Booking data: " . print_r($_POST, true));

            // Kiểm tra dữ liệu bắt buộc
            if (!$full_name || !$phone || !$department_id || !$appointment_date) {
                $response['message'] = 'Vui lòng điền đầy đủ thông tin bắt buộc (họ tên, số điện thoại, khoa khám, ngày giờ khám)!';
                error_log("Missing required fields: full_name=$full_name, phone=$phone, department_id=$department_id, appointment_date=$appointment_date");
                echo json_encode($response);
                exit;
            }

            // Kiểm tra định dạng appointment_date
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $appointment_date)) {
                $response['message'] = 'Định dạng ngày giờ khám không hợp lệ!';
                error_log("Invalid appointment_date format: $appointment_date");
                echo json_encode($response);
                exit;
            }

            // Kiểm tra department_id hợp lệ
            $stmt = $conn->prepare("SELECT COUNT(*) FROM departments WHERE department_id = :department_id");
            $stmt->execute(['department_id' => $department_id]);
            if ($stmt->fetchColumn() == 0) {
                $response['message'] = 'Khoa khám không hợp lệ!';
                error_log("Invalid department_id: $department_id");
                echo json_encode($response);
                exit;
            }

            // Kiểm tra bệnh nhân đã tồn tại
            $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE (phone = :phone OR (email = :email AND email IS NOT NULL)) AND is_deleted = 0");
            $stmt->execute(['phone' => $phone, 'email' => $email]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                // Thêm bệnh nhân mới
                $stmt = $conn->prepare("
                    INSERT INTO patients (full_name, gender, email, phone, source, department_id, appointment_date, symptoms, created_at, is_deleted)
                    VALUES (:full_name, :gender, :email, :phone, 'form', :department_id, :appointment_date, :symptoms, NOW(), 0)
                ");
                $stmt->execute([
                    'full_name' => $full_name,
                    'gender' => $gender,
                    'email' => $email,
                    'phone' => $phone,
                    'department_id' => $department_id,
                    'appointment_date' => $appointment_date,
                    'symptoms' => $symptoms
                ]);
                $patient_id = $conn->lastInsertId();
                error_log("New patient added via form: ID=$patient_id, full_name=$full_name, department_id=$department_id");
            } else {
                // Cập nhật thông tin đặt lịch nếu bệnh nhân đã tồn tại
                $patient_id = $patient['patient_id'];
                $stmt = $conn->prepare("
                    UPDATE patients SET department_id = :department_id, appointment_date = :appointment_date, symptoms = :symptoms, source = 'form'
                    WHERE patient_id = :patient_id AND is_deleted = 0
                ");
                $stmt->execute([
                    'patient_id' => $patient_id,
                    'department_id' => $department_id,
                    'appointment_date' => $appointment_date,
                    'symptoms' => $symptoms
                ]);
                error_log("Patient updated via form: ID=$patient_id, department_id=$department_id");
            }

            $response['success'] = true;
            $response['message'] = 'Đặt lịch khám thành công! Vui lòng chờ liên hệ từ bệnh viện.';
            $response['patient'] = [
                'patient_id' => $patient_id,
                'full_name' => $full_name,
                'gender' => $gender,
                'email' => $email,
                'phone' => $phone,
                'source' => 'form',
                'department_id' => $department_id,
                'appointment_date' => $appointment_date,
                'symptoms' => $symptoms
            ];
        }

        echo json_encode($response);
    } catch (PDOException $e) {
        $error_message = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
        error_log($error_message);
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
    exit;
}

// Lấy danh sách bệnh nhân chưa bị xóa
try {
    $stmt = $conn->query("SELECT p.*, d.department_name FROM patients p LEFT JOIN departments d ON p.department_id = d.department_id WHERE p.is_deleted = 0 ORDER BY p.created_at DESC");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}
?>

<div class="container mx-auto px-4 py-5">
    <!-- Phần quản lý bệnh nhân -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-4">Quản Lý Bệnh Nhân</h3>
        
        <!-- Nút thêm bệnh nhân -->
        <div class="mb-4">
            <button onclick="showAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i> Thêm Bệnh Nhân
            </button>
        </div>

        <!-- Bảng danh sách bệnh nhân -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã BN</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Họ Tên</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ngày Sinh</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Giới Tính</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Địa Chỉ</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Email</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">SĐT</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Nguồn</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Khoa</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ngày Khám</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Triệu Chứng</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-4">Không có bệnh nhân nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                            <tr id="patient-<?php echo htmlspecialchars($patient['patient_id']); ?>">
                                <td class="px-4 py-2">#<?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['date_of_birth'] ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['gender'] === 'Male' ? 'Nam' : ($patient['gender'] === 'Female' ? 'Nữ' : ($patient['gender'] === 'Other' ? 'Khác' : 'N/A'))); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['address'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['source'] === 'admin' ? 'Admin' : 'Biểu mẫu'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['department_name'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['appointment_date'] ? date('d/m/Y H:i', strtotime($patient['appointment_date'])) : 'N/A'); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($patient['symptoms'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='showEditModal(<?php echo json_encode($patient); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800" onclick="showDeleteConfirm(<?php echo htmlspecialchars($patient['patient_id']); ?>, '<?php echo htmlspecialchars(addslashes($patient['full_name'])); ?>')">
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

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-[1000]"></div>
</div>

<!-- Modal thêm/sửa bệnh nhân -->
<div id="patientModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[1000]">
    <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[80vh] overflow-y-auto">
        <h3 id="modalTitle" class="text-lg font-bold mb-4">Thêm Bệnh Nhân</h3>
        <form id="patientForm">
            <input type="hidden" id="patient_id" name="patient_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Họ Tên</label>
                <input type="text" id="full_name" name="full_name" required class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Ngày Sinh</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Giới Tính</label>
                <select id="gender" name="gender" class="w-full px-3 py-2 border rounded-md">
                    <option value="">Chọn giới tính</option>
                    <option value="Male">Nam</option>
                    <option value="Female">Nữ</option>
                    <option value="Other">Khác</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Địa Chỉ</label>
                <input type="text" id="address" name="address" class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Số Điện Thoại</label>
                <input type="tel" id="phone" name="phone" required class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="flex justify-end space-x-2 sticky bottom-0 bg-white pt-4">
                <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Hủy</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[1000]">
    <div class="bg-white rounded-lg p-6 w-full max-w-sm">
        <h3 class="text-lg font-bold mb-4">Xác Nhận Xóa</h3>
        <p id="deleteConfirmMessage" class="mb-4">Bạn có chắc chắn muốn xóa bệnh nhân này? Lịch khám và đơn thuốc sẽ được giữ lại.</p>
        <div class="flex justify-end space-x-2">
            <button onclick="closeDeleteConfirm()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Hủy</button>
            <button id="confirmDeleteBtn" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Xóa</button>
        </div>
    </div>
</div>

<style>
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        color: white;
        z-index: 9999;
        transition: all 0.4s ease-in-out;
        transform: translateX(100%);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        border-left: 5px solid;
        opacity: 0;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }
    .toast.show {
        transform: translateX(0);
        opacity: 1;
    }
    .toast-success {
        background-color: #10b981;
        border-left-color: #059669;
    }
    .toast-error {
        background-color: #ef4444;
        border-left-color: #dc2626;
    }
    .toast .fas {
        margin-right: 0.75rem;
        font-size: 1.2rem;
    }
</style>

<script>
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
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Bệnh Nhân';
    document.getElementById('patientForm').reset();
    document.getElementById('patient_id').value = '';
    document.getElementById('patientModal').classList.remove('hidden');
}

function showEditModal(patient) {
    document.getElementById('modalTitle').textContent = 'Sửa Bệnh Nhân';
    document.getElementById('patient_id').value = patient.patient_id;
    document.getElementById('full_name').value = patient.full_name;
    document.getElementById('date_of_birth').value = patient.date_of_birth || '';
    document.getElementById('gender').value = patient.gender || '';
    document.getElementById('address').value = patient.address || '';
    document.getElementById('email').value = patient.email || '';
    document.getElementById('phone').value = patient.phone || '';
    document.getElementById('patientModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('patientModal').classList.add('hidden');
}

function showDeleteConfirm(patientId, fullName) {
    document.getElementById('deleteConfirmMessage').textContent = `Bạn có chắc chắn muốn xóa bệnh nhân ${fullName}? Lịch khám và đơn thuốc sẽ được giữ lại.`;
    document.getElementById('confirmDeleteBtn').onclick = () => deletePatient(patientId);
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
}

function closeDeleteConfirm() {
    document.getElementById('deleteConfirmModal').classList.add('hidden');
}

function deletePatient(patientId) {
    fetch('patients.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `patient_id=${patientId}`
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            document.getElementById(`patient-${patientId}`).remove();
            closeDeleteConfirm();
        }
    })
    .catch(error => {
        showToast('Lỗi khi xóa bệnh nhân: ' + error.message, 'error');
    });
}

document.getElementById('patientForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const patientId = document.getElementById('patient_id').value;
    const action = patientId ? 'edit' : 'add';
    
    const formData = new FormData(this);
    console.log(`Submitting ${action} patient:`, Object.fromEntries(formData));
    
    fetch(`patients.php?action=${action}`, {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            const patient = data.patient;
            const tbody = document.querySelector('tbody');
            if (action === 'add') {
                const tr = document.createElement('tr');
                tr.id = `patient-${patient.patient_id}`;
                tr.innerHTML = `
                    <td class="px-4 py-2">#${patient.patient_id}</td>
                    <td class="px-4 py-2">${patient.full_name}</td>
                    <td class="px-4 py-2">${patient.date_of_birth ? new Date(patient.date_of_birth).toLocaleDateString('vi-VN') : 'N/A'}</td>
                    <td class="px-4 py-2">${patient.gender === 'Male' ? 'Nam' : patient.gender === 'Female' ? 'Nữ' : patient.gender === 'Other' ? 'Khác' : 'N/A'}</td>
                    <td class="px-4 py-2">${patient.address || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.email || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.phone || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.source === 'admin' ? 'Admin' : 'Biểu mẫu'}</td>
                    <td class="px-4 py-2">${patient.department_name || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.appointment_date ? new Date(patient.appointment_date).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : 'N/A'}</td>
                    <td class="px-4 py-2">${patient.symptoms || 'N/A'}</td>
                    <td class="px-4 py-2">
                        <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='showEditModal(${JSON.stringify(patient)})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="showDeleteConfirm(${patient.patient_id}, '${patient.full_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.prepend(tr);
            } else {
                const tr = document.getElementById(`patient-${patient.patient_id}`);
                tr.innerHTML = `
                    <td class="px-4 py-2">#${patient.patient_id}</td>
                    <td class="px-4 py-2">${patient.full_name}</td>
                    <td class="px-4 py-2">${patient.date_of_birth ? new Date(patient.date_of_birth).toLocaleDateString('vi-VN') : 'N/A'}</td>
                    <td class="px-4 py-2">${patient.gender === 'Male' ? 'Nam' : patient.gender === 'Female' ? 'Nữ' : patient.gender === 'Other' ? 'Khác' : 'N/A'}</td>
                    <td class="px-4 py-2">${patient.address || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.email || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.phone || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.source === 'admin' ? 'Admin' : 'Biểu mẫu'}</td>
                    <td class="px-4 py-2">${patient.department_name || 'N/A'}</td>
                    <td class="px-4 py-2">${patient.appointment_date ? new Date(patient.appointment_date).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : 'N/A'}</td>
                    <td class="px-4 py-2">${patient.symptoms || 'N/A'}</td>
                    <td class="px-4 py-2">
                        <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='showEditModal(${JSON.stringify(patient)})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="showDeleteConfirm(${patient.patient_id}, '${patient.full_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
            }
            closeModal();
        }
    })
    .catch(error => {
        showToast('Lỗi khi lưu bệnh nhân: ' + error.message, 'error');
    });
});
</script>