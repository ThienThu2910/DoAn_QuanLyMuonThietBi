<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = $user;
        if ($user['role'] === 'admin') {
            header("Location: /admin/index.php");
        } else {
            header("Location: /index.php");
        }
        exit;
    } else {
        $error = "Sai tài khoản hoặc mật khẩu!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Quản lý Thiết bị</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/theme.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.css') ?>"><script src="/assets/theme.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.js') ?>"></script>
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container" style="max-width: 420px;">
    <div class="card shadow border-0">
        <div class="card-body p-4">
            <h4 class="card-title text-center text-primary mb-4 fw-bold">ĐĂNG NHẬP</h4>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email:</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@gmail.com hoặc student@gmail.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu:</label>
                    <input type="password" name="password" class="form-control" placeholder="Mật khẩu" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Đăng nhập</button>
                <div style="margin-top: 20px; text-align: center; font-size: 0.88rem; color: #59627E;">
                    Chưa có tài khoản? <a href="/register.php" style="color: #2563EB; font-weight: 600; text-decoration: none;">Đăng ký tài khoản mới</a>
                </div>
            </form>
            <div class="mt-3 text-muted small">
                Demo Accounts:<br>
                - Admin: <code>admin@gmail.com</code> / <code>admin123</code><br>
                - Sinh viên: <code>student@gmail.com</code> / <code>123456</code>
            </div>
        </div>
    </div>
</div>
</body>
</html>