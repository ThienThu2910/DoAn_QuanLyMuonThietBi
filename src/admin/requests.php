<?php
require_once '../config.php';
checkAdmin();

$msg = '';
$err = '';
$filter_status = trim($_GET['status'] ?? '');

// 1. Xử lý các thao tác quản trị
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM borrow_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if ($req) {
        try {
            $pdo->beginTransaction();
            $adminId = $_SESSION['user']['id'];

            // A. Duyệt & Bàn giao thiết bị
            if ($action === 'approve' && $req['status'] === 'pending') {
                $avail = $pdo->query("SELECT available_qty FROM devices WHERE id = {$req['device_id']} FOR UPDATE")->fetchColumn();
                if ($avail >= $req['quantity']) {
                    $pdo->exec("UPDATE devices SET available_qty = available_qty - {$req['quantity']} WHERE id = {$req['device_id']}");
                    $pdo->prepare("UPDATE borrow_requests SET status = 'approved', handover_by = ?, handover_at = NOW() WHERE id = ?")
                        ->execute([$adminId, $requestId]);
                    
                    // Ghi log
                    $pdo->prepare("INSERT INTO borrow_logs (request_id, actor_id, action, note) VALUES (?, ?, 'approved', 'Đã duyệt và bàn giao thiết bị')")
                        ->execute([$requestId, $adminId]);

                    $msg = "Đã duyệt và xác nhận bàn giao thiết bị cho phiếu #{$requestId}!";
                } else {
                    $err = "Không đủ số lượng thiết bị khả dụng trong kho!";
                }
            } 
            // B. Từ chối kèm lý do
            elseif ($action === 'reject' && $req['status'] === 'pending') {
                $reason = trim($_POST['reject_reason'] ?? 'Không đủ điều kiện mượn');
                $pdo->prepare("UPDATE borrow_requests SET status = 'rejected', reject_reason = ? WHERE id = ?")
                    ->execute([$reason, $requestId]);

                // Ghi log
                $pdo->prepare("INSERT INTO borrow_logs (request_id, actor_id, action, note) VALUES (?, ?, 'rejected', ?)")
                    ->execute([$requestId, $adminId, "Từ chối: " . $reason]);

                $msg = "Đã từ chối phiếu mượn #{$requestId}.";
            } 
            // C. Nhận lại thiết bị & Đánh giá tình trạng
            elseif ($action === 'return' && $req['status'] === 'approved') {
                $condition = $_POST['return_condition'] ?? 'good';
                $returnNote = trim($_POST['return_note'] ?? '');

                // Hoàn trả vào kho
                $pdo->exec("UPDATE devices SET available_qty = available_qty + {$req['quantity']} WHERE id = {$req['device_id']}");
                
                // Nếu bị hỏng hoặc mất, cập nhật trạng thái thiết bị sang bảo trì
                if (in_array($condition, ['damaged', 'lost'])) {
                    $pdo->exec("UPDATE devices SET status = 'maintenance' WHERE id = {$req['device_id']}");
                }

                $pdo->prepare("UPDATE borrow_requests SET status = 'returned', actual_return_date = CURDATE(), return_condition = ?, return_note = ? WHERE id = ?")
                    ->execute([$condition, $returnNote, $requestId]);

                // Ghi log
                $pdo->prepare("INSERT INTO borrow_logs (request_id, actor_id, action, note) VALUES (?, ?, 'returned', ?)")
                    ->execute([$requestId, $adminId, "Hoàn trả: Tình trạng {$condition}. Ghi chú: {$returnNote}"]);

                $msg = "Đã xác nhận hoàn trả thiết bị và kiểm toán kho thành công!";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = "Lỗi xử lý: " . $e->getMessage();
        }
    }
}

// 2. Lấy danh sách phiếu mượn
$sql = "
    SELECT b.*, u.fullname, u.student_code, u.email, d.name as device_name, d.code as device_code 
    FROM borrow_requests b
    JOIN users u ON b.user_id = u.id
    JOIN devices d ON b.device_id = d.id
    WHERE 1=1
";
$params = [];
if (in_array($filter_status, ['pending', 'approved', 'returned', 'rejected'])) {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
}
$sql .= " ORDER BY b.id DESC";

$stmtReq = $pdo->prepare($sql);
$stmtReq->execute($params);
$requests = $stmtReq->fetchAll();

$today = new DateTime('today');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xét Duyệt Phiếu Mượn • Admin</title>
    <style>
        body { background-color: #E6EDF6; font-family: 'Plus Jakarta Sans', sans-serif; color: #020C21; margin: 0; padding-bottom: 60px; }
        .container { max-width: 1240px; margin: 0 auto; padding: 0 16px; }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn {
            text-decoration: none; padding: 8px 18px; border-radius: 999px; font-size: 0.88rem; font-weight: 600; color: #59627E;
            background: rgba(255, 255, 255, 0.55); border: 1px solid rgba(255, 255, 255, 0.85); transition: all 0.2s ease;
        }
        .tab-btn.active { background: #0F182F; color: #fff; }
        .glass-box {
            background: rgba(255, 255, 255, 0.78); border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 28px; padding: 30px; backdrop-filter: blur(40px);
            box-shadow: 0 16px 40px rgba(15, 24, 47, 0.05);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(206, 213, 224, 0.6); }
        th { font-size: 0.8rem; text-transform: uppercase; color: #64748B; font-weight: 700; }
        .status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; }
        .status-pending  { background: #FEF3C7; color: #92400E; }
        .status-approved { background: #D1FAE5; color: #065F46; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        .status-returned { background: #E2E8F0; color: #334155; }
        .btn-action { padding: 6px 14px; border-radius: 999px; border: none; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s ease; }
    </style>
    <?php include __DIR__ . '/../head.php'; ?>
</head>
<body>

<?php include __DIR__ . '/../navbar.php'; ?>

<main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 800; margin: 0;">Quản Lý & Duyệt Phiếu Mượn</h1>
            <p style="color: #64748B; margin: 6px 0 0;">Phê duyệt bàn giao, ghi nhận lý do từ chối và nghiệm thu kiểm tra khi trả</p>
        </div>
        <a href="/admin/export.php" style="background: #059669; color: #fff; padding: 10px 22px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: 0.88rem;">
            <i class="bi bi-file-earmark-excel me-1"></i> Xuất Báo Cáo Excel/CSV
        </a>
    </div>

    <?php if ($msg): ?><div style="padding: 14px 20px; background: #D1FAE5; color: #065F46; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div style="padding: 14px 20px; background: #FEE2E2; color: #991B1B; border-radius: 14px; margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="filter-tabs">
        <a href="/admin/requests.php" class="tab-btn <?= empty($filter_status) ? 'active' : '' ?>">Tất cả</a>
        <a href="/admin/requests.php?status=pending" class="tab-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">Chờ duyệt</a>
        <a href="/admin/requests.php?status=approved" class="tab-btn <?= $filter_status === 'approved' ? 'active' : '' ?>">Đang mượn</a>
        <a href="/admin/requests.php?status=returned" class="tab-btn <?= $filter_status === 'returned' ? 'active' : '' ?>">Đã hoàn trả</a>
        <a href="/admin/requests.php?status=rejected" class="tab-btn <?= $filter_status === 'rejected' ? 'active' : '' ?>">Từ chối</a>
    </div>

    <div class="glass-box">
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Sinh Viên</th>
                    <th>Thiết Bị</th>
                    <th>Số Lượng</th>
                    <th>Thời Gian</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><strong>#<?= $r['id'] ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($r['fullname']) ?></strong><br>
                            <small style="color: #64748B;"><?= htmlspecialchars($r['student_code'] ?? 'N/A') ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['device_name']) ?></strong><br>
                            <code style="color: #2563EB; font-size: 0.8rem;"><?= htmlspecialchars($r['device_code']) ?></code>
                        </td>
                        <td><strong><?= $r['quantity'] ?></strong></td>
                        <td style="font-size: 0.85rem;">
                            <?= date('d/m/Y', strtotime($r['borrow_date'])) ?> &rarr; <?= date('d/m/Y', strtotime($r['expected_return_date'])) ?>
                        </td>
                        <td>
                            <span class="status-pill status-<?= $r['status'] ?>">
                                <?php 
                                    $map = ['pending' => 'Chờ duyệt', 'approved' => 'Đang mượn', 'rejected' => 'Từ chối', 'returned' => 'Đã trả'];
                                    echo $map[$r['status']] ?? $r['status'];
                                ?>
                            </span>
                        </td>
                        <td>
                            <a href="/request_detail.php?id=<?= $r['id'] ?>" style="background: rgba(37,99,235,0.1); color: #2563EB; padding: 6px 14px; border-radius: 999px; text-decoration: none; font-size: 0.8rem; font-weight: 700; margin-right: 4px;">
                                Chi tiết
                            </a>

                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-action" style="background: #10B981; color: #fff;" onclick="return confirm('Duyệt và bàn giao thiết bị cho sinh viên?')">
                                        Duyệt & Bàn giao
                                    </button>
                                </form>
                                <button type="button" class="btn-action" style="background: #EF4444; color: #fff;" onclick="openRejectModal(<?= $r['id'] ?>)">
                                    Từ chối
                                </button>
                            <?php elseif ($r['status'] === 'approved'): ?>
                                <button type="button" class="btn-action" style="background: #3B82F6; color: #fff;" onclick="openReturnModal(<?= $r['id'] ?>)">
                                    Nhận lại thiết bị
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal Từ Chối Kèm Lý Do -->
<div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: #fff; padding: 30px; border-radius: 24px; width: 100%; max-width: 440px;">
        <h3 style="margin-top: 0;">Lý Do Từ Chối Phiếu Mượn</h3>
        <form method="POST">
            <input type="hidden" name="id" id="rejectId">
            <input type="hidden" name="action" value="reject">
            <textarea name="reject_reason" style="width: 100%; height: 100px; padding: 12px; border-radius: 12px; border: 1px solid #CBD5E1; box-sizing: border-box; margin-bottom: 15px;" placeholder="Nhập lý do (VD: Thiết bị đang bảo trì, SV mượn quá số lượng quy định...)" required></textarea>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModals()" style="padding: 8px 18px; border-radius: 999px; border: 1px solid #CBD5E1; background: #fff; cursor: pointer;">Đóng</button>
                <button type="submit" style="padding: 8px 20px; border-radius: 999px; border: none; background: #EF4444; color: #fff; font-weight: 700; cursor: pointer;">Xác Nhận Từ Chối</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nhận Lại & Kiểm Tra Tình Trạng -->
<div id="returnModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: #fff; padding: 30px; border-radius: 24px; width: 100%; max-width: 480px;">
        <h3 style="margin-top: 0;">Nghiệm Thu & Nhận Lại Thiết Bị</h3>
        <form method="POST">
            <input type="hidden" name="id" id="returnId">
            <input type="hidden" name="action" value="return">
            
            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Tình trạng thiết bị khi hoàn trả</label>
            <select name="return_condition" style="width: 100%; padding: 10px; border-radius: 12px; border: 1px solid #CBD5E1; margin-bottom: 14px;">
                <option value="good">Hoạt động tốt, nguyên vẹn</option>
                <option value="damaged">Hư hỏng / Trầy xước nặng (Chuyển bảo trì)</option>
                <option value="incomplete">Thiếu phụ kiện / cáp kết nối</option>
                <option value="lost">Mất mát thiết bị</option>
            </select>

            <label style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px;">Ghi chú kiểm tra</label>
            <textarea name="return_note" style="width: 100%; height: 80px; padding: 10px; border-radius: 12px; border: 1px solid #CBD5E1; box-sizing: border-box; margin-bottom: 15px;" placeholder="Ghi nhận phụ kiện đi kèm hoặc tình trạng phát sinh nếu có"></textarea>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModals()" style="padding: 8px 18px; border-radius: 999px; border: 1px solid #CBD5E1; background: #fff; cursor: pointer;">Hủy</button>
                <button type="submit" style="padding: 8px 20px; border-radius: 999px; border: none; background: #3B82F6; color: #fff; font-weight: 700; cursor: pointer;">Xác Nhận Nhận Lại</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('rejectId').value = id;
    document.getElementById('rejectModal').style.display = 'flex';
}
function openReturnModal(id) {
    document.getElementById('returnId').value = id;
    document.getElementById('returnModal').style.display = 'flex';
}
function closeModals() {
    document.getElementById('rejectModal').style.display = 'none';
    document.getElementById('returnModal').style.display = 'none';
}
</script>

</body>
</html>