<?php
require_once '../config.php';
require_once '../minio_helper.php';
checkAdmin();

$msg = '';
$err = '';

// Xử lý thêm thiết bị mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $catId = (int)$_POST['category_id'];
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $qty = (int)$_POST['quantity'];
    $desc = trim($_POST['description']);
    $imageKey = null;

    if (empty($code) || empty($name) || $qty < 1) {
        $err = "Vui lòng nhập đầy đủ mã, tên thiết bị và số lượng lớn hơn 0.";
    } else {
        if (isset($_FILES['device_image']) && $_FILES['device_image']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['device_image']['tmp_name'];
            $origName = $_FILES['device_image']['name'];
            $fileSize = $_FILES['device_image']['size'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $mimeType = mime_content_type($tmpFile);

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $objectKey = "devices/" . uniqid('dev_') . "." . $ext;
                $minio = new MinioClient();
                if ($minio->uploadObject($objectKey, file_get_contents($tmpFile), $mimeType)) {
                    $imageKey = $objectKey;
                    $pdo->prepare("INSERT INTO file_metadata (original_name, object_key, file_type, file_size, entity_type) VALUES (?, ?, ?, ?, 'device')")->execute([$origName, $objectKey, $mimeType, $fileSize]);
                }
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO devices (category_id, code, name, quantity, available_qty, description, image_key) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$catId, $code, $name, $qty, $qty, $desc, $imageKey]);
            $msg = "Đã thêm thiết bị mới vào kho thành công!";
        } catch (Exception $e) {
            $err = "Mã thiết bị đã tồn tại hoặc lỗi dữ liệu: " . $e->getMessage();
        }
    }
}

// Xử lý xóa thiết bị
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$delId]);
        $msg = "Đã xóa thiết bị khỏi hệ thống.";
    } catch (Exception $e) {
        $err = "Không thể xóa: Thiết bị đang có lịch sử mượn liên kết!";
    }
}

$devices = $pdo->query("SELECT d.*, c.name as category_name FROM devices d JOIN categories c ON d.category_id = c.id ORDER BY d.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Kho Thiết Bị • Admin</title>
    <style>
        body { 
            background-color: #E6EDF6; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #020C21; 
            margin: 0; 
            padding-bottom: 60px; 
        }
        .container { max-width: 1240px; margin: 0 auto; padding: 0 16px; }
        .glass-box { 
            background: rgba(255, 255, 255, 0.82); 
            border: 1px solid rgba(255, 255, 255, 0.92); 
            border-radius: 26px; 
            padding: 28px; 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 10px 30px rgba(15, 24, 47, 0.04); 
            margin-bottom: 25px; 
            contain: content;
        }
        .form-label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #334155; }
        .form-control { 
            width: 100%; padding: 10px 14px; border-radius: 12px; 
            border: 1px solid #CBD5E1; background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box; font-family: inherit; font-size: 0.9rem; outline: none;
        }
        .form-control:focus { border-color: #2563EB; }
        .btn-add { 
            background: #0F182F; color: #fff; padding: 11px 24px; border-radius: 999px; 
            border: none; font-weight: 700; cursor: pointer; font-size: 0.9rem; width: 100%;
            transition: all 0.15s ease;
        }
        .btn-add:hover { background: #1E293B; transform: translateY(-1px); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(206, 213, 224, 0.6); }
        th { font-size: 0.8rem; text-transform: uppercase; color: #64748B; font-weight: 700; letter-spacing: 0.05em; }

        .btn-action-pill {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 6px 14px; border-radius: 999px; text-decoration: none;
            font-size: 0.8rem; font-weight: 700; margin-right: 6px;
            transition: all 0.15s ease;
        }
        .btn-view-detail { background: rgba(37, 99, 235, 0.1); color: #2563EB; }
        .btn-view-detail:hover { background: #2563EB; color: #fff; }

        .btn-edit-device { background: rgba(15, 24, 47, 0.08); color: #0F182F; }
        .btn-edit-device:hover { background: #0F182F; color: #fff; }

        .btn-delete { background: #FEE2E2; color: #DC2626; }
        .btn-delete:hover { background: #DC2626; color: #fff; }
        
        .alert { padding: 12px 20px; border-radius: 14px; margin-bottom: 20px; font-weight: 600; font-size: 0.9rem; }
    </style>
    <link rel="stylesheet" href="/assets/theme.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.css') ?>"><script src="/assets/theme.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.js') ?>"></script>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.03em;">Quản Lý Kho Thiết Bị</h1>
        <p style="color: #64748B; margin: 6px 0 0; font-size: 0.95rem;">Đăng tải thiết bị mới, giám sát tồn kho và lưu trữ ảnh chụp lên MinIO S3</p>
    </div>

    <?php if ($msg): ?><div class="alert" style="background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert" style="background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- 1. Form Thêm Thiết Bị -->
    <div class="glass-box">
        <h3 style="margin-top: 0; margin-bottom: 18px; font-size: 1.25rem; font-weight: 700;">+ Thêm Thiết Bị Mới Vào Kho</h3>
        <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(3, 1fr) 1fr; gap: 16px; align-items: end;">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="form-label">Mã thiết bị</label>
                <input type="text" name="code" class="form-control" placeholder="VD: IOT-ESP32-01" required>
            </div>
            <div>
                <label class="form-label">Tên thiết bị</label>
                <input type="text" name="name" class="form-control" placeholder="VD: Board NodeMCU ESP32" required>
            </div>
            <div>
                <label class="form-label">Danh mục</label>
                <select name="category_id" class="form-control">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Số lượng tổng</label>
                <input type="number" name="quantity" class="form-control" min="1" value="5" required>
            </div>
            <div style="grid-column: span 2;">
                <label class="form-label">Mô tả thông số chi tiết</label>
                <input type="text" name="description" class="form-control" placeholder="Thông số kỹ thuật, cáp kết nối hoặc linh kiện kèm theo">
            </div>
            <div>
                <label class="form-label">Ảnh thiết bị (Lưu MinIO S3)</label>
                <input type="file" name="device_image" class="form-control" accept="image/*">
            </div>
            <div>
                <button type="submit" class="btn-add"><i class="bi bi-cloud-arrow-up me-1"></i> Thêm Vào Kho</button>
            </div>
        </form>
    </div>

    <!-- 2. Danh Sách Thiết Bị Trong Kho -->
    <div class="glass-box">
        <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.25rem; font-weight: 700;">Danh Sách Trong Kho (<?= count($devices) ?>)</h3>
        <table>
            <thead>
                <tr>
                    <th>Ảnh S3</th>
                    <th>Mã / Tên Thiết Bị</th>
                    <th>Danh Mục</th>
                    <th>Tổng Kho</th>
                    <th>Khả Dụng</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $d): ?>
                    <tr>
                        <td style="width: 70px;">
                            <?php if ($d['image_key']): ?>
                                <img src="/view_file.php?key=<?= urlencode($d['image_key']) ?>" 
                                     style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(0,0,0,0.08);" 
                                     loading="lazy" 
                                     decoding="async">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #E2E8F0; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #64748B;"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="font-size: 0.98rem;"><?= htmlspecialchars($d['name']) ?></strong><br>
                            <code style="font-size: 0.8rem; color: #2563EB;"><?= htmlspecialchars($d['code']) ?></code>
                        </td>
                        <td><?= htmlspecialchars($d['category_name']) ?></td>
                        <td><?= $d['quantity'] ?> món</td>
                        <td>
                            <strong style="color: <?= $d['available_qty'] > 0 ? '#059669' : '#DC2626' ?>; font-size: 1.05rem;">
                                <?= $d['available_qty'] ?>
                            </strong>
                        </td>
                        <td>
                            <a href="/device_detail.php?id=<?= $d['id'] ?>" class="btn-action-pill btn-view-detail">
                                <i class="bi bi-eye"></i> Chi tiết
                            </a>
                            <a href="/admin/device_edit.php?id=<?= $d['id'] ?>" class="btn-action-pill btn-edit-device">
                                <i class="bi bi-pencil-square"></i> Sửa
                            </a>
                            <a href="?delete=<?= $d['id'] ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa thiết bị này khỏi hệ thống?')" class="btn-action-pill btn-delete">
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