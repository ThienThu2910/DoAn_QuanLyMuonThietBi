# Alt F4 Hub — Quản lý mượn thiết bị phòng Lab

Đồ án môn **Điện toán đám mây** — ứng dụng web PHP chạy bằng **Docker Compose** (PHP/Apache + MySQL + MinIO + phpMyAdmin).

## 👥 Thành viên nhóm Alt F4

| STT | Họ và tên | MSSV | Vai trò |
|:---:|---|:---:|---|
| 1 | Trương Thiên Thư | 24210501050 | Trưởng nhóm |
| 2 | Lê Nhật Duy | 24210501005 | Thành viên |
| 3 | Lê Chí Toàn | 24210501038 | Thành viên |
| 4 | Dương Hoàng Anh Chương | 24210501001 | Thành viên |

## Công nghệ

| Thành phần | Công nghệ |
|---|---|
| Backend + giao diện | PHP 8.2 (Apache), PDO |
| Cơ sở dữ liệu | MySQL 8.0 (volume `thietbi_db_data`) |
| Lưu trữ tệp | MinIO, bucket `project-files` (volume `thietbi_minio_data`) |
| Quản trị CSDL | phpMyAdmin |
| Hạ tầng | Docker Compose, mạng `thietbi_cloud_net` |

## Yêu cầu

Docker Desktop (Windows/macOS, Windows cần bật WSL 2) hoặc Docker Engine + Docker Compose v2. Máy cần trống các cổng `8080`, `8081`, `3307`, `9000`, `9001`.

## Cài đặt và chạy

**Bước 1 — Khởi động** (tại thư mục chứa `compose.yaml`):

```bash
docker compose up -d --build
```

Lần đầu MySQL cần khoảng 30–60 giây để khởi tạo. Nếu web báo lỗi kết nối CSDL, hãy đợi rồi tải lại trang.

**Bước 2 — Tạo bucket MinIO.** Mở http://localhost:9001, đăng nhập `minioadmin` / `minioadmin123`, vào **Buckets → Create Bucket** và tạo bucket tên đúng là `project-files`. Thiếu bước này thì không tải được tệp.

**Bước 3 — Đăng nhập quản trị rồi đồng bộ CSDL.** Dùng `admin@gmail.com` / `admin123` và mở http://localhost:8080/setup.php. Xác nhận biểu mẫu để cập nhật schema, tạo tài khoản thử nghiệm còn thiếu và hash các mật khẩu cũ mà không đổi giá trị mật khẩu người dùng.

**Bước 4 — Đăng nhập** tại http://localhost:8080.

Trang quản lý tài khoản có thêm 10 tài khoản sinh viên thử nghiệm sau khi chạy `/setup.php`. Các tài khoản dùng mật khẩu `123456`, mã từ `SVTEST001` đến `SVTEST010`, email từ `student.test01@example.com` đến `student.test10@example.com`.

## Kiểm tra tĩnh

Trên Windows có Docker CLI và Node.js, chạy:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/run-static-checks.ps1
```

Bộ kiểm tra này xác nhận cấu hình Compose, cú pháp JavaScript và một số invariant bảo mật trong source. Nó không thay thế PHP lint hoặc test nghiệp vụ với MySQL/MinIO.

## Chạy smoke test trên stack QA riêng

Stack QA trong `tests/compose.qa.yaml` dùng database/MinIO volume riêng và bind cổng vào `127.0.0.1` (`18080`, `18081`, `13307`, `19000`, `19001`). Khởi động bằng project name riêng để không trộn với stack ứng dụng:

```powershell
docker compose -p altf4codexqa20261114 -f tests/compose.qa.yaml up -d --build
$env:ALT_F4_ADMIN_EMAIL = 'admin@gmail.com'
$env:ALT_F4_ADMIN_PASSWORD = 'admin123'
$env:ALT_F4_STUDENT_EMAIL = 'student@gmail.com'
$env:ALT_F4_STUDENT_PASSWORD = '123456'
node tests/run-qa-smoke.mjs
```

Runner kiểm tra login/role, CSRF và MinIO readiness; nó không tạo phiếu hay thay dữ liệu nghiệp vụ. Cấu hình `tests/compose.schema.qa.yaml` dành cho database schema mới trên cổng `13308`. Hai stack QA có volume riêng; không dùng volume của ứng dụng chính và không chạy `down -v` nếu muốn giữ dữ liệu test.

`tests/run-qa-return-conditions.mjs` kiểm tra nhận lại thiết bị hỏng/thiếu phụ kiện qua HTTP. Chỉ chạy trên QA với hai phiếu test đang ở trạng thái `approved`, rồi truyền ID của chúng qua `ALT_F4_DAMAGED_REQUEST_ID` và `ALT_F4_INCOMPLETE_REQUEST_ID`; runner sẽ thay đổi phiếu và tồn kho QA tương ứng.

## Địa chỉ và tài khoản

| Dịch vụ | Địa chỉ | Đăng nhập |
|---|---|---|
| Web | http://localhost:8080 | `admin@gmail.com` / `admin123` (quản trị); `student@gmail.com` / `123456` (sinh viên) |
| MinIO Console | http://localhost:9001 | `minioadmin` / `minioadmin123` |
| phpMyAdmin | http://localhost:8081 | `root` / `rootpassword` |
| MySQL (từ host) | `localhost:3307` | `root` / `rootpassword`, CSDL `ql_thietbi` |

Tài khoản demo chỉ dùng cho môi trường cục bộ.

## Dừng và xóa dữ liệu

```bash
docker compose down        # dừng, GIỮ dữ liệu
docker compose up -d       # chạy lại, dữ liệu vẫn còn
docker compose down -v     # dừng và XÓA toàn bộ dữ liệu (CSDL + tệp)
```

Kiểm chứng dữ liệu bền vững: tạo phiếu mượn có đính kèm tệp → `docker compose down` → `docker compose up -d` → dữ liệu và tệp vẫn còn. Không dùng `down -v` ở bước này.

## Xử lý sự cố

| Hiện tượng | Cách khắc phục |
|---|---|
| "Lỗi kết nối cơ sở dữ liệu" | MySQL chưa khởi tạo xong, đợi rồi tải lại; xem `docker compose logs database` |
| Tải tệp báo lỗi MinIO | Tạo bucket `project-files` (Bước 2) |
| Lỗi cột/bảng không tồn tại khi duyệt phiếu, sửa thiết bị, báo cáo | Đăng nhập quản trị rồi chạy `/setup.php` (Bước 3) |
| Đăng nhập demo báo sai mật khẩu | Đăng nhập quản trị rồi mở `/reset_pw.php` và xác nhận biểu mẫu |
| Chữ lỗi kiểu `Quáº£n trá»‹ viÃªn` | Dữ liệu cũ được tự sửa lần truy cập đầu; muốn sạch hẳn: `docker compose down -v` rồi `up -d --build` |
| Cổng bị chiếm | Đổi cổng bên trái trong mục `ports` của `compose.yaml` |
| Tải tệp lớn bị từ chối | Dùng tệp nhỏ hơn 2 MB |

## Cấu trúc thư mục

```
├── compose.yaml      # 4 dịch vụ, volume, network
├── Dockerfile        # PHP 8.2 + Apache + pdo_mysql
├── init.sql          # Bảng cơ bản + dữ liệu mẫu
└── src/              # Mã nguồn PHP (bind mount vào container)
    ├── admin/        # Dashboard, thiết bị, danh mục, người dùng, duyệt phiếu, báo cáo, xuất CSV
    ├── assets/       # theme.css, theme.js, loader.css
    └── setup.php, minio_helper.php, config.php, ...
```

## Lưu ý

Ứng dụng hash mật khẩu mới bằng `password_hash` và tự nâng cấp mật khẩu cũ khi đăng nhập hoặc chạy setup. Tài khoản demo, mật khẩu mặc định, MySQL root và MinIO credentials vẫn dành riêng cho môi trường cục bộ; hãy thay secrets trước khi triển khai ra mạng công khai.
