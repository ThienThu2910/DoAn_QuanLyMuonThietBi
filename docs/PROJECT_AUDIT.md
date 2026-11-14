# Project Audit — Alt F4 Hub

**Ngày audit:** 2026-11-14  
**Phạm vi:** đọc tĩnh mã nguồn hiện có, cấu hình Docker, SQL và tài liệu. Chưa thể xác minh hành vi runtime trong vòng audit này vì Docker Engine từ chối kết nối và không có PHP CLI trong shell.

## Tổng quan kiến trúc

Ứng dụng quản lý mượn thiết bị phòng Lab, triển khai bằng Docker Compose với bốn dịch vụ:

- `app`: PHP 8.2 + Apache, PDO; mã PHP vừa xử lý nghiệp vụ vừa render HTML.
- `database`: MySQL 8.0, dữ liệu lưu trong named volume `thietbi_db_data`.
- `minio`: object storage, dữ liệu lưu trong named volume `thietbi_minio_data`.
- `phpmyadmin`: giao diện quản trị MySQL.

Không có framework, router/API riêng, Composer hoặc npm. `src` được bind-mount vào container; `init.sql` chỉ tự chạy khi MySQL khởi tạo volume rỗng. `setup.php` bổ sung schema cho các phiên bản cũ. PDO dùng prepared statements ở phần lớn truy vấn dữ liệu người dùng.

## Cấu trúc và vai trò mã nguồn

- Trang truy cập và nghiệp vụ: `index.php`, `device_detail.php`, `borrow.php`, `my_borrows.php`, `request_detail.php`, `profile.php`, `login.php`, `register.php`, `logout.php`.
- Quản trị: `admin/index.php`, `admin/devices.php`, `admin/device_edit.php`, `admin/categories.php`, `admin/users.php`, `admin/requests.php`, `admin/reports.php`, `admin/export.php`.
- Hạ tầng/tiện ích: `config.php`, `setup.php`, `encoding_fix.php`, `minio_helper.php`, `view_file.php`, `ui.php`, `navbar.php`, `head.php`.
- Tệp phụ trợ cần xem xét: `reset_pw.php`, `check_password.php`.
- Frontend: `assets/theme.css`, `assets/theme.js`, `assets/loader.css`, `loader.php`, `hero_art.php`, `auth_aside.php`.

## Danh sách chức năng hiện có

### Tài khoản và phân quyền

- Đăng nhập, đăng xuất, đăng ký sinh viên.
- Hồ sơ tài khoản và đổi mật khẩu.
- Quản trị viên thêm tài khoản, đổi vai trò, đặt lại mật khẩu.
- `checkLogin()` bảo vệ trang yêu cầu đăng nhập; `checkAdmin()` bảo vệ các trang quản trị chính.

### Kho thiết bị

- Trang kho có tìm kiếm, lọc danh mục, sắp xếp, phân trang và thống kê tồn kho.
- Xem chi tiết thiết bị, ảnh/tài liệu hướng dẫn và khởi tạo luồng mượn.
- Quản trị thêm, sửa, xóa thiết bị; cập nhật ảnh, tài liệu, số lượng, tình trạng và vị trí.
- Trang danh mục cho phép thêm/xóa; trang báo cáo có thống kê danh mục, sinh viên và thiết bị sự cố.

### Mượn và trả

- Sinh viên gửi yêu cầu với số lượng, ngày mượn/trả, ghi chú và tệp minh chứng; yêu cầu bắt đầu ở trạng thái `pending`.
- Quản trị duyệt/bàn giao, từ chối và nhận lại thiết bị; có ghi log nếu schema đã được đồng bộ.
- Quản trị có thể ghi nhận bàn giao trực tiếp cho một tài khoản sinh viên tại `borrow.php`.
- Sinh viên xem lịch sử, lọc trạng thái và hủy yêu cầu đang chờ.
- Có trang chi tiết phiếu, thống kê hạn trả/cảnh báo và xuất CSV.

### Tệp và giao diện

- `MinioClient` ký AWS Signature V4 cho PUT/GET/DELETE.
- `view_file.php` proxy object từ MinIO; giao diện có sáng/tối, menu responsive, thông báo, hiệu ứng, xác nhận và tiện ích biểu mẫu.

## Cấu trúc dữ liệu

| Bảng | Vai trò | Quan hệ/chú ý |
|---|---|---|
| `users` | Tài khoản, mã sinh viên, email, mật khẩu, role | `student_code` và `email` unique |
| `categories` | Nhóm thiết bị | `devices.category_id` hiện khai báo `ON DELETE CASCADE` |
| `devices` | Thiết bị và số lượng | được tham chiếu bởi phiếu mượn; xóa cascade trong `init.sql` |
| `borrow_requests` | Phiếu mượn/trả, ngày, trạng thái, ghi chú, object key | liên kết user và device; các cột audit được thêm trong `setup.php` |
| `file_metadata` | Metadata tệp | chưa có FK tới entity; chưa thấy quy trình đồng bộ xóa metadata |
| `borrow_logs` | Nhật ký thao tác phiếu | được tạo bởi `setup.php`, không có FK trong DDL setup |

Trạng thái phiếu: `pending`, `approved`, `rejected`, `returned`. Các trường `handover_by`, `handover_at`, `reject_reason`, `return_condition`, `return_note` được bổ sung khi chạy setup.

## Luồng dữ liệu

1. Trình duyệt gửi GET/POST tới các trang PHP trực tiếp.
2. Mỗi trang nạp `config.php`, mở session và tạo kết nối PDO tới MySQL bằng biến môi trường.
3. PHP kiểm tra role ở cấp trang, truy vấn MySQL và render HTML.
4. Upload được PHP chuyển tiếp sang MinIO; object key lưu trong `devices` hoặc `borrow_requests`, một phần có metadata ở `file_metadata`.
5. Trình duyệt tải nội dung qua `view_file.php`, trang này lấy object trực tiếp từ MinIO rồi trả binary.
6. Compose cung cấp MySQL/MinIO credentials mặc định cho môi trường demo; không có lớp API/service độc lập.

## Vấn đề phát hiện qua đọc tĩnh

Đây là phát hiện trong source, chưa phải kết quả khai thác hoặc kiểm thử runtime.

| Mức độ | Phát hiện | Bằng chứng/ảnh hưởng |
|---|---|---|
| Critical | Mật khẩu lưu và so sánh dạng plaintext | `init.sql`, `login.php`, `register.php`, `profile.php`, `admin/users.php`; lộ DB đồng nghĩa lộ credential. README cũng cảnh báo chỉ dùng demo. |
| Critical | Công cụ nhạy cảm mở công khai | `setup.php`, `reset_pw.php`, `check_password.php` không gọi `checkLogin()`/`checkAdmin()`. Setup còn đặt lại mật khẩu demo và chỉnh schema. |
| High | Thiếu CSRF protection | Các form POST và hành động đổi/xóa/duyệt không có token CSRF. Một số thay đổi trạng thái/xóa dùng GET. |
| High | Xóa danh mục có thể xóa thiết bị trực thuộc | FK `devices.category_id` dùng `ON DELETE CASCADE`, trong khi giao diện báo không thể xóa khi còn thiết bị. Đây là sai khác giữa thông báo và hành vi DB. |
| High | Xóa thiết bị có thể xóa lịch sử phiếu | FK `borrow_requests.device_id` dùng `ON DELETE CASCADE`; cần xác nhận chính sách lưu lịch sử trước khi cho xóa. |
| High | Xử lý upload chỉ kiểm tra extension ở một số luồng | `admin/devices.php`, `admin/device_edit.php`, `borrow.php`; chưa thấy giới hạn kích thước và xác minh MIME/nội dung nhất quán phía server. |
| High | File proxy không kiểm tra quyền theo entity | `view_file.php` yêu cầu đăng nhập nhưng người dùng đã đăng nhập có thể thử object key tùy ý; chưa có kiểm tra quyền sở hữu/quan hệ. |
| High | CSV có nguy cơ formula injection | `admin/export.php` xuất dữ liệu DB vào CSV mà chưa escape ô bắt đầu bằng `=`, `+`, `-`, `@`. |
| High | Thay đổi trạng thái phiếu tồn tại ở hai luồng | `admin/requests.php` dùng POST + log; `request_detail.php` dùng GET `action`, cập nhật kho riêng và không đồng bộ đầy đủ audit fields/log. Dễ lệch hành vi và kiểm kê. |
| Medium | Điều chỉnh số lượng thiết bị có thể lệch số đang mượn | `admin/device_edit.php` tính `available_qty` theo chênh lệch tổng, không đối chiếu số lượng đang ở ngoài kho khi giảm quantity. |
| Medium | Kiểm tra ngày mượn chỉ so sánh chuỗi | `borrow.php` chưa xác nhận định dạng/ngày lịch hợp lệ phía server ngoài so sánh min/max. |
| Medium | Session hardening chưa thấy | `login.php` chưa gọi `session_regenerate_id(true)` sau đăng nhập; `config.php` khởi tạo session mặc định, chưa cấu hình cookie flags. |
| Medium | Mật khẩu mới thiếu chính sách nhất quán | Đăng ký kiểm tra độ dài tối thiểu, nhưng `profile.php` và reset/admin add không áp dụng cùng quy tắc; hiện vẫn lưu plaintext. |
| Medium | Xóa object/metadata không đồng bộ | Có `deleteObject()` nhưng không thấy luồng dọn object/metadata thống nhất khi thay ảnh, xóa thiết bị/phiếu. Có thể để lại object mồ côi. |
| Medium | Cấu hình secrets mặc định trong compose | MySQL root và MinIO credentials ghi thẳng trong `compose.yaml`; `.env.example` không được compose sử dụng. Chấp nhận cho demo nội bộ, không an toàn khi public. |
| Low | Lỗi DB trả chi tiết exception trực tiếp | `config.php` in `$e->getMessage()` khi kết nối thất bại, có thể lộ thông tin hệ thống. |
| Low | Dữ liệu thống kê/reports cần kiểm tra ý nghĩa | `admin/reports.php` dùng phép JOIN/grouping có thể bỏ danh mục không có thiết bị và đếm theo số phiếu thay vì số lượng mượn. |

## Kế hoạch cải tiến ưu tiên

1. Chặn truy cập công khai các trang bảo trì/debug; dùng quyền admin và xác nhận rõ cho các tác vụ bảo trì.
2. Thêm CSRF token cho form và chuyển thao tác trạng thái/xóa sang POST.
3. Chuẩn hóa một service nghiệp vụ cho approve/return và cập nhật kho trong transaction; gỡ luồng GET thay đổi trạng thái.
4. Rà soát chính sách FK/lưu lịch sử trước khi thay đổi cascade; bổ sung kiểm tra số lượng đang mượn trước khi sửa/xóa.
5. Xác minh MIME, kích thước, tên và nội dung file ở server; đồng bộ dọn object/metadata an toàn.
6. Escape CSV chống công thức; giới hạn quyền đọc file theo entity.
7. Thêm kiểm thử tách biệt cho validation, quyền, transaction, CRUD và upload; ghi kết quả runtime thực tế.
8. Sau khi có môi trường runtime, chạy smoke test qua browser và kiểm tra log, DB, MinIO, HTTP status và console.

## Giới hạn xác minh hiện tại

- `docker ps` bị từ chối kết nối Docker Engine (`npipe://./pipe/dockerDesktopLinuxEngine`), vì vậy chưa xác minh container, MySQL, phpMyAdmin, MinIO, bucket hoặc HTTP của website.
- `php` không có trong PATH của host; chưa chạy `php -l` hoặc PHPUnit.
- Không có `composer.json`, `package.json`, test suite hoặc migration framework trong repository.
- Chưa thao tác với dữ liệu thật hoặc Docker volumes.

## Cập nhật sau đợt khắc phục

Các mục ở bảng phát hiện bên trên là snapshot ban đầu. Từ sau snapshot, source đã được cập nhật như sau; các sửa đổi vẫn cần xác minh runtime:

- **Đã xử lý trong source:** hash mật khẩu mới, nâng cấp legacy password khi đăng nhập/setup, session cookie HttpOnly/SameSite, xoay session ID và đọc lại role từ DB; CSRF cho form thay đổi dữ liệu; chuyển thao tác xóa/hủy/role/reset và approve/return sang POST.
- **Đã xử lý trong source:** serialize approve/return bằng khóa hàng, lưu nhật ký khi thao tác từ trang chi tiết, giữ lịch sử khi xóa thiết bị/danh mục, bảo toàn lượng đang được mượn khi sửa tổng số lượng.
- **Đã xử lý trong source:** kiểm tra MIME/đuôi/kích thước upload, dùng object key ngẫu nhiên, chỉ đọc object đã gắn với thiết bị/phiếu hợp lệ, cache riêng tư cho file đăng nhập, trung hòa CSV formula, cài PHP cURL extension cho MinIO.
- **Migration an toàn đã thêm vào setup:** đổi FK category/device từ cascade sang restrict; thêm seed student idempotent; hash các mật khẩu đang lưu mà không đổi nội dung password hiện tại. Setup giờ cần admin, POST và CSRF.
- **Chưa khắc phục/giới hạn:** credentials demo vẫn nằm trong compose và SQL seed; `compose.yaml` chưa đọc `.env.example`; không có rate limiting đăng nhập hay quét malware; các object cũ sau thay ảnh có thể còn mồ côi; TLS/HTTPS do môi trường triển khai quyết định.
- **Chưa xác minh:** PHP lint, migrate/rollback, truy vấn runtime, giao diện browser, luồng MinIO và test nghiệp vụ đều bị chặn bởi môi trường.
- **Phát hiện bổ sung khi rà soát sau sửa:** luồng ghi nhận thiết bị mất trước đó cộng nhầm số lượng vào kho. Source hiện dùng helper khóa dòng thiết bị, giảm tổng tồn và không tăng khả dụng; nếu hết tổng tồn thì chuyển thiết bị sang trạng thái ngừng sử dụng. Thay đổi này mới được kiểm tra tĩnh, chưa chạy MySQL.
- **Phát hiện bổ sung khi chạy stack QA:** `init.sql` chưa khai báo các cột thiết bị/phiếu và bảng `borrow_logs` mà giao diện cần. Đã bổ sung vào schema mới; database QA đang chạy được nâng cấp bằng `setup.php`. Runtime của database trước khi migration có thể phát sinh PHP warnings/lỗi thao tác đến khi admin chạy setup.

## Cập nhật kiểm thử runtime — 2026-11-14

Các giới hạn môi trường trong snapshot ban đầu phía trên đã được vượt qua trong phiên sau bằng một stack Docker QA riêng. Các volume chính `thietbi_db_data` và `thietbi_minio_data` không được gắn vào hoặc thay đổi.

- Stack app/MySQL/MinIO/phpMyAdmin QA dùng project `altf4codexqa20261114`, volume riêng và cổng bind loopback; schema mới được khởi tạo ở project `altf4schemaqa20261114`.
- Docker build thành công; 30/30 tệp PHP qua `php -l`; 35 static assertions và 8 HTTP smoke checks đều PASS.
- Runtime đã xác minh đăng nhập/phân quyền, CSRF denial, student bị chặn khỏi setup/reset/debug, nâng cấp schema setup lặp lại, seed/hash tài khoản, mượn pending, bàn giao, đủ bốn tình trạng trả, upload PNG và từ chối file sai/quá cỡ, quyền proxy file, cùng picker sinh viên ở dark mode.
- Bộ test có 40 ca: 27 PASS, 0 FAIL, 13 BLOCKED. Các ca chưa chạy gồm concurrency, nhiều nhánh CRUD/validation, CSV thực tế, lỗi phụ thuộc và ma trận responsive/keyboard; xem [TEST_CASES.md](TEST_CASES.md).
- QA services vẫn chạy để có thể kiểm tra lại: web `127.0.0.1:18080`, phpMyAdmin `:18081`, MinIO console `:19001`, MySQL `:13307`. Không xóa volume QA khi chưa cần dọn fixture.
- Các rủi ro secrets demo, rate limiting, malware scanning và object cũ sau thay file vẫn còn mở; xem [BUG_REPORT.md](BUG_REPORT.md).
