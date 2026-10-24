<?php
require_once 'config.php';
checkLogin();

// 1. Logic lọc & tìm kiếm dữ liệu
$search = trim($_GET['search'] ?? '');
$category_id = $_GET['cat'] ?? '';

$sqlBase = "FROM devices d JOIN categories c ON d.category_id = c.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sqlBase .= " AND (d.name LIKE ? OR d.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id !== '') {
    $sqlBase .= " AND d.category_id = ?";
    $params[] = $category_id;
}

// Tính tổng số lượng bản ghi thỏa mãn điều kiện lọc
$countStmt = $pdo->prepare("SELECT COUNT(*) " . $sqlBase);
$countStmt->execute($params);
$totalDevicesCount = (int)$countStmt->fetchColumn();

// Thống kê nhanh toàn bộ kho
$statStmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) as total_qty, COALESCE(SUM(available_qty), 0) as total_avail FROM devices");
$statData = $statStmt->fetch();
$totalQty = (int)$statData['total_qty'];
$totalAvailable = (int)$statData['total_avail'];
$availabilityRate = $totalQty > 0 ? round(($totalAvailable / $totalQty) * 100) : 0;

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Cấu hình phân trang (9 thiết bị mỗi trang)
$perPage = 9;
$totalPages = max(1, (int)ceil($totalDevicesCount / $perPage));
$page = min($totalPages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$sql = "SELECT d.*, c.name as category_name " . $sqlBase . " ORDER BY d.id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#E6EDF6">
    <title>Alt F4 Hub — Quản Lý Mượn Thiết Bị Phòng Lab</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --ink: #020C21;
            --ink-soft: #0F182F;
            --muted: #59627E;
            --muted2: #4D5B77;
            --frame: #E6EDF6;
            --cta: #0F1B31;
            --cta-knob: #384B64;
            --meet-knob: #1A2B45;
            --accent: #2563EB;
            --track: #DDE4EE;
            
            --glass-bg: rgba(255, 255, 255, 0.82);
            --glass-border: rgba(255, 255, 255, 0.92);
            --glass-shadow: 0 10px 30px rgba(15, 24, 47, 0.04);
            --glass-inset: inset 1px 1px 0 rgba(255, 255, 255, 0.75);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--frame);
            color: var(--ink);
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            padding-bottom: 50px;
        }

        /* Tối ưu hiệu suất nền GPU */
        .ambient-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            transform: translateZ(0);
            will-change: transform;
            background: 
                radial-gradient(circle at 15% 20%, rgba(255, 255, 255, 0.75) 0%, transparent 40%),
                radial-gradient(circle at 85% 15%, rgba(186, 215, 248, 0.4) 0%, transparent 45%),
                linear-gradient(180deg, #E6EDF6 0%, #DCE5F0 100%);
        }

        .main-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 16px;
            position: relative;
            z-index: 2;
        }

        .hero-section {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 40px;
            align-items: center;
            margin-bottom: 45px;
            padding-top: 10px;
        }

        .eyebrow {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 14px;
        }

        h1.hero-title {
            font-size: 3.4rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1.08;
            color: var(--ink);
            margin-bottom: 22px;
        }

        .tagrow {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .play-indicator {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 16px rgba(28, 52, 92, 0.1);
            color: var(--cta);
            flex-shrink: 0;
        }

        .tag-text {
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--ink-soft);
        }

        .stats-row {
            display: flex;
            align-items: flex-end;
            gap: 22px;
        }

        .stat-item {
            display: flex;
            align-items: flex-end;
            gap: 14px;
        }

        .stat-num {
            font-size: 4rem;
            font-weight: 300;
            line-height: 0.9;
            color: var(--ink);
            letter-spacing: -0.02em;
        }

        .stat-lbl {
            font-size: 0.86rem;
            font-weight: 600;
            line-height: 1.35;
            color: #39455F;
            padding-bottom: 6px;
        }

        .slash-divider {
            width: 18px;
            height: 55px;
            background: linear-gradient(to top left, transparent calc(50% - 1px), #A7B4C6 calc(50% - 1px), #A7B4C6 calc(50% + 1px), transparent calc(50% + 1px));
            margin-bottom: 6px;
        }

        /* Khối Panel Kính tối ưu */
        .glass-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 30px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--glass-shadow), var(--glass-inset);
            contain: content;
        }

        .panel-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .p-title-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .p-title {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--ink);
        }

        .p-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            display: inline-block;
        }

        .shield-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(28, 52, 92, 0.08);
            color: var(--accent);
            font-size: 1.4rem;
        }

        .p-sub {
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.5;
            color: var(--muted2);
            margin-bottom: 28px;
        }

        .scale-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: #586580;
            margin-bottom: 8px;
        }

        .track-bar {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: var(--track);
            overflow: hidden;
        }

        .track-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 999px;
            transition: width 0.6s ease;
        }

        /* Search Console */
        .search-console-wrapper {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .search-pill-form {
            display: flex;
            align-items: center;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 6px 8px 6px 20px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--glass-shadow);
            flex: 1;
            max-width: 620px;
        }

        .search-pill-form input {
            border: none;
            background: transparent;
            outline: none;
            color: var(--ink);
            font-size: 0.92rem;
            width: 100%;
            padding-right: 12px;
            font-family: inherit;
        }

        .search-pill-form select {
            border: none;
            background: transparent;
            outline: none;
            color: var(--muted2);
            font-size: 0.88rem;
            font-weight: 600;
            padding: 0 16px;
            border-left: 1px solid #CED5E0;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-search-pill {
            background: var(--cta);
            color: #ffffff;
            border: none;
            border-radius: 999px;
            padding: 9px 22px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-search-pill:hover {
            background: var(--cta-knob);
        }

        /* Lưới Thẻ Thiết Bị */
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
            gap: 25px;
        }

        .glass-device-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 26px;
            padding: 22px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--glass-shadow), var(--glass-inset);
            display: flex;
            flex-direction: column;
            contain: content;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .glass-device-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 35px rgba(15, 24, 47, 0.08);
        }

        .card-img-plate {
            width: 100%;
            height: 190px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.9);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 18px;
        }

        .card-img-plate img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .category-floating-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(15, 24, 47, 0.82);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 999px;
            z-index: 2;
        }

        .card-device-code {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .card-device-name {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .card-device-desc {
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-bottom-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid rgba(206, 213, 224, 0.6);
        }

        .stock-indicator {
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stock-indicator .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .btn-meet-capsule {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.95);
            padding: 5px 6px 5px 16px;
            border-radius: 999px;
            box-shadow: 0 4px 10px rgba(28, 52, 92, 0.04);
            transition: all 0.2s ease;
        }

        .btn-meet-capsule:hover {
            background: #ffffff;
            transform: translateY(-1px);
        }

        .btn-meet-capsule b {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1B2A44;
        }

        .btn-meet-capsule .knob {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--meet-knob);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        /* Phân trang Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 45px;
        }

        .page-link {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            text-decoration: none;
            color: var(--ink);
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: #ffffff;
            color: var(--accent);
        }

        .page-link.active {
            background: var(--cta);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 24, 47, 0.15);
        }

        /* Footer */
        .footer-glass-bar {
            margin-top: 60px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 999px;
            padding: 16px 32px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--glass-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.86rem;
            color: var(--muted);
            flex-wrap: wrap;
            gap: 16px;
        }

        @media (max-width: 992px) {
            .hero-section { grid-template-columns: 1fr; }
            h1.hero-title { font-size: 2.6rem; }
        }
        @media (max-width: 768px) {
            .footer-glass-bar { border-radius: 20px; flex-direction: column; text-align: center; }
        }
    </style>
    <link rel="stylesheet" href="/assets/theme.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.css') ?>"><script src="/assets/theme.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/theme.js') ?>"></script>
</head>
<body>

<div class="ambient-glow"></div>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="main-container">
    <section class="hero-section">
        <div>
            <p class="eyebrow">Hạ tầng thực hành & nghiên cứu số</p>
            <h1 class="hero-title">
                Smarter Devices.<br>
                Starts Here.
            </h1>

            <div class="tagrow">
                <span class="play-indicator">
                    <svg width="14" height="15" viewBox="0 0 13 14" fill="none">
                        <path d="M1.4 1.3 11.6 7 1.4 12.7z" fill="#0b1526"/>
                    </svg>
                </span>
                <span class="tag-text">Bảo mật thiết bị phòng Lab. Lưu trữ minh chứng trên MinIO S3.</span>
            </div>

            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-num"><?= $totalDevicesCount ?></span>
                    <span class="stat-lbl">Mẫu thiết bị<br>phù hợp</span>
                </div>
                <span class="slash-divider"></span>
                <div class="stat-item">
                    <span class="stat-num"><?= count($categories) ?></span>
                    <span class="stat-lbl">Danh mục<br>chuyên dụng</span>
                </div>
            </div>
        </div>

        <aside class="glass-panel">
            <div class="panel-top">
                <div class="p-title-group">
                    <span class="p-title">MinIO Storage</span>
                    <span class="p-dot"></span>
                </div>
                <div class="shield-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>

            <p class="p-sub">
                Object Storage Architecture<br>
                Persistent Docker Volume<br>
                Tamper-proof Handover Docs
            </p>

            <div class="scale-labels">
                <span>Tỷ lệ sẵn sàng</span>
                <span><?= $totalAvailable ?> / <?= $totalQty ?> món</span>
                <span><?= $availabilityRate ?>%</span>
            </div>
            <div class="track-bar">
                <div class="track-fill" style="width: <?= $availabilityRate ?>%;"></div>
            </div>
        </aside>
    </section>

    <!-- Bộ Lọc & Tìm Kiếm -->
    <div class="search-console-wrapper">
        <form class="search-pill-form" method="GET" action="/index.php">
            <i class="bi bi-search" style="color: var(--muted); margin-right: 12px;"></i>
            <input type="text" name="search" placeholder="Tìm theo tên hoặc mã thiết bị..." value="<?= htmlspecialchars($search) ?>">
            
            <select name="cat" onchange="this.form.submit()">
                <option value="">Tất cả danh mục</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-search-pill">Lọc</button>
        </form>

        <div style="font-size: 0.9rem; color: var(--muted); font-weight: 600;">
            Hiển thị <strong><?= count($devices) ?></strong> / <?= $totalDevicesCount ?> kết quả (Trang <?= $page ?>/<?= $totalPages ?>)
        </div>
    </div>

    <!-- Lưới Thẻ Thiết Bị -->
    <div class="devices-grid">
        <?php if (empty($devices)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--muted);">
                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.4;"></i>
                <p style="margin-top: 14px; font-weight: 600;">Không có thiết bị nào phù hợp với điều kiện tìm kiếm.</p>
                <a href="/index.php" style="color: var(--accent); font-size: 0.9rem; text-decoration: none; font-weight: 600;">Đặt lại bộ lọc</a>
            </div>
        <?php endif; ?>

        <?php foreach ($devices as $dev): ?>
            <div class="glass-device-card">
                <div class="card-img-plate">
                    <span class="category-floating-badge"><?= htmlspecialchars($dev['category_name']) ?></span>
                    <?php if ($dev['image_key']): ?>
                        <img src="/view_file.php?key=<?= urlencode($dev['image_key']) ?>" 
                             alt="<?= htmlspecialchars($dev['name']) ?>" 
                             loading="lazy" 
                             decoding="async">
                    <?php else: ?>
                        <i class="bi bi-cpu" style="font-size: 3.5rem; color: var(--muted); opacity: 0.35;"></i>
                    <?php endif; ?>
                </div>

                <div class="card-device-code"><?= htmlspecialchars($dev['code']) ?></div>
                <h3 class="card-device-name"><?= htmlspecialchars($dev['name']) ?></h3>
                <p class="card-device-desc"><?= htmlspecialchars($dev['description'] ?? 'Thiết bị phục vụ nghiên cứu và thực hành tiêu chuẩn.') ?></p>

                <div class="card-bottom-actions">
                    <?php if ($dev['available_qty'] > 0): ?>
                        <div class="stock-indicator" style="color: #059669;">
                            <span class="dot" style="background: #059669;"></span>
                            <span>Còn <?= $dev['available_qty'] ?> / <?= $dev['quantity'] ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <a href="/device_detail.php?id=<?= $dev['id'] ?>" style="color: var(--accent); font-size: 0.85rem; font-weight: 700; text-decoration: none;">
                                Chi tiết
                            </a>
                            <a class="btn-meet-capsule" href="/borrow.php?device_id=<?= $dev['id'] ?>">
                                <b>Mượn ngay</b>
                                <span class="knob">
                                    <svg width="12" height="12" viewBox="0 0 18 18" fill="none">
                                        <path d="m6.6 3.6 6 5.4-6 5.4" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="stock-indicator" style="color: #E11D48;">
                            <span class="dot" style="background: #E11D48;"></span>
                            <span>Hết thiết bị</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <a href="/device_detail.php?id=<?= $dev['id'] ?>" style="color: var(--muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">
                                Chi tiết
                            </a>
                            <button class="btn-meet-capsule" style="opacity: 0.5; cursor: not-allowed;" disabled>
                                <b>Tạm khóa</b>
                                <span class="knob" style="background: #94A3B8;">—</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Thanh Phân Trang (Chỉ hiện khi tổng số trang > 1) -->
    <?php if ($totalPages > 1): ?>
        <nav class="pagination-container">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php 
                    $queryArgs = $_GET;
                    $queryArgs['page'] = $i;
                    $pageUrl = '/index.php?' . http_build_query($queryArgs);
                ?>
                <a href="<?= $pageUrl ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

    <footer class="footer-glass-bar">
        <div>
            <strong>Alt F4</strong> • Đại học Bình Dương phân hiệu Cà Mau
        </div>
        <div>
            Hạ tầng lưu trữ: MinIO S3 Object Storage • Mạng ảo hóa Docker Compose
        </div>
        <div style="font-weight: 700; color: var(--ink);">
            Điện toán đám mây
        </div>
    </footer>
</main>

</body>
</html>