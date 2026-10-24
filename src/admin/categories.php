<?php
require_once '../config.php';
checkAdmin();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description'] ?? '');
    if (!empty($name)) {
        $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)")->execute([$name, $desc]);
        $msg = "Đã thêm danh mục mới thành công!";
    }
}

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$delId]);
        $msg = "Đã xóa danh mục thành công.";
    } catch (Exception $e) {
        $err = "Không thể xóa: Danh mục đang có thiết bị trực thuộc!";
    }
}

$categories = $pdo->query("SELECT c.*, COUNT(d.id) as device_count FROM categories c LEFT JOIN devices d ON c.id = d.category_id GROUP BY c.id ORDER BY c.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Danh Mục • Admin</title>
    <style>
        body { 
            background-color: #E6EDF6; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #020C21; 
            margin: 0; 
            padding-bottom: 60px; 
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 16px; }
        .glass-box { 
            background: rgba(255, 255, 255, 0.78); 
            border: 1px solid rgba(255, 255, 255, 0.9); 
            border-radius: 28px; 
            padding: 30px; 
            backdrop-filter: blur(40px); 
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05); 
            margin-bottom: 30px; 
        }
        .form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #334155; }
        .form-control { 
            width: 100%; padding: 10px 14px; border-radius: 12px; 
            border: 1px solid #CBD5E1; background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box; font-family: inherit; font-size: 0.9rem; outline: none;
        }
        .form-control:focus { border-color: #2563EB; }
        .btn-primary { 
            background: #0F182F; color: #fff; padding: 11px 24px; border-radius: 999px; 
            border: none; font-weight: 700; cursor: pointer; transition: all 0.2s ease;
        }
        .btn-primary:hover { background: #1E293B; transform: translateY(-2px); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(206, 213, 224, 0.6); }
        th { font-size: 0.8rem; text-transform: uppercase; color: #64748B; font-weight: 700; letter-spacing: 0.05em; }
        .btn-delete { 
            display: inline-flex; align-items: center; gap: 4px;
            background: #FEE2E2; color: #DC2626; padding: 6px 14px; 
            border-radius: 999px; text-decoration: none; font-size: 0.82rem; font-weight: 700;
        }
        .btn-delete:hover { background: #DC2626; color: #fff; }
        .alert { padding: 12px 20px; border-radius: 14px; margin-bottom: 20px; font-weight: 600; font-size: 0.9rem; }
    </style>
    <link rel="stylesheet" href="/assets/theme.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.css') ?>"><script src="/assets/theme.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.js') ?>"></script>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.03em;">Nhóm Phân Loại Danh Mục</h1>
        <p style="color: #64748B; margin: 6px 0 0; font-size: 0.95rem;">Quản lý các nhóm thiết bị phòng thực hành (IoT, Mạng, Laptop, Trình chiếu)</p>
    </div>

    <?php if ($msg): ?><div class="alert" style="background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert" style="background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- 1. Form Thêm Danh Mục -->
    <div class="glass-box">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.2rem; font-weight: 700;">+ Thêm Danh Mục Mới</h3>
        <form method="POST" style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 15px; align-items: end;">
            <div>
                <label class="form-label">Tên danh mục *</label>
                <input type="text" name="name" class="form-control" placeholder="VD: Kính VR & AR" required>
            </div>
            <div>
                <label class="form-label">Mô tả mục đích sử dụng</label>
                <input type="text" name="description" class="form-control" placeholder="Thiết bị thực hành cho đồ án thực tế ảo">
            </div>
            <div>
                <button type="submit" class="btn-primary">+ Thêm Danh Mục</button>
            </div>
        </form>
    </div>

    <!-- 2. Danh Sách Các Danh Mục Hiện Có -->
    <div class="glass-box">
        <table>
            <thead>
                <tr>
                    <th>Tên Danh Mục</th>
                    <th>Mô Tả Chi Tiết</th>
                    <th>Thiết Bị Trực Thuộc</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong style="font-size: 1rem;"><?= htmlspecialchars($cat['name']) ?></strong></td>
                        <td style="color: #64748B; font-size: 0.9rem;"><?= htmlspecialchars($cat['description'] ?? 'Chưa có mô tả') ?></td>
                        <td><strong style="color: #2563EB; font-size: 1rem;"><?= $cat['device_count'] ?></strong> thiết bị</td>
                        <td>
                            <a href="?delete=<?= $cat['id'] ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')" class="btn-delete">
                                <i class="bi bi-trash"></i> Xóa
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>