# Improvement report — Alt F4 Hub

## Các cải tiến đã thực hiện

| Lĩnh vực | Thay đổi | Lý do | Tệp chính |
|---|---|---|---|
| Đăng nhập | Hash password mới; tự hash lại legacy khi đăng nhập; không giữ password trong session; xoay session ID | Giảm tác động khi DB/session lộ và chống session fixation | `src/config.php`, `src/login.php`, `src/register.php`, `src/profile.php`, `src/admin/users.php` |
| Session | HttpOnly, SameSite Lax; role và hồ sơ được tải lại từ DB ở request có bảo vệ | Giảm CSRF/session risk và chặn role cũ | `src/config.php` |
| CSRF | Token session cho form; chuyển thao tác thay đổi trạng thái/xóa/hủy/reset sang POST | Chặn request cross-site và state change qua GET | Trang admin, `src/my_borrows.php`, `src/request_detail.php`, `src/logout.php`, `src/setup.php`, `src/reset_pw.php` |
| Bảo toàn dữ liệu | FK restrict được áp dụng trong SQL mới và setup cũ; app chặn xóa category/device có dữ liệu con | Giữ thiết bị và lịch sử mượn | `init.sql`, `src/setup.php`, `src/admin/categories.php`, `src/admin/devices.php` |
| Kho và giao dịch | Khóa request/device trong approve/return; log ở quick actions; kiểm tra status khi bàn giao; không giảm tồn thấp hơn lượng đang mượn | Giảm cập nhật lặp và lệch tồn | `src/admin/requests.php`, `src/request_detail.php`, `src/borrow.php`, `src/admin/device_edit.php` |
| Kiểm kê khi mất thiết bị | Dùng chung helper trong transaction; thiết bị mất giảm tổng tồn nhưng không được cộng vào hàng khả dụng; khi hết toàn bộ tồn thì chuyển ngừng sử dụng | Ngăn số liệu tồn kho tăng sai khi ghi nhận mất | `src/config.php`, `src/admin/requests.php`, `src/request_detail.php` |
| Validation | Kiểm tra ngày lịch và scalar POST; kiểm tra trạng thái/category/student phía server | Không dựa riêng validation trình duyệt | `src/config.php`, `src/borrow.php`, trang thiết bị và tài khoản |
| Upload | Kiểm tra MIME thực, extension allowlist, kích thước; object key ngẫu nhiên; ghi metadata cho file mới và cleanup object mới khi DB thất bại | Chặn file sai loại, theo dõi file thay mới và giảm object mồ côi trong lỗi ghi DB | `src/minio_helper.php`, `src/admin/devices.php`, `src/admin/device_edit.php`, `src/borrow.php` |
| Tài liệu | Kiểm tra object có gắn entity hợp lệ, giới hạn file phiếu theo chủ/admin, cache `private` | Chặn đọc object tùy ý và cache file xác thực công khai | `src/view_file.php` |
| CSV | Trung hòa cell formula trước khi xuất | Tránh công thức chạy khi mở file | `src/admin/export.php` |
| Docker | Cài PHP cURL và fileinfo extension | Tránh lỗi gọi `curl_init()` và bảo đảm kiểm tra MIME upload | `Dockerfile` |
| Đồng bộ dữ liệu | Setup admin-only, CSRF, hash legacy không đổi giá trị, seed 10 account idempotent | Giảm endpoint công khai và thao tác lặp gây reset password | `src/setup.php`, `init.sql`, `README.md` |
| Schema mới | Khai báo đầy đủ trạng thái/vị trí/tài liệu thiết bị, trường kiểm toán phiếu và `borrow_logs` trong SQL khởi tạo | Tránh trang thiết bị và thao tác mượn/trả phát sinh cảnh báo/lỗi ngay trên database mới | `init.sql` |
| Trải nghiệm mượn | Admin tìm kiếm/chọn sinh viên bằng gợi ý và thẻ xác nhận; dark-mode tăng tương phản tên sinh viên | Rút gọn danh sách và đọc rõ thông tin ở giao diện tối | `src/borrow.php`, `src/device_detail.php`, `src/assets/theme.css` |
| Kiểm thử | Thêm static regression checks chạy lại được trên Windows PowerShell | Bắt các invariant bảo mật/cấu hình mà không đụng DB thật | `tests/run-static-checks.ps1` |
| Smoke test QA | Thêm runner kiểm tra HTTP login/role/CSRF/MinIO, dùng biến môi trường cho credential | Chạy lại nhanh trên stack cô lập mà không ghi dữ liệu nghiệp vụ | `tests/run-qa-smoke.mjs`, `tests/compose.qa.yaml`, `tests/compose.schema.qa.yaml` |

## Kết quả xác minh thực tế

- `docker compose config --quiet` cho compose gốc, QA và schema QA: PASS.
- `node --check src/assets/theme.js` và `tests/run-qa-smoke.mjs`: PASS.
- `tests/run-static-checks.ps1`: PASS, 35 assertion.
- `git diff --check`: PASS.
- Docker build và PHP lint trong app container: PASS, 30/30 tệp.
- HTTP smoke test: PASS, 11/11 assertion; app, phpMyAdmin, MinIO, role gates và CSRF trả lời đúng.
- MySQL setup/schema QA, đăng nhập/role, mượn/trả tốt/mất, PNG MinIO, từ chối file sai/quá cỡ và picker dark mode được kiểm tra như phạm vi ghi trong [TEST_CASES.md](TEST_CASES.md).
- Tổng cộng 40 ca: 27 PASS, 0 FAIL, 13 BLOCKED; không khẳng định các chức năng chưa chạy.

Stack test dùng named volume khác và chỉ bind cổng vào `127.0.0.1`. Không gắn, reset hoặc xóa volume/object của stack gốc. Các service QA còn đang chạy để xem lại.

## Hạn chế còn lại

- Static checks không thay thế PHP lint hoặc integration/E2E test.
- Demo secrets vẫn cần được chuyển sang secrets cấu hình riêng trước triển khai công khai.
- Chưa có rate limiting, malware scanning hoặc cleanup job cho object thay thế cũ.
- Nên chạy test runtime trên DB/MinIO riêng trước khi đưa migration lên môi trường đang có dữ liệu.
- Còn 13 test case cần chạy thêm: concurrency, các nhánh CRUD/validation, CSV output, lỗi phụ thuộc và các kích thước/keyboard UX.
