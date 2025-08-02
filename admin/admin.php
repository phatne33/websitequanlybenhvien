<?php
session_start();
require_once '../db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Lấy trang hiện tại từ URL
$page = $_GET['page'] ?? 'overview';

// Lấy thông tin admin
try {
    $stmt = $conn->prepare("SELECT full_name, email FROM admins WHERE admin_id = :admin_id");
    $stmt->execute(['admin_id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin['full_name'] ?? 'Quản Trị Viên';
    $admin_email = $admin['email'] ?? 'admin@hospital.com';

    // Lấy số liệu thống kê cho Tổng Quan
    if ($page === 'overview') {
        $stmt = $conn->query("SELECT COUNT(*) AS total FROM appointments");
        $total_appointments = $stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) AS total FROM doctors");
        $total_doctors = $stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) AS total FROM patients");
        $total_patients = $stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE status = 'Chờ xác nhận'");
        $pending_appointments = $stmt->fetchColumn();

        // Lấy dữ liệu cho biểu đồ trạng thái lịch hẹn
        $stmt = $conn->prepare("
            SELECT status, COUNT(*) AS count 
            FROM appointments 
            GROUP BY status
        ");
        $stmt->execute();
        $appointment_status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy dữ liệu cho biểu đồ giới tính bệnh nhân
        $stmt = $conn->prepare("
            SELECT gender, COUNT(*) AS count 
            FROM patients 
            GROUP BY gender
        ");
        $stmt->execute();
        $patient_gender_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy dữ liệu cho biểu đồ trạng thái đơn thuốc
        $stmt = $conn->prepare("
            SELECT status, COUNT(*) AS count 
            FROM prescriptions 
            GROUP BY status
        ");
        $stmt->execute();
        $prescription_status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Lỗi khi lấy dữ liệu: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Điều Khiển - Hệ Thống Đặt Lịch Khám Bệnh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding-top: 1rem;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar-hidden {
            transform: translateX(-250px);
        }
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            transition: margin-left 0.3s ease;
        }
        .sidebar-hidden + .main-content {
            margin-left: 0;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-toggle {
                display: block !important;
            }
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .nav-link.active {
            background-color: #3498db;
            color: white;
        }
        .stat-card {
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.5rem;
            color: white;
            z-index: 9999;
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: translateX(100%);
        }
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .toast-success {
            background-color: #10b981;
        }
        .toast-error {
            background-color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle hidden fixed top-4 left-4 z-[1001] bg-gray-800 text-white p-2 rounded-md" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center text-xl font-bold mb-6">Bảng Điều Khiển</h4>
            <ul class="space-y-2">
                <li>
                    <a href="admin.php?page=overview" class="nav-link <?php echo $page === 'overview' ? 'active' : ''; ?>">
                        <i class="fas fa-home mr-2"></i> Tổng Quan
                    </a>
                </li>
                <li>
                    <a href="admin.php?page=appointments" class="nav-link <?php echo $page === 'appointments' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check mr-2"></i> Lịch Khám
                    </a>
                </li>
                <li>
                    <a href="admin.php?page=doctors" class="nav-link <?php echo $page === 'doctors' ? 'active' : ''; ?>">
                        <i class="fas fa-user-md mr-2"></i> Bác Sĩ
                    </a>
                </li>
                <li>
                    <a href="admin.php?page=patients" class="nav-link <?php echo $page === 'patients' ? 'active' : ''; ?>">
                        <i class="fas fa-users mr-2"></i> Bệnh Nhân
                    </a>
                </li>
                <li>
                    <a href="admin.php?page=prescriptions" class="nav-link <?php echo $page === 'prescriptions' ? 'active' : ''; ?>">
                        <i class="fas fa-prescription-bottle-alt mr-2"></i> Quản Lý Đơn Thuốc
                    </a>
                </li>
                <li>
                    <a href="admin.php?page=medications" class="nav-link <?php echo $page === 'medications' ? 'active' : ''; ?>">
                        <i class="fas fa-pills mr-2"></i> Quản Lý Thuốc
                    </a>
                </li>
                <li>
                    <a href="admin.php?page=manage_departments_rooms" class="nav-link <?php echo $page === 'manage_departments_rooms' ? 'active' : ''; ?>">
                        <i class="fas fa-building mr-2"></i> Khoa và Phòng Khám
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt mr-2"></i> Đăng Xuất
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                <?php
                if ($page === 'overview') {
                    echo 'Tổng Quan';
                } elseif ($page === 'appointments') {
                    echo 'Lịch Khám';
                } elseif ($page === 'doctors') {
                    echo 'Quản Lý Bác Sĩ';
                } elseif ($page === 'patients') {
                    echo 'Quản Lý Bệnh Nhân';
                } elseif ($page === 'prescriptions') {
                    echo 'Quản Lý Đơn Thuốc';
                } elseif ($page === 'medications') {
                    echo 'Quản Lý Thuốc';
                } elseif ($page === 'manage_departments_rooms') {
                    echo 'Quản Lý Khoa và Phòng Khám';
                } else {
                    echo 'Tổng Quan';
                }
                ?>
            </h2>
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=3498db&color=fff" 
                         alt="Admin Avatar" 
                         class="w-10 h-10 rounded-full object-cover mr-2">
                    <div>
                        <span class="block font-bold"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="text-sm text-gray-600">Administrator</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nội dung động -->
        <?php
        if ($page === 'overview') {
        ?>
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card bg-blue-600 text-white">
                    <i class="fas fa-calendar-check text-3xl mb-2"></i>
                    <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($total_appointments); ?></h3>
                    <p class="mb-0">Tổng Lịch Hẹn</p>
                </div>
                <div class="stat-card bg-green-600 text-white">
                    <i class="fas fa-user-md text-3xl mb-2"></i>
                    <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($total_doctors); ?></h3>
                    <p class="mb-0">Bác Sĩ Đang Làm Việc</p>
                </div>
                <div class="stat-card bg-teal-600 text-white">
                    <i class="fas fa-users text-3xl mb-2"></i>
                    <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($total_patients); ?></h3>
                    <p class="mb-0">Tổng Bệnh Nhân</p>
                </div>
                <div class="stat-card bg-yellow-600 text-white">
                    <i class="fas fa-clock text-3xl mb-2"></i>
                    <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($pending_appointments); ?></h3>
                    <p class="mb-0">Lịch Hẹn Đang Chờ</p>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Biểu đồ trạng thái lịch hẹn -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold mb-4">Phân Bố Trạng Thái Lịch Hẹn</h3>
                    <canvas id="appointmentStatusChart"></canvas>
                </div>
                <!-- Biểu đồ giới tính bệnh nhân -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold mb-4">Phân Bố Giới Tính Bệnh Nhân</h3>
                    <canvas id="patientGenderChart"></canvas>
                </div>
                <!-- Biểu đồ trạng thái đơn thuốc -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold mb-4">Phân Bố Trạng Thái Đơn Thuốc</h3>
                    <canvas id="prescriptionStatusChart"></canvas>
                </div>
            </div>
        <?php
        } elseif ($page === 'doctors') {
            include 'doctors.php';
        } elseif ($page === 'appointments') {
            include 'appointments.php';
        } elseif ($page === 'patients') {
            include 'patients.php';
        } elseif ($page === 'prescriptions') {
            include 'prescriptions.php';
        } elseif ($page === 'medications') {
            include 'medications.php';
        } elseif ($page === 'manage_departments_rooms') {
            include 'manage_departments_rooms.php';
        } else {
            include 'overview.php';
        }
        ?>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
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

        // Hàm chuyển đổi sidebar
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Ẩn sidebar khi click ra ngoài trên mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const mobileToggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileToggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        // Ẩn sidebar trên desktop khi resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('active');
            }
        });

        // Khởi tạo biểu đồ Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            // Biểu đồ trạng thái lịch hẹn
            new Chart(document.getElementById('appointmentStatusChart'), {
                type: 'pie',
                data: {
                    labels: ['Chờ xác nhận', 'Đã xác nhận', 'Hoàn thành', 'Đã hủy'],
                    datasets: [{
                        data: [
                            <?php echo array_sum(array_column(array_filter($appointment_status_data, function($row) { return $row['status'] === 'Chờ xác nhận'; }), 'count')); ?>,
                            <?php echo array_sum(array_column(array_filter($appointment_status_data, function($row) { return $row['status'] === 'Đã xác nhận'; }), 'count')); ?>,
                            <?php echo array_sum(array_column(array_filter($appointment_status_data, function($row) { return $row['status'] === 'Hoàn thành'; }), 'count')); ?>,
                            <?php echo array_sum(array_column(array_filter($appointment_status_data, function($row) { return $row['status'] === 'Đã hủy'; }), 'count')); ?>
                        ],
                        backgroundColor: ['#facc15', '#22c55e', '#3b82f6', '#ef4444'],
                        borderColor: ['#ca8a04', '#15803d', '#1e40af', '#b91c1c'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    }
                }
            });

            // Biểu đồ giới tính bệnh nhân
            new Chart(document.getElementById('patientGenderChart'), {
                type: 'pie',
                data: {
                    labels: ['Nam', 'Nữ'],
                    datasets: [{
                        data: [
                            <?php echo array_sum(array_column(array_filter($patient_gender_data, function($row) { return $row['gender'] === 'Male'; }), 'count')); ?>,
                            <?php echo array_sum(array_column(array_filter($patient_gender_data, function($row) { return $row['gender'] === 'Female'; }), 'count')); ?>
                        ],
                        backgroundColor: ['#3b82f6', '#ec4899'],
                        borderColor: ['#1e40af', '#be185d'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    }
                }
            });

            // Biểu đồ trạng thái đơn thuốc
            new Chart(document.getElementById('prescriptionStatusChart'), {
                type: 'bar',
                data: {
                    labels: ['Chờ xử lý', 'Đã duyệt', 'Đã hủy'],
                    datasets: [{
                        label: 'Số lượng đơn thuốc',
                        data: [
                            <?php echo array_sum(array_column(array_filter($prescription_status_data, function($row) { return $row['status'] === 'Pending'; }), 'count')); ?>,
                            <?php echo array_sum(array_column(array_filter($prescription_status_data, function($row) { return $row['status'] === 'Approved'; }), 'count')); ?>,
                            <?php echo array_sum(array_column(array_filter($prescription_status_data, function($row) { return $row['status'] === 'Cancelled'; }), 'count')); ?>
                        ],
                        backgroundColor: '#facc15',
                        borderColor: '#ca8a04',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: false }
                    }
                }
            });
        });
    </script>
</body>
</html>