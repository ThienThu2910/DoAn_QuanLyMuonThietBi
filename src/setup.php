<?php
require_once 'config.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Đồng bộ hệ thống</title></head>
    <body style="font-family:sans-serif;max-width:620px;margin:8vh auto;padding:24px;color:#18332E">
        <h1>Đồng bộ cấu trúc hệ thống</h1>
        <p>Thao tác này bổ sung schema còn thiếu và tạo các tài khoản thử nghiệm chưa tồn tại. Dữ liệu hiện có sẽ được giữ nguyên.</p>
        <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button style="padding:12px 20px;border:0;border-radius:10px;background:#14795F;color:white;font-weight:700;cursor:pointer">Đồng bộ ngay</button></form>
        <p><a href="/admin/index.php">Quay lại trang quản trị</a></p>
    </body></html>
    <?php
    exit;
}
app_require_csrf();

try {
    // Chuyển schema cũ sang cột password dạng văn bản cho bản demo.
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('password_hash', $userCols) && !in_array('password', $userCols)) {
        $pdo->exec("ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL");
    }
    // Thêm tài khoản sinh viên thử nghiệm mà không tạo trùng khi chạy setup nhiều lần.
    $seedStudent = $pdo->prepare("INSERT IGNORE INTO users (fullname, student_code, email, password, role) VALUES (?, ?, ?, ?, 'student')");
    for ($i = 1; $i <= 10; $i++) {
        $number = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $seedStudent->execute([
            "Sinh viên thử nghiệm {$number}",
            'SVTEST' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            "student.test{$number}@example.com",
            app_password_hash('123456'),
        ]);
    }

    // Hash mật khẩu cũ từng lưu trực tiếp, vẫn giữ nguyên nội dung mật khẩu hiện tại.
    $legacyPasswords = $pdo->query('SELECT id, password FROM users')->fetchAll();
    $savePassword = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    foreach ($legacyPasswords as $account) {
        if (empty(password_get_info((string)$account['password'])['algo'])) {
            $savePassword->execute([app_password_hash((string)$account['password']), $account['id']]);
        }
    }

    // Chặn thao tác xóa danh mục/thiết bị làm mất dữ liệu con và lịch sử mượn.
    $cascadeForeignKeys = $pdo->query("
        SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
               k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
        JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
          ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         AND r.TABLE_NAME = k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND r.DELETE_RULE = 'CASCADE'
          AND ((k.TABLE_NAME = 'devices' AND k.COLUMN_NAME = 'category_id')
            OR (k.TABLE_NAME = 'borrow_requests' AND k.COLUMN_NAME = 'device_id'))
    ")->fetchAll();
    $safeIdentifier = static fn(string $name): string => '`' . str_replace('`', '``', $name) . '`';
    foreach ($cascadeForeignKeys as $fk) {
        $table = $safeIdentifier($fk['TABLE_NAME']);
        $oldConstraint = $safeIdentifier($fk['CONSTRAINT_NAME']);
        $newConstraint = $safeIdentifier($fk['TABLE_NAME'] === 'devices' ? 'fk_devices_category_restrict' : 'fk_borrow_requests_device_restrict');
        $column = $safeIdentifier($fk['COLUMN_NAME']);
        $refTable = $safeIdentifier($fk['REFERENCED_TABLE_NAME']);
        $refColumn = $safeIdentifier($fk['REFERENCED_COLUMN_NAME']);
        $pdo->exec("ALTER TABLE {$table} DROP FOREIGN KEY {$oldConstraint}, ADD CONSTRAINT {$newConstraint} FOREIGN KEY ({$column}) REFERENCES {$refTable} ({$refColumn}) ON DELETE RESTRICT");
    }

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
} catch (Throwable $e) {
    error_log('System setup failed: ' . $e->getMessage());
    http_response_code(500);
    die('Không thể đồng bộ hệ thống. Vui lòng kiểm tra log máy chủ hoặc liên hệ quản trị viên.');
}
