# Test cases — Alt F4 Hub

**Ngày chạy:** 2026-11-14. Runtime checks dùng stack Docker QA với named volumes riêng (`altf4codexqa20261114_*` và `altf4schemaqa20261114_*`); cổng chỉ bind vào `127.0.0.1`. Không gắn volume DB/MinIO gốc.

`PASS` chỉ áp dụng cho phạm vi đã chạy ở cột kết quả. Những phần còn lại của một chức năng rộng được ghi rõ ở ca riêng hoặc trong ghi chú, không suy ra từ việc đọc source.

| ID | Nhóm | Kiểm thử / dữ liệu | Kết quả mong đợi | Kết quả hiện tại |
|---|---|---|---|---|
| ENV-01 | Môi trường | Parse Compose gốc, QA và schema QA | Cả ba cấu hình hợp lệ | PASS — `docker compose config --quiet` |
| UI-01 | Frontend | Parse JavaScript giao diện và runner QA | Không lỗi cú pháp | PASS — `node --check` cho `theme.js`, `run-qa-smoke.mjs`, `run-qa-return-conditions.mjs` |
| STATIC-01 | Regression tĩnh | `tests/run-static-checks.ps1` | Invariant bảo mật/cấu hình không bị mất | PASS — 35 assertion |
| ENV-02 | Môi trường | Trạng thái app, MySQL, MinIO, phpMyAdmin | Các service QA khởi động | PASS — bốn container Up; DB được truy vấn thành công |
| ENV-03 | Môi trường | HTTP app, phpMyAdmin, MinIO readiness/console | Endpoint trả lời đúng | PASS — app/login, phpMyAdmin, MinIO readiness và console trả HTTP 200 |
| ENV-04 | Môi trường | PHP lint toàn bộ trang và setup | Không lỗi parse | PASS — 30/30 tệp PHP |
| AUTH-01 | Tài khoản | Đăng nhập admin và sinh viên bằng credential đúng | Redirect theo role | PASS — dashboard admin và trang thiết bị sinh viên |
| AUTH-02 | Tài khoản | Đăng nhập sai mật khẩu | Bị từ chối, không có lỗi server | PASS — giữ trang login và hiện thông báo từ chối |
| AUTH-03 | Tài khoản | Chạy setup trên dữ liệu QA có password cũ và kiểm tra hash | Password được hash, đăng nhập vẫn hoạt động | PASS — login admin/student hoạt động; toàn bộ 13 password QA có tiền tố bcrypt `$2y$` sau setup |
| AUTH-04 | Tài khoản | Đăng ký: thiếu trường, email/mã trùng, ký tự đặc biệt | Validation/unique constraint đúng, không XSS | BLOCKED — chưa chạy các nhánh đăng ký này |
| AUTH-05 | Tài khoản | Đổi role rồi dùng session admin đã mở | Quyền lấy lại từ DB và chặn session bị hạ quyền | PASS — admin truy cập được; sau khi hạ role, cùng session nhận HTTP 403 |
| AUTH-06 | Tài khoản | Đổi mật khẩu ở hồ sơ: mật khẩu hiện tại đúng/sai, mismatch, quá ngắn | Chỉ lưu hash khi hợp lệ | BLOCKED — chưa chạy đủ các nhánh form hồ sơ |
| AUTH-07 | Tài khoản | POST thao tác phiếu quản trị không có CSRF token | Trả 403, không đổi dữ liệu | PASS — HTTP 403 |
| AUTH-08 | Tài khoản | Sinh viên mở setup/reset/debug | Trả 403; admin setup vẫn yêu cầu POST + CSRF | PASS — student nhận HTTP 403 ở cả ba endpoint; setup đã chạy bằng admin+CSRF |
| USERS-01 | Người dùng | Tạo tài khoản QA, đổi role, đặt lại password | Hash được lưu; role/session và password mới có hiệu lực | PASS — tạo QAUSER01, promotion/demotion được phản ánh ngay trong session, password cũ bị từ chối và password mới đăng nhập được |
| USERS-02 | Người dùng | Hạ quyền chính admin đang đăng nhập | Không tự khóa/mất an toàn quản trị | BLOCKED — chưa thử |
| DEV-01 | Thiết bị | Tìm kiếm, filter, sort, phân trang và empty state | Danh sách và URL nhất quán | BLOCKED — chưa chạy ma trận danh sách |
| DEV-02 | Thiết bị | Tạo thiết bị hợp lệ có ảnh PNG | Lưu record và file object | PASS — QA-IMAGE-01 được tạo; ảnh được upload lên MinIO |
| DEV-03 | Thiết bị | Sửa thiết bị, thay ảnh và trở lại đúng vị trí dòng vừa sửa | Lưu metadata, redirect có anchor | PASS — metadata file mới được ghi; redirect trở lại `#device-4` và vị trí dòng được khôi phục trên browser |
| DEV-04 | Thiết bị | Giảm tổng lượng thấp hơn số đang cho mượn | Từ chối cập nhật | BLOCKED — mới kiểm tra invariant tĩnh |
| DEV-05 | Thiết bị | Xóa thiết bị có lịch sử / không có lịch sử | Giữ lịch sử; chỉ xóa khi an toàn | BLOCKED — mới kiểm tra source tĩnh |
| DEV-06 | Danh mục | Xóa danh mục có thiết bị / danh mục trống | Không cascade xóa thiết bị | BLOCKED — mới kiểm tra source và DDL |
| BORROW-01 | Mượn | Sinh viên gửi yêu cầu hợp lệ và ngày lịch không tồn tại | Tạo phiếu pending; ngày giả mạo bị chặn | PASS — pending request tạo được; `2026-02-31` bị từ chối; sinh viên tự hủy phiếu pending thành công |
| BORROW-02 | Mượn | Admin chọn sinh viên, bàn giao trực tiếp | Tạo phiếu approved và giảm available đúng | PASS — phiếu approved; available của thiết bị giảm 10 xuống 9 |
| BORROW-03 | Mượn | Admin gửi student ID không tồn tại hoặc tài khoản admin | Không tạo phiếu | BLOCKED — chưa chạy |
| BORROW-04 | Mượn | Mượn thiết bị maintenance/broken/decommissioned hoặc tồn thấp | Không tạo giao dịch/trừ kho | BLOCKED — chưa chạy |
| BORROW-05 | Duyệt | Hai thao tác duyệt đồng thời cho cùng phiếu | Chỉ một chuyển trạng thái/trừ kho | BLOCKED — chưa chạy concurrency test |
| BORROW-06 | Trả | Trả thiết bị tốt hoặc ghi nhận mất | Good tăng available đúng một lần; lost giảm total và không tăng available | PASS — good return; lost return lưu `returned/lost`, số lượng total giảm và không cộng available |
| BORROW-07 | Phiếu | Sinh viên hủy phiếu pending của mình | Chỉ chủ phiếu hủy được pending | PASS — chủ phiếu hủy thành công |
| BORROW-08 | Phiếu | Sinh viên mở phiếu người khác | Trả 403 và không lộ nội dung phiếu | PASS — HTTP 403 |
| BORROW-09 | Trả | Trả thiết bị hỏng hoặc thiếu phụ kiện | Ghi condition/log; chuyển thiết bị sang bảo trì và cập nhật khả dụng | PASS — cả `damaged` và `incomplete` trả HTTP 200, lưu condition/log; device status thành maintenance |
| FILE-01 | MinIO | Ảnh PNG hợp lệ, extension sai và file vượt giới hạn | Chỉ file hợp lệ được lưu | PASS — PNG hợp lệ lên MinIO; `.txt` và ảnh vượt 5 MB bị từ chối |
| FILE-02 | MinIO | Đọc ảnh thiết bị đã xác thực | Đúng nội dung và MIME | PASS — proxy trả HTTP 200, `image/png`, 85 bytes |
| FILE-03 | Quyền file | Sinh viên thử object key không liên kết tới entity được phép | Không đọc được | PASS — object tùy ý trả 404 |
| REPORT-01 | Báo cáo | CSV có ô bắt đầu `=`, `+`, `-`, `@` | CSV tiếng Việt đúng, công thức được trung hòa | BLOCKED — mới kiểm tra source tĩnh |
| UI-02 | Giao diện | Ma trận desktop/tablet/mobile, dark mode, tên dài và bàn phím | Bố cục và tương phản phù hợp | BLOCKED — chưa chạy đủ kích thước và thao tác bàn phím |
| UI-03 | Giao diện | Picker sinh viên: trạng thái chưa chọn, lọc và dark mode | Chỉ hiện kết quả phù hợp; tên/email dễ đọc | PASS — browser xác nhận thẻ chưa chọn và kết quả không khớp được ẩn; tên/email đọc rõ ở dark mode |
| OPS-01 | Lỗi hệ thống | MySQL/MinIO mất kết nối trong request | HTTP/log an toàn, không lộ secrets | BLOCKED — chưa mô phỏng lỗi phụ thuộc |
| MIG-01 | Database | Setup lặp trên schema cũ thiếu cột/bảng | Bổ sung schema và chạy lặp không lỗi | PASS — setup trên QA cũ chạy được; lần chạy thứ hai HTTP 200 |
| MIG-02 | Database | Khởi tạo schema mới và kiểm tra seed/password | Bảng/cột cần thiết tồn tại, seed được hash | PASS — import `init.sql` mới; 12 user seed, không có password plaintext; device enum và `borrow_logs` tồn tại |

## Cách chạy lại

Static checks:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/run-static-checks.ps1
```

Stack QA dùng project name riêng và bind cổng loopback:

```powershell
docker compose -p altf4codexqa20261114 -f tests/compose.qa.yaml up -d --build
$env:ALT_F4_ADMIN_EMAIL = 'admin@gmail.com'
$env:ALT_F4_ADMIN_PASSWORD = 'admin123'
$env:ALT_F4_STUDENT_EMAIL = 'student@gmail.com'
$env:ALT_F4_STUDENT_PASSWORD = '123456'
node tests/run-qa-smoke.mjs
```

Không gắn dữ liệu stack QA vào volume dự án gốc. Không dùng `down -v` nếu muốn giữ fixture QA.
