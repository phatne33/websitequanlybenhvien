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
            $full_name = $_POST['full_name'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $contact_phone = $_POST['contact_phone'] ?? '';
            $email = $_POST['email'] ?? '';
            $specialty = $_POST['specialty'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $profile_image = '';

            // Kiểm tra trùng email hoặc username
            $stmt = $conn->prepare("SELECT COUNT(*) FROM doctors WHERE (email = :email OR username = :username) AND is_deleted = 0");
            $stmt->execute(['email' => $email, 'username' => $username]);
            if ($stmt->fetchColumn() > 0) {
                $response['message'] = 'Email hoặc username đã tồn tại!';
                echo json_encode($response);
                exit;
            }

            // Xử lý upload ảnh
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../Uploads/doctors/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = uniqid() . '_' . basename($_FILES['profile_image']['name']);
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $file_path)) {
                    $profile_image = $file_name;
                } else {
                    $response['message'] = 'Lỗi khi tải lên ảnh!';
                    echo json_encode($response);
                    exit;
                }
            }

            // Mã hóa mật khẩu
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Thêm bác sĩ mới
            $stmt = $conn->prepare("
                INSERT INTO doctors (full_name, gender, contact_phone, email, specialty, username, password, created_at, profile_image, is_deleted)
                VALUES (:full_name, :gender, :contact_phone, :email, :specialty, :username, :password, NOW(), :profile_image, 0)
            ");
            $stmt->execute([
                'full_name' => $full_name,
                'gender' => $gender,
                'contact_phone' => $contact_phone,
                'email' => $email,
                'specialty' => $specialty,
                'username' => $username,
                'password' => $hashed_password,
                'profile_image' => $profile_image
            ]);

            $response['success'] = true;
            $response['message'] = 'Thêm bác sĩ thành công!';
            $response['doctor'] = [
                'doctor_id' => $conn->lastInsertId(),
                'full_name' => $full_name,
                'gender' => $gender,
                'contact_phone' => $contact_phone,
                'email' => $email,
                'specialty' => $specialty,
                'username' => $username,
                'profile_image' => $profile_image ? '/Uploads/doctors/' . $profile_image : null
            ];
        } elseif ($_GET['action'] === 'edit') {
            $doctor_id = $_POST['doctor_id'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $contact_phone = $_POST['contact_phone'] ?? '';
            $email = $_POST['email'] ?? '';
            $specialty = $_POST['specialty'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            // Kiểm tra trùng email hoặc username
            $stmt = $conn->prepare("SELECT COUNT(*) FROM doctors WHERE (email = :email OR username = :username) AND doctor_id != :doctor_id AND is_deleted = 0");
            $stmt->execute(['email' => $email, 'username' => $username, 'doctor_id' => $doctor_id]);
            if ($stmt->fetchColumn() > 0) {
                $response['message'] = 'Email hoặc username đã tồn tại!';
                echo json_encode($response);
                exit;
            }

            // Lấy ảnh hiện tại
            $stmt = $conn->prepare("SELECT profile_image FROM doctors WHERE doctor_id = :doctor_id");
            $stmt->execute(['doctor_id' => $doctor_id]);
            $current_image = $stmt->fetchColumn();

            $profile_image = $current_image;
            // Xử lý upload ảnh mới
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../Uploads/doctors/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = uniqid() . '_' . basename($_FILES['profile_image']['name']);
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $file_path)) {
                    // Xóa ảnh cũ nếu tồn tại
                    if ($current_image && file_exists($upload_dir . $current_image)) {
                        unlink($upload_dir . $current_image);
                    }
                    $profile_image = $file_name;
                } else {
                    $response['message'] = 'Lỗi khi tải lên ảnh!';
                    echo json_encode($response);
                    exit;
                }
            } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1' && $current_image) {
                // Xóa ảnh hiện tại
                if (file_exists('../Uploads/doctors/' . $current_image)) {
                    unlink('../Uploads/doctors/' . $current_image);
                }
                $profile_image = null;
            }

            // Cập nhật thông tin bác sĩ
            $sql = "UPDATE doctors SET full_name = :full_name, gender = :gender, contact_phone = :contact_phone, 
                    email = :email, specialty = :specialty, username = :username, profile_image = :profile_image";
            $params = [
                'doctor_id' => $doctor_id,
                'full_name' => $full_name,
                'gender' => $gender,
                'contact_phone' => $contact_phone,
                'email' => $email,
                'specialty' => $specialty,
                'username' => $username,
                'profile_image' => $profile_image
            ];

            // Nếu có mật khẩu mới, mã hóa và cập nhật
            if (!empty($password)) {
                $sql .= ", password = :password";
                $params['password'] = password_hash($password, PASSWORD_BCRYPT);
            }

            $sql .= " WHERE doctor_id = :doctor_id AND is_deleted = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $response['success'] = true;
            $response['message'] = 'Cập nhật bác sĩ thành công!';
            $response['doctor'] = [
                'doctor_id' => $doctor_id,
                'full_name' => $full_name,
                'gender' => $gender,
                'contact_phone' => $contact_phone,
                'email' => $email,
                'specialty' => $specialty,
                'username' => $username,
                'profile_image' => $profile_image ? '/Uploads/doctors/' . $profile_image : null
            ];
        } elseif ($_GET['action'] === 'delete') {
            $doctor_id = $_POST['doctor_id'] ?? '';
            // Lấy tên bác sĩ để cập nhật doctor_name
            $stmt = $conn->prepare("SELECT full_name, profile_image FROM doctors WHERE doctor_id = :doctor_id AND is_deleted = 0");
            $stmt->execute(['doctor_id' => $doctor_id]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doctor) {
                $response['message'] = 'Bác sĩ không tồn tại hoặc đã bị xóa!';
                echo json_encode($response);
                exit;
            }

            // Xóa ảnh nếu có
            if ($doctor['profile_image'] && file_exists('../Uploads/doctors/' . $doctor['profile_image'])) {
                unlink('../Uploads/doctors/' . $doctor['profile_image']);
            }

            // Cập nhật doctor_name trong appointments và prescriptions
            $stmt = $conn->prepare("UPDATE appointments SET doctor_name = :full_name WHERE doctor_id = :doctor_id AND doctor_name IS NULL");
            $stmt->execute(['full_name' => $doctor['full_name'], 'doctor_id' => $doctor_id]);

            $stmt = $conn->prepare("UPDATE prescriptions SET doctor_name = :full_name WHERE doctor_id = :doctor_id AND doctor_name IS NULL");
            $stmt->execute(['full_name' => $doctor['full_name'], 'doctor_id' => $doctor_id]);

            // Đánh dấu bác sĩ là đã xóa
            $stmt = $conn->prepare("UPDATE doctors SET is_deleted = 1, profile_image = NULL WHERE doctor_id = :doctor_id");
            $stmt->execute(['doctor_id' => $doctor_id]);

            $response['success'] = true;
            $response['message'] = 'Đã đánh dấu bác sĩ là xóa. Lịch khám và đơn thuốc được giữ lại.';
        }

        echo json_encode($response);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit;
}

// Lấy danh sách bác sĩ chưa bị xóa
$stmt = $conn->query("SELECT * FROM doctors WHERE is_deleted = 0 ORDER BY created_at DESC");
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4">Quản Lý Bác Sĩ</h3>
    
    <!-- Nút thêm bác sĩ -->
    <div class="mb-4">
        <button onclick="showAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> Thêm Bác Sĩ
        </button>
    </div>

    <!-- Bảng danh sách bác sĩ -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Mã BS</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Ảnh</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Họ Tên</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Giới Tính</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">SĐT</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Email</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Chuyên Khoa</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Username</th>
                    <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4">Không có bác sĩ nào</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr id="doctor-<?php echo htmlspecialchars($doctor['doctor_id']); ?>">
                            <td class="px-4 py-2">#<?php echo htmlspecialchars($doctor['doctor_id']); ?></td>
                            <td class="px-4 py-2">
                                <?php if ($doctor['profile_image']): ?>
                                    <img src="/Uploads/doctors/<?php echo htmlspecialchars($doctor['profile_image']); ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                <?php else: ?>
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doctor['full_name']); ?>&background=3498db&color=fff" alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($doctor['full_name']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($doctor['gender'] === 'Male' ? 'Nam' : ($doctor['gender'] === 'Female' ? 'Nữ' : 'Khác')); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($doctor['contact_phone'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($doctor['email'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($doctor['specialty'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($doctor['username']); ?></td>
                            <td class="px-4 py-2">
                                <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='showEditModal(<?php echo json_encode($doctor); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-800" onclick="showDeleteConfirm(<?php echo htmlspecialchars($doctor['doctor_id']); ?>, '<?php echo htmlspecialchars($doctor['full_name']); ?>')">
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

<!-- Modal thêm/sửa bác sĩ -->
<div id="doctorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[1000]">
    <div class="bg-white rounded-lg p-6 w-full max-w-md max-h-[80vh] overflow-y-auto">
        <h3 id="modalTitle" class="text-lg font-bold mb-4">Thêm Bác Sĩ</h3>
        <form id="doctorForm" enctype="multipart/form-data">
            <input type="hidden" id="doctor_id" name="doctor_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Họ Tên</label>
                <input type="text" id="full_name" name="full_name" required class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Giới Tính</label>
                <select id="gender" name="gender" class="w-full px-3 py-2 border rounded-md">
                    <option value="Male">Nam</option>
                    <option value="Female">Nữ</option>
                    <option value="Other">Khác</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Số Điện Thoại</label>
                <input type="text" id="contact_phone" name="contact_phone" class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Chuyên Khoa</label>
                <input type="text" id="specialty" name="specialty" class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" id="username" name="username" required class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Mật Khẩu</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 border rounded-md">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Ảnh Đại Diện</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" class="w-full px-3 py-2 border rounded-md">
                <div id="imagePreview" class="mt-2"></div>
                <label class="flex items-center mt-2">
                    <input type="checkbox" id="remove_image" name="remove_image" value="1" class="mr-2">
                    Xóa ảnh hiện tại
                </label>
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
        <p id="deleteConfirmMessage" class="mb-4">Bạn có chắc chắn muốn xóa bác sĩ này?</p>
        <div class="flex justify-end space-x-2">
            <button onclick="closeDeleteConfirm()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">Hủy</button>
            <button id="confirmDeleteBtn" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Xóa</button>
        </div>
    </div>
</div>

<script>
function showToast(message, type) {
    const toastContainer = document.getElementById('toast-container') || document.createElement('div');
    toastContainer.id = 'toast-container';
    toastContainer.className = 'fixed top-4 right-4 z-[1001]';
    document.body.appendChild(toastContainer);
    
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white px-4 py-2 rounded-md shadow-lg flex items-center space-x-2`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    toastContainer.appendChild(toast);
    setTimeout(() => toast.classList.add('opacity-100'), 100);
    setTimeout(() => {
        toast.classList.remove('opacity-100');
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Bác Sĩ';
    document.getElementById('doctorForm').reset();
    document.getElementById('doctor_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('remove_image').parentElement.classList.add('hidden');
    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('doctorModal').classList.remove('hidden');
}

function showEditModal(doctor) {
    document.getElementById('modalTitle').textContent = 'Sửa Bác Sĩ';
    document.getElementById('doctor_id').value = doctor.doctor_id;
    document.getElementById('full_name').value = doctor.full_name;
    document.getElementById('gender').value = doctor.gender || 'Other';
    document.getElementById('contact_phone').value = doctor.contact_phone || '';
    document.getElementById('email').value = doctor.email || '';
    document.getElementById('specialty').value = doctor.specialty || '';
    document.getElementById('username').value = doctor.username;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('remove_image').parentElement.classList.toggle('hidden', !doctor.profile_image);
    document.getElementById('imagePreview').innerHTML = doctor.profile_image ? 
        `<img src="/Uploads/doctors/${doctor.profile_image}" alt="Profile" class="w-20 h-20 rounded-full object-cover">` : '';
    document.getElementById('doctorModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('doctorModal').classList.add('hidden');
}

function showDeleteConfirm(doctorId, fullName) {
    document.getElementById('deleteConfirmMessage').textContent = `Bạn có chắc chắn muốn xóa bác sĩ ${fullName}? Lịch khám và đơn thuốc sẽ được giữ lại.`;
    document.getElementById('confirmDeleteBtn').onclick = () => deleteDoctor(doctorId);
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
}

function closeDeleteConfirm() {
    document.getElementById('deleteConfirmModal').classList.add('hidden');
}

function deleteDoctor(doctorId) {
    fetch('doctors.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `doctor_id=${doctorId}`
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            document.getElementById(`doctor-${doctorId}`).remove();
            closeDeleteConfirm();
        }
    });
}

document.getElementById('doctorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const doctorId = document.getElementById('doctor_id').value;
    const action = doctorId ? 'edit' : 'add';
    
    const formData = new FormData(this);
    
    fetch(`doctors.php?action=${action}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            const doctor = data.doctor;
            if (action === 'add') {
                const tbody = document.querySelector('tbody');
                const tr = document.createElement('tr');
                tr.id = `doctor-${doctor.doctor_id}`;
                tr.innerHTML = `
                    <td class="px-4 py-2">#${doctor.doctor_id}</td>
                    <td class="px-4 py-2">
                        ${doctor.profile_image ? 
                            `<img src="${doctor.profile_image}" alt="Profile" class="w-10 h-10 rounded-full object-cover">` :
                            `<img src="https://ui-avatars.com/api/?name=${encodeURIComponent(doctor.full_name)}&background=3498db&color=fff" alt="Profile" class="w-10 h-10 rounded-full object-cover">`
                        }
                    </td>
                    <td class="px-4 py-2">${doctor.full_name}</td>
                    <td class="px-4 py-2">${doctor.gender === 'Male' ? 'Nam' : doctor.gender === 'Female' ? 'Nữ' : 'Khác'}</td>
                    <td class="px-4 py-2">${doctor.contact_phone || 'N/A'}</td>
                    <td class="px-4 py-2">${doctor.email || 'N/A'}</td>
                    <td class="px-4 py-2">${doctor.specialty || 'N/A'}</td>
                    <td class="px-4 py-2">${doctor.username}</td>
                    <td class="px-4 py-2">
                        <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='showEditModal(${JSON.stringify(doctor)})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="showDeleteConfirm(${doctor.doctor_id}, '${doctor.full_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.prepend(tr);
            } else {
                const tr = document.getElementById(`doctor-${doctor.doctor_id}`);
                tr.innerHTML = `
                    <td class="px-4 py-2">#${doctor.doctor_id}</td>
                    <td class="px-4 py-2">
                        ${doctor.profile_image ? 
                            `<img src="${doctor.profile_image}" alt="Profile" class="w-10 h-10 rounded-full object-cover">` :
                            `<img src="https://ui-avatars.com/api/?name=${encodeURIComponent(doctor.full_name)}&background=3498db&color=fff" alt="Profile" class="w-10 h-10 rounded-full object-cover">`
                        }
                    </td>
                    <td class="px-4 py-2">${doctor.full_name}</td>
                    <td class="px-4 py-2">${doctor.gender === 'Male' ? 'Nam' : doctor.gender === 'Female' ? 'Nữ' : 'Khác'}</td>
                    <td class="px-4 py-2">${doctor.contact_phone || 'N/A'}</td>
                    <td class="px-4 py-2">${doctor.email || 'N/A'}</td>
                    <td class="px-4 py-2">${doctor.specialty || 'N/A'}</td>
                    <td class="px-4 py-2">${doctor.username}</td>
                    <td class="px-4 py-2">
                        <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='showEditModal(${JSON.stringify(doctor)})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="text-red-600 hover:text-red-800" onclick="showDeleteConfirm(${doctor.doctor_id}, '${doctor.full_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
            }
            closeModal();
        }
    });
});

// Xem trước ảnh khi chọn
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-20 h-20 rounded-full object-cover">`;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});
</script>