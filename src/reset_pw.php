<?php
require_once 'config.php';
echo "Số tài khoản trong DB: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . "<br>";
$up = $pdo->prepare("UPDATE users SET password_hash=? WHERE email=?");
$up->execute([password_hash('admin123', PASSWORD_BCRYPT), 'admin@gmail.com']);
echo "admin: " . $up->rowCount() . " dòng được cập nhật<br>";
$up->execute([password_hash('123456', PASSWORD_BCRYPT), 'student@gmail.com']);
echo "student: " . $up->rowCount() . " dòng được cập nhật";