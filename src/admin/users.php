<?php
require_once '../config.php';
checkAdmin();

$msg = '';
$err = '';

// 1. Thêm người dùng mới
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_csrf();
    $action = $_POST['action'] ?? '';
    $uid = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    if ($action === 'add') {
        $fullname = trim(app_post_string('fullname'));
        $student_code = trim(app_post_string('student_code'));
        $email = trim(app_post_string('email'));
        $role = app_post_string('role') === 'admin' ? 'admin' : 'student';
        $password = app_post_string('password');

        if (empty($fullname) || empty($email) || empty($password)) {
            $err = "Vui lòng nhập đầy đủ họ tên, email và mật khẩu.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $err = 'Email không hợp lệ hoặc mật khẩu phải có ít nhất 6 ký tự.';
        } else {
            try {
                $pdo->prepare("INSERT INTO users (fullname, student_code, email, password, role) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$fullname, !empty($student_code) ? $student_code : null, $email, app_password_hash($password), $role]);
                $msg = "Đã tạo tài khoản mới thành công!";
            } catch (Exception $e) {
                $err = "Email hoặc mã SV đã tồn tại trên hệ thống!";
            }
        }
    } elseif ($action === 'make_admin' || $action === 'make_student') {
        if ($uid === (int)$_SESSION['user']['id'] && $action === 'make_student') {
            $err = 'Bạn không thể tự hạ quyền tài khoản đang đăng nhập.';
        } else {
            $role = $action === 'make_admin' ? 'admin' : 'student';
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
            $msg = $role === 'admin' ? 'Đã cấp quyền quản trị viên.' : 'Đã chuyển tài khoản về quyền sinh viên.';
        }
    } elseif ($action === 'reset_pass') {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([app_password_hash('123456'), $uid]);
        $msg = 'Đã đặt mật khẩu mới về giá trị mặc định 123456.';
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Tài Khoản • Admin</title>
    <style>
        body { background-color: #E6EDF6; font-family: 'Plus Jakarta Sans', sans-serif; color: #020C21; margin: 0; padding-bottom: 60px; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
        .glass-box {
            background: rgba(255, 255, 255, 0.78); border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px; padding: 30px; backdrop-filter: blur(40px);
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05); margin-bottom: 25px;
        }
        .form-control {
            width: 100%; padding: 10px 14px; border-radius: 12px;
            border: 1px solid #CBD5E1; background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box; font-family: inherit; font-size: 0.9rem; margin-bottom: 12px; outline: none;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(206, 213, 224, 0.6); }
        th { font-size: 0.8rem; text-transform: uppercase; color: #64748B; font-weight: 700; }
        .btn-sm { padding: 5px 12px; border-radius: 999px; text-decoration: none; font-size: 0.78rem; font-weight: 700; display: inline-block; margin-right: 4px; }
    </style>
    <?php include __DIR__ . '/../head.php'; ?>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 2rem; font-weight: 800; margin: 0;">Quản Lý Tài Khoản Người Dùng</h1>
        <p style="color: #64748B; margin: 6px 0 0;">Phân quyền vai trò (Admin / Student) và cấp lại mật khẩu truy cập</p>
    </div>

    <?php if ($msg): ?><div style="padding: 14px 20px; background: #D1FAE5; color: #065F46; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="padding: 14px 20px; background: #FEE2E2; color: #991B1B; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Form Thêm Tài Khoản Nhanh -->
    <div class="glass-box">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 700; margin-bottom: 15px;">+ Thêm Tài Khoản Mới</h3>
        <form method="POST" style="display: grid; grid-template-columns: repeat(4, 1fr) 1fr auto; gap: 12px; align-items: end;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="add">
            <div>
                <label style="font-size: 0.82rem; font-weight: 600;">Họ và tên</label>
                <input type="text" name="fullname" class="form-control" placeholder="Nguyễn Văn B" required>
            </div>
            <div>
                <label style="font-size: 0.82rem; font-weight: 600;">Mã SV</label>
                <input type="text" name="student_code" class="form-control" placeholder="SV002">
            </div>
            <div>
                <label style="font-size: 0.82rem; font-weight: 600;">Email</label>
                <input type="email" name="email" class="form-control" placeholder="user@gmail.com" required>
            </div>
            <div>
                <label style="font-size: 0.82rem; font-weight: 600;">Mật khẩu</label>
                <input type="password" name="password" class="form-control" placeholder="123456" required>
            </div>
            <div>
                <label style="font-size: 0.82rem; font-weight: 600;">Vai trò</label>
                <select name="role" class="form-control">
                    <option value="student">Sinh viên</option>
                    <option value="admin">Quản trị viên</option>
                </select>
            </div>
            <div>
                <button type="submit" style="background: #0F182F; color: #fff; padding: 10px 20px; border-radius: 999px; border: none; font-weight: 700; cursor: pointer; margin-bottom: 12px;">+ Tạo</button>
            </div>
        </form>
    </div>

    <!-- Danh Sách Tài Khoản -->
    <div class="glass-box">
        <table>
            <thead>
                <tr>
                    <th>Họ và Tên</th>
                    <th>Mã SV</th>
                    <th>Email</th>
                    <th>Vai Trò</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['fullname']) ?></strong></td>
                        <td><code><?= htmlspecialchars($u['student_code'] ?? '—') ?></code></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <strong style="color: <?= $u['role'] === 'admin' ? '#D97706' : '#2563EB' ?>;">
                                <?= strtoupper($u['role']) ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'student'): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Nâng cấp tài khoản này lên Quản trị viên?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="make_admin"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn-sm" style="border:0;cursor:pointer;background: #FEF3C7; color: #92400E;">Lên Admin</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Chuyển tài khoản về quyền Sinh viên?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="make_student"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn-sm" style="border:0;cursor:pointer;background: #E2E8F0; color: #334155;">Về SV</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Đặt lại mật khẩu về 123456?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="reset_pass"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn-sm" style="border:0;cursor:pointer;background: #FEE2E2; color: #DC2626;">Reset Pass</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>
