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
<?php require_once __DIR__ . '/ui.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#F5F8F6">
    <title>Đăng nhập • Alt F4 Hub</title>
    <?php include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="/assets/loader.css?v=<?= @filemtime(__DIR__ . '/assets/loader.css') ?>">
</head>
<body class="auth-body">
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST') { include __DIR__ . '/loader.php'; }   // loading ~3 giây trước khi vào đăng nhập (không hiện lại khi gửi form sai) ?>
<div class="auth">
    <?php include __DIR__ . '/auth_aside.php'; ?>

    <main class="auth-main">
        <button type="button" class="t-icon-btn t-theme-btn t-theme-fab" data-theme-toggle aria-label="Đổi giao diện sáng / tối" title="Đổi giao diện sáng / tối"><?= ic('sun', 19) ?><?= ic('moon', 19) ?></button>

        <div class="auth-card">
            <a class="auth-logo" href="/login.php"><span class="t-brand-icon"><?= ic('box', 22) ?></span><b>Alt F4<span class="auth-brand-hub"> Hub</span></b></a>

            <h1>Đăng nhập</h1>
            <p class="auth-sub">Chào mừng trở lại. Đăng nhập để mượn và theo dõi thiết bị phòng Lab.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= ic('alert', 18) ?><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" class="form-control" placeholder="ten@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="username" required autofocus>
                </label>
                <label class="field">
                    <span>Mật khẩu</span>
                    <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                </label>
                <button type="submit" class="btn-submit">Đăng nhập <?= ic('arrow', 18) ?></button>
            </form>

            <p class="auth-alt">Chưa có tài khoản? <a href="/register.php">Đăng ký tài khoản mới</a></p>

            <div class="demo-box">
                <p class="demo-title">Tài khoản demo <small>(bấm để điền nhanh)</small></p>
                <div class="demo-list">
                    <button type="button" class="demo-chip" data-fill-email="admin@gmail.com" data-fill-pass="admin123">
                        <span class="demo-role">Admin</span><code>admin@gmail.com</code><code>admin123</code>
                    </button>
                    <button type="button" class="demo-chip" data-fill-email="student@gmail.com" data-fill-pass="123456">
                        <span class="demo-role">Sinh viên</span><code>student@gmail.com</code><code>123456</code>
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>