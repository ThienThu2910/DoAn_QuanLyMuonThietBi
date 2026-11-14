<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$host = getenv('DB_HOST') ?: 'database';
$db   = getenv('DB_NAME') ?: 'ql_thietbi';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'rootpassword';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.');
}

// Tự sửa dữ liệu tiếng Việt bị lỗi mã hoá (Quáº£n trá»‹ viÃªn → Quản trị viên) nếu có
require_once __DIR__ . '/encoding_fix.php';
altf4_auto_repair($pdo);

function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login.php");
        exit;
    }
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $stmt = $GLOBALS['pdo']->prepare('SELECT id, fullname, student_code, email, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    if (!$currentUser) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: /login.php');
        exit;
    }
    $_SESSION['user'] = $currentUser;
}

function checkAdmin() {
    checkLogin();
    if ($_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        exit('Truy cập bị từ chối: Bạn không có quyền Administrator!');
    }
}

function app_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function app_require_csrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ hoặc phiên đã hết hạn. Vui lòng tải lại trang và thử lại.');
    }
}

function app_post_string(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function app_password_matches($plain, string $stored): bool {
    if (!is_string($plain)) return false;
    $info = password_get_info($stored);
    if (!empty($info['algo'])) {
        return password_verify($plain, $stored);
    }
    // Tương thích tài khoản cũ trong khi được nâng cấp dần sang hash.
    return hash_equals($stored, $plain);
}

function app_password_hash(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

function app_is_valid_date($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false && $date->format('Y-m-d') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

/** Update aggregate inventory when an approved loan is returned. Call inside a transaction. */
function app_return_borrowed_stock(PDO $pdo, int $deviceId, int $quantity, string $condition): void {
    if ($quantity < 1 || !in_array($condition, ['good', 'damaged', 'lost', 'incomplete'], true)) {
        throw new RuntimeException('Thông tin hoàn trả không hợp lệ.');
    }

    $stmt = $pdo->prepare('SELECT quantity, available_qty, status FROM devices WHERE id = ? FOR UPDATE');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();
    if (!$device) throw new RuntimeException('Không tìm thấy thiết bị trong kho.');

    $total = (int)$device['quantity'];
    $available = (int)$device['available_qty'];
    if ($total < $quantity) throw new RuntimeException('Số lượng tồn kho không nhất quán; chưa thể hoàn tất phiếu trả.');

    if ($condition === 'lost') {
        // Món bị mất không quay lại kho: bỏ khỏi tổng tồn, không cộng vào số khả dụng.
        $newTotal = $total - $quantity;
        if ($available > $newTotal) throw new RuntimeException('Số lượng khả dụng không nhất quán; chưa thể ghi nhận mất thiết bị.');
        $newStatus = $newTotal === 0 ? 'decommissioned' : $device['status'];
        $pdo->prepare('UPDATE devices SET quantity = ?, status = ? WHERE id = ?')->execute([$newTotal, $newStatus, $deviceId]);
        return;
    }

    $newAvailable = $available + $quantity;
    if ($newAvailable > $total) throw new RuntimeException('Số lượng khả dụng vượt tổng tồn kho; chưa thể hoàn tất phiếu trả.');
    $newStatus = in_array($condition, ['damaged', 'incomplete'], true) ? 'maintenance' : $device['status'];
    $pdo->prepare('UPDATE devices SET available_qty = ?, status = ? WHERE id = ?')->execute([$newAvailable, $newStatus, $deviceId]);
}
