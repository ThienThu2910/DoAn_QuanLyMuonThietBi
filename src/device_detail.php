<?php
require_once 'config.php';
checkLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT d.*, c.name as category_name, c.description as category_desc
    FROM devices d
    JOIN categories c ON d.category_id = c.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Thiết bị không tồn tại!</h2><a href='/index.php'>Quay lại danh sách</a></div>");
}

// Thống kê số lượt mượn thành công của thiết bị
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE device_id = ? AND status IN ('approved', 'returned')");
$stmtCount->execute([$id]);
$borrowTurnCount = $stmtCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Thiết Bị • <?= htmlspecialchars($device['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --ink: #020C21; --muted: #59627E; --frame: #E6EDF6; --cta: #0F1B31;
            --accent: #2563EB; --glass-bg: rgba(255, 255, 255, 0.78);
            --glass-border: rgba(255, 255, 255, 0.9); --glass-shadow: 0 16px 40px rgba(15, 24, 47, 0.05);
        }
        body {
            background-color: var(--frame); margin: 0; padding-bottom: 60px;
            font-family: 'Plus Jakarta Sans', sans-serif; color: var(--ink);
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
        .detail-card {
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            border-radius: 32px; padding: 40px; backdrop-filter: blur(40px);
            box-shadow: var(--glass-shadow); display: grid;
            grid-template-columns: 1fr 1.2fr; gap: 40px; align-items: start;
        }
        .image-preview-plate {
            width: 100%; height: 380px; border-radius: 24px;
            background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(255, 255, 255, 0.9);
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            box-shadow: inset 0 0 20px rgba(15, 24, 47, 0.03);
        }
        .image-preview-plate img { width: 100%; height: 100%; object-fit: cover; }
        .badge-category {
            display: inline-block; background: rgba(37, 99, 235, 0.1); color: var(--accent);
            padding: 5px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;
            text-transform: uppercase; margin-bottom: 12px;
        }
        .device-title { font-size: 2.2rem; font-weight: 800; letter-spacing: -0.03em; margin: 0 0 10px 0; }
        .specs-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
            margin: 24px 0; padding: 20px 0; border-top: 1px solid rgba(206, 213, 224, 0.6);
            border-bottom: 1px solid rgba(206, 213, 224, 0.6);
        }
        .spec-item p { margin: 0; font-size: 0.8rem; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .spec-item h4 { margin: 4px 0 0 0; font-size: 1.25rem; font-weight: 700; }
        .btn-borrow-now {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            background: var(--cta); color: #fff; padding: 14px 32px; border-radius: 999px;
            text-decoration: none; font-weight: 700; font-size: 0.95rem; transition: all 0.2s ease;
        }
        .btn-borrow-now:hover { background: #1E293B; transform: translateY(-2px); }
        @media (max-width: 860px) {
            .detail-card { grid-template-columns: 1fr; gap: 25px; padding: 24px; }
            .image-preview-plate { height: 260px; }
        }
    </style>
    <link rel="stylesheet" href="/assets/theme.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.css') ?>"><script src="/assets/theme.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.js') ?>"></script>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <a href="/index.php" style="color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.9rem;">
            <i class="bi bi-arrow-left"></i> Quay lại kho thiết bị
        </a>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="/admin/devices.php" style="color: #64748B; text-decoration: none; font-size: 0.88rem; font-weight: 600;">
                <i class="bi bi-gear-fill me-1"></i> Trang quản trị kho
            </a>
        <?php endif; ?>
    </div>

    <div class="detail-card">
        <!-- Khối hiển thị hình ảnh MinIO S3 -->
        <div>
            <div class="image-preview-plate">
                <?php if ($device['image_key']): ?>
                    <img src="/view_file.php?key=<?= urlencode($device['image_key']) ?>" alt="<?= htmlspecialchars($device['name']) ?>">
                <?php else: ?>
                    <i class="bi bi-cpu" style="font-size: 5rem; color: #94A3B8; opacity: 0.4;"></i>
                <?php endif; ?>
            </div>
            <div style="margin-top: 14px; text-align: center; font-size: 0.82rem; color: var(--muted);">
                <i class="bi bi-shield-check me-1"></i> Ảnh xác thực lưu trữ trên MinIO Object Storage
            </div>
        </div>

        <!-- Khối thông tin chi tiết -->
        <div>
            <span class="badge-category"><?= htmlspecialchars($device['category_name']) ?></span>
            <div style="color: var(--muted); font-size: 0.88rem; font-weight: 600; margin-bottom: 4px;">Mã quản lý: <code><?= htmlspecialchars($device['code']) ?></code></div>
            <h1 class="device-title"><?= htmlspecialchars($device['name']) ?></h1>
            <p style="color: var(--muted); font-size: 0.95rem; line-height: 1.6; margin: 0;">
                <?= nl2br(htmlspecialchars($device['description'] ?? 'Chưa có thông số chi tiết cho thiết bị này.')) ?>
            </p>

            <div class="specs-grid">
                <div class="spec-item">
                    <p>Khả dụng trong kho</p>
                    <h4 style="color: <?= $device['available_qty'] > 0 ? '#059669' : '#DC2626' ?>;">
                        <?= $device['available_qty'] ?> / <?= $device['quantity'] ?> món
                    </h4>
                </div>
                <div class="spec-item">
                    <p>Lượt mượn tích lũy</p>
                    <h4><?= $borrowTurnCount ?> lượt</h4>
                </div>
                <div class="spec-item">
                    <p>Trạng thái</p>
                    <h4 style="color: <?= $device['available_qty'] > 0 ? '#059669' : '#DC2626' ?>; font-size: 1rem;">
                        <?= $device['available_qty'] > 0 ? '• Sẵn sàng mượn' : '• Tạm hết thiết bị' ?>
                    </h4>
                </div>
                <div class="spec-item">
                    <p>Nhóm danh mục</p>
                    <h4 style="font-size: 1rem;"><?= htmlspecialchars($device['category_name']) ?></h4>
                </div>
            </div>

            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if ($device['available_qty'] > 0): ?>
                    <a href="/borrow.php?device_id=<?= $device['id'] ?>" class="btn-borrow-now">
                        <i class="bi bi-journal-plus me-1"></i> Đăng Ký Mượn Thiết Bị Này
                    </a>
                <?php else: ?>
                    <button class="btn-borrow-now" style="opacity: 0.5; cursor: not-allowed;" disabled>
                        Tạm Hết Thiết Bị
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

</body>
</html>