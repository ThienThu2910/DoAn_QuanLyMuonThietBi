<?php

require_once __DIR__ . '/config.php';

$email = 'admin@gmail.com';
$password = 'admin123';

$stmt = $pdo->prepare(
    'SELECT password_hash FROM users WHERE email = ?'
);
$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo 'Không tìm thấy tài khoản.';
} elseif (password_verify($password, $user['password_hash'])) {
    echo 'Mật khẩu khớp với hash.';
} else {
    echo 'Mật khẩu không khớp với hash.';
}