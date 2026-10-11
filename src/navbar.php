<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_folder = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$user = $_SESSION['user'] ?? null;
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
:root {
    --nav-ink: #020C21;
    --nav-ink-muted: #59627E;
    --nav-accent: #2563EB;
    --nav-glass-bg: rgba(255, 255, 255, 0.88);
    --nav-glass-border: rgba(255, 255, 255, 0.95);
    --nav-glass-shadow: 0 8px 24px rgba(15, 24, 47, 0.05);
    --nav-pill-active: #0F182F;
}

.global-header {
    position: sticky;
    top: 14px;
    z-index: 1000;
    margin: 0 auto 28px;
    max-width: 1240px;
    padding: 0 16px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transform: translateZ(0);
}

.global-nav-bar {
    background: var(--nav-glass-bg);
    border: 1px solid var(--nav-glass-border);
    border-radius: 999px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: var(--nav-glass-shadow);
    padding: 7px 14px 7px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.nav-brand-group {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: var(--nav-ink);
}

.nav-brand-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0F182F, #2563EB);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
}

.nav-brand-text { display: flex; flex-direction: column; }
.nav-brand-text b { font-size: 1.05rem; font-weight: 800; letter-spacing: -0.03em; line-height: 1.1; }
.nav-brand-text span { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--nav-ink-muted); }

.nav-menu-links {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
    margin: 0;
    padding: 0;
    overflow-x: auto;
}

.nav-item-link {
    text-decoration: none;
    color: #334155;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.nav-item-link:hover { color: var(--nav-accent); background: rgba(255, 255, 255, 0.7); }
.nav-item-link.active { background: var(--nav-pill-active); color: #ffffff !important; }

.nav-menu-divider { width: 1px; height: 20px; background: #CBD5E1; margin: 0 4px; }
.nav-badge-admin { background: #FEF3C7; color: #92400E; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; }

.nav-user-control { display: flex; align-items: center; gap: 10px; }
.nav-profile-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.65);
    border: 1px solid rgba(255, 255, 255, 0.9);
    padding: 4px 14px 4px 6px;
    border-radius: 999px;
    text-decoration: none;
    color: var(--nav-ink);
    transition: all 0.15s ease;
}
.nav-profile-chip:hover { background: #ffffff; }

.nav-avatar-circle {
    width: 32px; height: 32px; border-radius: 50%;
    background: #E2E8F0; color: #334155; font-weight: 700; font-size: 0.8rem;
    display: flex; align-items: center; justify-content: center;
}

.btn-nav-logout {
    width: 36px; height: 36px; border-radius: 50%;
    background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; font-size: 0.95rem; transition: all 0.15s ease;
}
.btn-nav-logout:hover { background: #DC2626; color: #ffffff; }

@media (max-width: 992px) {
    .global-nav-bar { border-radius: 20px; flex-wrap: wrap; }
    .nav-menu-links { order: 3; width: 100%; border-top: 1px solid #E2E8F0; padding-top: 10px; }
}
</style>

<header class="global-header">
    <nav class="global-nav-bar">
        <a href="/index.php" class="nav-brand-group">
            <span class="nav-brand-icon"><i class="bi bi-shield-check"></i></span>
            <div class="nav-brand-text">
                <b>Alt F4</b>
                <span>Quản Lý Thiết Bị Lab</span>
            </div>
        </a>

        <ul class="nav-menu-links">
            <li>
                <a href="/index.php" class="nav-item-link <?= ($current_page === 'index.php' && !$is_admin_folder) ? 'active' : '' ?>">
                    <i class="bi bi-grid-fill"></i> Kho Thiết Bị
                </a>
            </li>
            <li>
                <a href="/my_borrows.php" class="nav-item-link <?= ($current_page === 'my_borrows.php') ? 'active' : '' ?>">
                    <i class="bi bi-card-checklist"></i> Phiếu Của Tôi
                </a>
            </li>

            <?php if ($user && $user['role'] === 'admin'): ?>
                <li class="nav-menu-divider"></li>
                <li>
                    <a href="/admin/index.php" class="nav-item-link <?= ($current_page === 'index.php' && $is_admin_folder) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="/admin/devices.php" class="nav-item-link <?= ($current_page === 'devices.php' || $current_page === 'device_edit.php') ? 'active' : '' ?>">
                        <i class="bi bi-cpu"></i> Thiết Bị
                    </a>
                </li>
                <li>
                    <a href="/admin/categories.php" class="nav-item-link <?= ($current_page === 'categories.php') ? 'active' : '' ?>">
                        <i class="bi bi-tags"></i> Danh Mục
                    </a>
                </li>
                <li>
                    <a href="/admin/requests.php" class="nav-item-link <?= ($current_page === 'requests.php') ? 'active' : '' ?>">
                        <i class="bi bi-clipboard2-check"></i> Duyệt Mượn
                    </a>
                </li>
                <li>
                    <a href="/admin/users.php" class="nav-item-link <?= ($current_page === 'users.php') ? 'active' : '' ?>">
                        <i class="bi bi-people"></i> Tài Khoản
                    </a>
                </li>
                <li>
                    <a href="/admin/reports.php" class="nav-item-link <?= ($current_page === 'reports.php') ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart-line"></i> Báo Cáo
                    </a>
                </li>
                <li>
                    <a href="http://localhost:9001" target="_blank" class="nav-item-link" style="color: #D97706;" title="Mở MinIO S3 Console">
                        <i class="bi bi-cloud-arrow-up-fill"></i> MinIO
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="nav-user-control">
            <?php if ($user): ?>
                <a href="/profile.php" class="nav-profile-chip" title="Xem hồ sơ & đổi mật khẩu">
                    <span class="nav-avatar-circle"><?= mb_substr($user['fullname'], 0, 1, 'UTF-8') ?></span>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 0.82rem; font-weight: 700;"><?= htmlspecialchars($user['fullname']) ?></span>
                        <span style="font-size: 0.68rem; color: #64748B;">
                            <?= $user['role'] === 'admin' ? '<span class="nav-badge-admin">Admin</span>' : htmlspecialchars($user['student_code'] ?? 'Sinh viên') ?>
                        </span>
                    </div>
                </a>
                <a href="/logout.php" class="btn-nav-logout" title="Đăng xuất"><i class="bi bi-box-arrow-right"></i></a>
            <?php else: ?>
                <a href="/login.php" class="nav-item-link active">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </nav>
</header>