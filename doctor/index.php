<?php
session_start();
require_once '../db.php';

// Kiểm tra đăng nhập bác sĩ
if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../login.php');
    exit;
}

// Kết nối cơ sở dữ liệu
try {
    $conn = new PDO("mysql:host=localhost;dbname=benhviensql", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);
} catch(PDOException $e) {
    $error_message = "Lỗi kết nối: " . $e->getMessage();
}

// Lấy thông tin bác sĩ
$doctor_id = $_SESSION['doctor_id'];
try {
    $stmt = $conn->prepare("SELECT full_name FROM doctors WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy thông tin bác sĩ: " . $e->getMessage();
}

// Xử lý chuyển đổi trang
$page = $_GET['page'] ?? 'profile';
$valid_pages = ['profile', 'appointments', 'history', 'prescription'];
if (!in_array($page, $valid_pages)) {
    $page = 'profile';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bác Sĩ - Hệ Thống Quản Lý Bệnh Viện</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .sidebar { 
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background-color: #212529;
            overflow-y: auto;
        }
        .sidebar .nav-link { 
            color: #f8f9fa; 
            padding: 15px 20px;
            transition: background-color 0.3s, color 0.3s;
        }
        .sidebar .nav-link:hover { 
            background-color: #6c757d; 
            color: #ffffff; 
        }
        .sidebar .nav-link.active { 
            background-color: #343a40; 
            color: #ffffff; 
            font-weight: 600; 
        }
        .content { 
            margin-left: 250px; 
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        @media (max-width: 768px) {
            .sidebar { 
                width: 100%;
                height: auto;
                position: relative;
            }
            .content { 
                margin-left: 0; 
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar bên trái -->
        <nav class="sidebar d-md-block d-none">
            <div class="p-3 border-bottom border-dark">
                <h2 class="fs-5 fw-bold text-white">Bác Sĩ</h2>
                <p class=" mb-0" style="color: white;"><?php echo htmlspecialchars($doctor['full_name']); ?></p>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="?page=profile" class="nav-link <?php echo $page === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user mr-2"></i> Thông Tin Cá Nhân
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=appointments" class="nav-link <?php echo $page === 'appointments' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt mr-2"></i> Lịch Khám
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=prescription" class="nav-link <?php echo $page === 'prescription' ? 'active' : ''; ?>">
                        <i class="fas fa-prescription-bottle-alt mr-2"></i> Kê Đơn Thuốc
                    </a>
                </li>
     
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt mr-2"></i> Đăng Xuất
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Menu hamburger cho mobile -->
        <nav class="navbar navbar-dark bg-dark d-md-none">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
        </nav>
        <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header border-bottom border-dark">
                <h5 class="offcanvas-title text-white" id="sidebarMenuLabel">Bác Sĩ</h5>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($doctor['full_name']); ?></p>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="?page=profile" class="nav-link <?php echo $page === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user mr-2"></i> Thông Tin Cá Nhân
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=appointments" class="nav-link <?php echo $page === 'appointments' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt mr-2"></i> Lịch Khám
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=prescription" class="nav-link <?php echo $page === 'prescription' ? 'active' : ''; ?>">
                            <i class="fas fa-prescription-bottle-alt mr-2"></i> Kê Đơn Thuốc
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?page=history" class="nav-link <?php echo $page === 'history' ? 'active' : ''; ?>">
                            <i class="fas fa-history mr-2"></i> Lịch Sử Khám
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt mr-2"></i> Đăng Xuất
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Nội dung chính -->
        <div class="content">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-dark alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php
            // Include file tương ứng với trang
            switch ($page) {
                case 'profile':
                    include 'profile.php';
                    break;
                case 'appointments':
                    include 'appointments.php';
                    break;
                case 'history':
                    include 'history.php';
                    break;
                case 'prescription':
                    include 'prescription.php';
                    break;
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>