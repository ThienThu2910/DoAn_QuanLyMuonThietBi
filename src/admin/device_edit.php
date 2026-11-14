<?php
require_once '../config.php';
require_once '../minio_helper.php';
checkAdmin();

$id = (int)($_GET['id'] ?? 0);
$returnTo = $_GET['return'] ?? '';
if (!is_string($returnTo) || !preg_match('~^/admin/(?:devices|reports)\.php(?:#[A-Za-z0-9_-]+)?$~', $returnTo)) {
    $returnTo = '/admin/devices.php#device-' . $id;
}
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    die("Thiết bị không tồn tại! <a href='/admin/devices.php'>Quay lại</a>");
}

$msg = '';
$err = '';
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_csrf();
    $catId    = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $code     = trim(app_post_string('code'));
    $name     = trim(app_post_string('name'));
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $location = trim(app_post_string('location'));
    $status   = app_post_string('status');
    $desc     = trim(app_post_string('description'));

    if ($code === '' || $name === '' || $quantity < 1) {
        $err = 'Nhập mã, tên và tổng số lượng hợp lệ.';
    } elseif (!in_array($status, ['good', 'maintenance', 'broken', 'decommissioned'], true)) {
        $err = 'Trạng thái thiết bị không hợp lệ.';
    } else {
        $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE id = ?');
        $categoryCheck->execute([$catId]);
        if (!$categoryCheck->fetchColumn()) $err = 'Vui lòng chọn danh mục hợp lệ.';
    }

    $imageKey  = $device['image_key'];
    $manualKey = $device['manual_doc_key'];
    $newObjectKeys = [];
    $newFileMetadata = [];

    // 1. Upload ảnh mới nếu có
    if (!$err && isset($_FILES['device_image'])) {
        $upload = minio_validate_uploaded_file($_FILES['device_image'], ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'], 5 * 1024 * 1024);
        $ext = strtolower(pathinfo($_FILES['device_image']['name'], PATHINFO_EXTENSION));
        if (!$upload['valid']) {
            $err = $upload['error'];
        } elseif ($upload['mime'] !== null) {
            $newImgKey = 'devices/' . bin2hex(random_bytes(16)) . '.' . $ext;
            $minio = new MinioClient();
            if ($minio->uploadObject($newImgKey, file_get_contents($_FILES['device_image']['tmp_name']), $upload['mime'])) {
                $imageKey = $newImgKey;
                $newObjectKeys[] = $newImgKey;
                $newFileMetadata[] = [$_FILES['device_image']['name'], $newImgKey, $upload['mime'], (int)$_FILES['device_image']['size']];
            } else {
                $err = 'Không thể lưu ảnh lên MinIO. Vui lòng kiểm tra dịch vụ lưu trữ.';
            }
        }
    }

    // 2. Upload tài liệu hướng dẫn PDF nếu có
    if (!$err && isset($_FILES['manual_doc'])) {
        $upload = minio_validate_uploaded_file($_FILES['manual_doc'], ['pdf'=>'application/pdf'], 10 * 1024 * 1024);
        if (!$upload['valid']) {
            $err = $upload['error'];
        } elseif ($upload['mime'] !== null) {
            $newDocKey = 'manuals/' . bin2hex(random_bytes(16)) . '.pdf';
            $minio = new MinioClient();
            if ($minio->uploadObject($newDocKey, file_get_contents($_FILES['manual_doc']['tmp_name']), 'application/pdf')) {
                $manualKey = $newDocKey;
                $newObjectKeys[] = $newDocKey;
                $newFileMetadata[] = [$_FILES['manual_doc']['name'], $newDocKey, 'application/pdf', (int)$_FILES['manual_doc']['size']];
            } else {
                $err = 'Không thể lưu tài liệu lên MinIO. Vui lòng kiểm tra dịch vụ lưu trữ.';
            }
        }
    }

    if ($err && $newObjectKeys) {
        foreach ($newObjectKeys as $objectKey) (new MinioClient())->deleteObject($objectKey);
    }
    if (!$err) {
        try {
            $pdo->beginTransaction();
            $stock = $pdo->prepare('SELECT quantity, available_qty FROM devices WHERE id = ? FOR UPDATE');
            $stock->execute([$id]);
            $currentStock = $stock->fetch();
            if (!$currentStock) throw new RuntimeException('Thiết bị không còn tồn tại.');
            $checkedOut = max(0, (int)$currentStock['quantity'] - (int)$currentStock['available_qty']);
            if ($quantity < $checkedOut) {
                throw new RuntimeException("Tổng số lượng không thể thấp hơn {$checkedOut} thiết bị đang được mượn.");
            }
            $newAvailable = $quantity - $checkedOut;
            $update = $pdo->prepare("
                UPDATE devices
                SET category_id = ?, code = ?, name = ?, quantity = ?, available_qty = ?, location = ?, status = ?, description = ?, image_key = ?, manual_doc_key = ?
                WHERE id = ?
            ");
            $update->execute([$catId, $code, $name, $quantity, $newAvailable, $location, $status, $desc, $imageKey, $manualKey, $id]);
            if ($newFileMetadata) {
                $saveMetadata = $pdo->prepare("INSERT INTO file_metadata (original_name, object_key, file_type, file_size, entity_id, entity_type) VALUES (?, ?, ?, ?, ?, 'device')");
                foreach ($newFileMetadata as $metadata) $saveMetadata->execute([...$metadata, $id]);
            }
            $pdo->commit();
            $returnPath = strtok($returnTo, '#');
            $returnHash = parse_url($returnTo, PHP_URL_FRAGMENT);
            header("Location: " . $returnPath . "?msg=" . urlencode("Cập nhật thông tin thiết bị thành công!") . ($returnHash ? "#" . $returnHash : ""));
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($newObjectKeys as $objectKey) (new MinioClient())->deleteObject($objectKey);
            error_log('Device update failed: ' . $e->getMessage());
            $err = $e instanceof RuntimeException ? $e->getMessage() : 'Không thể cập nhật thiết bị. Hãy kiểm tra dữ liệu và thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Thiết Bị • <?= htmlspecialchars($device['name']) ?></title>
    <style>
        body { background-color: #E6EDF6; font-family: 'Plus Jakarta Sans', sans-serif; color: #020C21; margin: 0; padding-bottom: 60px; }
        .container { max-width: 900px; margin: 0 auto; padding: 0 16px; }
        .glass-box {
            background: rgba(255, 255, 255, 0.78); border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px; padding: 35px; backdrop-filter: blur(40px);
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05);
        }
        .form-control {
            width: 100%; padding: 11px 15px; border-radius: 12px;
            border: 1px solid #CBD5E1; background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box; font-family: inherit; font-size: 0.92rem; margin-bottom: 15px; outline: none;
        }
        .form-control:focus { border-color: #2563EB; }
        .btn-save {
            background: #0F182F; color: #fff; padding: 12px 30px; border-radius: 999px;
            border: none; font-weight: 700; cursor: pointer; font-size: 0.95rem;
        }
    </style>
    <?php include __DIR__ . '/../head.php'; ?>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0;">Chỉnh Sửa Thiết Bị</h1>
            <p style="color: #64748B; margin: 6px 0 0;">Cập nhật thông số kỹ thuật, vị trí lưu trữ và trạng thái sử dụng</p>
        </div>
        <a data-return href="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" style="color: #2563EB; text-decoration: none; font-weight: 700;">&larr; Quay lại danh sách</a>
    </div>

    <?php if ($msg): ?><div style="padding: 14px 20px; background: #D1FAE5; color: #065F46; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="padding: 14px 20px; background: #FEE2E2; color: #991B1B; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="glass-box">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 18px;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Mã thiết bị</label>
                    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($device['code']) ?>" required>
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Tên thiết bị</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($device['name']) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Danh mục</label>
                    <select name="category_id" class="form-control">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $device['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Tổng số lượng</label>
                    <input type="number" name="quantity" class="form-control" value="<?= $device['quantity'] ?>" min="1" required>
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Tình trạng thiết bị</label>
                    <select name="status" class="form-control">
                        <option value="good" <?= $device['status'] === 'good' ? 'selected' : '' ?>>Hoạt động tốt</option>
                        <option value="maintenance" <?= $device['status'] === 'maintenance' ? 'selected' : '' ?>>Đang bảo trì</option>
                        <option value="broken" <?= $device['status'] === 'broken' ? 'selected' : '' ?>>Hư hỏng</option>
                        <option value="decommissioned" <?= $device['status'] === 'decommissioned' ? 'selected' : '' ?>>Ngừng sử dụng</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Vị trí lưu trữ trong Lab</label>
                    <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($device['location'] ?? 'Phòng Lab CNTT') ?>" placeholder="VD: Tủ A2, Kệ 3, Phòng Lab 102">
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600;">Thay ảnh mới (MinIO S3)</label>
                    <input type="file" name="device_image" class="form-control" accept="image/*">
                </div>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 600;">Tài liệu hướng dẫn kỹ thuật PDF (MinIO S3)</label>
                <input type="file" name="manual_doc" class="form-control" accept=".pdf">
                <?php if ($device['manual_doc_key']): ?>
                    <p style="margin: -10px 0 15px 0; font-size: 0.82rem;">
                        <a href="/view_file.php?key=<?= urlencode($device['manual_doc_key']) ?>" target="_blank" style="color: #2563EB;">
                            <i class="bi bi-file-earmark-pdf"></i> Xem tài liệu hiện tại
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 600;">Mô tả chi tiết</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($device['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-save">
                <i class="bi bi-check2-circle me-1"></i> Lưu Cập Nhật Thiết Bị
            </button>
        </form>
    </div>
</main>

</body>
</html>
