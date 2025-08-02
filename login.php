<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu']);
        exit;
    }

    try {
        // Kiểm tra trong bảng admins
        $stmt = $conn->prepare("SELECT admin_id, username, password FROM admins WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            echo json_encode(['success' => true, 'redirect' => 'admin/admin.php']);
            exit;
        }

        // Kiểm tra trong bảng doctors
        $stmt = $conn->prepare("SELECT doctor_id, username, password FROM doctors WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($doctor && password_verify($password, $doctor['password'])) {
            $_SESSION['doctor_id'] = $doctor['doctor_id'];
            $_SESSION['username'] = $doctor['username'];
            echo json_encode(['success' => true, 'redirect' => 'doctor/index.php']);
            exit;
        }

        // Nếu không tìm thấy tài khoản
        echo json_encode(['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }
} else {
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ Thống Đặt Lịch Khám Bệnh</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Đăng Nhập Hệ Thống</h2>
        <form id="loginForm" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Tên Đăng Nhập</label>
                <div class="mt-1 relative">
                    <input type="text" id="username" name="username" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400">
                        <i class="fas fa-user"></i>
                    </span>
                </div>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Mật Khẩu</label>
                <div class="mt-1 relative">
                    <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400">
                        <i class="fas fa-lock"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition duration-300">Đăng Nhập</button>
        </form>
        <p id="errorMessage" class="text-red-500 text-sm mt-4 hidden"></p>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const errorMessage = document.getElementById('errorMessage');
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMessage.textContent = data.message;
                    errorMessage.classList.remove('hidden');
                }
            })
            .catch(error => {
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.textContent = 'Lỗi hệ thống, vui lòng thử lại!';
                errorMessage.classList.remove('hidden');
            });
        });
    </script>
</body>
</html>

<?php } ?>