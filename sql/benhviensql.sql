-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th7 05, 2025 lúc 09:41 AM
-- Phiên bản máy phục vụ: 10.4.25-MariaDB
-- Phiên bản PHP: 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `benhviensql`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `email`, `created_at`) VALUES
(1, 'admin1', '$2y$10$ZRcWSs6TkjQMhBvbNb8qP.90hfXu2WNtIiGZiMZE/AWeB5TxnVdZS', 'Quản Trị Viên', 'admin@hospital.com', '2025-07-02 08:25:39');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `appointment_date` datetime DEFAULT NULL,
  `appointment_type` varchar(50) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `status` enum('Chờ xác nhận','Đã xác nhận','Hoàn thành','Đã hủy') NOT NULL DEFAULT 'Chờ xác nhận',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `doctor_name` varchar(255) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `department_id`, `room_id`, `appointment_date`, `appointment_type`, `symptoms`, `status`, `created_at`, `doctor_name`, `patient_name`) VALUES
(7, 14, 13, 1, 1, '2025-07-05 06:22:00', '123', '', 'Đã xác nhận', '2025-07-05 06:20:18', 'Nguyễn Văn An', '123'),
(8, 14, 13, 1, 1, '2025-07-05 06:29:00', '123', '1233', 'Chờ xác nhận', '2025-07-05 06:27:27', 'Nguyễn Văn An', '123'),
(9, 14, 14, 1, 1, '2025-07-17 14:40:00', '123', '123', 'Đã xác nhận', '2025-07-05 07:40:06', 'Phát nè', '123'),
(10, 14, 14, 1, 1, '2025-07-10 14:41:00', '31', '122', 'Chờ xác nhận', '2025-07-05 07:41:33', 'Phát nè', '123');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`) VALUES
(1, 'Khoa Nội'),
(2, 'Khoa Ngoại'),
(3, 'Khoa Nhi'),
(4, 'Khoa Sản'),
(5, 'Khoa Răng'),
(7, 'Kế Toán');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `contact_phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `doctors`
--

INSERT INTO `doctors` (`doctor_id`, `full_name`, `gender`, `contact_phone`, `email`, `specialty`, `username`, `password`, `created_at`, `profile_image`, `is_deleted`) VALUES
(13, 'Nguyễn Văn An', 'Female', '0123456789', 'phat018355@gmail.com', 'Sản', '1', '$2y$10$oih3aTbuwcU5B6500OGL6eIZe7u1pJNQ0TH.lSC.4X7aklsfJHnHm', '2025-07-05 05:47:00', NULL, 1),
(14, 'Phát nè', 'Female', '0123456789', 'phat08355@gmail.com', 'Sản', '11', '$2y$10$ltSDysqLckPcF/a7ZSDwLOwMXv7S9vfpOz3m1uSI/k4VBTdw3TYBG', '2025-07-05 07:39:14', '', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `medications`
--

CREATE TABLE `medications` (
  `medication_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `medications`
--

INSERT INTO `medications` (`medication_id`, `name`, `description`, `unit`) VALUES
(1, 'Paracetamol', 'Giảm đau, hạ sốt', 'Viên'),
(2, 'Omeprazole', 'Điều trị viêm loét dạ dày', 'Viên'),
(3, 'Amoxicillin', 'Kháng sinh điều trị nhiễm khuẩn', 'Viên'),
(4, 'Ibuprofen', 'Giảm đau, chống viêm', 'Viên'),
(5, 'Paracetamol', 'Giảm đau, hạ sốt', 'Viên'),
(6, 'Omeprazole', 'Điều trị viêm loét dạ dày', 'Viên'),
(7, 'Amoxicillin', 'Kháng sinh điều trị nhiễm khuẩn', 'Ống'),
(8, 'Ibuprofen', 'Giảm đau, chống viêm', 'Viên'),
(9, 'Dexamethasone', 'Chống viêm, dị ứng', 'Ống'),
(10, 'Oral Rehydration Salts', 'Bù điện giải', 'Bịch'),
(11, '1231', 'ádasd', '123123aa1111'),
(12, 'Apple Watch', '1', '1');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `source` enum('admin','form') NOT NULL DEFAULT 'admin',
  `appointment_date` datetime DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `patients`
--

INSERT INTO `patients` (`patient_id`, `full_name`, `date_of_birth`, `gender`, `address`, `email`, `phone`, `created_at`, `source`, `appointment_date`, `department_id`, `symptoms`, `is_deleted`) VALUES
(14, '123', '2025-07-02', 'Male', '123123', 'phat08355@gmail.com', '0835512896', '2025-07-05 05:46:46', 'admin', NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescription_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `status` enum('Pending','Approved','Cancelled') DEFAULT 'Pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `doctor_name` varchar(255) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `prescriptions`
--

INSERT INTO `prescriptions` (`prescription_id`, `appointment_id`, `doctor_id`, `patient_id`, `diagnosis`, `status`, `admin_note`, `created_at`, `doctor_name`, `patient_name`) VALUES
(8, 7, 13, 14, '3113', 'Approved', '131313', '2025-07-12 07:33:00', 'Nguyễn Văn An', '123'),
(9, 9, 14, 14, 'cuee', 'Pending', NULL, '2025-07-05 07:40:36', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `prescription_details`
--

CREATE TABLE `prescription_details` (
  `detail_id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `prescription_details`
--

INSERT INTO `prescription_details` (`detail_id`, `prescription_id`, `medication_id`, `quantity`, `unit`, `instructions`) VALUES
(57, 8, 12, 1, '1', '331'),
(58, 9, 3, 1, 'Viên', '123'),
(59, 9, 11, 12312, '123123aa1111', '123123');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Đang đổ dữ liệu cho bảng `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `department_id`) VALUES
(1, 'PHÒNG 1', 1),
(2, 'Phòng 3', 2);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Chỉ mục cho bảng `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `appointments_ibfk_2` (`doctor_id`),
  ADD KEY `appointments_ibfk_1` (`patient_id`);

--
-- Chỉ mục cho bảng `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Chỉ mục cho bảng `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`medication_id`);

--
-- Chỉ mục cho bảng `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `idx_patient_email` (`email`),
  ADD KEY `idx_patient_phone` (`phone`),
  ADD KEY `department_id` (`department_id`);

--
-- Chỉ mục cho bảng `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Chỉ mục cho bảng `prescription_details`
--
ALTER TABLE `prescription_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Chỉ mục cho bảng `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `medications`
--
ALTER TABLE `medications`
  MODIFY `medication_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `prescription_details`
--
ALTER TABLE `prescription_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT cho bảng `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);

--
-- Các ràng buộc cho bảng `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Các ràng buộc cho bảng `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `prescription_details`
--
ALTER TABLE `prescription_details`
  ADD CONSTRAINT `prescription_details_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`prescription_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_details_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`medication_id`);

--
-- Các ràng buộc cho bảng `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
