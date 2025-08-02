<?php
// Bắt đầu session
session_start();

// Kết nối tới cơ sở dữ liệu
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "benhviensql";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => "Lỗi kết nối: " . $e->getMessage()]);
    exit();
}

// Xử lý AJAX request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $full_name = trim($_POST['full_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department_id = $_POST['department_id'];
    $appointment_date = $_POST['appointment_date'];
    $symptoms = trim($_POST['symptoms']);

    try {
        $sql = "INSERT INTO patients (full_name, date_of_birth, gender, address, email, phone, source, appointment_date, department_id, symptoms)
                VALUES (:full_name, :date_of_birth, :gender, :address, :email, :phone, 'form', :appointment_date, :department_id, :symptoms)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':date_of_birth', $date_of_birth);
        $stmt->bindParam(':gender', $gender);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':appointment_date', $appointment_date);
        $stmt->bindParam(':department_id', $department_id);
        $stmt->bindParam(':symptoms', $symptoms);
        
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đăng ký lịch khám thành công! Bệnh viện sẽ sớm liên hệ với bạn qua email hoặc số điện thoại, mong bạn chú ý.']);
        exit();
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => "Lỗi: " . $e->getMessage()]);
        exit();
    }
}

// Lấy danh sách các khoa từ bảng departments
try {
    $stmt = $conn->query("SELECT department_id, department_name FROM departments");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Lỗi khi lấy danh sách khoa: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phòng Khám ABC - Đặt Lịch Khám Bệnh</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar bg-primary text-white py-2">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="contact-info d-flex align-items-center me-4">
                            <i class="fas fa-phone-alt me-2"></i>
                            <div>
                                <small class="d-block">Hotline</small>
                                <strong>(012) 345 6789</strong>
                            </div>
                        </div>
                        <div class="contact-info d-flex align-items-center">
                            <i class="fas fa-envelope me-2"></i>
                            <div>
                                <small class="d-block">Email</small>
                                <strong>info@phongkhamabc.com</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end align-items-center">
                        <div class="working-hours me-4">
                            <i class="far fa-clock me-2"></i>
                            <span>Giờ làm việc: 8:00 - 17:00</span>
                        </div>
                        <div class="social-links">
                            <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <div class="logo-wrapper me-3">
                    <i class="fas fa-hospital text-primary"></i>
                </div>
                <div class="brand-text">
                    <span class="fw-bold d-block">Phòng Khám ABC</span>
                    <small class="text-muted">Chăm sóc sức khỏe toàn diện</small>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Trang chủ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Dịch vụ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#doctors">Bác sĩ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Giới thiệu</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Liên hệ</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary btn-lg d-flex align-items-center" href="#booking">
                            <i class="far fa-calendar-alt me-2"></i>
                            Đặt lịch ngay
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="hero-content">
                        <span class="badge bg-primary mb-3">Phòng khám uy tín hàng đầu</span>
                        <h1 class="display-4 fw-bold mb-4">Chăm Sóc Sức Khỏe Toàn Diện</h1>
                        <p class="lead mb-4">Phòng khám ABC với đội ngũ y bác sĩ chuyên môn cao, trang thiết bị hiện đại, cam kết mang đến dịch vụ y tế chất lượng tốt nhất cho bạn và gia đình.</p>
                        <div class="d-flex gap-3">
                            <a href="#booking" class="btn btn-primary btn-lg d-flex align-items-center">
                                <i class="far fa-calendar-alt me-2"></i>
                                Đặt lịch khám
                            </a>
                            <a href="#services" class="btn btn-outline-primary btn-lg d-flex align-items-center">
                                <i class="fas fa-list-ul me-2"></i>
                                Xem dịch vụ
                            </a>
                        </div>
                        <div class="hero-features mt-5">
                            <div class="row g-4">
                                <div class="col-6">
                                    <div class="feature-item d-flex align-items-center">
                                        <i class="fas fa-check-circle text-primary me-2"></i>
                                        <span>Đội ngũ bác sĩ chuyên môn cao</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="feature-item d-flex align-items-center">
                                        <i class="fas fa-check-circle text-primary me-2"></i>
                                        <span>Trang thiết bị hiện đại</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="feature-item d-flex align-items-center">
                                        <i class="fas fa-check-circle text-primary me-2"></i>
                                        <span>Dịch vụ 24/7</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="feature-item d-flex align-items-center">
                                        <i class="fas fa-check-circle text-primary me-2"></i>
                                        <span>Chi phí hợp lý</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="hero-image position-relative">
                        <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Medical" class="img-fluid rounded-3 shadow-lg">
                        <div class="experience-badge">
                            <span class="years">15+</span>
                            <span class="text">Năm kinh nghiệm</span>
                        </div>
                        <div class="appointment-badge">
                            <span class="number">10k+</span>
                            <span class="text">Bệnh nhân hài lòng</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- About Section -->
    <section id="about" class="about-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="about-image position-relative">
                        <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="About Us" class="img-fluid rounded-3 shadow-lg">
                        <div class="experience-box">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <h3 class="counter">15+</h3>
                                        <p>Năm kinh nghiệm</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <h3 class="counter">50+</h3>
                                        <p>Bác sĩ chuyên môn</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <h3 class="counter">10k+</h3>
                                        <p>Bệnh nhân hài lòng</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center">
                                        <h3 class="counter">24/7</h3>
                                        <p>Hỗ trợ khẩn cấp</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-content ps-lg-5">
                        <span class="badge bg-primary mb-3">Về chúng tôi</span>
                        <h2 class="section-title mb-4">Phòng Khám ABC - Nơi Chăm Sóc Sức Khỏe Toàn Diện</h2>
                        <p class="lead mb-4">Với hơn 15 năm kinh nghiệm trong lĩnh vực y tế, chúng tôi tự hào là địa chỉ tin cậy cho việc chăm sóc sức khỏe của bạn và gia đình.</p>
                        
                        <div class="about-features mb-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="feature-item d-flex align-items-start">
                                        <div class="icon-box me-3">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div>
                                            <h4>Đội ngũ chuyên môn cao</h4>
                                            <p>Đội ngũ y bác sĩ giàu kinh nghiệm, được đào tạo bài bản</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="feature-item d-flex align-items-start">
                                        <div class="icon-box me-3">
                                            <i class="fas fa-hospital"></i>
                                        </div>
                                        <div>
                                            <h4>Cơ sở vật chất hiện đại</h4>
                                            <p>Trang thiết bị y tế tiên tiến, phòng khám khang trang</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="feature-item d-flex align-items-start">
                                        <div class="icon-box me-3">
                                            <i class="fas fa-heart"></i>
                                        </div>
                                        <div>
                                            <h4>Dịch vụ tận tâm</h4>
                                            <p>Chăm sóc bệnh nhân chu đáo, tận tình</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="feature-item d-flex align-items-start">
                                        <div class="icon-box me-3">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div>
                                            <h4>Phục vụ 24/7</h4>
                                            <p>Luôn sẵn sàng hỗ trợ bạn mọi lúc, mọi nơi</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="about-cta">
                            <a href="#booking" class="btn btn-primary btn-lg d-inline-flex align-items-center">
                                <i class="far fa-calendar-alt me-2"></i>
                                Đặt lịch khám ngay
                            </a>
                            <a href="#contact" class="btn btn-outline-primary btn-lg ms-3 d-inline-flex align-items-center">
                                <i class="fas fa-phone-alt me-2"></i>
                                Liên hệ với chúng tôi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Carousel Section -->
    <section class="carousel-section py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Khám Phá Phòng Khám ABC</h2>
                <p class="section-subtitle">Cơ sở vật chất hiện đại, dịch vụ chuyên nghiệp</p>
            </div>

            <!-- Main Carousel -->
            <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="2"></button>
                </div>
                <div class="carousel-inner rounded-4 overflow-hidden shadow-lg">
                    <div class="carousel-item active">
                        <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80" class="d-block w-100" alt="Phòng khám">
                        <div class="carousel-caption">
                            <h3>Phòng Khám Hiện Đại</h3>
                            <p>Không gian rộng rãi, trang thiết bị tiên tiến</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80" class="d-block w-100" alt="Đội ngũ bác sĩ">
                        <div class="carousel-caption">
                            <h3>Đội Ngũ Chuyên Môn</h3>
                            <p>Bác sĩ giàu kinh nghiệm, tận tâm với bệnh nhân</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80" class="d-block w-100" alt="Dịch vụ">
                        <div class="carousel-caption">
                            <h3>Dịch Vụ Chuyên Nghiệp</h3>
                            <p>Quy trình khám chữa bệnh hiệu quả, an toàn</p>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>

            <!-- Thumbnail Carousel -->
            <div class="row mt-4">
                <div class="col-12">
                    <div id="thumbnailCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="thumbnail-item">
                                            <img src="https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80" class="img-fluid rounded" alt="Thumbnail 1">
                                            <div class="thumbnail-caption">
                                                <h5>Phòng Khám Nội</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="thumbnail-item">
                                            <img src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80" class="img-fluid rounded" alt="Thumbnail 2">
                                            <div class="thumbnail-caption">
                                                <h5>Phòng Khám Nhi</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="thumbnail-item">
                                            <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80" class="img-fluid rounded" alt="Thumbnail 3">
                                            <div class="thumbnail-caption">
                                                <h5>Phòng Xét Nghiệm</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="thumbnail-item">
                                            <img src="https://images.unsplash.com/photo-1587351021759-3e566b6af7cc?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80" class="img-fluid rounded" alt="Thumbnail 4">
                                            <div class="thumbnail-caption">
                                                <h5>Phòng Cấp Cứu</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Testimonial Carousel -->
            <div class="testimonial-carousel mt-5">
                <div class="text-center mb-4">
                    <h3 class="section-title">Đánh Giá Từ Bệnh Nhân</h3>
                </div>
                <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <div class="testimonial-item text-center">
                                <div class="testimonial-image mb-3">
                                    <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&auto=format&fit=crop&w=100&q=80" class="rounded-circle" alt="Patient">
                                </div>
                                <div class="testimonial-content">
                                    <p class="testimonial-text">"Dịch vụ rất chuyên nghiệp, bác sĩ tận tâm. Tôi rất hài lòng với quá trình điều trị tại đây."</p>
                                    <h5 class="testimonial-name">Chị Nguyễn Thị A</h5>
                                    <p class="testimonial-position">Bệnh nhân nội trú</p>
                                </div>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <div class="testimonial-item text-center">
                                <div class="testimonial-image mb-3">
                                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=100&q=80" class="rounded-circle" alt="Patient">
                                </div>
                                <div class="testimonial-content">
                                    <p class="testimonial-text">"Cơ sở vật chất hiện đại, đội ngũ y bác sĩ nhiệt tình. Tôi rất yên tâm khi điều trị tại đây."</p>
                                    <h5 class="testimonial-name">Anh Trần Văn B</h5>
                                    <p class="testimonial-position">Bệnh nhân ngoại trú</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Dịch Vụ Của Chúng Tôi</h2>
                <p class="section-subtitle">Cung cấp các dịch vụ y tế chất lượng cao</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="icon-box">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h3>Khám Nội Tổng Quát</h3>
                        <p>Kiểm tra sức khỏe toàn diện với các xét nghiệm chuyên sâu</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="icon-box">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                        <h3>Khám Chuyên Khoa</h3>
                        <p>Đội ngũ bác sĩ chuyên môn cao trong nhiều lĩnh vực</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card">
                        <div class="icon-box">
                            <i class="fas fa-ambulance"></i>
                        </div>
                        <h3>Cấp Cứu 24/7</h3>
                        <p>Dịch vụ cấp cứu khẩn cấp suốt 24 giờ</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors Section -->
    <section id="doctors" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Đội Ngũ Bác Sĩ</h2>
                <p class="section-subtitle">Những chuyên gia y tế hàng đầu</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="doctor-card">
                        <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Doctor" class="img-fluid rounded">
                        <div class="doctor-info">
                            <h3>BS. Nguyễn Văn A</h3>
                            <p class="specialty">Chuyên khoa Nội</p>
                            <p class="experience">Hơn 15 năm kinh nghiệm</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="doctor-card">
                        <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Doctor" class="img-fluid rounded">
                        <div class="doctor-info">
                            <h3>BS. Trần Thị B</h3>
                            <p class="specialty">Chuyên khoa Nhi</p>
                            <p class="experience">Hơn 10 năm kinh nghiệm</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="doctor-card">
                        <img src="https://images.unsplash.com/photo-1594824476967-48c8b964273f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Doctor" class="img-fluid rounded">
                        <div class="doctor-info">
                            <h3>BS. Lê Văn C</h3>
                            <p class="specialty">Chuyên khoa Ngoại</p>
                            <p class="experience">Hơn 12 năm kinh nghiệm</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-choose-us py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Tại Sao Chọn Chúng Tôi</h2>
                <p class="section-subtitle">Những lý do để bạn tin tưởng</p>
            </div>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="feature-card text-center">
                        <i class="fas fa-user-md fa-3x text-primary mb-3"></i>
                        <h4>Bác sĩ chuyên môn cao</h4>
                        <p>Đội ngũ y bác sĩ giàu kinh nghiệm</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-card text-center">
                        <i class="fas fa-hospital fa-3x text-primary mb-3"></i>
                        <h4>Cơ sở vật chất hiện đại</h4>
                        <p>Trang thiết bị y tế tiên tiến</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-card text-center">
                        <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                        <h4>Phục vụ 24/7</h4>
                        <p>Luôn sẵn sàng hỗ trợ bạn</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-card text-center">
                        <i class="fas fa-heart fa-3x text-primary mb-3"></i>
                        <h4>Chăm sóc tận tâm</h4>
                        <p>Đặt sức khỏe của bạn lên hàng đầu</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="booking-section py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Đặt Lịch Khám Trực Tuyến</h2>
                <p class="section-subtitle">Điền thông tin để đặt lịch khám nhanh chóng và tiện lợi</p>
            </div>

            <!-- Toast Notification -->
            <div class="toast-container position-fixed bottom-0 end-0 p-3">
                <div id="successToast" class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            Đăng ký lịch khám thành công! Bệnh viện sẽ sớm liên hệ với bạn qua email hoặc số điện thoại, mong bạn chú ý.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
                <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body"></div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form id="bookingForm" class="row g-4 max-w-2xl mx-auto bg-white p-8 rounded-xl shadow-lg">
                <div class="col-md-6">
                    <label for="full_name" class="form-label fw-medium">Họ và tên</label>
                    <input type="text" name="full_name" id="full_name" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                </div>

                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label fw-medium">Ngày sinh</label>
                    <input type="date" name="date_of_birth" id="date_of_birth" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                </div>

                <div class="col-md-6">
                    <label for="gender" class="form-label fw-medium">Giới tính</label>
                    <select name="gender" id="gender" required class="form-select bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                        <option value="">Chọn giới tính</option>
                        <option value="Male">Nam</option>
                        <option value="Female">Nữ</option>
                        <option value="Other">Khác</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="address" class="form-label fw-medium">Địa chỉ</label>
                    <input type="text" name="address" id="address" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-medium">Email</label>
                    <input type="email" name="email" id="email" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label fw-medium">Số điện thoại</label>
                    <input type="tel" name="phone" id="phone" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                </div>

                <div class="col-md-6">
                    <label for="department_id" class="form-label fw-medium">Khoa khám</label>
                    <select name="department_id" id="department_id" required class="form-select bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                        <option value="">Chọn khoa</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['department_id']; ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="appointment_date" class="form-label fw-medium">Ngày khám</label>
                    <input type="datetime-local" name="appointment_date" id="appointment_date" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200">
                </div>

                <div class="col-12">
                    <label for="symptoms" class="form-label fw-medium">Triệu chứng</label>
                    <textarea name="symptoms" id="symptoms" rows="5" required class="form-control bg-light border-0 px-4 py-3 rounded-lg focus:ring-2 focus:ring-primary focus:bg-white transition duration-200"></textarea>
                </div>

                <div class="col-12 text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-6 py-3 rounded-lg">Đăng ký</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Liên Hệ</h2>
                <p class="section-subtitle">Chúng tôi luôn sẵn sàng hỗ trợ bạn</p>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="contact-info">
                        <div class="contact-item mb-4">
                            <i class="fas fa-map-marker-alt text-primary me-3"></i>
                            <div>
                                <h5>Địa chỉ</h5>
                                <p>123 Đường ABC, Quận XYZ, TP. HCM</p>
                            </div>
                        </div>
                        <div class="contact-item mb-4">
                            <i class="fas fa-phone-alt text-primary me-3"></i>
                            <div>
                                <h5>Điện thoại</h5>
                                <p>(012) 345 6789</p>
                            </div>
                        </div>
                        <div class="contact-item mb-4">
                            <i class="fas fa-envelope text-primary me-3"></i>
                            <div>
                                <h5>Email</h5>
                                <p>info@phongkhamabc.com</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock text-primary me-3"></i>
                            <div>
                                <h5>Giờ làm việc</h5>
                                <p>Thứ 2 - Thứ 6: 8:00 - 17:00<br>
                                Thứ 7: 8:00 - 12:00<br>
                                Chủ nhật: Nghỉ</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="map-container">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.4241677414477!2d106.6981!3d10.7756!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTDCsDQ2JzMyLjEiTiAxMDbCsDQxJzUzLjIiRQ!5e0!3m2!1svi!2s!4v1620000000000!5m2!1svi!2s" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- News Section -->
    <section id="news" class="news-section py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Tin Tức Y Tế</h2>
                <p class="section-subtitle">Cập nhật những thông tin mới nhất về sức khỏe</p>
            </div>

            <div class="row g-4">
                <!-- Featured News -->
                <div class="col-lg-6">
                    <div class="featured-news">
                        <div class="card border-0 shadow-lg h-100">
                            <div class="position-relative">
                                <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" class="card-img-top" alt="Featured News">
                                <div class="news-date">
                                    <span class="day">15</span>
                                    <span class="month">Th3</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="news-meta mb-3">
                                    <span class="me-3"><i class="far fa-user me-2"></i>Admin</span>
                                    <span><i class="far fa-comments me-2"></i>5 Comments</span>
                                </div>
                                <h3 class="card-title">Những Điều Cần Biết Về Tiêm Chủng COVID-19</h3>
                                <p class="card-text">Bài viết cung cấp thông tin chi tiết về các loại vaccine COVID-19, lịch tiêm chủng và những lưu ý quan trọng trước và sau khi tiêm...</p>
                                <a href="news-detail.html" class="btn btn-primary">Đọc thêm</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- News Grid -->
                <div class="col-lg-6">
                    <div class="row g-4">
                        <!-- News Item 1 -->
                        <div class="col-md-6">
                            <div class="news-card">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" class="img-fluid" alt="News 1">
                                    <div class="news-date">
                                        <span class="day">10</span>
                                        <span class="month">Th3</span>
                                    </div>
                                </div>
                                <div class="news-content">
                                    <h4>Chế Độ Dinh Dưỡng Cho Người Cao Tuổi</h4>
                                    <p>Những lời khuyên về chế độ ăn uống khoa học cho người cao tuổi...</p>
                                    <a href="news-detail2.html" class="read-more">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>

                        <!-- News Item 2 -->
                        <div class="col-md-6">
                            <div class="news-card">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1584982751601-97dcc096659c?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" class="img-fluid" alt="News 2">
                                    <div class="news-date">
                                        <span class="day">05</span>
                                        <span class="month">Th3</span>
                                    </div>
                                </div>
                                <div class="news-content">
                                    <h4>Phòng Ngừa Bệnh Tim Mạch</h4>
                                    <p>Các biện pháp phòng ngừa và dấu hiệu cảnh báo sớm của bệnh tim mạch...</p>
                                    <a href="news-detail3.html" class="read-more">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>

                        <!-- News Item 3 -->
                        <div class="col-md-6">
                            <div class="news-card">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" class="img-fluid" alt="News 3">
                                    <div class="news-date">
                                        <span class="day">01</span>
                                        <span class="month">Th3</span>
                                    </div>
                                </div>
                                <div class="news-content">
                                    <h4>Tầm Quan Trọng Của Khám Sức Khỏe Định Kỳ</h4>
                                    <p>Vì sao bạn nên khám sức khỏe định kỳ và những xét nghiệm cần thiết...</p>
                                    <a href="news-detail4.html" class="read-more">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>

                        <!-- News Item 4 -->
                        <div class="col-md-6">
                            <div class="news-card">
                                <div class="news-image">
                                    <img src="https://images.unsplash.com/photo-1584982751601-97dcc096659c?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" class="img-fluid" alt="News 4">
                                    <div class="news-date">
                                        <span class="day">25</span>
                                        <span class="month">Th2</span>
                                    </div>
                                </div>
                                <div class="news-content">
                                    <h4>Chăm Sóc Sức Khỏe Tâm Thần</h4>
                                    <p>Các phương pháp giảm stress và chăm sóc sức khỏe tâm thần hiệu quả...</p>
                                    <a href="news-detail5.html" class="read-more">Đọc thêm <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- News Categories -->
            <div class="news-categories mt-5">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="category-card text-center">
                            <i class="fas fa-heartbeat fa-3x text-primary mb-3"></i>
                            <h4>Sức Khỏe Tim Mạch</h4>
                            <p>12 bài viết</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="category-card text-center">
                            <i class="fas fa-brain fa-3x text-primary mb-3"></i>
                            <h4>Sức Khỏe Tâm Thần</h4>
                            <p>8 bài viết</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="category-card text-center">
                            <i class="fas fa-baby fa-3x text-primary mb-3"></i>
                            <h4>Chăm Sóc Trẻ Em</h4>
                            <p>15 bài viết</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="category-card text-center">
                            <i class="fas fa-utensils fa-3x text-primary mb-3"></i>
                            <h4>Dinh Dưỡng</h4>
                            <p>10 bài viết</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<section id="pricing" class="pricing-section py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Bảng Giá Dịch Vụ</h2>
            <p class="section-subtitle">Thông tin chi phí các dịch vụ y tế tại Phòng Khám ABC</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover bg-white rounded shadow-lg">
                <thead class="bg-primary text-white">
                    <tr>
                        <th scope="col" class="px-4 py-3">Dịch Vụ</th>
                        <th scope="col" class="px-4 py-3 text-end">Giá</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-4 py-3">Tai Mũi Họng</td>
                        <td class="px-4 py-3 text-end">140.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Tai Mũi Họng (Theo Yêu Cầu Bác Sĩ)</td>
                        <td class="px-4 py-3 text-end">160.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Bệnh Theo Yêu Cầu (CKI)</td>
                        <td class="px-4 py-3 text-end">180.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Bệnh Theo Yêu Cầu (CKII)</td>
                        <td class="px-4 py-3 text-end">230.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Da Liễu</td>
                        <td class="px-4 py-3 text-end">140.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Nội</td>
                        <td class="px-4 py-3 text-end">140.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Nội (Theo Yêu Cầu Bác Sĩ)</td>
                        <td class="px-4 py-3 text-end">160.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Ngoại</td>
                        <td class="px-4 py-3 text-end">50.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Ngoại (Theo Yêu Cầu Bác Sĩ)</td>
                        <td class="px-4 py-3 text-end">140.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Y Học Cổ Truyền (YHCT)</td>
                        <td class="px-4 py-3 text-end">50.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Y Học Cổ Truyền (Theo Yêu Cầu Bác Sĩ)</td>
                        <td class="px-4 py-3 text-end">140.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Mắt</td>
                        <td class="px-4 py-3 text-end">50.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Khám Phụ Sản (Theo Yêu Cầu Bác Sĩ)</td>
                        <td class="px-4 py-3 text-end">160.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Nội Soi Màng Phổi Sinh Thiết</td>
                        <td class="px-4 py-3 text-end">5.830.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Nội Soi Màng Phổi Sinh Thiết (Người Thở Máy)</td>
                        <td class="px-4 py-3 text-end">2.350.000đ</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Tìm Ký Sinh Trùng Sốt Rét Trong Máu</td>
                        <td class="px-4 py-3 text-end">39.000đ</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="mb-3">Phòng Khám ABC</h5>
                    <p>Chúng tôi cam kết mang đến dịch vụ y tế chất lượng cao nhất cho bạn và gia đình.</p>
                    <div class="social-links mt-3">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Liên kết nhanh</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light text-decoration-none">Trang chủ</a></li>
                        <li><a href="#services" class="text-light text-decoration-none">Dịch vụ</a></li>
                        <li><a href="#doctors" class="text-light text-decoration-none">Bác sĩ</a></li>
                        <li><a href="#about" class="text-light text-decoration-none">Giới thiệu</a></li>
                        <li><a href="#contact" class="text-light text-decoration-none">Liên hệ</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Đăng ký nhận tin</h5>
                    <p>Nhận thông tin về các chương trình khuyến mãi và tin tức y tế mới nhất.</p>
                    <form class="mt-3">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Email của bạn">
                            <button class="btn btn-primary" type="submit">Đăng ký</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">© 2024 Phòng Khám ABC. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('bookingForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(form);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'index.php', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Hiển thị toast thành công
                                const toastEl = document.getElementById('successToast');
                                const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
                                toast.show();
                                // Reset form
                                form.reset();
                            } else {
                                // Hiển thị toast lỗi
                                const errorToastEl = document.getElementById('errorToast');
                                errorToastEl.querySelector('.toast-body').textContent = response.error || 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                                const toast = new bootstrap.Toast(errorToastEl, { delay: 5000 });
                                toast.show();
                            }
                        } catch (e) {
                            // Hiển thị toast lỗi nếu parse JSON thất bại
                            const errorToastEl = document.getElementById('errorToast');
                            errorToastEl.querySelector('.toast-body').textContent = 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            const toast = new bootstrap.Toast(errorToastEl, { delay: 5000 });
                            toast.show();
                        }
                    } else {
                        // Hiển thị toast lỗi nếu request thất bại
                        const errorToastEl = document.getElementById('errorToast');
                        errorToastEl.querySelector('.toast-body').textContent = 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                        const toast = new bootstrap.Toast(errorToastEl, { delay: 5000 });
                        toast.show();
                    }
                };

                xhr.onerror = function() {
                    // Hiển thị toast lỗi nếu có lỗi mạng
                    const errorToastEl = document.getElementById('errorToast');
                    errorToastEl.querySelector('.toast-body').textContent = 'Lỗi kết nối. Vui lòng kiểm tra mạng và thử lại.';
                    const toast = new bootstrap.Toast(errorToastEl, { delay: 5000 });
                    toast.show();
                };

                xhr.send(formData);
            });
        });
    </script>
</body>
</html>