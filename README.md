# Alt F4 Hub — Quản lý mượn thiết bị phòng Lab

Đồ án môn **Điện toán đám mây** — ứng dụng web PHP chạy bằng **Docker Compose** (PHP/Apache + MySQL + MinIO + phpMyAdmin).

**Nhóm:**Alt F4
· Trương Thiện Thư (nhóm trưởng) - 24210501050
· Lê Nhật Duy - 24210501005
· Lê Chí Toàn - 24210501038
· Dương Hoàng Anh Chương - 24210501001


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

**Bước 3 — Đồng bộ CSDL.** Mở http://localhost:8080/setup.php **một lần** để thêm các cột và bảng `borrow_logs` mà `init.sql` chưa có. Thiếu bước này thì duyệt phiếu, nhận trả, sửa thiết bị và báo cáo sẽ lỗi.

**Bước 4 — Đăng nhập** tại http://localhost:8080.

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
| Lỗi cột/bảng không tồn tại khi duyệt phiếu, sửa thiết bị, báo cáo | Chạy `/setup.php` (Bước 3) |
| Đăng nhập demo báo sai mật khẩu | Mở http://localhost:8080/reset_pw.php một lần để đặt lại `admin123` và `123456` |
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

Các tệp `setup.php`, `reset_pw.php`, `check_password.php` không yêu cầu đăng nhập và mật khẩu mặc định được ghi sẵn trong `compose.yaml`, nên chỉ phù hợp môi trường demo cục bộ.
