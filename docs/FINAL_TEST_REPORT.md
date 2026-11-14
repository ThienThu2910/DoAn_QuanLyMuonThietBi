# Báo cáo kiểm thử cuối — Alt F4 Hub

**Ngày chạy:** 2026-11-14  
**Phạm vi:** source audit, kiểm tra tĩnh, Docker build/Compose, PHP syntax, web smoke test và một số luồng nghiệp vụ trên QA.

## Kết quả tổng hợp

Danh sách chi tiết có **40 test case** tại [TEST_CASES.md](TEST_CASES.md).

| Trạng thái | Số lượng | Ghi chú |
|---|---:|---|
| PASS | 27 | Có kết quả lệnh/runtime cụ thể trong test case |
| FAIL | 0 | Không có ca đã chạy bị xác nhận lỗi |
| BLOCKED | 13 | Chưa chạy hoặc chưa đủ phạm vi để kết luận |
| Tổng | 40 | Bao gồm ca riêng cho `damaged`/`incomplete` và các luồng chưa kiểm thử |

## Đã xác minh

- Ba cấu hình Compose (gốc, QA, schema QA) parse thành công; `git diff --check` sạch.
- `tests/run-static-checks.ps1`: **35/35 assertion** đạt.
- `node --check`: `src/assets/theme.js`, `tests/run-qa-smoke.mjs` và `tests/run-qa-return-conditions.mjs` không lỗi.
- PHP lint trong app container: **30/30 tệp** không lỗi cú pháp.
- Docker build hoàn tất; app, MySQL, MinIO và phpMyAdmin chạy trên stack QA. Các cổng QA chỉ bind ở `127.0.0.1`.
- HTTP smoke test: **11/11** đạt — trang login, đăng nhập admin/sinh viên, phân quyền, CSRF, sai mật khẩu, setup/reset/debug và MinIO readiness.
- Database QA cũ được setup nâng cấp và chạy setup lần hai thành công. 13 tài khoản QA có password bcrypt `$2y$`; tài khoản mới đăng nhập bằng mật khẩu mới.
- Database schema mới khởi tạo được với 12 tài khoản seed có hash, trường trạng thái/tài liệu thiết bị và `borrow_logs`.
- Đã chạy luồng mượn pending, hủy phiếu của chủ, bàn giao trực tiếp và đủ bốn nhánh trả tốt/hỏng/thiếu phụ kiện/mất. Thiết bị hỏng hoặc thiếu phụ kiện được chuyển bảo trì; luồng mất giảm tổng tồn nhưng không cộng số khả dụng.
- Đã xác minh upload PNG tới MinIO, thay ảnh thiết bị và metadata, từ chối `.txt`/ảnh quá 5 MB, proxy ảnh trả HTTP 200 `image/png`, và chặn object key không liên quan.
- Browser xác nhận picker sinh viên lọc đúng, ẩn trạng thái/kết quả không phù hợp và hiển thị tên/email tương phản ở dark mode. Sửa thiết bị xong trở lại anchor dòng vừa thao tác.

## Chưa thể kết luận

- Các ca còn `BLOCKED` trong bảng chưa được chạy: kiểm thử concurrency, đầy đủ ma trận CRUD/xóa, validation đăng ký, các kích thước responsive/bàn phím, lỗi MySQL/MinIO và CSV formula injection ở file thực tế.
- Không có kết luận rằng mọi chức năng của ứng dụng đã được E2E kiểm thử; các luồng trên chỉ xác nhận đúng phạm vi ghi ở bảng.
- Một lần gọi smoke từ sandbox thường không truy cập được loopback (`fetch failed`); chạy lại cùng QA services trong phiên được cấp quyền đã đạt 8/8.

## An toàn dữ liệu QA

Stack QA là môi trường riêng, dùng project name `altf4codexqa20261114` và volume riêng; schema QA dùng project `altf4schemaqa20261114`. Không gắn volume DB hoặc MinIO của stack gốc, không reset hay xóa volume dữ liệu gốc. Các service QA vẫn đang chạy để có thể xem lại tại `http://127.0.0.1:18080` (web), `18081` (phpMyAdmin), `19001` (MinIO Console); MySQL QA ở cổng `13307`.
