<?php
require_once '../config.php';
checkAdmin();

// 1. Thống kê số liệu hệ thống
$totalDevices = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$totalAvailable = $pdo->query("SELECT COALESCE(SUM(available_qty), 0) FROM devices")->fetchColumn();
$totalInventory = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM devices")->fetchColumn();

$totalPending = $pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'")->fetchColumn();
$totalBorrowing = $pdo->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'approved'")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

// 2. Lấy danh sách 5 phiếu mượn mới nhất
$recentRequests = $pdo->query("
    SELECT b.*, u.fullname, u.student_code, d.name as device_name, d.code as device_code 
    FROM borrow_requests b
    JOIN users u ON b.user_id = u.id
    JOIN devices d ON b.device_id = d.id
    ORDER BY b.id DESC LIMIT 5
")->fetchAll();

$today = new DateTime('today');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • Alt F4 Hub</title>
    <style>
        body { 
            background-color: #E6EDF6; 
            margin: 0; 
            padding-bottom: 60px; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #020C21; 
        }
        .container { max-width: 1240px; margin: 0 auto; padding: 0 16px; }
        
        /* Bento Grid Thống kê */
        .metrics-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .metric-card {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 26px; 
            padding: 24px 28px; 
            backdrop-filter: blur(40px);
            box-shadow: 0 12px 32px rgba(15, 24, 47, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .metric-header { display: flex; justify-content: space-between; align-items: center; }
        .metric-title { margin: 0; color: #64748B; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .metric-icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .metric-num { font-size: 2.5rem; margin: 12px 0 4px 0; font-weight: 800; letter-spacing: -0.03em; line-height: 1; }
        .metric-sub { font-size: 0.82rem; color: #64748B; margin: 0; }

        /* Khối Tác vụ Nhanh */
        .quick-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .action-chip {
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.88);
            border-radius: 999px;
            padding: 10px 22px;
            text-decoration: none;
            color: #0F182F;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .action-chip:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 24, 47, 0.06);
            color: #2563EB;
        }

        /* Khối Bảng Kính Mờ */
        .glass-panel {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px; 
            padding: 30px; 
            backdrop-filter: blur(40px);
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05);
        }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(206, 213, 224, 0.6); }
        th { font-size: 0.8rem; color: #64748B; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }

        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
        }
        .status-pending  { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-returned { background: #E2E8F0; color: #334155; }

        .btn-detail {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(37, 99, 235, 0.1); color: #2563EB;
            padding: 6px 14px; border-radius: 999px; text-decoration: none;
            font-size: 0.8rem; font-weight: 700; transition: all 0.2s ease;
        }
        .btn-detail:hover { background: #2563EB; color: #fff; }

        @media (max-width: 992px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 580px) {
            .metrics-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="margin-bottom: 25px;">
        <h1 style="font-size: 2.1rem; font-weight: 800; margin: 0; letter-spacing: -0.03em;">Trung Tâm Quản Trị</h1>
    </div>

    <!-- 1. Thanh Tác Vụ Nhanh (Quick Actions) -->
    <div class="quick-actions">
        <a href="/admin/devices.php" class="action-chip">
            <i class="bi bi-plus-circle-fill" style="color: #2563EB;"></i> Thêm thiết bị mới
        </a>
        <a href="/admin/requests.php?status=pending" class="action-chip">
            <i class="bi bi-clock-history" style="color: #D97706;"></i> Duyệt phiếu chờ (<?= $totalPending ?>)
        </a>
        <a href="/admin/categories.php" class="action-chip">
            <i class="bi bi-tag-fill" style="color: #059669;"></i> Quản lý danh mục
        </a>
        <a href="http://localhost:9001" target="_blank" class="action-chip" style="background: rgba(217, 119, 6, 0.1); border-color: rgba(217, 119, 6, 0.3); color: #B45309;">
            <i class="bi bi-cloud-arrow-up-fill"></i> Mở MinIO Console
        </a>
    </div>

    <!-- 2. Bento Grid 4 Chỉ Số KPI -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">Tổng Thiết Bị Kho</span>
                <span class="metric-icon" style="background: #EFF6FF; color: #2563EB;"><i class="bi bi-cpu"></i></span>
            </div>
            <div class="metric-num"><?= $totalDevices ?></div>
            <p class="metric-sub">Khả dụng: <strong><?= $totalAvailable ?></strong> / <?= $totalInventory ?> món</p>
        </div>

        <div class="metric-card" style="border-color: #FDE68A;">
            <div class="metric-header">
                <span class="metric-title" style="color: #B45309;">Cần Phê Duyệt</span>
                <span class="metric-icon" style="background: #FEF3C7; color: #D97706;"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="metric-num" style="color: #B45309;"><?= $totalPending ?></div>
            <p class="metric-sub">Yêu cầu sinh viên đang đợi duyệt</p>
        </div>

        <div class="metric-card" style="border-color: #BFDBFE;">
            <div class="metric-header">
                <span class="metric-title" style="color: #1D4ED8;">Đang Cho Mượn</span>
                <span class="metric-icon" style="background: #DBEAFE; color: #2563EB;"><i class="bi bi-arrow-repeat"></i></span>
            </div>
            <div class="metric-num" style="color: #1D4ED8;"><?= $totalBorrowing ?></div>
            <p class="metric-sub">Thiết bị đang ngoài phòng Lab</p>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">Nhóm Phân Loại</span>
                <span class="metric-icon" style="background: #F1F5F9; color: #475569;"><i class="bi bi-tags"></i></span>
            </div>
            <div class="metric-num"><?= $totalCategories ?></div>
            <p class="metric-sub">Danh mục phần cứng & kit thực hành</p>
        </div>
    </div>

    <!-- 3. Bảng Phiếu Mượn Gần Đây -->
    <div class="glass-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700;">Phiếu Mượn Gần Đây</h3>
            <a href="/admin/requests.php" style="color: #2563EB; font-size: 0.88rem; font-weight: 700; text-decoration: none;">
                Xem tất cả phiếu mượn &rarr;
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Sinh Viên</th>
                    <th>Thiết Bị</th>
                    <th>Số Lượng</th>
                    <th>Thời Gian</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRequests as $req): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($req['fullname']) ?></strong><br>
                            <small style="color: #64748B;"><?= htmlspecialchars($req['student_code'] ?? 'N/A') ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($req['device_name']) ?></strong><br>
                            <code style="font-size: 0.8rem; color: #2563EB;"><?= htmlspecialchars($req['device_code']) ?></code>
                        </td>
                        <td><strong><?= $req['quantity'] ?></strong> món</td>
                        <td style="font-size: 0.88rem;">
                            <?= date('d/m/Y', strtotime($req['borrow_date'])) ?> &rarr; <?= date('d/m/Y', strtotime($req['expected_return_date'])) ?>
                        </td>
                        <td>
                            <span class="status-pill status-<?= $req['status'] ?>">
                                <?php 
                                    $map = ['pending' => '• Chờ duyệt', 'approved' => '• Đang mượn', 'rejected' => '• Từ chối', 'returned' => '• Đã trả'];
                                    echo $map[$req['status']] ?? $req['status'];
                                ?>
                            </span>
                        </td>
                        <td>
                            <a href="/request_detail.php?id=<?= $req['id'] ?>" class="btn-detail">
                                <i class="bi bi-eye"></i> Chi tiết
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