<?php
require_once '../config.php';
checkAdmin();

// 1. Thống kê theo danh mục
$categoryStats = $pdo->query("
    SELECT c.name, COUNT(b.id) as borrow_count 
    FROM categories c
    JOIN devices d ON c.id = d.category_id
    LEFT JOIN borrow_requests b ON d.id = b.device_id
    GROUP BY c.id
")->fetchAll();

// 2. Danh sách thiết bị gặp sự cố (Hỏng / Bảo trì / Ngừng sử dụng)
$issueDevices = $pdo->query("
    SELECT d.*, c.name as category_name 
    FROM devices d 
    JOIN categories c ON d.category_id = c.id
    WHERE d.status IN ('maintenance', 'broken', 'decommissioned')
")->fetchAll();

// 3. Top sinh viên tích cực mượn thiết bị
$topStudents = $pdo->query("
    SELECT u.fullname, u.student_code, COUNT(b.id) as total_borrows
    FROM users u
    JOIN borrow_requests b ON u.id = b.user_id
    GROUP BY u.id
    ORDER BY total_borrows DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo & Thống Kê • Admin</title>
    <style>
        body { background-color: #E6EDF6; font-family: 'Plus Jakarta Sans', sans-serif; color: #020C21; margin: 0; padding-bottom: 60px; }
        .container { max-width: 1240px; margin: 0 auto; padding: 0 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 25px; }
        .glass-box {
            background: rgba(255, 255, 255, 0.78); border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px; padding: 30px; backdrop-filter: blur(40px);
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05); margin-bottom: 25px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid rgba(206, 213, 224, 0.6); }
        th { font-size: 0.8rem; text-transform: uppercase; color: #64748B; font-weight: 700; }
    </style>
    <?php include __DIR__ . '/../head.php'; ?>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0;">Báo Cáo & Thống Kê Phòng Lab</h1>
            <p style="color: #64748B; margin: 6px 0 0;">Theo dõi tình trạng hư hỏng, mức độ khai thác danh mục và sinh viên mượn</p>
        </div>
        <a href="/admin/export.php" style="background: #0F182F; color: #fff; padding: 10px 22px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: 0.88rem;">
            <i class="bi bi-download me-1"></i> Xuất Dữ Liệu CSV
        </a>
    </div>

    <div class="grid-2">
        <!-- Khối thống kê theo danh mục -->
        <div class="glass-box">
            <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 700; margin-bottom: 15px;">Lượt Mượn Theo Nhóm Thiết Bị</h3>
            <table>
                <thead>
                    <tr><th>Danh Mục</th><th>Lượt Mượn</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryStats as $cs): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cs['name']) ?></strong></td>
                            <td><strong style="color: #2563EB; font-size: 1.1rem;"><?= $cs['borrow_count'] ?></strong> lượt</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Khối Top sinh viên mượn -->
        <div class="glass-box">
            <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 700; margin-bottom: 15px;">Top Sinh Viên Mượn Nhiều Nhất</h3>
            <table>
                <thead>
                    <tr><th>Sinh Viên</th><th>Mã SV</th><th>Lượt Mượn</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topStudents as $ts): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ts['fullname']) ?></strong></td>
                            <td><code><?= htmlspecialchars($ts['student_code'] ?? 'N/A') ?></code></td>
                            <td><strong style="color: #059669; font-size: 1.1rem;"><?= $ts['total_borrows'] ?></strong> lần</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Khối thiết bị sự cố / bảo trì -->
    <div class="glass-box">
        <h3 style="margin-top: 0; font-size: 1.15rem; font-weight: 700; margin-bottom: 15px; color: #DC2626;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Báo Cáo Thiết Bị Hư Hỏng / Cần Bảo Trì (<?= count($issueDevices) ?>)
        </h3>
        <?php if (empty($issueDevices)): ?>
            <p style="color: #059669; font-weight: 600; margin: 0;">Hiện không có thiết bị nào bị hư hỏng hoặc đang bảo trì. Tất cả đều hoạt động tốt!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Mã / Tên Thiết Bị</th>
                        <th>Danh Mục</th>
                        <th>Vị Trí Lưu Trữ</th>
                        <th>Tình Trạng</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issueDevices as $idv): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($idv['name']) ?></strong><br>
                                <code style="color: #2563EB;"><?= htmlspecialchars($idv['code']) ?></code>
                            </td>
                            <td><?= htmlspecialchars($idv['category_name']) ?></td>
                            <td><?= htmlspecialchars($idv['location'] ?? 'Chưa đặt') ?></td>
                            <td>
                                <span style="background: #FEE2E2; color: #991B1B; padding: 4px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700;">
                                    <?= strtoupper($idv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/admin/device_edit.php?id=<?= $idv['id'] ?>" style="color: #2563EB; font-weight: 700; text-decoration: none; font-size: 0.85rem;">
                                    Sửa / Khắc phục &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

</body>
</html>