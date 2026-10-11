<?php
session_start();

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
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}

function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: /login.php");
        exit;
    }
}

function checkAdmin() {
    checkLogin();
    if ($_SESSION['user']['role'] !== 'admin') {
        die("Truy cập bị từ chối: Bạn không có quyền Administrator!");
    }
}