<?php
/* ==========================================================================
   ui.php — hàm hỗ trợ giao diện dùng chung (icon, ngày tiếng Việt, escape).
   Không chứa logic nghiệp vụ. Được nạp bởi navbar.php, login.php, register.php.
   ========================================================================== */

/** Icon SVG nội tuyến (không phụ thuộc CDN) — dùng cho khung điều hướng & trang đăng nhập. */
function ic(string $name, int $size = 20): string {
    static $paths = [
        'grid'     => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'box'      => 'm12 3 9 5-9 5-9-5 9-5z M3 8v9l9 5 9-5V8 M12 13v9',
        'list'     => 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01',
        'users'    => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
        'chart'    => 'M4 3v18h17 M8 16v-5 M13 16V6 M18 16v-8',
        'search'   => 'm21 21-4.3-4.3 M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z',
        'arrow'    => 'M5 12h14 m-5-5 5 5-5 5',
        'clock'    => 'M12 7v5l3 2 M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
        'check'    => 'm5 12 4 4L19 6',
        'bell'     => 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9 M10.3 21a1.9 1.9 0 0 0 3.4 0',
        'sun'      => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M12 2v2 M12 20v2 M4.9 4.9l1.4 1.4 M17.7 17.7l1.4 1.4 M2 12h2 M20 12h2 M4.9 19.1l1.4-1.4 M17.7 6.3l1.4-1.4',
        'moon'     => 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
        'help'     => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3 M12 17h.01',
        'chevron'  => 'm9 6 6 6-6 6',
        'plus'     => 'M12 5v14 M5 12h14',
        'download' => 'M12 3v12 m-5-5 5 5 5-5 M4 17v4h16v-4',
        'settings' => 'M4 21v-7 M4 10V3 M12 21v-9 M12 8V3 M20 21v-5 M20 12V3 M1 14h6 M9 8h6 M17 16h6',
        'logout'   => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4 M16 17l5-5-5-5 M21 12H9',
        'menu'     => 'M4 6h16 M4 12h16 M4 18h16',
        'user'     => 'M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'shield'   => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z m-3-10 2 2 4-4',
        'cloud'    => 'M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9z',
        'tag'      => 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z M7 7h.01',
        'external' => 'M15 3h6v6 M10 14 21 3 M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6',
        'x'        => 'M18 6 6 18 M6 6l12 12',
        'alert'    => 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z M12 9v4 M12 17h.01',
        'check-circle' => 'M22 11.1V12a10 10 0 1 1-5.9-9.1 M22 4 12 14l-3-3',
    ];
    $d = $paths[$name] ?? $paths['box'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

/** Ngày hiện tại theo giờ Việt Nam, ví dụ: "Thứ Bảy, 25 tháng 10, 2026". */
function vn_date(): string {
    $days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    return $days[(int)$now->format('w')] . ', ' . $now->format('j') . ' tháng ' . $now->format('n') . ', ' . $now->format('Y');
}

/** Escape HTML ngắn gọn. */
function t_esc($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
