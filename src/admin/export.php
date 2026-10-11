<?php
require_once '../config.php';
checkAdmin();

// Thiết lập header xuất file CSV tương thích tốt với Excel tiếng Việt (UTF-8 BOM)
$filename = "BaoCao_MuonThietBi_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// Ghi byte UTF-8 BOM để Excel không bị lỗi font tiếng Việt
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Tiêu đề các cột
fputcsv($output, [
    'Mã Phiếu',
    'Họ và Tên Sinh Viên',
    'Mã Sinh Viên',
    'Email',
    'Tên Thiết Bị',
    'Mã Thiết Bị',
    'Số Lượng',
    'Ngày Mượn',
    'Hạn Dự Kiến Trả',
    'Ngày Trả Thực Tế',
    'Trạng Thái',
    'Tình Trạng Khi Trả',
    'Lý Do Từ Chối / Ghi Chú'
]);

$sql = "
    SELECT b.id, u.fullname, u.student_code, u.email, d.name as device_name, d.code as device_code,
           b.quantity, b.borrow_date, b.expected_return_date, b.actual_return_date,
           b.status, b.return_condition, COALESCE(b.reject_reason, b.return_note, b.note) as notes
    FROM borrow_requests b
    JOIN users u ON b.user_id = u.id
    JOIN devices d ON b.device_id = d.id
    ORDER BY b.id DESC
";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['fullname'],
        $row['student_code'],
        $row['email'],
        $row['device_name'],
        $row['device_code'],
        $row['quantity'],
        $row['borrow_date'],
        $row['expected_return_date'],
        $row['actual_return_date'] ?? 'Chưa hoàn trả',
        strtoupper($row['status']),
        $row['return_condition'],
        $row['notes']
    ]);
}

fclose($output);
exit;