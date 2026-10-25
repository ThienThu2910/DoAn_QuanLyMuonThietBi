<?php
require_once 'config.php';
checkLogin();

// 1. Logic lọc, tìm kiếm & sắp xếp dữ liệu
$search = trim($_GET['search'] ?? '');
$category_id = $_GET['cat'] ?? '';

$sortOptions = [
    'new'   => 'd.id DESC',
    'name'  => 'd.name ASC',
    'avail' => 'd.available_qty DESC, d.name ASC',
];
$sort = $_GET['sort'] ?? 'new';
if (!isset($sortOptions[$sort])) {
    $sort = 'new';
}

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
$borrowedQty = max(0, $totalQty - $totalAvailable);

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Số mẫu thiết bị theo từng danh mục (hiện trên các tab)
$catCounts = [];
foreach ($pdo->query("SELECT category_id, COUNT(*) AS c FROM devices GROUP BY category_id")->fetchAll() as $row) {
    $catCounts[$row['category_id']] = (int)$row['c'];
}
$totalModels = array_sum($catCounts);

// Phiếu đang chờ duyệt: admin xem toàn hệ thống, sinh viên chỉ xem phiếu của mình
$isAdminUser = ($_SESSION['user']['role'] === 'admin');
if ($isAdminUser) {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'")->fetchColumn();
    $pendingUrl   = '/admin/requests.php?status=pending';
    $pendingNote  = 'Cần bạn xem xét';
} else {
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'pending'");
    $pendingStmt->execute([$_SESSION['user']['id']]);
    $pendingCount = (int)$pendingStmt->fetchColumn();
    $pendingUrl   = '/my_borrows.php?status=pending';
    $pendingNote  = 'Phiếu của bạn đang chờ';
}

// Cấu hình phân trang (9 thiết bị mỗi trang)
$perPage = 9;
$totalPages = max(1, (int)ceil($totalDevicesCount / $perPage));
$page = min($totalPages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$sql = "SELECT d.*, c.name as category_name " . $sqlBase . " ORDER BY " . $sortOptions[$sort] . " LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

// 2. Chuẩn bị dữ liệu hiển thị
$tints = ['lilac', 'peach', 'blue', 'mint', 'sand', 'pink'];
$cards = [];
foreach ($devices as $i => $dev) {
    $avail = (int)$dev['available_qty'];
    $cards[] = [
        'id'        => $dev['id'],
        'code'      => $dev['code'],
        'name'      => $dev['name'],
        'cat'       => $dev['category_name'],
        'desc'      => !empty($dev['description']) ? $dev['description'] : 'Thiết bị phục vụ nghiên cứu và thực hành tiêu chuẩn.',
        'image_key' => $dev['image_key'],
        'avail'     => $avail,
        'avail_pad' => str_pad((string)$avail, 2, '0', STR_PAD_LEFT),
        'total'     => (int)$dev['quantity'],
        'tint'      => $tints[$dev['id'] % 6],
        'delay'     => $i,
    ];
}

// Liên kết giữ nguyên các bộ lọc đang chọn
$buildUrl = function (array $override) use ($search, $category_id, $sort) {
    $q = ['search' => $search, 'cat' => $category_id, 'sort' => $sort];
    $q = array_merge($q, $override);
    if ($q['search'] === '') unset($q['search']);
    if ($q['cat'] === '') unset($q['cat']);
    if ($q['sort'] === 'new') unset($q['sort']);
    if (isset($q['page']) && $q['page'] <= 1) unset($q['page']);
    return '/index.php' . ($q ? '?' . http_build_query($q) : '');
};

$tabs = [[
    'name'   => 'Tất cả thiết bị',
    'count'  => $totalModels,
    'url'    => $buildUrl(['cat' => '', 'page' => 1]),
    'active' => ($category_id === '') ? 'is-active' : '',
]];
foreach ($categories as $cat) {
    $tabs[] = [
        'name'   => $cat['name'],
        'count'  => $catCounts[$cat['id']] ?? 0,
        'url'    => $buildUrl(['cat' => (string)$cat['id'], 'page' => 1]),
        'active' => ((string)$category_id === (string)$cat['id']) ? 'is-active' : '',
    ];
}

$pages = [];
for ($i = 1; $i <= $totalPages; $i++) {
    $pages[] = ['n' => $i, 'url' => $buildUrl(['page' => $i]), 'active' => ($i === $page) ? 'is-active' : ''];
}
$prevUrl = $page > 1 ? $buildUrl(['page' => $page - 1]) : '';
$nextUrl = $page < $totalPages ? $buildUrl(['page' => $page + 1]) : '';
$rangeFrom = $totalDevicesCount > 0 ? $offset + 1 : 0;
$rangeTo = $offset + count($devices);
$resetUrl = '/index.php';
?>
<?php require_once __DIR__ . '/ui.php'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#F5F8F6">
    <title>Kho thiết bị • Alt F4 Hub — Quản Lý Mượn Thiết Bị Phòng Lab</title>
    <?php include __DIR__ . '/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<main class="container page">

    <!-- Giới thiệu hệ thống -->
    <section class="lg-hero" id="intro" aria-labelledby="introTitle">
        <div class="lg-hero-copy">
            <div class="eyebrow"><span></span>Alt F4 Hub · Quản lý thiết bị phòng Lab</div>
            <h1 id="introTitle">Mượn thiết bị phòng Lab<br><em>nhanh gọn, rõ ràng.</em></h1>
            <p class="lead">Alt F4 Hub giúp sinh viên tra cứu kho thiết bị theo thời gian thực, đăng ký phiếu mượn trực tuyến và theo dõi hạn trả — còn quản trị viên duyệt phiếu, kiểm soát tồn kho và lưu biên bản minh chứng an toàn trên MinIO S3.</p>
            <div class="lg-hero-cta">
                <a class="btn-primary" href="#kho">Khám phá kho thiết bị <?= ic('arrow', 17) ?></a>
                <a class="secondary" href="#loi-tat"><?= ic('grid', 17) ?>Lối tắt nhanh</a>
            </div>
            <ul class="lg-hero-facts">
                <li><?= ic('clock', 17) ?>Đăng ký trong vài bước</li>
                <li><?= ic('bell', 17) ?>Nhắc hạn trả</li>
                <li><?= ic('cloud', 17) ?>Minh chứng lưu bền vững</li>
            </ul>
        </div>
        <div class="lg-hero-art" aria-hidden="true"><?php include __DIR__ . '/hero_art.php'; ?></div>
    </section>

    <!-- Lối tắt: gom các đường dẫn tới từng phần của hệ thống -->
    <section class="lg-links" id="loi-tat" aria-label="Lối tắt tới các phần của hệ thống">
        <div class="section-heading">
            <div>
                <h2>Đi đến nhanh</h2>
                <p>Chọn một khu vực để chuyển thẳng tới đó.</p>
            </div>
        </div>

        <div class="lg-fan">
            <a class="lg-glass" style="--r:-5" href="#kho" data-text="Xem kho ↓">
                <span class="lg-glass-icon"><?= ic('box', 24) ?></span>
                <b>Kho thiết bị</b>
                <small>Tìm và chọn thiết bị cần mượn.</small>
            </a>
            <a class="lg-glass" style="--r:0" href="/my_borrows.php" data-text="Mở trang →">
                <span class="lg-glass-icon"><?= ic('list', 24) ?></span>
                <b>Phiếu mượn của tôi</b>
                <small>Theo dõi trạng thái và hạn trả.</small>
                <?php if ($navMine > 0): ?><em class="lg-badge"><?= (int)$navMine ?></em><?php endif; ?>
            </a>
            <a class="lg-glass" style="--r:5" href="/profile.php" data-text="Mở trang →">
                <span class="lg-glass-icon"><?= ic('user', 24) ?></span>
                <b>Hồ sơ cá nhân</b>
                <small>Thông tin tài khoản, đổi mật khẩu.</small>
            </a>
        </div>

        <?php if ($isAdminUser): ?>
        <p class="lg-sub">Quản trị</p>
        <div class="lg-tiles">
            <a class="lg-tile" href="/admin/index.php"><span class="lg-tile-icon"><?= ic('grid', 22) ?></span><div><b>Dashboard</b><small>Tổng quan hệ thống</small></div><?= ic('chevron', 16) ?></a>
            <a class="lg-tile" href="/admin/devices.php"><span class="lg-tile-icon"><?= ic('box', 22) ?></span><div><b>Quản lý thiết bị</b><small>Thêm, sửa, tồn kho</small></div><?= ic('chevron', 16) ?></a>
            <a class="lg-tile" href="/admin/requests.php"><span class="lg-tile-icon"><?= ic('clock', 22) ?></span><div><b>Duyệt phiếu mượn</b><small><?= $navPending > 0 ? (int)$navPending . ' phiếu đang chờ' : 'Không có phiếu chờ' ?></small></div><?= ic('chevron', 16) ?></a>
            <a class="lg-tile" href="/admin/users.php"><span class="lg-tile-icon"><?= ic('users', 22) ?></span><div><b>Người dùng</b><small>Tài khoản &amp; phân quyền</small></div><?= ic('chevron', 16) ?></a>
            <a class="lg-tile" href="/admin/categories.php"><span class="lg-tile-icon"><?= ic('tag', 22) ?></span><div><b>Danh mục</b><small>Nhóm thiết bị</small></div><?= ic('chevron', 16) ?></a>
            <a class="lg-tile" href="/admin/reports.php"><span class="lg-tile-icon"><?= ic('chart', 22) ?></span><div><b>Báo cáo &amp; thống kê</b><small>Số liệu mượn – trả</small></div><?= ic('chevron', 16) ?></a>
            <a class="lg-tile" href="http://localhost:9001" target="_blank" rel="noopener"><span class="lg-tile-icon"><?= ic('cloud', 22) ?></span><div><b>MinIO Console</b><small>Kho lưu trữ tệp S3</small></div><?= ic('external', 16) ?></a>
        </div>
        <?php endif; ?>
    </section>

    <section class="page-heading" id="kho">
        <div>
            <div class="eyebrow"><span></span>Sẵn sàng cho ý tưởng tiếp theo</div>
            <h1>Khám phá kho thiết bị</h1>
            <p>Đúng thiết bị, đúng thời điểm. Cùng biến ý tưởng thành hiện thực.</p>
        </div>
        <a class="secondary" href="/my_borrows.php"><?= ic('list', 17) ?>Phiếu mượn của tôi<?= ic('arrow', 17) ?></a>
    </section>

    <section class="stats" aria-label="Tổng quan kho">
        <div class="stat">
            <div class="stat-top"><span>Tổng thiết bị</span><span class="stat-icon purple"><?= ic('box', 19) ?></span></div>
            <strong><?= $totalQty ?></strong>
            <div class="stat-sub"><?= count($categories) ?> danh mục · <?= $totalModels ?> mẫu thiết bị</div>
        </div>
        <div class="stat">
            <div class="stat-top"><span>Sẵn sàng cho mượn</span><span class="stat-icon green"><?= ic('check', 19) ?></span></div>
            <strong><?= $totalAvailable ?></strong>
            <div class="stat-sub"><span class="tiny-dot"></span><?= $availabilityRate ?>% tổng số thiết bị</div>
            <div class="track-bar" role="progressbar" aria-valuenow="<?= $availabilityRate ?>" aria-valuemin="0" aria-valuemax="100"><div class="track-fill" style="width: <?= $availabilityRate ?>%;"></div></div>
        </div>
        <div class="stat">
            <div class="stat-top"><span>Đang được mượn</span><span class="stat-icon blue"><?= ic('clock', 19) ?></span></div>
            <strong><?= $borrowedQty ?></strong>
            <div class="stat-sub">Đang phục vụ học tập</div>
        </div>
        <a class="stat stat-link" href="<?= $pendingUrl ?>">
            <div class="stat-top"><span>Chờ phê duyệt</span><span class="stat-icon amber"><?= ic('list', 19) ?></span></div>
            <strong><?= $pendingCount ?></strong>
            <div class="stat-sub"><?= $pendingNote ?><span class="stat-go"><?= ic('arrow', 15) ?></span></div>
        </a>
    </section>

    <section class="notice">
        <span class="notice-symbol"><?= ic('help', 21) ?></span>
        <div>
            <b>Một chút lưu ý, một trải nghiệm tốt hơn.</b>
            <p>Vui lòng kiểm tra tình trạng thiết bị và trả đúng hạn để mọi người cùng sử dụng. Biên bản minh chứng được lưu bền vững trên MinIO S3.</p>
        </div>
        <span class="notice-tag">Mượn có trách nhiệm</span>
    </section>

    <div class="section-heading">
        <div>
            <h2>Thiết bị phòng Lab <span><?= $totalDevicesCount ?></span></h2>
            <p>Tìm và chọn thiết bị phù hợp với nhu cầu của bạn.</p>
        </div>
        <a class="text-button" href="<?= $resetUrl ?>">Xem tất cả <?= ic('arrow', 16) ?></a>
    </div>

    <!-- Bộ lọc & tìm kiếm -->
    <form class="filters" method="GET" action="/index.php" role="search">
        <input type="hidden" name="cat" value="<?= t_esc($category_id) ?>">
        <label class="search">
            <?= ic('search', 19) ?>
            <input type="search" name="search" aria-label="Tìm thiết bị" placeholder="Tìm tên thiết bị, mã thiết bị..." value="<?= t_esc($search) ?>">
            <kbd>/</kbd>
        </label>
        <select name="sort" aria-label="Sắp xếp" onchange="this.form.submit()">
            <option value="new"<?= $sort === 'new' ? ' selected' : '' ?>>Mới nhất</option>
            <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Tên A – Z</option>
            <option value="avail"<?= $sort === 'avail' ? ' selected' : '' ?>>Còn nhiều nhất</option>
        </select>
        <button type="submit" class="btn-search">Tìm</button>
        <div class="view-toggle" role="group" aria-label="Kiểu hiển thị">
            <button type="button" data-view="grid" class="selected" aria-label="Dạng lưới" aria-pressed="true"><?= ic('grid', 18) ?></button>
            <button type="button" data-view="list" aria-label="Dạng danh sách" aria-pressed="false"><?= ic('list', 18) ?></button>
        </div>
    </form>

    <nav class="category-tabs" aria-label="Danh mục thiết bị">
        <?php foreach ($tabs as $tab): ?>
            <a class="<?= $tab['active'] ?>" href="<?= $tab['url'] ?>"><?= t_esc($tab['name']) ?><span><?= $tab['count'] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <!-- Lưới thẻ thiết bị -->
    <div class="devices" id="devices">
        <?php if (empty($cards)): ?>
            <div class="empty">
                <?= ic('search', 34) ?>
                <p>Không có thiết bị nào phù hợp với điều kiện tìm kiếm.</p>
                <a class="text-button" href="<?= $resetUrl ?>">Đặt lại bộ lọc</a>
            </div>
        <?php endif; ?>

        <?php foreach ($cards as $card): ?>
            <article class="device-card">
                <div class="device-image tint-<?= $card['tint'] ?>">
                    <?php if ($card['avail'] > 0): ?>
                        <span class="availability"><span></span>Có thể mượn</span>
                    <?php else: ?>
                        <span class="availability unavailable"><span></span>Đã hết thiết bị</span>
                    <?php endif; ?>
                    <a class="expand" href="/device_detail.php?id=<?= $card['id'] ?>" aria-label="Chi tiết <?= t_esc($card['name']) ?>"><?= ic('external', 14) ?></a>
                    <?php if ($card['image_key']): ?>
                        <img src="/view_file.php?key=<?= urlencode($card['image_key']) ?>" alt="<?= t_esc($card['name']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <i class="bi bi-cpu device-placeholder" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="device-info">
                    <div class="device-meta"><span><?= t_esc($card['cat']) ?></span><span><?= t_esc($card['code']) ?></span></div>
                    <h3><a href="/device_detail.php?id=<?= $card['id'] ?>"><?= t_esc($card['name']) ?></a></h3>
                    <p><?= t_esc($card['desc']) ?></p>
                    <div class="device-bottom">
                        <div>
                            <span class="<?= $card['avail'] > 0 ? 'stock' : 'no-stock' ?>"><?= $card['avail_pad'] ?></span>
                            <span class="stock-total"> / <?= $card['total'] ?> khả dụng</span>
                        </div>
                        <?php if ($card['avail'] > 0): ?>
                            <a class="btn-borrow" href="/borrow.php?device_id=<?= $card['id'] ?>">Mượn ngay <?= ic('arrow', 15) ?></a>
                        <?php else: ?>
                            <button type="button" class="btn-borrow" disabled>Tạm hết</button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="results-footer">
        <span>Hiển thị <b><?= $rangeFrom ?>–<?= $rangeTo ?></b> / <?= $totalDevicesCount ?> thiết bị</span>
        <?php if ($totalPages > 1): ?>
            <nav class="pager" aria-label="Phân trang">
                <?php if ($prevUrl): ?><a href="<?= $prevUrl ?>" aria-label="Trang trước" class="pager-arrow pager-prev"><?= ic('chevron', 15) ?></a><?php endif; ?>
                <?php foreach ($pages as $p): ?>
                    <a href="<?= $p['url'] ?>" class="<?= $p['active'] ?>"><?= $p['n'] ?></a>
                <?php endforeach; ?>
                <?php if ($nextUrl): ?><a href="<?= $nextUrl ?>" aria-label="Trang sau" class="pager-arrow"><?= ic('chevron', 15) ?></a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>

    <footer class="page-footer">
        <span><strong>Alt F4</strong> • Đại học Bình Dương phân hiệu Cà Mau</span>
        <span>Hạ tầng lưu trữ: MinIO S3 Object Storage • Mạng ảo hóa Docker Compose</span>
        <span class="page-footer-course">Điện toán đám mây</span>
    </footer>
</main>
</body>
</html>
