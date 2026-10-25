<?php
require_once 'config.php';

// Nếu đã đăng nhập thì chuyển hướng về trang chủ
if (isset($_SESSION['user'])) {
    header("Location: /index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname     = trim($_POST['fullname'] ?? '');
    $student_code = trim($_POST['student_code'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $re_password  = $_POST['re_password'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ các trường bắt buộc!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Định dạng email không hợp lệ!";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có độ dài từ 6 ký tự trở lên!";
    } elseif ($password !== $re_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra xem email hoặc mã sinh viên đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR (student_code IS NOT NULL AND student_code = ?)");
        $stmt->execute([$email, $student_code]);
        if ($stmt->fetch()) {
            $error = "Email hoặc mã sinh viên này đã được đăng ký trên hệ thống!";
        } else {
            // Băm mật khẩu an toàn và lưu vào cơ sở dữ liệu
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $role = 'student'; // Mặc định tài khoản đăng ký mới là sinh viên

            try {
                $insert = $pdo->prepare("INSERT INTO users (fullname, student_code, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$fullname, !empty($student_code) ? $student_code : null, $email, $passwordHash, $role]);
                $success = "Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.";
            } catch (Exception $e) {
                $error = "Lỗi hệ thống: " . $e->getMessage();
            }
        }
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
    <title>Đăng ký tài khoản • Alt F4 Hub</title>
    <?php include __DIR__ . '/head.php'; ?>
</head>
<body class="auth-body">
<div class="auth">
    <?php include __DIR__ . '/auth_aside.php'; ?>

    <main class="auth-main">
        <button type="button" class="t-icon-btn t-theme-btn t-theme-fab" data-theme-toggle aria-label="Đổi giao diện sáng / tối" title="Đổi giao diện sáng / tối"><?= ic('sun', 19) ?><?= ic('moon', 19) ?></button>

        <div class="auth-card">
            <a class="auth-logo" href="/login.php"><span class="t-brand-icon"><?= ic('box', 22) ?></span><b>Alt F4<span class="auth-brand-hub"> Hub</span></b></a>

            <h1>Đăng ký tài khoản</h1>
            <p class="auth-sub">Tạo tài khoản sinh viên để mượn thiết bị phòng Lab.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= ic('alert', 18) ?><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    <?= ic('check-circle', 18) ?>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <a class="btn-submit auth-cta" href="/login.php">Đến trang đăng nhập <?= ic('arrow', 18) ?></a>
            <?php else: ?>

            <form method="POST" class="auth-form">
                <label class="field">
                    <span>Họ và tên *</span>
                    <input type="text" name="fullname" class="form-control" placeholder="VD: Nguyễn Văn A" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" autocomplete="name" required>
                </label>
                <div class="field-row">
                    <label class="field">
                        <span>Mã sinh viên</span>
                        <input type="text" name="student_code" class="form-control" placeholder="VD: 21000123" value="<?= htmlspecialchars($_POST['student_code'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Email *</span>
                        <input type="email" name="email" class="form-control" placeholder="sinhvien@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
                    </label>
                </div>
                <div class="field-row">
                    <label class="field">
                        <span>Mật khẩu * <small>(tối thiểu 6 ký tự)</small></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" autocomplete="new-password" minlength="6" required>
                    </label>
                    <label class="field">
                        <span>Xác nhận mật khẩu *</span>
                        <input type="password" name="re_password" class="form-control" placeholder="••••••••" autocomplete="new-password" required>
                    </label>
                </div>
                <button type="submit" class="btn-submit">Tạo tài khoản <?= ic('arrow', 18) ?></button>
            </form>
            <?php endif; ?>

            <p class="auth-alt">Đã có tài khoản? <a href="/login.php">Đăng nhập tại đây</a></p>
        </div>
    </main>
</div>
</body>
</html>