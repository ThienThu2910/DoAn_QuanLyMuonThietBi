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
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản • Alt F4 Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --ink: #020C21;
            --muted: #59627E;
            --frame: #E6EDF6;
            --cta: #0F1B31;
            --cta-knob: #384B64;
            --accent: #2563EB;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.9);
            --glass-shadow: 0 20px 45px rgba(15, 24, 47, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--frame);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            position: relative;
        }
        .ambient-glow {
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background: 
                radial-gradient(circle at 15% 20%, rgba(255, 255, 255, 0.8) 0%, transparent 40%),
                radial-gradient(circle at 85% 15%, rgba(186, 215, 248, 0.45) 0%, transparent 45%),
                linear-gradient(180deg, #E6EDF6 0%, #DCE5F0 100%);
        }
        .register-card {
            position: relative; z-index: 2;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 40px;
            width: 100%; max-width: 480px;
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            box-shadow: var(--glass-shadow);
        }
        .brand-badge {
            width: 48px; height: 48px; border-radius: 14px;
            background: linear-gradient(135deg, #0F182F, #2563EB);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin: 0 auto 16px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
        }
        .card-title { text-align: center; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 6px; }
        .card-desc { text-align: center; font-size: 0.88rem; color: var(--muted); margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 6px; color: #334155; }
        .form-control {
            width: 100%; padding: 12px 16px; border-radius: 12px;
            border: 1px solid #CBD5E1; background: rgba(255, 255, 255, 0.85);
            font-family: inherit; font-size: 0.92rem; outline: none;
            transition: all 0.2s ease;
        }
        .form-control:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
        .btn-submit {
            width: 100%; background: var(--cta); color: #fff;
            padding: 13px 20px; border-radius: 999px; border: none;
            font-family: inherit; font-weight: 700; font-size: 0.95rem;
            cursor: pointer; transition: all 0.2s ease; margin-top: 10px;
            box-shadow: 0 6px 18px rgba(15, 24, 47, 0.15);
        }
        .btn-submit:hover { background: var(--cta-knob); transform: translateY(-2px); }
        .alert {
            padding: 12px 16px; border-radius: 12px; margin-bottom: 20px;
            font-size: 0.86rem; font-weight: 500;
        }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .footer-links { text-align: center; margin-top: 22px; font-size: 0.88rem; color: var(--muted); }
        .footer-links a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="ambient-glow"></div>

<div class="register-card">
    <div class="brand-badge"><i class="bi bi-person-plus-fill"></i></div>
    <h2 class="card-title">Đăng Ký Tài Khoản</h2>
    <p class="card-desc">Tạo tài khoản để mượn thiết bị phòng Lab</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?>
            <div style="margin-top: 10px;">
                <a href="/login.php" style="color: #065F46; font-weight: 700;">Đến trang Đăng nhập ngay &rarr;</a>
            </div>
        </div>
    <?php else: ?>

    <form method="POST">
        <div class="form-group">
            <label>Họ và tên *</label>
            <input type="text" name="fullname" class="form-control" placeholder="VD: Nguyễn Văn A" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Mã sinh viên (nếu có)</label>
            <input type="text" name="student_code" class="form-control" placeholder="VD: 21000123" value="<?= htmlspecialchars($_POST['student_code'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" class="form-control" placeholder="VD: sinhvien@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label>Mật khẩu * (Tối thiểu 6 ký tự)</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <div class="form-group">
            <label>Xác nhận mật khẩu *</label>
            <input type="password" name="re_password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">
            Tạo Tài Khoản
        </button>
    </form>
    <?php endif; ?>

    <div class="footer-links">
        Đã có tài khoản? <a href="/login.php">Đăng nhập tại đây</a>
    </div>
</div>

</body>
</html>