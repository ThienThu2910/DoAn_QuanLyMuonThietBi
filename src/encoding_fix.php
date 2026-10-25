<?php
/* ==========================================================================
   encoding_fix.php — tự sửa dữ liệu tiếng Việt bị lỗi "mojibake"
   --------------------------------------------------------------------------
   Nguyên nhân: init.sql từng được import không khai báo UTF-8, MySQL đọc các byte
   UTF-8 như latin1 rồi mã hoá lại lần nữa → "Quản trị viên" thành "Quáº£n trá»‹ viÃªn".
   Hàm dưới đảo ngược đúng phép biến đổi đó cho các cột văn bản. Chạy tối đa một lần
   mỗi phiên làm việc, chỉ ghi khi PHÁT HIỆN dữ liệu lỗi; dữ liệu đúng không bị đụng tới.
   ========================================================================== */

/** Trả về chuỗi đã sửa, hoặc null nếu chuỗi không bị lỗi mã hoá. */
function altf4_demojibake(string $s): ?string {
    if ($s === '' || !preg_match('/[^\x00-\x7F]/', $s)) {
        return null;                                   // rỗng hoặc thuần ASCII
    }
    static $cp1252 = [
        0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85, 0x2020 => 0x86,
        0x2021 => 0x87, 0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A, 0x2039 => 0x8B, 0x0152 => 0x8C,
        0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95,
        0x2013 => 0x96, 0x2014 => 0x97, 0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B,
        0x0153 => 0x9C, 0x017E => 0x9E, 0x0178 => 0x9F,
    ];
    $bytes = '';
    foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
        $cp = mb_ord($ch, 'UTF-8');
        if ($cp < 0x100) {
            $bytes .= chr($cp);                        // latin1 + vùng điều khiển 0x80–0x9F
        } elseif (isset($cp1252[$cp])) {
            $bytes .= chr($cp1252[$cp]);               // ký tự đặc biệt của cp1252 (€ ‚ ƒ „ … ‹ ...)
        } else {
            return null;                               // có chữ ngoài latin1 → đây là tiếng Việt đúng, bỏ qua
        }
    }
    if ($bytes === $s || !mb_check_encoding($bytes, 'UTF-8')) {
        return null;
    }
    return $bytes;
}

/** Có đang bị lỗi mã hoá không? (kiểm tra vài bản ghi mẫu, rất nhẹ) */
function altf4_needs_repair(PDO $pdo): bool {
    $probes = [
        "SELECT fullname FROM users ORDER BY id LIMIT 3",
        "SELECT name FROM categories ORDER BY id LIMIT 3",
        "SELECT name FROM devices ORDER BY id LIMIT 3",
    ];
    foreach ($probes as $sql) {
        try {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $v) {
                if (altf4_demojibake((string)$v) !== null) {
                    return true;
                }
            }
        } catch (Throwable $e) { /* bảng chưa có */ }
    }
    return false;
}

/** Sửa toàn bộ cột văn bản đã biết. Trả về số ô dữ liệu đã được sửa. */
function altf4_repair_encoding(PDO $pdo): int {
    $targets = [
        'users'           => ['fullname'],
        'categories'      => ['name', 'description'],
        'devices'         => ['name', 'description', 'location'],
        'borrow_requests' => ['note', 'reject_reason', 'return_note'],
        'borrow_logs'     => ['note'],
        'file_metadata'   => ['original_name'],
    ];
    $fixed = 0;
    foreach ($targets as $table => $cols) {
        foreach ($cols as $col) {
            try {
                $rows = $pdo->query("SELECT id, `$col` AS v FROM `$table` WHERE `$col` IS NOT NULL")->fetchAll();
            } catch (Throwable $e) {
                continue;                              // bảng / cột chưa tồn tại
            }
            $up = $pdo->prepare("UPDATE `$table` SET `$col` = ? WHERE id = ?");
            foreach ($rows as $r) {
                $new = altf4_demojibake((string)$r['v']);
                if ($new !== null) {
                    $up->execute([$new, $r['id']]);
                    $fixed++;
                }
            }
        }
    }
    return $fixed;
}

/** Gọi từ config.php: kiểm tra một lần mỗi phiên, sửa nếu cần, làm mới thông tin người đăng nhập. */
function altf4_auto_repair(PDO $pdo): void {
    if (!empty($_SESSION['_enc_ok'])) {
        return;
    }
    try {
        if (altf4_needs_repair($pdo)) {
            altf4_repair_encoding($pdo);
            if (!empty($_SESSION['user']['id'])) {     // tên hiển thị đang lưu trong session cũng bị lỗi
                $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $st->execute([$_SESSION['user']['id']]);
                if ($fresh = $st->fetch()) {
                    $_SESSION['user'] = $fresh;
                }
            }
        }
        $_SESSION['_enc_ok'] = true;
    } catch (Throwable $e) {
        // không để việc tự sửa làm hỏng trang
    }
}
