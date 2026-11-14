<?php
require_once 'config.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>Đặt lại tài khoản demo</title></head><body style="font-family:sans-serif;max-width:620px;margin:8vh auto;padding:24px">
    <h1>Đặt lại mật khẩu demo</h1><p>Thao tác sẽ đặt lại mật khẩu hai tài khoản mẫu về giá trị công bố trong README.</p>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button type="submit">Xác nhận đặt lại</button></form>
    </body></html>
    <?php
    exit;
}
app_require_csrf();
// Tương thích với CSDL chưa chạy setup.php.
$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('password_hash', $userCols) && !in_array('password', $userCols)) {
    $pdo->exec("ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL");
}
$up = $pdo->prepare("UPDATE users SET password=? WHERE email=?");
$up->execute([app_password_hash('admin123'), 'admin@gmail.com']);
$up->execute([app_password_hash('123456'), 'student@gmail.com']);
echo 'Đã cập nhật mật khẩu demo an toàn. <a href="/admin/index.php">Về trang quản trị</a>.';
