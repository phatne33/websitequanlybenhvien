<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}require_once '../db.php';

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

// Xử lý AJAX request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';

    // Quản lý khoa
    if ($action == 'add_department') {
        $department_name = trim($_POST['department_name'] ?? '');
        if (empty($department_name)) {
            echo json_encode(['error' => 'Tên khoa không được để trống']);
            exit();
        }
        try {
            $sql = "INSERT INTO departments (department_name) VALUES (:department_name)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':department_name' => $department_name]);
            echo json_encode(['success' => true, 'message' => 'Thêm khoa thành công!', 'department_id' => $conn->lastInsertId(), 'department_name' => $department_name]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi thêm khoa: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'edit_department') {
        $department_id = $_POST['department_id'] ?? '';
        $department_name = trim($_POST['department_name'] ?? '');
        if (empty($department_name)) {
            echo json_encode(['error' => 'Tên khoa không được để trống']);
            exit();
        }
        try {
            $sql = "UPDATE departments SET department_name = :department_name WHERE department_id = :department_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':department_name' => $department_name, ':department_id' => $department_id]);
            echo json_encode(['success' => true, 'message' => 'Sửa khoa thành công!', 'department_id' => $department_id, 'department_name' => $department_name]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi sửa khoa: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'delete_department') {
        $department_id = $_POST['department_id'] ?? '';
        try {
            // Kiểm tra khóa ngoại
            $stmt = $conn->prepare("SELECT COUNT(*) FROM rooms WHERE department_id = :department_id");
            $stmt->execute([':department_id' => $department_id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['error' => 'Không thể xóa khoa vì có phòng liên quan']);
                exit();
            }
            $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE department_id = :department_id");
            $stmt->execute([':department_id' => $department_id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['error' => 'Không thể xóa khoa vì có lịch khám liên quan']);
                exit();
            }
            $sql = "DELETE FROM departments WHERE department_id = :department_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':department_id' => $department_id]);
            echo json_encode(['success' => true, 'message' => 'Xóa khoa thành công!']);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi xóa khoa: " . $e->getMessage()]);
            exit();
        }
    }

    // Quản lý phòng
    if ($action == 'add_room') {
        $room_name = trim($_POST['room_name'] ?? '');
        $department_id = $_POST['department_id'] ?? '';
        if (empty($room_name) || empty($department_id)) {
            echo json_encode(['error' => 'Tên phòng và khoa không được để trống']);
            exit();
        }
        try {
            $sql = "INSERT INTO rooms (room_name, department_id) VALUES (:room_name, :department_id)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':room_name' => $room_name, ':department_id' => $department_id]);
            $stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = :department_id");
            $stmt->execute([':department_id' => $department_id]);
            $department_name = $stmt->fetchColumn();
            echo json_encode(['success' => true, 'message' => 'Thêm phòng thành công!', 'room_id' => $conn->lastInsertId(), 'room_name' => $room_name, 'department_id' => $department_id, 'department_name' => $department_name]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi thêm phòng: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'edit_room') {
        $room_id = $_POST['room_id'] ?? '';
        $room_name = trim($_POST['room_name'] ?? '');
        $department_id = $_POST['department_id'] ?? '';
        if (empty($room_name) || empty($department_id)) {
            echo json_encode(['error' => 'Tên phòng và khoa không được để trống']);
            exit();
        }
        try {
            $sql = "UPDATE rooms SET room_name = :room_name, department_id = :department_id WHERE room_id = :room_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':room_name' => $room_name, ':department_id' => $department_id, ':room_id' => $room_id]);
            $stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = :department_id");
            $stmt->execute([':department_id' => $department_id]);
            $department_name = $stmt->fetchColumn();
            echo json_encode(['success' => true, 'message' => 'Sửa phòng thành công!', 'room_id' => $room_id, 'room_name' => $room_name, 'department_id' => $department_id, 'department_name' => $department_name]);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi sửa phòng: " . $e->getMessage()]);
            exit();
        }
    } elseif ($action == 'delete_room') {
        $room_id = $_POST['room_id'] ?? '';
        try {
            // Kiểm tra khóa ngoại
            $stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE room_id = :room_id");
            $stmt->execute([':room_id' => $room_id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['error' => 'Không thể xóa phòng vì có lịch khám liên quan']);
                exit();
            }
            $sql = "DELETE FROM rooms WHERE room_id = :room_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':room_id' => $room_id]);
            echo json_encode(['success' => true, 'message' => 'Xóa phòng thành công!']);
            exit();
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => "Lỗi khi xóa phòng: " . $e->getMessage()]);
            exit();
        }
    }
}

// Lấy danh sách khoa và phòng
try {
    $departments = $conn->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);
    $rooms = $conn->query("SELECT r.*, d.department_name FROM rooms r LEFT JOIN departments d ON r.department_id = d.department_id")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4">Quản Lý Khoa và Phòng Khám</h3>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-[9999]"></div>

    <!-- Modal xác nhận xóa -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-[1000]">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4 text-gray-800" id="deleteModalTitle"></h3>
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

    <!-- Form quản lý khoa -->
    <div class="mb-6">
        <h4 class="text-base font-semibold mb-3 text-gray-800">Quản Lý Khoa</h4>
        <form id="departmentForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" id="department_action" value="add_department">
            <input type="hidden" name="department_id" id="department_id">
            <div>
                <label for="department_name" class="block text-sm font-medium text-gray-700">Tên khoa</label>
                <input type="text" name="department_name" id="department_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Lưu</button>
                <button type="button" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition ml-4" id="cancelEditDepartment" style="display: none;">Hủy sửa</button>
            </div>
        </form>
    </div>

    <!-- Danh sách khoa -->
    <div class="mb-6">
        <h4 class="text-base font-semibold mb-3 text-gray-800">Danh Sách Khoa</h4>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Tên Khoa</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="departmentTable">
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-gray-600">Không có khoa nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $department): ?>
                            <tr id="department-<?php echo htmlspecialchars($department['department_id']); ?>">
                                <td class="px-4 py-2 text-gray-700">#<?php echo htmlspecialchars($department['department_id']); ?></td>
                                <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($department['department_name']); ?></td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2 edit-department-btn" data-id="<?php echo htmlspecialchars($department['department_id']); ?>" data-name="<?php echo htmlspecialchars($department['department_name']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-department-btn" data-id="<?php echo htmlspecialchars($department['department_id']); ?>" data-name="<?php echo htmlspecialchars($department['department_name']); ?>">
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

    <!-- Form quản lý phòng -->
    <div class="mb-6">
        <h4 class="text-base font-semibold mb-3 text-gray-800">Quản Lý Phòng Khám</h4>
        <form id="roomForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" id="room_action" value="add_room">
            <input type="hidden" name="room_id" id="room_id">
            <div>
                <label for="room_name" class="block text-sm font-medium text-gray-700">Tên phòng</label>
                <input type="text" name="room_name" id="room_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label for="room_department_id" class="block text-sm font-medium text-gray-700">Khoa</label>
                <select name="department_id" id="room_department_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Chọn khoa</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department['department_id']; ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Lưu</button>
                <button type="button" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 transition ml-4" id="cancelEditRoom" style="display: none;">Hủy sửa</button>
            </div>
        </form>
    </div>

    <!-- Danh sách phòng -->
    <div>
        <h4 class="text-base font-semibold mb-3 text-gray-800">Danh Sách Phòng Khám</h4>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Tên Phòng</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Khoa</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao tác</th>
                    </tr>
                </thead>
                <tbody id="roomTable">
                    <?php if (empty($rooms)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-600">Không có phòng nào</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $room): ?>
                            <tr id="room-<?php echo htmlspecialchars($room['room_id']); ?>">
                                <td class="px-4 py-2 text-gray-700">#<?php echo htmlspecialchars($room['room_id']); ?></td>
                                <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($room['room_name']); ?></td>
                                <td class="px-4 py-2 text-gray-700"><?php echo htmlspecialchars($room['department_name'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2 edit-room-btn" data-id="<?php echo htmlspecialchars($room['room_id']); ?>" data-name="<?php echo htmlspecialchars($room['room_name']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-room-btn" data-id="<?php echo htmlspecialchars($room['room_id']); ?>" data-name="<?php echo htmlspecialchars($room['room_name']); ?>">
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
        const departmentForm = document.getElementById('departmentForm');
        const roomForm = document.getElementById('roomForm');
        const cancelEditDepartment = document.getElementById('cancelEditDepartment');
        const cancelEditRoom = document.getElementById('cancelEditRoom');
        const deleteModal = document.getElementById('deleteModal');
        const deleteModalTitle = document.getElementById('deleteModalTitle');
        const deleteModalMessage = document.getElementById('deleteModalMessage');
        const confirmDelete = document.getElementById('confirmDelete');
        const cancelDelete = document.getElementById('cancelDelete');
        let deleteAction = null;
        let deleteId = null;

        // Xử lý form khoa
        departmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(departmentForm);
            fetch('manage_departments_rooms.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    if (formData.get('action') === 'add_department') {
                        const newRow = `
                            <tr id="department-${data.department_id}">
                                <td class="px-4 py-2 text-gray-700">#${data.department_id}</td>
                                <td class="px-4 py-2 text-gray-700">${data.department_name}</td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2 edit-department-btn" data-id="${data.department_id}" data-name="${data.department_name}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-department-btn" data-id="${data.department_id}" data-name="${data.department_name}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                        const tableBody = document.getElementById('departmentTable');
                        if (tableBody.querySelector('tr td[colspan="3"]')) {
                            tableBody.innerHTML = newRow;
                        } else {
                            tableBody.insertAdjacentHTML('beforeend', newRow);
                        }
                        // Cập nhật select trong form phòng
                        const select = document.getElementById('room_department_id');
                        select.insertAdjacentHTML('beforeend', `<option value="${data.department_id}">${data.department_name}</option>`);
                    } else {
                        const row = document.getElementById(`department-${data.department_id}`);
                        row.children[1].textContent = data.department_name;
                        row.querySelector('.edit-department-btn').setAttribute('data-name', data.department_name);
                        row.querySelector('.delete-department-btn').setAttribute('data-name', data.department_name);
                        // Cập nhật select trong form phòng
                        const option = document.getElementById('room_department_id').querySelector(`option[value="${data.department_id}"]`);
                        if (option) option.textContent = data.department_name;
                    }
                    departmentForm.reset();
                    document.getElementById('department_action').value = 'add_department';
                    document.getElementById('department_id').value = '';
                    cancelEditDepartment.style.display = 'none';
                }
            })
            .catch(() => showToast('Lỗi kết nối. Vui lòng kiểm tra mạng.', 'error'));
        });

        // Xử lý form phòng
        roomForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(roomForm);
            fetch('manage_departments_rooms.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    if (formData.get('action') === 'add_room') {
                        const newRow = `
                            <tr id="room-${data.room_id}">
                                <td class="px-4 py-2 text-gray-700">#${data.room_id}</td>
                                <td class="px-4 py-2 text-gray-700">${data.room_name}</td>
                                <td class="px-4 py-2 text-gray-700">${data.department_name}</td>
                                <td class="px-4 py-2">
                                    <button class="text-blue-600 hover:text-blue-800 mr-2 edit-room-btn" data-id="${data.room_id}" data-name="${data.room_name}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-room-btn" data-id="${data.room_id}" data-name="${data.room_name}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                        const tableBody = document.getElementById('roomTable');
                        if (tableBody.querySelector('tr td[colspan="4"]')) {
                            tableBody.innerHTML = newRow;
                        } else {
                            tableBody.insertAdjacentHTML('beforeend', newRow);
                        }
                    } else {
                        const row = document.getElementById(`room-${data.room_id}`);
                        row.children[1].textContent = data.room_name;
                        row.children[2].textContent = data.department_name;
                        row.querySelector('.edit-room-btn').setAttribute('data-name', data.room_name);
                        row.querySelector('.delete-room-btn').setAttribute('data-name', data.room_name);
                    }
                    roomForm.reset();
                    document.getElementById('room_action').value = 'add_room';
                    document.getElementById('room_id').value = '';
                    cancelEditRoom.style.display = 'none';
                }
            })
            .catch(() => showToast('Lỗi kết nối. Vui lòng kiểm tra mạng.', 'error'));
        });

        // Xử lý nút sửa và xóa
        document.addEventListener('click', function(e) {
            // Sửa khoa
            if (e.target.closest('.edit-department-btn')) {
                const btn = e.target.closest('.edit-department-btn');
                const row = btn.closest('tr');
                document.getElementById('department_action').value = 'edit_department';
                document.getElementById('department_id').value = btn.getAttribute('data-id');
                document.getElementById('department_name').value = row.children[1].textContent;
                cancelEditDepartment.style.display = 'inline-block';
            }

            // Sửa phòng
            if (e.target.closest('.edit-room-btn')) {
                const btn = e.target.closest('.edit-room-btn');
                const row = btn.closest('tr');
                document.getElementById('room_action').value = 'edit_room';
                document.getElementById('room_id').value = btn.getAttribute('data-id');
                document.getElementById('room_name').value = row.children[1].textContent;
                document.getElementById('room_department_id').value = Array.from(document.getElementById('room_department_id').options)
                    .find(opt => opt.text === row.children[2].textContent)?.value || '';
                cancelEditRoom.style.display = 'inline-block';
            }

            // Xóa khoa
            if (e.target.closest('.delete-department-btn')) {
                const btn = e.target.closest('.delete-department-btn');
                deleteAction = 'delete_department';
                deleteId = btn.getAttribute('data-id');
                deleteModalTitle.textContent = 'Xác nhận xóa khoa';
                deleteModalMessage.textContent = `Bạn có chắc chắn muốn xóa khoa "${btn.getAttribute('data-name')}"?`;
                deleteModal.classList.remove('hidden');
            }

            // Xóa phòng
            if (e.target.closest('.delete-room-btn')) {
                const btn = e.target.closest('.delete-room-btn');
                deleteAction = 'delete_room';
                deleteId = btn.getAttribute('data-id');
                deleteModalTitle.textContent = 'Xác nhận xóa phòng';
                deleteModalMessage.textContent = `Bạn có chắc chắn muốn xóa phòng "${btn.getAttribute('data-name')}"?`;
                deleteModal.classList.remove('hidden');
            }
        });

        // Xử lý xác nhận xóa
        confirmDelete.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', deleteAction);
            formData.append(deleteAction === 'delete_department' ? 'department_id' : 'room_id', deleteId);
            fetch('manage_departments_rooms.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    if (deleteAction === 'delete_department') {
                        document.getElementById(`department-${deleteId}`)?.remove();
                        const select = document.getElementById('room_department_id');
                        const option = select.querySelector(`option[value="${deleteId}"]`);
                        if (option) option.remove();
                        if (!document.getElementById('departmentTable').children.length) {
                            document.getElementById('departmentTable').innerHTML = '<tr><td colspan="3" class="text-center py-4 text-gray-600">Không có khoa nào</td></tr>';
                        }
                    } else {
                        document.getElementById(`room-${deleteId}`)?.remove();
                        if (!document.getElementById('roomTable').children.length) {
                            document.getElementById('roomTable').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-600">Không có phòng nào</td></tr>';
                        }
                    }
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

        // Xử lý hủy sửa khoa
        cancelEditDepartment.addEventListener('click', function() {
            departmentForm.reset();
            document.getElementById('department_action').value = 'add_department';
            document.getElementById('department_id').value = '';
            cancelEditDepartment.style.display = 'none';
        });

        // Xử lý hủy sửa phòng
        cancelEditRoom.addEventListener('click', function() {
            roomForm.reset();
            document.getElementById('room_action').value = 'add_room';
            document.getElementById('room_id').value = '';
            cancelEditRoom.style.display = 'none';
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