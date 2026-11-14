<?php
require_once 'config.php';
checkLogin();

$user = $_SESSION['user'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_csrf();
    $current_pass = app_post_string('current_password');
    $new_pass     = app_post_string('new_password');
    $confirm_pass = app_post_string('confirm_password');

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $currentPassword = $stmt->fetchColumn();

    if (!app_password_matches($current_pass, (string)$currentPassword)) {
        $err = "Mật khẩu hiện tại không chính xác!";
    } elseif (strlen($new_pass) < 6) {
        $err = "Mật khẩu mới phải có ít nhất 6 ký tự!";
    } elseif ($new_pass !== $confirm_pass) {
        $err = "Mật khẩu xác nhận không khớp!";
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([app_password_hash($new_pass), $user['id']]);
        $msg = "Đổi mật khẩu thành công!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Cá Nhân • Alt F4</title>
    <style>
        body { background-color: #E6EDF6; font-family: 'Plus Jakarta Sans', sans-serif; color: #020C21; margin: 0; padding-bottom: 60px; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 16px; }
        .glass-box {
            background: rgba(255, 255, 255, 0.78); border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px; padding: 35px; backdrop-filter: blur(40px);
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05); margin-bottom: 25px;
        }
        .form-control {
            width: 100%; padding: 12px 16px; border-radius: 12px;
            border: 1px solid #CBD5E1; background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box; font-family: inherit; font-size: 0.95rem; margin-bottom: 15px; outline: none;
        }
        .form-control:focus { border-color: #2563EB; }
        .btn-submit {
            background: #0F182F; color: #fff; padding: 12px 28px; border-radius: 999px;
            border: none; font-weight: 700; cursor: pointer; font-size: 0.92rem;
        }
    </style>
    <?php include __DIR__ . '/head.php'; ?>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 2rem; font-weight: 800; margin: 0;">Thông Tin Cá Nhân</h1>
        <p style="color: #64748B; margin: 6px 0 0;">Quản lý thông tin tài khoản và cập nhật mật khẩu đăng nhập</p>
    </div>

    <?php if ($msg): ?><div style="padding: 14px 20px; background: #D1FAE5; color: #065F46; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="padding: 14px 20px; background: #FEE2E2; color: #991B1B; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="glass-box">
        <h3 style="margin-top: 0; font-size: 1.2rem; font-weight: 700; margin-bottom: 18px;">Thông Tin Tài Khoản</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
            <div>
                <label style="font-size: 0.85rem; color: #64748B; font-weight: 600;">Họ và tên</label>
                <div style="font-size: 1.1rem; font-weight: 700; margin-top: 4px;"><?= htmlspecialchars($user['fullname']) ?></div>
            </div>
            <div>
                <label style="font-size: 0.85rem; color: #64748B; font-weight: 600;">Mã sinh viên / Mã định danh</label>
                <div style="font-size: 1.1rem; font-weight: 700; margin-top: 4px;"><code><?= htmlspecialchars($user['student_code'] ?? 'ADMIN') ?></code></div>
            </div>
            <div>
                <label style="font-size: 0.85rem; color: #64748B; font-weight: 600;">Địa chỉ Email</label>
                <div style="font-size: 1.05rem; font-weight: 600; margin-top: 4px;"><?= htmlspecialchars($user['email']) ?></div>
            </div>
            <div>
                <label style="font-size: 0.85rem; color: #64748B; font-weight: 600;">Vai trò hệ thống</label>
                <div style="font-size: 1.05rem; font-weight: 700; margin-top: 4px; color: #2563EB;"><?= strtoupper($user['role']) ?></div>
            </div>
        </div>
    </div>

    <div class="glass-box">
        <h3 style="margin-top: 0; font-size: 1.2rem; font-weight: 700; margin-bottom: 18px;">Đổi Mật Khẩu</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label style="font-size: 0.85rem; font-weight: 600;">Mật khẩu hiện tại</label>
            <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>

            <label style="font-size: 0.85rem; font-weight: 600;">Mật khẩu mới (tối thiểu 6 ký tự)</label>
            <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>

            <label style="font-size: 0.85rem; font-weight: 600;">Xác nhận mật khẩu mới</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>

            <button type="submit" class="btn-submit">Lưu Thay Đổi Mật Khẩu</button>
        </form>
    </div>
</main>

</body>
</html>
