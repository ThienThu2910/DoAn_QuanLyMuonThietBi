<?php
require_once 'config.php';
require_once 'minio_helper.php';
checkLogin();

$deviceId = (int)($_GET['device_id'] ?? 0);
$stmt = $pdo->prepare("SELECT d.*, c.name as category_name FROM devices d JOIN categories c ON d.category_id = c.id WHERE d.id = ?");
$stmt->execute([$deviceId]);
$device = $stmt->fetch();

if (!$device) {
    die("Thiết bị không tồn tại! <a href='/index.php'>Quay lại</a>");
}

$error = '';
$success = '';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = (int)($_POST['quantity'] ?? 1);
    $borrowDate = $_POST['borrow_date'] ?? '';
    $expectedReturn = $_POST['expected_return_date'] ?? '';
    $note = trim($_POST['note'] ?? '');

    // 1. Kiểm tra tính hợp lệ của dữ liệu mượn
    if ($qty < 1 || $qty > $device['available_qty']) {
        $error = "Số lượng yêu cầu không hợp lệ hoặc vượt quá số lượng hiện có ({$device['available_qty']}).";
    } elseif (empty($borrowDate) || empty($expectedReturn)) {
        $error = "Vui lòng chọn đầy đủ ngày mượn và ngày hẹn trả.";
    } elseif ($borrowDate < $today) {
        $error = "Ngày mượn không hợp lệ! Bạn không thể đăng ký mượn vào ngày đã qua.";
    } elseif ($expectedReturn < $borrowDate) {
        $error = "Ngày dự kiến trả không được nhỏ hơn ngày bắt đầu mượn.";
    } else {
        $handoverDocKey = null;

        // 2. Xử lý tải tệp minh chứng lên MinIO
        if (isset($_FILES['handover_doc']) && !empty($_FILES['handover_doc']['name'])) {
            $fileError = $_FILES['handover_doc']['error'];

            if ($fileError === UPLOAD_ERR_INI_SIZE || $fileError === UPLOAD_ERR_FORM_SIZE) {
                $error = "Tệp đính kèm quá lớn vượt quá giới hạn tải lên của hệ thống. Vui lòng chọn tệp dung lượng nhỏ hơn (dưới 2MB).";
            } elseif ($fileError !== UPLOAD_ERR_OK) {
                $error = "Lỗi khi tải tệp từ máy lên (Mã lỗi: " . $fileError . "). Vui lòng thử lại.";
            } else {
                $tmpFile  = $_FILES['handover_doc']['tmp_name'];
                $origName = $_FILES['handover_doc']['name'];
                $fileSize = $_FILES['handover_doc']['size'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $mimeType = mime_content_type($tmpFile);

                $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
                if (!in_array($ext, $allowed)) {
                    $error = "Định dạng tệp không được hỗ trợ. Chỉ chấp nhận tài liệu PDF hoặc hình ảnh (PNG, JPG, JPEG, WEBP).";
                } else {
                    $objectKey   = "handover_docs/" . uniqid('doc_') . "." . $ext;
                    $fileContent = file_get_contents($tmpFile);

                    try {
                        $minio = new MinioClient();
                        if ($minio->uploadObject($objectKey, $fileContent, $mimeType)) {
                            $handoverDocKey = $objectKey;

                            // Lưu thông tin metadata vào cơ sở dữ liệu
                            $stmtMeta = $pdo->prepare("INSERT INTO file_metadata (original_name, object_key, file_type, file_size, entity_type) VALUES (?, ?, ?, ?, 'borrow_request')");
                            $stmtMeta->execute([$origName, $objectKey, $mimeType, $fileSize]);
                        } else {
                            $error = "Không thể tải tệp lên kho MinIO. Vui lòng mở bảng điều khiển MinIO Console (http://localhost:9001) kiểm tra xem đã tạo bucket 'project-files' chưa.";
                        }
                    } catch (Exception $e) {
                        $error = "Lỗi kết nối MinIO S3: " . $e->getMessage();
                    }
                }
            }
        }

        // 3. Ghi nhận phiếu mượn nếu không phát sinh lỗi
        if (empty($error)) {
            $insert = $pdo->prepare("INSERT INTO borrow_requests (user_id, device_id, quantity, borrow_date, expected_return_date, handover_doc_key, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insert->execute([
                $_SESSION['user']['id'],
                $device['id'],
                $qty,
                $borrowDate,
                $expectedReturn,
                $handoverDocKey,
                $note
            ]);
            $success = "Đã gửi yêu cầu mượn thành công! Vui lòng chờ quản trị viên phê duyệt.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Mượn Thiết Bị • <?= htmlspecialchars($device['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --ink: #020C21;
            --frame: #E6EDF6;
            --cta: #0F1B31;
            --accent: #2563EB;
            --glass-bg: rgba(255, 255, 255, 0.78);
            --glass-border: rgba(255, 255, 255, 0.9);
        }
        body {
            background-color: var(--frame);
            color: var(--ink);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            margin: 0;
        }
        .form-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            backdrop-filter: blur(40px);
            box-shadow: 0 20px 45px rgba(28, 52, 92, 0.08);
            width: 100%;
            max-width: 650px;
            padding: 40px;
            box-sizing: border-box;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #334155;
        }
        .form-control {
            width: 100%;
            padding: 12px 18px;
            border-radius: 14px;
            border: 1px solid #CBD5E1;
            background: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            outline: none;
            box-sizing: border-box;
            font-family: inherit;
            transition: border 0.2s ease;
        }
        .form-control:focus {
            border-color: var(--accent);
        }
        .btn-submit {
            background: var(--cta);
            color: #fff;
            padding: 14px 28px;
            border-radius: 999px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .btn-submit:hover {
            opacity: 0.92;
            transform: translateY(-2px);
        }
        .alert {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
    </style>
</head>
<body>

<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="font-weight: 700; font-size: 1.5rem; margin: 0;">Phiếu Mượn Thiết Bị</h2>
        <a href="/index.php" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
            <i class="bi bi-arrow-left"></i> Quay lại kho
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?><br><br>
            <a href="/my_borrows.php" style="color: #065F46; font-weight: bold;">Xem danh sách phiếu mượn của bạn &rarr;</a>
        </div>
    <?php else: ?>

    <div style="background: rgba(255,255,255,0.6); padding: 18px; border-radius: 18px; margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.8);">
        <div style="font-size: 0.8rem; color: var(--accent); font-weight: 700; text-transform: uppercase;">
            <?= htmlspecialchars($device['code']) ?> • <?= htmlspecialchars($device['category_name']) ?>
        </div>
        <div style="font-size: 1.25rem; font-weight: 700; margin: 4px 0;"><?= htmlspecialchars($device['name']) ?></div>
        <div style="font-size: 0.9rem; color: #64748B;">
            Hiện có sẵn: <strong style="color: #059669;"><?= $device['available_qty'] ?></strong> / <?= $device['quantity'] ?> thiết bị
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Số lượng cần mượn</label>
            <input type="number" name="quantity" class="form-control" min="1" max="<?= $device['available_qty'] ?>" value="1" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label>Ngày bắt đầu mượn</label>
                <input type="date" 
                       id="borrow_date" 
                       name="borrow_date" 
                       class="form-control" 
                       min="<?= $today ?>" 
                       value="<?= $today ?>" 
                       onchange="updateReturnDateMin()"
                       required>
            </div>
            <div class="form-group">
                <label>Ngày dự kiến trả</label>
                <input type="date" 
                       id="expected_return_date" 
                       name="expected_return_date" 
                       class="form-control" 
                       min="<?= $today ?>" 
                       value="<?= date('Y-m-d', strtotime('+3 days')) ?>" 
                       required>
            </div>
        </div>

        <div class="form-group">
            <label>Tệp minh chứng đính kèm (Biên bản / Thẻ SV - MinIO S3)</label>
            <input type="file" name="handover_doc" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
            <small style="color: #64748B; font-size: 0.78rem;">Chấp nhận: PDF, PNG, JPG, JPEG, WEBP (Khuyến nghị dung lượng &lt; 2MB)</small>
        </div>

        <div class="form-group">
            <label>Mục đích sử dụng thiết bị</label>
            <textarea name="note" class="form-control" rows="3" placeholder="Nhập mục đích sử dụng (Đồ án, bài tập thực hành...)"></textarea>
        </div>

        <button type="submit" class="btn-submit">
            <i class="bi bi-send-fill me-1"></i> Gửi Yêu Cầu Mượn
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
function updateReturnDateMin() {
    const borrowDateInput = document.getElementById('borrow_date');
    const returnDateInput = document.getElementById('expected_return_date');
    
    if (borrowDateInput.value) {
        returnDateInput.min = borrowDateInput.value;
        if (returnDateInput.value < borrowDateInput.value) {
            returnDateInput.value = borrowDateInput.value;
        }
    }
}
</script>

</body>
</html>