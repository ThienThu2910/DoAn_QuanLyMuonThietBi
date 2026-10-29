# Alt F4 Hub — Quản lý mượn thiết bị phòng Lab

Đồ án cuối kỳ môn **Điện toán đám mây** — ứng dụng web PHP đóng gói và triển khai bằng **Docker Compose**.

- **Nhóm / thành viên:**
- Trương Thiện Thư (leader)
- Lê Nhật Duy
- Lê Chí Toàn
- Dương Hoàng Anh Chương

## Công nghệ

| Thành phần | Công nghệ |
|---|---|
| Backend + giao diện | PHP 8.2 (Apache), PDO |
| Cơ sở dữ liệu | MySQL 8.0 (volume `thietbi_db_data`) |
| Lưu trữ tệp | MinIO Object Storage (volume `thietbi_minio_data`, bucket `project-files`) |
| Giao diện | HTML/CSS/JS thuần, Bootstrap Icons, font Be Vietnam Pro |
| Hạ tầng | Docker Compose, network ảo `thietbi_cloud_net` |

## Yêu cầu môi trường

Docker Desktop (Windows/macOS) hoặc Docker Engine + Docker Compose. Trên Windows cần bật WSL 2.

## Chạy hệ thống

```bash
docker compose up -d --build
```

| Dịch vụ | Địa chỉ |
|---|---|
| Giao diện web | http://localhost:8080 |
| MinIO Console | http://localhost:9001 (`minioadmin` / `minioadmin123`) |
| MySQL (từ máy host) | `localhost:3307` |

> Lần đầu chạy: vào MinIO Console, tạo bucket tên **`project-files`** trước khi thử upload.

**Tài khoản demo:** `admin@gmail.com` / `admin123` (quản trị) · `student@gmail.com` / `123456` (sinh viên).
Trang đăng nhập có nút điền nhanh hai tài khoản này.

```bash
docker compose down        # dừng hệ thống, GIỮ dữ liệu
docker compose down -v     # dừng và XÓA toàn bộ dữ liệu demo
```

Kiểm chứng dữ liệu bền vững: tạo bản ghi + upload tệp → `docker compose down` → `docker compose up -d` → dữ liệu và tệp vẫn còn.
**Không** dùng `down -v` trong bước kiểm chứng.

## Giao diện Liquid Glass

- Nền cực quang + các tấm kính mờ (mục 15 của `theme.css`); chỉnh độ trong/mờ qua biến `--lg-*`.
- `login.php` hiện màn hình loading hex ~3 giây (`loader.php`, `assets/loader.css`).
- Thanh bên có tay nắm thu gọn/mở; khi thu gọn, rê chuột để bung tạm. Trạng thái được nhớ.
- Trang Kho có phần giới thiệu + lối tắt tới các khu vực (`hero_art.php`).
- Lỗi chữ kiểu `Quáº£n trá»‹ viÃªn`: `init.sql` nay có `SET NAMES utf8mb4`; dữ liệu cũ trong volume được `encoding_fix.php` tự sửa lần đầu truy cập. Nếu muốn sạch hẳn: `docker compose down -v` rồi `up -d --build`.

## Cấu trúc giao diện

Toàn bộ giao diện dùng chung một lớp thiết kế, các trang PHP chỉ chứa nội dung:

```
src/
├── head.php            # <head> dùng chung: font, icon, theme.css, theme.js
├── navbar.php          # khung điều hướng: thanh bên, thanh trên, thanh dưới mobile
├── ui.php              # hàm hỗ trợ: icon SVG, ngày tiếng Việt, escape
├── auth_aside.php      # cột thương hiệu của trang đăng nhập / đăng ký
└── assets/
    ├── theme.css       # bảng màu (token), sáng/tối, responsive, thành phần
    └── theme.js        # đổi giao diện, menu mobile, hộp thoại, kiểm tra tệp upload...
```

- **Sáng / tối:** nút mặt trời/mặt trăng trên thanh trên; lựa chọn được lưu, mặc định theo hệ điều hành.
- **Đổi bảng màu:** sửa các biến `--t-*` ở đầu `theme.css`.
- **Phím tắt:** `/` hoặc `Ctrl/⌘ + K` để tìm thiết bị.
- Thiết kế phản hồi: máy tính (thanh bên cố định), máy tính bảng (menu trượt), điện thoại (menu trượt + thanh điều hướng dưới, bảng dữ liệu dạng thẻ).
