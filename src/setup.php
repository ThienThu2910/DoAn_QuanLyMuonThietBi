<?php
require_once 'config.php';

try {
    // 1. Bổ sung cột cho bảng devices
    $deviceCols = $pdo->query("SHOW COLUMNS FROM devices")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('location', $deviceCols)) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN location VARCHAR(255) DEFAULT 'Phòng Lab CNTT'");
    }
    if (!in_array('status', $deviceCols)) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN status ENUM('good', 'maintenance', 'broken', 'decommissioned') DEFAULT 'good'");
    }
    if (!in_array('manual_doc_key', $deviceCols)) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN manual_doc_key VARCHAR(255) NULL");
    }

    // 2. Bổ sung cột cho bảng borrow_requests
    $requestCols = $pdo->query("SHOW COLUMNS FROM borrow_requests")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reject_reason', $requestCols)) {
        $pdo->exec("ALTER TABLE borrow_requests ADD COLUMN reject_reason TEXT NULL");
    }
    if (!in_array('handover_by', $requestCols)) {
        $pdo->exec("ALTER TABLE borrow_requests ADD COLUMN handover_by INT NULL");
    }
    if (!in_array('handover_at', $requestCols)) {
        $pdo->exec("ALTER TABLE borrow_requests ADD COLUMN handover_at DATETIME NULL");
    }
    if (!in_array('return_condition', $requestCols)) {
        $pdo->exec("ALTER TABLE borrow_requests ADD COLUMN return_condition ENUM('good', 'damaged', 'lost', 'incomplete') DEFAULT 'good'");
    }
    if (!in_array('return_note', $requestCols)) {
        $pdo->exec("ALTER TABLE borrow_requests ADD COLUMN return_note TEXT NULL");
    }

    // 3. Tạo bảng lưu vết lịch sử borrow_logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS borrow_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            actor_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>";
    echo "<h2 style='color:#059669;'>Cập nhật cấu trúc cơ sở dữ liệu thành công!</h2>";
    echo "<p>Tất cả các bảng, cột và tính năng mới đã được đồng bộ an toàn.</p>";
    echo "<a href='/index.php' style='display:inline-block; padding:10px 24px; background:#0F182F; color:#fff; border-radius:999px; text-decoration:none;'>Về trang chủ</a>";
    echo "</div>";
} catch (Exception $e) {
    die("Lỗi cập nhật CSDL: " . $e->getMessage());
}