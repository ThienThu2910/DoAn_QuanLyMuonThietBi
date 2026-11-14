<?php
require_once 'config.php';
require_once 'minio_helper.php';
checkLogin();

// 1. Mở khóa luồng Session ngay lập tức để tải song song nhiều ảnh cùng lúc
session_write_close();

$key = trim($_GET['key'] ?? '');
if (empty($key)) {
    http_response_code(400);
    die("Thiếu khóa tệp hợp lệ.");
}

// Chỉ proxy object đang gắn với thiết bị hoặc phiếu mượn mà người dùng có quyền xem.
$deviceFile = $pdo->prepare('SELECT 1 FROM devices WHERE image_key = ? OR manual_doc_key = ? LIMIT 1');
$deviceFile->execute([$key, $key]);
$canRead = (bool)$deviceFile->fetchColumn();
if (!$canRead) {
    $borrowFile = $pdo->prepare('SELECT user_id FROM borrow_requests WHERE handover_doc_key = ? LIMIT 1');
    $borrowFile->execute([$key]);
    $ownerId = $borrowFile->fetchColumn();
    $canRead = $ownerId !== false && (
        $_SESSION['user']['role'] === 'admin' || (int)$ownerId === (int)$_SESSION['user']['id']
    );
}
if (!$canRead) {
    http_response_code(404);
    exit('Không tìm thấy tệp tin.');
}

// 2. Kiểm tra bộ nhớ đệm trình duyệt (HTTP 304 Not Modified)
$etag = '"' . md5($key) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

$minio = new MinioClient();
$file = $minio->getObject($key);

if (!$file) {
    http_response_code(404);
    die("Không tìm thấy tệp tin trên hệ thống lưu trữ MinIO.");
}

// 3. Thiết lập tiêu đề HTTP tối ưu hóa truyền tải nhị phân
header("Content-Type: " . $file['content_type']);
header("Content-Length: " . strlen($file['body']));
header("ETag: " . $etag);
header("Cache-Control: private, max-age=3600, immutable");
header('X-Content-Type-Options: nosniff');
header("Content-Disposition: inline; filename=\"" . basename($key) . "\"");

echo $file['body'];
exit;
