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

// Lấy thông tin bác sĩ
$doctor_id = $_SESSION['doctor_id'];
try {
    $stmt = $conn->prepare("SELECT full_name, gender, contact_phone, email, username, profile_image FROM doctors WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doctor) {
        $error_message = "Không tìm thấy thông tin bác sĩ.";
    }
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy thông tin bác sĩ: " . $e->getMessage();
    error_log($error_message);
}

// Tạo SVG viết tắt từ full_name
function generateInitialsSVG($full_name) {
    $initials = '';
    $words = explode(' ', trim($full_name));
    if (count($words) >= 2) {
        $initials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr(end($words), 0, 1, 'UTF-8');
    } elseif (!empty($words)) {
        $initials = mb_substr($words[0], 0, 2, 'UTF-8');
    }
    $initials = strtoupper($initials);
    return <<<SVG
<svg width="150" height="150" viewBox="0 0 150 150" xmlns="http://www.w3.org/2000/svg">
    <circle cx="75" cy="75" r="75" fill="#6c757d"/>
    <text x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="60" font-family="Arial, sans-serif" fill="#ffffff">$initials</text>
</svg>
SVG;
}

// Xử lý AJAX request để cập nhật thông tin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($full_name) || empty($contact_phone) || empty($email) || empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ các trường bắt buộc.']);
        exit;
    }

    // Kiểm tra định dạng email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ.']);
        exit;
    }

    // Kiểm tra định dạng số điện thoại
    if (!preg_match('/^[0-9]{10}$/', $contact_phone)) {
        echo json_encode(['success' => false, 'message' => 'Số điện thoại phải có 10 chữ số.']);
        exit;
    }

    // Kiểm tra username trùng
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM doctors WHERE username = :username AND doctor_id != :doctor_id");
        $stmt->execute([':username' => $username, ':doctor_id' => $doctor_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username đã tồn tại.']);
            exit;
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi kiểm tra username: ' . $e->getMessage()]);
        exit;
    }

    // Kiểm tra gender
    if ($gender && !in_array($gender, ['Nam', 'Nữ'])) {
        echo json_encode(['success' => false, 'message' => 'Giới tính không hợp lệ.']);
        exit;
    }

    // Xử lý mật khẩu
    $password_sql = '';
    $params = [
        ':full_name' => $full_name,
        ':gender' => $gender ?: null,
        ':contact_phone' => $contact_phone,
        ':email' => $email,
        ':username' => $username,
        ':doctor_id' => $doctor_id
    ];
    if (!empty($password)) {
        $password_sql = ', password = :password';
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Xử lý upload ảnh
    $profile_image = $doctor['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file PNG, JPG, JPEG.']);
            exit;
        }
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Kích thước file tối đa 2MB.']);
            exit;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'doctor_' . $doctor_id . '_' . time() . '.' . $ext;
        $upload_path = 'uploads/' . $filename;
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            if ($profile_image && file_exists($profile_image)) {
                unlink($profile_image);
            }
            $profile_image = $upload_path;
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi upload ảnh.']);
            exit;
        }
    }

    try {
        $sql = "UPDATE doctors SET full_name = :full_name, gender = :gender, contact_phone = :contact_phone, 
                email = :email, username = :username, profile_image = :profile_image $password_sql 
                WHERE doctor_id = :doctor_id";
        $params[':profile_image'] = $profile_image;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công!',
            'profile_image' => $profile_image ?: '',
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'contact_phone' => $contact_phone,
            'gender' => $gender ?: 'Chưa cập nhật'
        ]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật thông tin: ' . $e->getMessage()]);
        exit;
    }
}
?>

<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-dark mb-4">Hồ Sơ Bác Sĩ</h3>

            <!-- Toast Container -->
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-dark alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Display -->
            <div class="row mb-4">
                <div class="col-md-4 text-center">
                    <?php if (empty($doctor['profile_image']) || !file_exists($doctor['profile_image'])): ?>
                        <div class="rounded-circle img-fluid shadow-sm d-inline-block" style="width: 150px; height: 150px;">
                            <?php echo generateInitialsSVG($doctor['full_name'] ?? ''); ?>
                        </div>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($doctor['profile_image']); ?>?t=<?php echo time(); ?>" 
                             id="profileImage" class="rounded-circle img-fluid shadow-sm" style="width: 150px; height: 150px; object-fit: cover;" alt="Ảnh đại diện">
                    <?php endif; ?>
                    <h4 class="mt-3 text-dark" id="profileFullName"><?php echo htmlspecialchars($doctor['full_name'] ?? ''); ?></h4>
                    <p class="text-muted" id="profileUsername"><?php echo htmlspecialchars($doctor['username'] ?? ''); ?></p>
                </div>
                <div class="col-md-8">
                    <div class="card bg-light h-100">
                        <div class="card-body">
                            <h5 class="card-title text-dark">Thông Tin Chi Tiết</h5>
                            <p class="mb-2"><strong>Giới tính:</strong> <span id="profileGender"><?php echo htmlspecialchars($doctor['gender'] ?? 'Chưa cập nhật'); ?></span></p>
                            <p class="mb-2"><strong>Email:</strong> <span id="profileEmail"><?php echo htmlspecialchars($doctor['email'] ?? 'Chưa cập nhật'); ?></span></p>
                            <p class="mb-2"><strong>Số điện thoại:</strong> <span id="profilePhone"><?php echo htmlspecialchars($doctor['contact_phone'] ?? 'Chưa cập nhật'); ?></span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form chỉnh sửa thông tin -->
            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title text-dark mb-4">Chỉnh Sửa Thông Tin</h5>
                    <form id="profileForm" class="row g-3" enctype="multipart/form-data">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label text-dark">Họ tên</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($doctor['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label text-dark">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($doctor['username'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label text-dark">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_phone" class="form-label text-dark">Số điện thoại</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($doctor['contact_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label text-dark">Giới tính</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="" <?php echo empty($doctor['gender']) ? 'selected' : ''; ?>>Chọn giới tính</option>
                                <option value="Nam" <?php echo ($doctor['gender'] === 'Nam') ? 'selected' : ''; ?>>Nam</option>
                                <option value="Nữ" <?php echo ($doctor['gender'] === 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label text-dark">Mật khẩu mới (để trống nếu không đổi)</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="col-md-6">
                            <label for="profile_image" class="form-label text-dark">Ảnh đại diện</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/png,image/jpeg,image/jpg">
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-dark">Cập nhật</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const toastContainer = document.querySelector('.toast-container');
    const profileImage = document.getElementById('profileImage');
    const profileFullName = document.getElementById('profileFullName');
    const profileUsername = document.getElementById('profileUsername');
    const profileGender = document.getElementById('profileGender');
    const profileEmail = document.getElementById('profileEmail');
    const profilePhone = document.getElementById('profilePhone');

    // Xử lý submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch('profile.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
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

            // Cập nhật giao diện ngay lập tức
            if (data.success) {
                profileFullName.textContent = data.full_name;
                profileUsername.textContent = data.username;
                profileEmail.textContent = data.email;
                profilePhone.textContent = data.contact_phone;
                profileGender.textContent = data.gender;
                if (data.profile_image) {
                    profileImage.src = data.profile_image + '?t=' + new Date().getTime();
                    profileImage.style.display = 'block';
                    profileImage.parentElement.querySelector('div')?.remove(); // Xóa SVG nếu có
                } else {
                    // Tạo SVG mới nếu không có ảnh
                    const svgContainer = document.createElement('div');
                    svgContainer.className = 'rounded-circle img-fluid shadow-sm d-inline-block';
                    svgContainer.style.width = '150px';
                    svgContainer.style.height = '150px';
                    fetch('profile.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=get_svg&full_name=' + encodeURIComponent(data.full_name)
                    })
                    .then(res => res.text())
                    .then(svg => {
                        svgContainer.innerHTML = svg;
                        profileImage.parentElement.insertBefore(svgContainer, profileImage);
                        profileImage.style.display = 'none';
                    });
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
</script>