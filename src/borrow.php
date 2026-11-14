<?php
require_once 'config.php';
require_once 'minio_helper.php';
checkLogin();
$isAdmin = ($_SESSION['user']['role'] === 'admin');

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
$students = $isAdmin
    ? $pdo->query("SELECT id, fullname, student_code, email FROM users WHERE role = 'student' ORDER BY fullname")->fetchAll()
    : [];
$selectedStudentId = (int)($_POST['student_id'] ?? 0);
$selectedStudent = null;
foreach ($students as $student) {
    if ((int)$student['id'] === $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_csrf();
    $qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $borrowDate = app_post_string('borrow_date');
    $expectedReturn = app_post_string('expected_return_date');
    $note = trim(app_post_string('note'));
    $borrowerId = $isAdmin ? (filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT) ?: 0) : (int)$_SESSION['user']['id'];

    if ($isAdmin) {
        $studentCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $studentCheck->execute([$borrowerId]);
        if (!$studentCheck->fetchColumn()) {
            $error = 'Vui lòng chọn một tài khoản sinh viên hợp lệ.';
        }
    }

    // 1. Kiểm tra tính hợp lệ của dữ liệu mượn
    if ($error) {
        // Giữ thông báo xác thực sinh viên.
    } elseif (($device['status'] ?? 'good') !== 'good') {
        $error = 'Thiết bị hiện không ở trạng thái sẵn sàng để bàn giao.';
    } elseif ($qty < 1 || $qty > $device['available_qty']) {
        $error = "Số lượng yêu cầu không hợp lệ hoặc vượt quá số lượng hiện có ({$device['available_qty']}).";
    } elseif (!app_is_valid_date($borrowDate) || !app_is_valid_date($expectedReturn)) {
                $error = "Vui lòng chọn ngày mượn và ngày hẹn trả hợp lệ.";
    } elseif ($borrowDate < $today) {
        $error = "Ngày mượn không hợp lệ! Bạn không thể đăng ký mượn vào ngày đã qua.";
    } elseif ($expectedReturn < $borrowDate) {
        $error = "Ngày dự kiến trả không được nhỏ hơn ngày bắt đầu mượn.";
    } else {
        $handoverDocKey = null;
        $handoverMeta = null;

        // 2. Xử lý tải tệp minh chứng lên MinIO
        if (isset($_FILES['handover_doc']) && !empty($_FILES['handover_doc']['name'])) {
            $upload = minio_validate_uploaded_file($_FILES['handover_doc'], ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'], 2 * 1024 * 1024);
            if (!$upload['valid']) {
                $error = $upload['error'];
            } elseif ($upload['mime'] !== null) {
                $tmpFile  = $_FILES['handover_doc']['tmp_name'];
                $origName = $_FILES['handover_doc']['name'];
                $fileSize = $_FILES['handover_doc']['size'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $mimeType = $upload['mime'];
                    $objectKey   = 'handover_docs/' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $fileContent = file_get_contents($tmpFile);

                    try {
                        $minio = new MinioClient();
                        if ($minio->uploadObject($objectKey, $fileContent, $mimeType)) {
                            $handoverDocKey = $objectKey;
                            $handoverMeta = [$origName, $objectKey, $mimeType, $fileSize];
                        } else {
                            $error = 'Không thể lưu tệp lên MinIO. Vui lòng kiểm tra dịch vụ lưu trữ.';
                        }
                    } catch (Exception $e) {
                        error_log('Handover upload failed: ' . $e->getMessage());
                        $error = 'Không thể tải tệp lên kho lưu trữ. Vui lòng thử lại sau.';
                    }
            }
        }

        // 3. Ghi nhận phiếu mượn nếu không phát sinh lỗi
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                $lockDevice = $pdo->prepare('SELECT available_qty, status FROM devices WHERE id = ? FOR UPDATE');
                $lockDevice->execute([$device['id']]);
                $lockedDevice = $lockDevice->fetch();
                $availableQty = (int)($lockedDevice['available_qty'] ?? 0);
                if (($lockedDevice['status'] ?? 'good') !== 'good') throw new RuntimeException('Thiết bị hiện không ở trạng thái sẵn sàng để bàn giao.');
                if ($qty > $availableQty) {
                    throw new RuntimeException("Chỉ còn {$availableQty} thiết bị khả dụng.");
                }

                if ($isAdmin) {
                    $insert = $pdo->prepare("INSERT INTO borrow_requests (user_id, device_id, quantity, borrow_date, expected_return_date, handover_doc_key, note, status, handover_by, handover_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())");
                    $insert->execute([$borrowerId, $device['id'], $qty, $borrowDate, $expectedReturn, $handoverDocKey, $note, $_SESSION['user']['id']]);
                    $requestId = (int)$pdo->lastInsertId();
                    if ($handoverMeta) $pdo->prepare("INSERT INTO file_metadata (original_name, object_key, file_type, file_size, entity_id, entity_type) VALUES (?, ?, ?, ?, ?, 'borrow_request')")->execute([...$handoverMeta, $requestId]);
                    $pdo->prepare('UPDATE devices SET available_qty = available_qty - ? WHERE id = ?')->execute([$qty, $device['id']]);
                    $pdo->prepare("INSERT INTO borrow_logs (request_id, actor_id, action, note) VALUES (?, ?, 'approved', ?)")
                        ->execute([$requestId, $_SESSION['user']['id'], 'Quản trị viên ghi nhận bàn giao thiết bị trực tiếp']);
                    $success = 'Đã ghi nhận bàn giao thiết bị cho sinh viên thành công.';
                } else {
                    $insert = $pdo->prepare("INSERT INTO borrow_requests (user_id, device_id, quantity, borrow_date, expected_return_date, handover_doc_key, note, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $insert->execute([$borrowerId, $device['id'], $qty, $borrowDate, $expectedReturn, $handoverDocKey, $note]);
                    $requestId = (int)$pdo->lastInsertId();
                    if ($handoverMeta) $pdo->prepare("INSERT INTO file_metadata (original_name, object_key, file_type, file_size, entity_id, entity_type) VALUES (?, ?, ?, ?, ?, 'borrow_request')")->execute([...$handoverMeta, $requestId]);
                    $success = 'Đã gửi yêu cầu mượn thành công! Vui lòng chờ quản trị viên phê duyệt.';
                }
                $pdo->commit();
                $device['available_qty'] = $availableQty - ($isAdmin ? $qty : 0);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($handoverDocKey) (new MinioClient())->deleteObject($handoverDocKey);
                error_log('Borrow request save failed: ' . $e->getMessage());
                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : ($isAdmin ? 'Không thể ghi nhận bàn giao. Vui lòng thử lại.' : 'Không thể gửi yêu cầu mượn. Vui lòng thử lại.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isAdmin ? 'Ghi Nhận Bàn Giao' : 'Đăng Ký Mượn' ?> • <?= htmlspecialchars($device['name']) ?></title>
    <style>
        .date-picker-field { display: flex; align-items: center; gap: 8px; }
        .date-picker-field .form-control { flex: 1; min-width: 0; margin-bottom: 0; }
        .date-picker-trigger { flex: 0 0 44px; height: 44px; border: 1px solid #CBD5E1; border-radius: 12px; background: #fff; color: #2563EB; font-size: 1.05rem; cursor: pointer; }
        .date-picker-trigger:hover { background: #EFF6FF; border-color: #93C5FD; }
        .date-picker-trigger:focus-visible { outline: 3px solid rgba(37, 99, 235, .25); outline-offset: 2px; }
        .student-picker { position: relative; }
        .student-search-wrap { position: relative; }
        .student-search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #718096; pointer-events: none; }
        .student-search-wrap .form-control { padding-left: 42px; margin-bottom: 0; }
        .student-results { position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; right: 0; max-height: 260px; overflow-y: auto; padding: 6px; border: 1px solid #D6E2DF; border-radius: 14px; background: #fff; box-shadow: 0 14px 32px rgba(15, 24, 47, .14); }
        .student-result { display: block; width: 100%; padding: 10px 12px; border: 0; border-radius: 9px; background: transparent; color: #18332E; text-align: left; cursor: pointer; }
        .student-result[hidden] { display: none; }
        .student-result:hover, .student-result:focus-visible { background: #EAF5F1; outline: none; }
        .student-result strong, .student-result span { display: block; }
        .student-result span { margin-top: 2px; color: #718096; font-size: .82rem; }
        .student-empty { padding: 12px; color: #718096; font-size: .9rem; }
        .student-selected { display: flex; align-items: center; gap: 12px; padding: 13px 14px; border: 1px solid #B7DED0; border-radius: 14px; background: #F0F8F4; }
        .student-selected[hidden] { display: none; }
        .student-selected-icon { display: grid; place-items: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; background: #D8EEE4; color: #167455; font-size: 1.1rem; }
        .student-selected-info { flex: 1; min-width: 0; }
        .student-selected-info strong, .student-selected-info span { display: block; overflow-wrap: anywhere; }
        .student-selected-info span { margin-top: 2px; color: #61756D; font-size: .84rem; }
        .student-change { border: 0; background: transparent; color: #167455; font: inherit; font-size: .88rem; font-weight: 700; cursor: pointer; }
    </style>
    <?php include __DIR__ . '/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<main class="container narrow">
<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="font-weight: 700; font-size: 1.5rem; margin: 0;"><?= $isAdmin ? 'Ghi Nhận Bàn Giao Thiết Bị' : 'Phiếu Mượn Thiết Bị' ?></h2>
        <a data-return href="/device_detail.php?id=<?= $deviceId ?>" style="color: var(--accent); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
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
            <a href="<?= $isAdmin ? '/admin/requests.php?status=approved' : '/my_borrows.php' ?>" style="color: #065F46; font-weight: bold;"><?= $isAdmin ? 'Xem danh sách thiết bị đang cho mượn' : 'Xem danh sách phiếu mượn của bạn' ?> &rarr;</a>
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($isAdmin): ?>
            <div class="form-group">
                <label for="student-search">Sinh viên nhận thiết bị</label>
                <div class="student-picker">
                    <input type="hidden" name="student_id" id="student_id" value="<?= $selectedStudent ? (int)$selectedStudent['id'] : '' ?>">
                    <div class="student-search-wrap" id="student-search-wrap" <?= $selectedStudent ? 'hidden' : '' ?>>
                        <i class="bi bi-search student-search-icon" aria-hidden="true"></i>
                        <input type="search" id="student-search" class="form-control" placeholder="Tìm theo tên, mã sinh viên hoặc email" autocomplete="off" aria-autocomplete="list" aria-controls="student-results" aria-expanded="false" <?= $selectedStudent ? '' : 'required' ?>>
                    </div>
                    <div id="student-results" class="student-results" role="listbox" aria-label="Kết quả tìm sinh viên" hidden>
                    <?php foreach ($students as $student): ?>
                        <?php $studentCode = $student['student_code'] ?: 'Chưa có mã sinh viên'; ?>
                        <button type="button" class="student-result" role="option" aria-selected="false"
                                data-id="<?= (int)$student['id'] ?>"
                                data-name="<?= htmlspecialchars($student['fullname'], ENT_QUOTES, 'UTF-8') ?>"
                                data-code="<?= htmlspecialchars($studentCode, ENT_QUOTES, 'UTF-8') ?>"
                                data-email="<?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?>">
                            <strong><?= htmlspecialchars($student['fullname']) ?></strong>
                            <span><?= htmlspecialchars($studentCode) ?> · <?= htmlspecialchars($student['email']) ?></span>
                        </button>
                    <?php endforeach; ?>
                        <div class="student-empty" id="student-empty" hidden>Không tìm thấy sinh viên phù hợp.</div>
                    </div>
                    <div class="student-selected" id="student-selected" <?= $selectedStudent ? '' : 'hidden' ?>>
                        <span class="student-selected-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                        <div class="student-selected-info">
                            <strong id="selected-student-name"><?= htmlspecialchars($selectedStudent['fullname'] ?? '') ?></strong>
                            <span id="selected-student-meta"><?php if ($selectedStudent): ?><?= htmlspecialchars($selectedStudent['student_code'] ?: 'Chưa có mã sinh viên') ?> · <?= htmlspecialchars($selectedStudent['email']) ?><?php endif; ?></span>
                        </div>
                        <button type="button" class="student-change" id="student-change">Đổi</button>
                    </div>
                </div>
                <?php if (!$students): ?><small style="color:#B91C1C">Chưa có tài khoản sinh viên trong hệ thống.</small><?php endif; ?>
                <small style="display:block; margin-top:8px; color:#64748B">Phiếu sẽ được ghi nhận là đã bàn giao và trừ số lượng khả dụng ngay.</small>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label>Số lượng cần mượn</label>
            <input type="number" name="quantity" class="form-control" min="1" max="<?= $device['available_qty'] ?>" value="1" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label>Ngày bắt đầu mượn</label>
                <div class="date-picker-field">
                    <input type="date"
                           id="borrow_date"
                           name="borrow_date"
                           class="form-control"
                           min="<?= $today ?>"
                           value="<?= $today ?>"
                           onchange="updateReturnDateMin()"
                           required>
                    <button type="button" class="date-picker-trigger" data-date-picker="borrow_date" aria-label="Mở lịch chọn ngày bắt đầu mượn" title="Chọn ngày">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Ngày dự kiến trả</label>
                <div class="date-picker-field">
                    <input type="date"
                           id="expected_return_date"
                           name="expected_return_date"
                           class="form-control"
                           min="<?= $today ?>"
                           value="<?= date('Y-m-d', strtotime('+3 days')) ?>"
                           required>
                    <button type="button" class="date-picker-trigger" data-date-picker="expected_return_date" aria-label="Mở lịch chọn ngày dự kiến trả" title="Chọn ngày">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                    </button>
                </div>
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
            <i class="bi <?= $isAdmin ? 'bi-box-arrow-up-right' : 'bi-send-fill' ?> me-1"></i> <?= $isAdmin ? 'Xác Nhận Bàn Giao' : 'Gửi Yêu Cầu Mượn' ?>
        </button>
    </form>
    <?php endif; ?>
</div>
</main>

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

document.querySelectorAll('[data-date-picker]').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.datePicker);
        if (!input) return;
        if (typeof input.showPicker === 'function') input.showPicker();
        else { input.focus(); input.click(); }
    });
});

const studentSearch = document.getElementById('student-search');
const studentSelect = document.getElementById('student_id');
const studentResults = document.getElementById('student-results');
const studentSearchWrap = document.getElementById('student-search-wrap');
const studentSelectedCard = document.getElementById('student-selected');
const studentEmpty = document.getElementById('student-empty');
function normalizeStudentSearch(value) {
    return value.toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
}
function closeStudentResults() {
    if (!studentResults || !studentSearch) return;
    studentResults.hidden = true;
    studentSearch.setAttribute('aria-expanded', 'false');
}
function selectStudent(option) {
    studentSelect.value = option.dataset.id;
    document.getElementById('selected-student-name').textContent = option.dataset.name;
    document.getElementById('selected-student-meta').textContent = option.dataset.code + ' · ' + option.dataset.email;
    studentSearchWrap.hidden = true;
    studentSelectedCard.hidden = false;
    studentSearch.required = false;
    studentSearch.value = '';
    closeStudentResults();
}
if (studentSearch && studentSelect && studentResults) {
    studentSearch.addEventListener('input', function () {
        const query = normalizeStudentSearch(studentSearch.value.trim());
        const options = Array.from(studentResults.querySelectorAll('.student-result'));
        let visibleCount = 0;
        options.forEach(function (option) {
            const searchable = normalizeStudentSearch(option.dataset.name + ' ' + option.dataset.code + ' ' + option.dataset.email);
            const matches = query.length >= 1 && searchable.includes(query);
            option.hidden = !matches;
            option.setAttribute('aria-selected', 'false');
            if (matches) visibleCount++;
        });
        studentEmpty.hidden = query.length < 1 || visibleCount > 0;
        studentResults.hidden = query.length < 1;
        studentSearch.setAttribute('aria-expanded', query.length >= 1 ? 'true' : 'false');
    });
    studentSearch.addEventListener('keydown', function (event) {
        const visibleOptions = Array.from(studentResults.querySelectorAll('.student-result:not([hidden])'));
        if (event.key === 'ArrowDown' && visibleOptions.length) {
            event.preventDefault(); visibleOptions[0].focus();
        } else if (event.key === 'Escape') {
            closeStudentResults();
        }
    });
    studentResults.addEventListener('keydown', function (event) {
        const visibleOptions = Array.from(studentResults.querySelectorAll('.student-result:not([hidden])'));
        const index = visibleOptions.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' && index < visibleOptions.length - 1) {
            event.preventDefault(); visibleOptions[index + 1].focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (index > 0) visibleOptions[index - 1].focus();
            else studentSearch.focus();
        } else if (event.key === 'Escape') {
            closeStudentResults(); studentSearch.focus();
        }
    });
    studentResults.addEventListener('click', function (event) {
        const option = event.target.closest('.student-result');
        if (option) selectStudent(option);
    });
    document.getElementById('student-change').addEventListener('click', function () {
        studentSelect.value = '';
        studentSelectedCard.hidden = true;
        studentSearchWrap.hidden = false;
        studentSearch.required = true;
        studentSearch.value = '';
        studentSearch.focus();
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.student-picker')) closeStudentResults();
    });
}
</script>

</body>
</html>
