<?php /* Phần <head> dùng chung: font, icon, giao diện sáng/tối. Mỗi trang nạp bằng 1 dòng include. */ ?>
    <meta name="color-scheme" content="light dark">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/theme.css?v=<?= @filemtime(__DIR__ . '/assets/theme.css') ?>">
    <script src="/assets/theme.js?v=<?= @filemtime(__DIR__ . '/assets/theme.js') ?>"></script>
