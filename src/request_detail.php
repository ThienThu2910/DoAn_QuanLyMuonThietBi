<?php
require_once 'config.php';
checkLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT b.*, 
           u.fullname as user_fullname, u.student_code, u.email as user_email,
           d.name as device_name, d.code as device_code, d.image_key as device_image,
           c.name as category_name
    FROM borrow_requests b
    JOIN users u ON b.user_id = u.id
    JOIN devices d ON b.device_id = d.id
    JOIN categories c ON d.category_id = c.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Phiếu mượn không tồn tại!</h2><a href='/my_borrows.php'>Quay lại</a></div>");
}

// Kiểm tra quyền hạn: Sinh viên chỉ được xem phiếu của chính mình
$isAdmin = ($_SESSION['user']['role'] === 'admin');
if (!$isAdmin && $req['user_id'] != $_SESSION['user']['id']) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color:red;'><h2>Truy cập bị từ chối!</h2><p>Bạn không có quyền xem phiếu mượn của tài khoản khác.</p></div>");
}

// Xử lý phê duyệt nhanh nếu là Admin thao tác
$msg = '';
$err = '';
if ($isAdmin && isset($_GET['action'])) {
    $action = $_GET['action'];
    try {
        $pdo->beginTransaction();
        if ($action === 'approve' && $req['status'] === 'pending') {
            $avail = $pdo->query("SELECT available_qty FROM devices WHERE id = {$req['device_id']} FOR UPDATE")->fetchColumn();
            if ($avail >= $req['quantity']) {
                $pdo->exec("UPDATE devices SET available_qty = available_qty - {$req['quantity']} WHERE id = {$req['device_id']}");
                $pdo->exec("UPDATE borrow_requests SET status = 'approved' WHERE id = {$id}");
                $msg = "Đã phê duyệt phiếu mượn #{$id} thành công!";
                $req['status'] = 'approved';
            } else {
                $err = "Không thể duyệt: Số lượng trong kho không đủ!";
            }
        } elseif ($action === 'reject' && $req['status'] === 'pending') {
            $pdo->exec("UPDATE borrow_requests SET status = 'rejected' WHERE id = {$id}");
            $msg = "Đã từ chối phiếu mượn #{$id}.";
            $req['status'] = 'rejected';
        } elseif ($action === 'return' && $req['status'] === 'approved') {
            $pdo->exec("UPDATE devices SET available_qty = available_qty + {$req['quantity']} WHERE id = {$req['device_id']}");
            $pdo->exec("UPDATE borrow_requests SET status = 'returned', actual_return_date = CURDATE() WHERE id = {$id}");
            $msg = "Đã xác nhận trả thiết bị và hoàn số lượng vào kho!";
            $req['status'] = 'returned';
            $req['actual_return_date'] = date('Y-m-d');
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $err = "Lỗi xử lý: " . $e->getMessage();
    }
}

// Tính ngày quá hạn / còn lại
$today = new DateTime('today');
$dueDate = new DateTime($req['expected_return_date']);
$diffDays = (int)$today->diff($dueDate)->format("%r%a");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Phiếu Mượn #<?= $req['id'] ?> • Alt F4</title>
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
        .grid-layout { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 30px; }
        .glass-box {
            background: var(--glass-bg); border: 1px solid var(--glass-border);
            border-radius: 28px; padding: 32px; backdrop-filter: blur(40px);
            box-shadow: var(--glass-shadow); margin-bottom: 25px;
        }
        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px; border-radius: 999px; font-size: 0.85rem; font-weight: 700;
        }
        .status-pending  { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-returned { background: #E2E8F0; color: #334155; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(206, 213, 224, 0.5); font-size: 0.92rem; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--muted); font-weight: 600; }
        .preview-pane {
            width: 100%; border-radius: 16px; overflow: hidden;
            border: 1px solid rgba(206, 213, 224, 0.8); background: #fff;
        }
        .btn-action {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 22px; border-radius: 999px; text-decoration: none;
            font-size: 0.88rem; font-weight: 700; transition: all 0.2s ease;
        }
        .btn-approve { background: #10B981; color: #fff; }
        .btn-reject  { background: #EF4444; color: #fff; }
        .btn-return  { background: #3B82F6; color: #fff; }
        @media (max-width: 860px) {
            .grid-layout { grid-template-columns: 1fr; }
        }
    </style>
    <?php include __DIR__ . '/head.php'; ?>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <a href="<?= $isAdmin ? '/admin/requests.php' : '/my_borrows.php' ?>" style="color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.9rem;">
            <i class="bi bi-arrow-left"></i> Quay lại danh sách phiếu
        </a>
        <span class="status-pill status-<?= $req['status'] ?>">
            <?php 
                $map = ['pending' => '• Đang chờ duyệt', 'approved' => '• Đang cho mượn', 'rejected' => '• Bị từ chối', 'returned' => '• Đã hoàn trả'];
                echo $map[$req['status']] ?? $req['status'];
            ?>
        </span>
    </div>

    <?php if ($msg): ?><div style="padding: 14px 20px; background: #D1FAE5; color: #065F46; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="padding: 14px 20px; background: #FEE2E2; color: #991B1B; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="grid-layout">
        <!-- Cột 1: Thông tin chi tiết phiếu & người mượn -->
        <div>
            <div class="glass-box">
                <h3 style="margin-top: 0; font-size: 1.3rem; font-weight: 700; margin-bottom: 20px;">
                    Phiếu Mượn #<?= $req['id'] ?>
                </h3>

                <div class="info-row">
                    <span class="info-label">Người mượn</span>
                    <strong><?= htmlspecialchars($req['user_fullname']) ?></strong>
                </div>
                <div class="info-row">
                    <span class="info-label">Mã sinh viên / Mã định danh</span>
                    <code><?= htmlspecialchars($req['student_code'] ?? 'N/A') ?></code>
                </div>
                <div class="info-row">
                    <span class="info-label">Email liên hệ</span>
                    <span><?= htmlspecialchars($req['user_email']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Thiết bị mượn</span>
                    <a href="/device_detail.php?id=<?= $req['device_id'] ?>" style="color: var(--accent); font-weight: 700; text-decoration: none;">
                        <?= htmlspecialchars($req['device_name']) ?> (<?= htmlspecialchars($req['device_code']) ?>) &rarr;
                    </a>
                </div>
                <div class="info-row">
                    <span class="info-label">Số lượng mượn</span>
                    <strong style="font-size: 1.1rem;"><?= $req['quantity'] ?> món</strong>
                </div>
                <div class="info-row">
                    <span class="info-label">Ngày bắt đầu mượn</span>
                    <span><?= date('d/m/Y', strtotime($req['borrow_date'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ngày dự kiến trả</span>
                    <span>
                        <strong><?= date('d/m/Y', strtotime($req['expected_return_date'])) ?></strong>
                        <?php if ($req['status'] === 'approved'): ?>
                            <?php if ($diffDays < 0): ?>
                                <span style="color: #DC2626; font-weight: 700;"> (Quá hạn <?= abs($diffDays) ?> ngày)</span>
                            <?php elseif ($diffDays === 0): ?>
                                <span style="color: #EA580C; font-weight: 700;"> (Hạn trả hôm nay)</span>
                            <?php else: ?>
                                <span style="color: #059669;"> (Còn <?= $diffDays ?> ngày)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($req['actual_return_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Ngày hoàn trả thực tế</span>
                        <strong style="color: #059669;"><?= date('d/m/Y', strtotime($req['actual_return_date'])) ?></strong>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Ghi chú yêu cầu</span>
                    <span><?= htmlspecialchars($req['note'] ?? 'Không có ghi chú') ?></span>
                </div>
            </div>

            <!-- Bảng điều khiển duyệt nhanh (Dành riêng cho Admin) -->
            <?php if ($isAdmin): ?>
                <div class="glass-box" style="border-color: #CBD5E1;">
                    <h4 style="margin-top: 0; font-size: 1.05rem; font-weight: 700; margin-bottom: 15px;">Thao Tác Quản Trị Viên</h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if ($req['status'] === 'pending'): ?>
                            <a href="?id=<?= $req['id'] ?>&action=approve" class="btn-action btn-approve" onclick="return confirm('Xác nhận duyệt phiếu mượn này?')">
                                <i class="bi bi-check-lg"></i> Duyệt Phiếu
                            </a>
                            <a href="?id=<?= $req['id'] ?>&action=reject" class="btn-action btn-reject" onclick="return confirm('Xác nhận từ chối phiếu mượn?')">
                                <i class="bi bi-x-lg"></i> Từ Chối
                            </a>
                        <?php elseif ($req['status'] === 'approved'): ?>
                            <a href="?id=<?= $req['id'] ?>&action=return" class="btn-action btn-return" onclick="return confirm('Xác nhận sinh viên đã trả thiết bị vào kho?')">
                                <i class="bi bi-box-arrow-in-down"></i> Xác Nhận Đã Nhận Lại Thiết Bị
                            </a>
                        <?php else: ?>
                            <span style="color: var(--muted); font-size: 0.9rem;">Phiếu mượn đã hoàn tất vòng đời.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cột 2: Minh chứng lưu trữ MinIO S3 (PDF / Hình ảnh) -->
        <div>
            <div class="glass-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                    <h4 style="margin: 0; font-size: 1.1rem; font-weight: 700;">
                        <i class="bi bi-cloud-check-fill me-1" style="color: var(--accent);"></i> Minh Chứng MinIO S3
                    </h4>
                    <?php if ($req['handover_doc_key']): ?>
                        <a href="/view_file.php?key=<?= urlencode($req['handover_doc_key']) ?>" target="_blank" style="color: var(--accent); font-size: 0.85rem; font-weight: 700; text-decoration: none;">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Tải về / Mở tab mới
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($req['handover_doc_key']): ?>
                    <?php $ext = strtolower(pathinfo($req['handover_doc_key'], PATHINFO_EXTENSION)); ?>
                    <div class="preview-pane">
                        <?php if ($ext === 'pdf'): ?>
                            <iframe src="/view_file.php?key=<?= urlencode($req['handover_doc_key']) ?>" style="width: 100%; height: 420px; border: none;"></iframe>
                        <?php else: ?>
                            <img src="/view_file.php?key=<?= urlencode($req['handover_doc_key']) ?>" style="width: 100%; height: auto; display: block; max-height: 420px; object-fit: contain;">
                        <?php endif; ?>
                    </div>
                    <p style="margin: 12px 0 0 0; font-size: 0.78rem; color: var(--muted); text-align: center;">
                        Khóa đối tượng: <code><?= htmlspecialchars($req['handover_doc_key']) ?></code>
                    </p>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--muted);">
                        <i class="bi bi-file-earmark-x" style="font-size: 3rem; opacity: 0.4;"></i>
                        <p style="margin-top: 10px; font-weight: 600;">Phiếu mượn này không có tệp đính kèm.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

</body>
</html>