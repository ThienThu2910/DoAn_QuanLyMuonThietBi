<?php
/* ==========================================================================
   navbar.php — khung điều hướng dùng chung (thanh bên + thanh trên + thanh dưới mobile)
   Được include ở đầu <body> của mọi trang đã đăng nhập. Giữ nguyên các biến
   $current_page, $is_admin_folder, $user như bản gốc để các trang khác không bị ảnh hưởng.
   ========================================================================== */
require_once __DIR__ . '/ui.php';

$current_page    = basename($_SERVER['PHP_SELF']);
$is_admin_folder = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$user            = $_SESSION['user'] ?? null;

$navIsAdmin = ($user && $user['role'] === 'admin');
$navKey     = ($is_admin_folder ? 'admin/' : '') . $current_page;

// Tiêu đề trang (hiện ở breadcrumb)
$navTitles = [
    'index.php'              => 'Kho thiết bị',
    'device_detail.php'      => 'Chi tiết thiết bị',
    'borrow.php'             => 'Đăng ký mượn',
    'my_borrows.php'         => 'Phiếu mượn của tôi',
    'request_detail.php'     => 'Chi tiết phiếu mượn',
    'profile.php'            => 'Hồ sơ cá nhân',
    'admin/index.php'        => 'Dashboard',
    'admin/devices.php'      => 'Quản lý thiết bị',
    'admin/device_edit.php'  => 'Chỉnh sửa thiết bị',
    'admin/categories.php'   => 'Danh mục',
    'admin/requests.php'     => 'Duyệt phiếu mượn',
    'admin/users.php'        => 'Người dùng',
    'admin/reports.php'      => 'Báo cáo & thống kê',
];
$navTitle = $navTitles[$navKey] ?? 'Alt F4 Hub';

// Mục menu đang được chọn
$navOn = [
    'kho'   => in_array($navKey, ['index.php', 'device_detail.php', 'borrow.php']),
    'mine'  => ($navKey === 'my_borrows.php') || ($navKey === 'request_detail.php' && !$navIsAdmin),
    'prof'  => ($navKey === 'profile.php'),
    'dash'  => ($navKey === 'admin/index.php'),
    'dev'   => in_array($navKey, ['admin/devices.php', 'admin/device_edit.php']),
    'req'   => ($navKey === 'admin/requests.php') || ($navKey === 'request_detail.php' && $navIsAdmin),
    'users' => ($navKey === 'admin/users.php'),
    'cat'   => ($navKey === 'admin/categories.php'),
    'rep'   => ($navKey === 'admin/reports.php'),
];
$navCls = [];
$navCur = [];
foreach ($navOn as $k => $on) {
    $navCls[$k] = $on ? 'is-active' : '';
    $navCur[$k] = $on ? 'aria-current="page"' : '';
}

// Số liệu nhỏ cho huy hiệu (an toàn nếu truy vấn lỗi)
$navPending = 0;   // admin: số phiếu chờ duyệt
$navMine    = 0;   // số phiếu đang chờ/đang mượn của chính mình
$navAlert   = 0;   // chuông: admin = phiếu chờ duyệt, sinh viên = món sắp/quá hạn trả
if ($user && isset($pdo)) {
    try {
        $navSt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status IN ('pending','approved')");
        $navSt->execute([$user['id']]);
        $navMine = (int)$navSt->fetchColumn();

        if ($navIsAdmin) {
            $navPending = (int)$pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'")->fetchColumn();
            $navAlert   = $navPending;
        } else {
            $navSt = $pdo->prepare("SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'approved' AND DATEDIFF(expected_return_date, CURDATE()) <= 2");
            $navSt->execute([$user['id']]);
            $navAlert = (int)$navSt->fetchColumn();
        }
    } catch (Throwable $e) {
        // Không để lỗi thống kê làm hỏng trang
    }
}
$navAlertHref  = $navIsAdmin ? '/admin/requests.php?status=pending' : '/my_borrows.php?status=approved';
$navAlertLabel = $navIsAdmin ? 'phiếu mượn đang chờ duyệt' : 'thiết bị sắp hoặc đã quá hạn trả';

// Tên viết tắt cho avatar: "Nguyễn Văn An" → "NA"
$navInit = '';
if ($user) {
    $navParts = preg_split('/\s+/u', trim($user['fullname']));
    $navInit  = mb_substr($navParts[0], 0, 1, 'UTF-8');
    if (count($navParts) > 1) {
        $navInit .= mb_substr($navParts[count($navParts) - 1], 0, 1, 'UTF-8');
    }
    $navInit = mb_strtoupper($navInit, 'UTF-8');
}
$navRole = ($user && $user['role'] === 'admin') ? 'Quản trị viên' : (($user && !empty($user['student_code'])) ? $user['student_code'] : 'Sinh viên');
?>
<div class="t-scrim" data-drawer-close></div>

<!-- Thanh bên dạng kính nổi: thu gọn thành thanh icon, rê chuột để bung ra, nút tay nắm để ghim mở / thu gọn -->
<div class="t-dock" id="tDock">
<aside class="t-side" id="tSide" aria-label="Điều hướng chính">
    <a class="t-brand" href="/index.php" title="Alt F4 Hub">
        <span class="t-brand-icon"><?= ic('box', 24) ?></span>
        <span class="t-brand-text">Alt F4<span class="t-brand-hub"> Hub</span><small>Lab equipment manager</small></span>
    </a>

    <div class="t-workspace">
        <span class="t-workspace-icon">L</span>
        <div>Không gian phòng Lab<small>ĐH Bình Dương · PH Cà Mau</small></div>
    </div>

    <p class="t-label"><b>Không gian làm việc</b></p>
    <nav class="t-navlist">
        <a class="t-nav <?= $navCls['kho'] ?>" href="/index.php" title="Kho thiết bị" <?= $navCur['kho'] ?>><?= ic('box') ?><span>Kho thiết bị</span></a>
        <a class="t-nav <?= $navCls['mine'] ?>" href="/my_borrows.php" title="Phiếu mượn của tôi" <?= $navCur['mine'] ?>><?= ic('list') ?><span>Phiếu mượn của tôi</span><?php if ($navMine > 0): ?><em class="t-count"><?= $navMine ?></em><?php endif; ?></a>
        <a class="t-nav <?= $navCls['prof'] ?>" href="/profile.php" title="Hồ sơ cá nhân" <?= $navCur['prof'] ?>><?= ic('user') ?><span>Hồ sơ cá nhân</span></a>
    </nav>

    <?php if ($navIsAdmin): ?>
    <p class="t-label t-label-admin"><b>Quản trị</b> <span>Admin</span></p>
    <nav class="t-navlist">
        <a class="t-nav <?= $navCls['dash'] ?>" href="/admin/index.php" title="Dashboard" <?= $navCur['dash'] ?>><?= ic('grid') ?><span>Dashboard</span></a>
        <a class="t-nav <?= $navCls['dev'] ?>" href="/admin/devices.php" title="Quản lý thiết bị" <?= $navCur['dev'] ?>><?= ic('box') ?><span>Quản lý thiết bị</span></a>
        <a class="t-nav <?= $navCls['req'] ?>" href="/admin/requests.php" title="Duyệt phiếu mượn" <?= $navCur['req'] ?>><?= ic('clock') ?><span>Duyệt phiếu mượn</span><?php if ($navPending > 0): ?><em class="t-count t-count-warn"><?= $navPending ?></em><?php endif; ?></a>
        <a class="t-nav <?= $navCls['users'] ?>" href="/admin/users.php" title="Người dùng" <?= $navCur['users'] ?>><?= ic('users') ?><span>Người dùng</span></a>
        <a class="t-nav <?= $navCls['cat'] ?>" href="/admin/categories.php" title="Danh mục" <?= $navCur['cat'] ?>><?= ic('tag') ?><span>Danh mục</span></a>
        <a class="t-nav <?= $navCls['rep'] ?>" href="/admin/reports.php" title="Báo cáo &amp; thống kê" <?= $navCur['rep'] ?>><?= ic('chart') ?><span>Báo cáo &amp; thống kê</span></a>
        <a class="t-nav" href="http://localhost:9001" target="_blank" rel="noopener" title="Mở MinIO S3 Console"><?= ic('cloud') ?><span>MinIO Console</span><?= ic('external', 14) ?></a>
    </nav>
    <?php endif; ?>

    <div class="t-side-bottom">
        <div class="t-help">
            <span class="t-help-icon"><?= ic('help') ?></span>
            <b>Cần một chút hỗ trợ?</b>
            <p>Xem nhanh cách mượn và sử dụng thiết bị phòng Lab.</p>
            <button type="button" data-toast="Chọn thiết bị → Đăng ký mượn → Chờ quản trị viên duyệt → Nhận thiết bị và trả đúng hạn.">Xem hướng dẫn <?= ic('arrow', 15) ?></button>
        </div>
        <?php if ($user): ?>
        <div class="t-me">
            <a class="t-me-link" href="/profile.php" title="Xem hồ sơ &amp; đổi mật khẩu">
                <span class="t-avatar"><?= t_esc($navInit) ?></span>
                <span class="t-me-text"><b><?= t_esc($user['fullname']) ?></b><small><?= t_esc($navRole) ?></small></span>
            </a>
            <a class="t-me-out" href="/logout.php" title="Đăng xuất" aria-label="Đăng xuất"><?= ic('logout', 18) ?></a>
        </div>
        <?php endif; ?>
        <div class="t-side-foot"><span class="t-dot"></span><span class="t-side-foot-text">Hệ thống hoạt động ổn định</span><span class="t-side-foot-ver">v2.0</span></div>
    </div>
</aside>
<button type="button" class="t-dock-toggle" data-side-toggle aria-controls="tSide" aria-expanded="true" aria-label="Thu gọn thanh bên" title="Thu gọn / mở rộng thanh bên"><?= ic('chevron', 15) ?></button>
</div>

<header class="t-top">
    <button type="button" class="t-icon-btn t-burger" data-drawer-open aria-label="Mở menu" aria-controls="tSide"><?= ic('menu', 22) ?></button>
    <div class="t-crumb"><span class="t-crumb-root">Không gian làm việc</span><span class="t-crumb-sep">/</span><b><?= t_esc($navTitle) ?></b></div>
    <div class="t-top-right">
        <span class="t-today"><?= vn_date() ?></span>
        <button type="button" class="t-icon-btn t-theme-btn" data-theme-toggle aria-label="Đổi giao diện sáng / tối" title="Đổi giao diện sáng / tối"><?= ic('sun', 19) ?><?= ic('moon', 19) ?></button>
        <a class="t-icon-btn t-bell" href="<?= $navAlertHref ?>" aria-label="Thông báo: <?= $navAlert ?> <?= $navAlertLabel ?>" title="<?= $navAlert ?> <?= $navAlertLabel ?>"><?= ic('bell', 19) ?><?php if ($navAlert > 0): ?><i class="t-bell-dot"></i><?php endif; ?></a>
        <?php if ($user): ?>
        <a class="t-user" href="/profile.php" title="Hồ sơ cá nhân">
            <span class="t-avatar"><?= t_esc($navInit) ?></span>
            <span class="t-user-text"><b><?= t_esc($user['fullname']) ?></b><small><?= t_esc($navRole) ?></small></span>
        </a>
        <?php endif; ?>
    </div>
</header>

<nav class="t-bottom" aria-label="Điều hướng nhanh">
    <a class="<?= $navCls['kho'] ?>" href="/index.php" <?= $navCur['kho'] ?>><?= ic('box', 21) ?><span>Kho</span></a>
    <?php if ($navIsAdmin): ?>
    <a class="<?= $navCls['dash'] ?>" href="/admin/index.php" <?= $navCur['dash'] ?>><?= ic('grid', 21) ?><span>Dashboard</span></a>
    <a class="<?= $navCls['req'] ?>" href="/admin/requests.php" <?= $navCur['req'] ?>><?= ic('clock', 21) ?><span>Duyệt phiếu</span><?php if ($navPending > 0): ?><em class="t-count t-count-warn"><?= $navPending ?></em><?php endif; ?></a>
    <a class="<?= $navCls['dev'] ?>" href="/admin/devices.php" <?= $navCur['dev'] ?>><?= ic('settings', 21) ?><span>Thiết bị</span></a>
    <?php else: ?>
    <a class="<?= $navCls['mine'] ?>" href="/my_borrows.php" <?= $navCur['mine'] ?>><?= ic('list', 21) ?><span>Phiếu của tôi</span><?php if ($navMine > 0): ?><em class="t-count"><?= $navMine ?></em><?php endif; ?></a>
    <a class="<?= $navCls['prof'] ?>" href="/profile.php" <?= $navCur['prof'] ?>><?= ic('user', 21) ?><span>Hồ sơ</span></a>
    <?php endif; ?>
    <button type="button" data-drawer-open aria-label="Mở menu đầy đủ"><?= ic('menu', 21) ?><span>Menu</span></button>
</nav>
