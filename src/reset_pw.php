<?php
require_once 'config.php';
// Tương thích với CSDL chưa chạy setup.php.
$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('password_hash', $userCols) && !in_array('password', $userCols)) {
    $pdo->exec("ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL");
}
echo "Số tài khoản trong DB: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . "<br>";
$up = $pdo->prepare("UPDATE users SET password=? WHERE email=?");
$up->execute(['admin123', 'admin@gmail.com']);
echo "admin: " . $up->rowCount() . " dòng được cập nhật<br>";
$up->execute(['123456', 'student@gmail.com']);
echo "student: " . $up->rowCount() . " dòng được cập nhật";
