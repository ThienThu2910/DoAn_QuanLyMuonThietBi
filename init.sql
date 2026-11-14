-- Bắt buộc: ép phiên import dùng UTF-8, nếu không MySQL sẽ đọc tiếng Việt như latin1
-- và lưu thành chữ lỗi kiểu "Quáº£n trá»‹ viÃªn".
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS ql_thietbi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ql_thietbi;

-- 1. Bảng Người dùng
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    student_code VARCHAR(20) UNIQUE,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bảng Loại thiết bị
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

-- 3. Bảng Thiết bị
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    available_qty INT NOT NULL DEFAULT 0,
    description TEXT,
    image_key VARCHAR(255) NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- 4. Bảng Phiếu mượn thiết bị
CREATE TABLE IF NOT EXISTS borrow_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    borrow_date DATE NOT NULL,
    expected_return_date DATE NOT NULL,
    actual_return_date DATE NULL,
    status ENUM('pending', 'approved', 'rejected', 'returned') DEFAULT 'pending',
    handover_doc_key VARCHAR(255) NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- 5. Bảng Metadata tệp MinIO (Mục 4.2 đề bài)
CREATE TABLE IF NOT EXISTS file_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    object_key VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size BIGINT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    entity_id INT NULL,
    entity_type VARCHAR(50) NULL
);

-- Dữ liệu ban đầu
INSERT INTO users (fullname, student_code, email, password, role) VALUES 
('Quản trị viên', 'ADMIN01', 'admin@gmail.com', 'admin123', 'admin'),
('Nguyễn Văn Sinh Viên', 'SV001', 'student@gmail.com', '123456', 'student'),
('Sinh viên thử nghiệm 01', 'SVTEST001', 'student.test01@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 02', 'SVTEST002', 'student.test02@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 03', 'SVTEST003', 'student.test03@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 04', 'SVTEST004', 'student.test04@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 05', 'SVTEST005', 'student.test05@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 06', 'SVTEST006', 'student.test06@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 07', 'SVTEST007', 'student.test07@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 08', 'SVTEST008', 'student.test08@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 09', 'SVTEST009', 'student.test09@example.com', '123456', 'student'),
('Sinh viên thử nghiệm 10', 'SVTEST010', 'student.test10@example.com', '123456', 'student');

INSERT INTO categories (name, description) VALUES 
('Máy chiếu & Phụ kiện', 'Thiết bị trình chiếu phòng họp/giảng đường'),
('Thiết bị Mạng & IoT', 'Router, Switch, Arduino, Raspberry Pi'),
('Laptop thực hành', 'Laptop cấu hình mạnh dùng đồ án/thực hành');

INSERT INTO devices (category_id, code, name, quantity, available_qty, description) VALUES 
(1, 'MC-EPSON-01', 'Máy chiếu Epson EB-X06', 5, 5, 'Máy chiếu độ sáng cao 3600 ANSI Lumens'),
(2, 'IOT-RPI4-01', 'Kit Raspberry Pi 4 Model B (4GB)', 10, 10, 'Kèm nguồn Type-C, thẻ nhớ 32GB'),
(3, 'LT-DELL-01', 'Laptop Dell Precision 3560', 3, 3, 'Core i7, 16GB RAM, Quadro T500');
