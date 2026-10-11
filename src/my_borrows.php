<?php
require_once 'config.php';
checkLogin();

// Xử lý sinh viên hủy phiếu mượn khi còn chờ duyệt
if (isset($_GET['cancel_id'])) {
    $cancelId = (int)$_GET['cancel_id'];
    $stmtCancel = $pdo->prepare("
        UPDATE borrow_requests 
        SET status = 'rejected', 
            reject_reason = 'Người mượn tự hủy yêu cầu',
            note = CONCAT(COALESCE(note, ''), ' [Sinh viên tự hủy yêu cầu]') 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmtCancel->execute([$cancelId, $_SESSION['user']['id']]);
    header("Location: /my_borrows.php?msg=" . urlencode("Đã hủy yêu cầu mượn thiết bị thành công!"));
    exit;
}

$userId = $_SESSION['user']['id'];
$filter_status = trim($_GET['status'] ?? '');
$msg = trim($_GET['msg'] ?? '');

// Xây dựng truy vấn theo tab lọc
$sql = "
    SELECT b.*, d.name as device_name, d.code as device_code, d.image_key
    FROM borrow_requests b
    JOIN devices d ON b.device_id = d.id
    WHERE b.user_id = ?
";
$params = [$userId];

if (in_array($filter_status, ['pending', 'approved', 'returned', 'rejected'])) {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Tính toán danh sách các thiết bị sắp hết hạn hoặc quá hạn
$today = new DateTime('today');
$urgentList = [];
$totalBorrowing = 0;

foreach ($requests as $r) {
    if ($r['status'] === 'approved') {
        $totalBorrowing++;
        $dueDate = new DateTime($r['expected_return_date']);
        $diffDays = (int)$today->diff($dueDate)->format("%r%a");

        if ($diffDays <= 2) {
            $urgentList[] = [
                'name' => $r['device_name'],
                'code' => $r['device_code'],
                'diff' => $diffDays,
                'due'  => date('d/m/Y', strtotime($r['expected_return_date']))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch Sử Mượn Thiết Bị • Alt F4 Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --ink: #020C21;
            --muted: #59627E;
            --frame: #E6EDF6;
            --accent: #2563EB;
            --glass-bg: rgba(255, 255, 255, 0.76);
            --glass-border: rgba(255, 255, 255, 0.9);
            --glass-shadow: 0 16px 40px rgba(15, 24, 47, 0.05);
        }

        body {
            background-color: var(--frame);
            margin: 0;
            padding: 0 0 60px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ink);
        }

        .content-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 16px;
        }

        /* Khối thông báo phản hồi thao tác */
        .msg-toast {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
            padding: 14px 22px;
            border-radius: 18px;
            margin-bottom: 22px;
            font-size: 0.92rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.12);
        }

        /* Khối thông báo nổi khi sắp hết hạn / quá hạn */
        .urgent-alert-box {
            background: linear-gradient(135deg, rgba(254, 242, 242, 0.92), rgba(254, 226, 226, 0.8));
            border: 1px solid #FECACA;
            border-radius: 24px;
            padding: 20px 26px;
            margin-bottom: 25px;
            backdrop-filter: blur(25px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.1);
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .urgent-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #EF4444;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 0 14px rgba(239, 68, 68, 0.4);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Thanh phân loại Tab lịch sử */
        .history-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            text-decoration: none;
            padding: 9px 20px;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.85);
            transition: all 0.2s ease;
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.9);
            color: var(--ink);
        }

        .tab-btn.active {
            background: #0F182F;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 24, 47, 0.15);
        }

        /* Bảng lịch sử kính mờ */
        .glass-box {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 30px;
            backdrop-filter: blur(40px);
            box-shadow: var(--glass-shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid rgba(206, 213, 224, 0.6);
        }

        th {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #64748B;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .device-link {
            color: var(--ink);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .device-link:hover {
            color: var(--accent);
        }

        /* Huy hiệu trạng thái cơ bản */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .status-pending  { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-returned { background: #E2E8F0; color: #334155; }

        /* Huy hiệu cảnh báo hạn trả */
        .deadline-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            margin-top: 4px;
        }

        .deadline-overdue {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        .deadline-urgent {
            background: #FFEDD5;
            color: #C2410C;
            border: 1px solid #FED7AA;
        }

        .deadline-normal {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }

        .btn-view-s3 {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(37, 99, 235, 0.1);
            color: #2563EB;
            padding: 6px 14px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-view-s3:hover {
            background: #2563EB;
            color: #fff;
        }

        /* Nút hành động trong cột Thao tác */
        .btn-detail-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(37, 99, 235, 0.1);
            color: #2563EB;
            padding: 6px 14px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .btn-detail-pill:hover {
            background: #2563EB;
            color: #ffffff;
        }

        .btn-cancel-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #FEE2E2;
            color: #DC2626;
            padding: 6px 14px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .btn-cancel-pill:hover {
            background: #DC2626;
            color: #ffffff;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="content-container">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.03em;">Lịch Sử Mượn Thiết Bị</h1>
            <p style="color: #64748B; margin: 6px 0 0; font-size: 0.95rem;">Theo dõi trạng thái phiếu mượn, hạn trả và biên bản lưu trữ trên MinIO S3</p>
        </div>
        <a href="/index.php" style="background: #0F182F; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600; font-size: 0.88rem;">
            + Mượn thêm thiết bị
        </a>
    </div>

    <!-- Thông báo hủy phiếu thành công -->
    <?php if (!empty($msg)): ?>
        <div class="msg-toast">
            <i class="bi bi-check-circle-fill" style="font-size: 1.15rem;"></i>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- 1. Hộp Cảnh Báo Sắp Hết Hạn / Quá Hạn -->
    <?php if (!empty($urgentList)): ?>
        <div class="urgent-alert-box">
            <div class="urgent-icon">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div style="flex: 1;">
                <h3 style="margin: 0 0 6px 0; font-size: 1.05rem; font-weight: 700; color: #991B1B;">
                    Cảnh Báo Hạn Trả Thiết Bị (<?= count($urgentList) ?> món cần lưu ý)
                </h3>
                <p style="margin: 0 0 10px 0; font-size: 0.88rem; color: #7F1D1D;">
                    Bạn có thiết bị đã tới hạn hoặc quá hạn trả. Vui lòng hoàn trả tại phòng Lab đúng hạn để tránh bị khóa quyền mượn thiết bị!
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($urgentList as $u): ?>
                        <span style="background: #fff; border: 1px solid #FECACA; padding: 4px 12px; border-radius: 999px; font-size: 0.82rem; font-weight: 600; color: #991B1B;">
                            <?= htmlspecialchars($u['name']) ?>: 
                            <?php if ($u['diff'] < 0): ?>
                                <strong>Quá hạn <?= abs($u['diff']) ?> ngày</strong>
                            <?php elseif ($u['diff'] === 0): ?>
                                <strong>Hết hạn hôm nay!</strong>
                            <?php else: ?>
                                Còn <?= $u['diff'] ?> ngày (Hạn: <?= $u['due'] ?>)
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. Thanh Tabs Phân Loại -->
    <div class="history-tabs">
        <a href="/my_borrows.php" class="tab-btn <?= empty($filter_status) ? 'active' : '' ?>">Tất cả</a>
        <a href="/my_borrows.php?status=approved" class="tab-btn <?= $filter_status === 'approved' ? 'active' : '' ?>">Đang mượn</a>
        <a href="/my_borrows.php?status=pending" class="tab-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">Chờ duyệt</a>
        <a href="/my_borrows.php?status=returned" class="tab-btn <?= $filter_status === 'returned' ? 'active' : '' ?>">Đã hoàn trả</a>
        <a href="/my_borrows.php?status=rejected" class="tab-btn <?= $filter_status === 'rejected' ? 'active' : '' ?>">Từ chối / Hủy</a>
    </div>

    <!-- 3. Bảng Lịch Sử Phiếu Mượn -->
    <div class="glass-box">
        <?php if (empty($requests)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #64748B;">
                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.4;"></i>
                <p style="margin-top: 14px; font-weight: 600;">Không có phiếu mượn nào trong danh mục này.</p>
                <a href="/index.php" style="color: #2563EB; text-decoration: none; font-weight: 600;">Khám phá kho thiết bị ngay &rarr;</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Thiết Bị</th>
                        <th>Số Lượng</th>
                        <th>Thời Gian Mượn</th>
                        <th>Hạn Trả / Cảnh Báo</th>
                        <th>Minh Chứng S3</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php
                        $diffDays = null;
                        if ($r['status'] === 'approved') {
                            $dueDate = new DateTime($r['expected_return_date']);
                            $diffDays = (int)$today->diff($dueDate)->format("%r%a");
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="/device_detail.php?id=<?= $r['device_id'] ?>" class="device-link" title="Bấm xem thông số chi tiết thiết bị">
                                    <strong style="font-size: 0.98rem;"><?= htmlspecialchars($r['device_name']) ?></strong>
                                </a>
                                <br>
                                <code style="font-size: 0.8rem; color: #2563EB;"><?= htmlspecialchars($r['device_code']) ?></code>
                            </td>
                            <td><strong style="font-size: 1.05rem;"><?= $r['quantity'] ?></strong> món</td>
                            <td style="font-size: 0.88rem;">
                                <div>Mượn: <strong><?= date('d/m/Y', strtotime($r['borrow_date'])) ?></strong></div>
                                <div style="color: #64748B;">Hạn: <strong><?= date('d/m/Y', strtotime($r['expected_return_date'])) ?></strong></div>
                                <?php if (!empty($r['actual_return_date'])): ?>
                                    <div style="color: #059669; font-size: 0.8rem; margin-top: 2px;">
                                        Đã trả: <?= date('d/m/Y', strtotime($r['actual_return_date'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'approved'): ?>
                                    <?php if ($diffDays < 0): ?>
                                        <span class="deadline-badge deadline-overdue">
                                            <i class="bi bi-exclamation-octagon-fill"></i> Quá hạn <?= abs($diffDays) ?> ngày
                                        </span>
                                    <?php elseif ($diffDays === 0): ?>
                                        <span class="deadline-badge deadline-urgent">
                                            <i class="bi bi-clock-fill"></i> Hết hạn hôm nay
                                        </span>
                                    <?php elseif ($diffDays <= 2): ?>
                                        <span class="deadline-badge deadline-urgent">
                                            <i class="bi bi-hourglass-split"></i> Còn <?= $diffDays ?> ngày
                                        </span>
                                    <?php else: ?>
                                        <span class="deadline-badge deadline-normal">
                                            <i class="bi bi-check-circle-fill"></i> Còn <?= $diffDays ?> ngày
                                        </span>
                                    <?php endif; ?>
                                <?php elseif ($r['status'] === 'returned'): ?>
                                    <span style="color: #64748B; font-size: 0.85rem;"><i class="bi bi-check2-all"></i> Đã hoàn tất</span>
                                <?php else: ?>
                                    <span style="color: #94A3B8; font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['handover_doc_key'])): ?>
                                    <a href="/view_file.php?key=<?= urlencode($r['handover_doc_key']) ?>" target="_blank" class="btn-view-s3">
                                        <i class="bi bi-file-earmark-arrow-down"></i> Tệp S3
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94A3B8; font-size: 0.85rem;">Không có</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill status-<?= $r['status'] ?>">
                                    <?php 
                                        $map = [
                                            'pending'  => '• Chờ duyệt',
                                            'approved' => '• Đang mượn',
                                            'rejected' => '• Từ chối',
                                            'returned' => '• Đã hoàn trả'
                                        ];
                                        echo $map[$r['status']] ?? $r['status'];
                                    ?>
                                </span>
                                <?php if ($r['status'] === 'rejected' && !empty($r['reject_reason'])): ?>
                                    <div style="font-size: 0.76rem; color: #DC2626; margin-top: 5px; line-height: 1.35; max-width: 170px;">
                                        <i class="bi bi-info-circle-fill me-1"></i><?= htmlspecialchars($r['reject_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <a href="/request_detail.php?id=<?= $r['id'] ?>" class="btn-detail-pill">
                                        <i class="bi bi-eye"></i> Chi tiết
                                    </a>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <a href="?cancel_id=<?= $r['id'] ?>" class="btn-cancel-pill" onclick="return confirm('Bạn có chắc chắn muốn hủy yêu cầu mượn thiết bị này?')">
                                            <i class="bi bi-x-circle"></i> Hủy
                                        </a>
                                    <?php endif; ?>
                                </div>
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