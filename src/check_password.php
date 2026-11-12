<?php

require_once __DIR__ . '/config.php';

$email = 'admin@gmail.com';
$password = 'admin123';

$stmt = $pdo->prepare(
    'SELECT password FROM users WHERE email = ?'
);
$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo 'Không tìm thấy tài khoản.';
} elseif ($password === $user['password']) {
    echo 'Mật khẩu khớp.';
} else {
    echo 'Mật khẩu không khớp.';
}
