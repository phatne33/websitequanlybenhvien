<?php
require_once '../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Lấy danh sách thuốc
try {
    $stmt = $conn->prepare("SELECT medication_id, name, unit, description FROM medications ORDER BY name");
    $stmt->execute();
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Lỗi khi lấy dữ liệu thuốc: " . $e->getMessage());
    die("Lỗi khi lấy dữ liệu: " . $e->getMessage());
}

// Xử lý AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || empty($unit)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ tên thuốc và đơn vị.']);
            exit;
        }

        try {
            // Kiểm tra thuốc đã tồn tại
            $stmt = $conn->prepare("SELECT medication_id FROM medications WHERE name = :name");
            $stmt->execute([':name' => $name]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Thuốc đã tồn tại.']);
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO medications (name, unit, description)
                VALUES (:name, :unit, :description)
            ");
            $stmt->execute([
                ':name' => $name,
                ':unit' => $unit,
                ':description' => $description ?: null
            ]);
            $medication_id = $conn->lastInsertId();

            // Lấy thông tin thuốc vừa tạo
            $stmt = $conn->prepare("
                SELECT medication_id, name, unit, description
                FROM medications
                WHERE medication_id = :medication_id
            ");
            $stmt->execute([':medication_id' => $medication_id]);
            $new_medication = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Tạo thuốc thành công!',
                'medication' => $new_medication
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo thuốc: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'edit') {
        $medication_id = $_POST['medication_id'] ?? '';
        if (empty($medication_id)) {
            echo json_encode(['success' => false, 'message' => 'Thiếu ID thuốc.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("
                SELECT medication_id, name, unit, description
                FROM medications
                WHERE medication_id = :medication_id
            ");
            $stmt->execute([':medication_id' => $medication_id]);
            $medication = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$medication) {
                echo json_encode(['success' => false, 'message' => 'Thuốc không tồn tại.']);
                exit;
            }

            echo json_encode(['success' => true, 'medication' => $medication]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy thông tin thuốc: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'update') {
        $medication_id = $_POST['medication_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($medication_id) || empty($name) || empty($unit)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin.']);
            exit;
        }

        try {
            // Kiểm tra thuốc
            $stmt = $conn->prepare("SELECT medication_id FROM medications WHERE medication_id = :medication_id");
            $stmt->execute([':medication_id' => $medication_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Thuốc không tồn tại.']);
                exit;
            }

            // Kiểm tra tên thuốc đã tồn tại (khác với ID hiện tại)
            $stmt = $conn->prepare("SELECT medication_id FROM medications WHERE name = :name AND medication_id != :medication_id");
            $stmt->execute([':name' => $name, ':medication_id' => $medication_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Tên thuốc đã tồn tại.']);
                exit;
            }

            $stmt = $conn->prepare("
                UPDATE medications 
                SET name = :name, unit = :unit, description = :description
                WHERE medication_id = :medication_id
            ");
            $stmt->execute([
                ':medication_id' => $medication_id,
                ':name' => $name,
                ':unit' => $unit,
                ':description' => $description ?: null
            ]);

            // Lấy thông tin thuốc vừa cập nhật
            $stmt = $conn->prepare("
                SELECT medication_id, name, unit, description
                FROM medications
                WHERE medication_id = :medication_id
            ");
            $stmt->execute([':medication_id' => $medication_id]);
            $updated_medication = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Cập nhật thuốc thành công!',
                'medication' => $updated_medication
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật thuốc: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'delete') {
        $medication_id = $_POST['medication_id'] ?? '';
        if (empty($medication_id)) {
            echo json_encode(['success' => false, 'message' => 'Thiếu ID thuốc.']);
            exit;
        }

        try {
            // Kiểm tra xem thuốc có được sử dụng trong đơn thuốc không
            $stmt = $conn->prepare("SELECT COUNT(*) FROM prescription_details WHERE medication_id = :medication_id");
            $stmt->execute([':medication_id' => $medication_id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Không thể xóa thuốc vì đã được sử dụng trong đơn thuốc.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM medications WHERE medication_id = :medication_id");
            $stmt->execute([':medication_id' => $medication_id]);

            echo json_encode(['success' => true, 'message' => 'Xóa thuốc thành công!']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa thuốc: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'get_medications') {
        try {
            $stmt = $conn->prepare("SELECT medication_id, name, unit, description FROM medications ORDER BY name");
            $stmt->execute();
            $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'medications' => $medications]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi lấy danh sách thuốc: ' . $e->getMessage()]);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Thuốc</title>
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
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Danh Sách Thuốc</h3>
                <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" onclick="openMedicationModal('add')">
                    <i class="fas fa-plus mr-2"></i> Thêm Thuốc
                </button>
            </div>
            <div class="mb-4">
                <div class="flex">
                    <input type="text" id="searchMedicationInput" class="w-full px-3 py-2 border rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tìm kiếm thuốc...">
                    <button class="bg-gray-200 px-4 py-2 rounded-r-md hover:bg-gray-300" onclick="searchMedications()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Tên Thuốc</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Đơn Vị</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mô Tả</th>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody id="medicationTableBody">
                        <?php if (empty($medications)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">Không có thuốc nào</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medications as $medication): ?>
                                <tr id="medication-<?php echo htmlspecialchars($medication['medication_id']); ?>">
                                    <td class="px-4 py-2">#<?php echo htmlspecialchars($medication['medication_id']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($medication['name']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($medication['unit']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($medication['description'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-2">
                                        <button class="text-blue-600 hover:text-blue-800 mr-2" onclick="openMedicationModal('edit', <?php echo htmlspecialchars($medication['medication_id']); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-800" onclick="deleteMedication(<?php echo htmlspecialchars($medication['medication_id']); ?>)">
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

    <!-- Modal Thêm/Sửa Thuốc -->
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden" id="medicationModal">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold" id="medicationModalTitle">Thêm Thuốc</h3>
                <button class="text-gray-600 hover:text-gray-800" onclick="closeMedicationModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="medicationForm">
                <input type="hidden" id="medication_id" name="medication_id">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700">Tên Thuốc <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="unit" class="block text-sm font-medium text-gray-700">Đơn Vị <span class="text-red-500">*</span></label>
                    <input type="text" id="unit" name="unit" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700">Mô Tả</label>
                    <textarea id="description" name="description" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300" onclick="closeMedicationModal()">Hủy</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Lưu</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Hàm hiển thị toast
        function showToast(message, type) {
            const toastContainer = document.createElement('div');
            toastContainer.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'}`;
            toastContainer.textContent = message;
            document.body.appendChild(toastContainer);
            setTimeout(() => toastContainer.classList.add('show'), 100);
            setTimeout(() => {
                toastContainer.classList.remove('show');
                setTimeout(() => toastContainer.remove(), 300);
            }, 3000);
        }

        function openMedicationModal(mode, medicationId = null) {
            const modal = document.getElementById('medicationModal');
            const form = document.getElementById('medicationForm');
            const title = document.getElementById('medicationModalTitle');
            const medicationIdInput = document.getElementById('medication_id');

            form.reset();
            medicationIdInput.value = '';

            if (mode === 'edit' && medicationId) {
                title.textContent = 'Sửa Thuốc';
                medicationIdInput.value = medicationId;
                fetch('medications.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({
                        action: 'edit',
                        medication_id: medicationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('name').value = data.medication.name;
                        document.getElementById('unit').value = data.medication.unit;
                        document.getElementById('description').value = data.medication.description || '';
                        modal.classList.remove('hidden');
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Lỗi khi tải dữ liệu thuốc: ' + error.message, 'error');
                });
            } else {
                title.textContent = 'Thêm Thuốc';
                modal.classList.remove('hidden');
            }
        }

        function closeMedicationModal() {
            document.getElementById('medicationModal').classList.add('hidden');
            document.getElementById('medicationForm').reset();
        }

        function deleteMedication(medicationId) {
            if (confirm('Bạn có chắc chắn muốn xóa thuốc này?')) {
                fetch('medications.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({
                        action: 'delete',
                        medication_id: medicationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    showToast(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        refreshMedicationTable();
                    }
                })
                .catch(error => {
                    showToast('Lỗi khi xóa thuốc: ' + error.message, 'error');
                });
            }
        }

        function searchMedications() {
            const searchTerm = document.getElementById('searchMedicationInput')?.value.toLowerCase();
            const rows = document.querySelectorAll('#medicationTableBody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        function refreshMedicationTable() {
            fetch('medications.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'get_medications' })
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('medicationTableBody');
                tbody.innerHTML = '';
                if (data.success && data.medications.length > 0) {
                    data.medications.forEach(medication => {
                        const row = document.createElement('tr');
                        row.id = `medication-${medication.medication_id}`;
                        row.innerHTML = `
                            <td class="px-4 py-2">#${medication.medication_id}</td>
                            <td class="px-4 py-2">${medication.name}</td>
                            <td class="px-4 py-2">${medication.unit}</td>
                            <td class="px-4 py-2">${medication.description || 'N/A'}</td>
                            <td class="px-4 py-2">
                                <button class="text-blue-600 hover:text-blue-800 mr-2" onclick="openMedicationModal('edit', ${medication.medication_id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-800" onclick="deleteMedication(${medication.medication_id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Không có thuốc nào</td></tr>';
                }
            })
            .catch(error => {
                showToast('Lỗi khi tải danh sách thuốc: ' + error.message, 'error');
            });
        }

        document.getElementById('medicationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', document.getElementById('medication_id').value ? 'update' : 'create');

            fetch('medications.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    closeMedicationModal();
                    refreshMedicationTable();
                }
            })
            .catch(error => {
                showToast('Lỗi khi lưu thuốc: ' + error.message, 'error');
            });
        });

        // Tải danh sách thuốc ban đầu
        refreshMedicationTable();
    </script>
</body>
</html>