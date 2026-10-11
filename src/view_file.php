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
header("Cache-Control: public, max-age=604800, immutable");
header("Content-Disposition: inline; filename=\"" . basename($key) . "\"");

echo $file['body'];
exit;