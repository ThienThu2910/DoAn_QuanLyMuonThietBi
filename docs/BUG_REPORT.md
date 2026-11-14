# Bug report — Alt F4 Hub

Các lỗi ban đầu được phát hiện qua đọc tĩnh mã nguồn. Trạng thái dưới đây ghi kết quả đúng theo phần đã kiểm tra trên stack QA cô lập; `một phần` không có nghĩa mọi nhánh đã được kiểm thử.

| ID | Mức độ | Vấn đề | Khắc phục | Trạng thái xác minh |
|---|---|---|---|---|
| BUG-001 | Critical | Setup/reset/debug thiếu kiểm soát truy cập | Yêu cầu admin; setup/reset dùng POST + CSRF; debug giới hạn admin | Runtime một phần — student nhận 403 ở cả ba endpoint; setup admin+CSRF chạy được |
| BUG-002 | High | Thiếu CSRF và một số thay đổi trạng thái dùng GET | Thêm token; chuyển xóa/hủy/role/reset/duyệt/trả sang POST | Một phần runtime — POST phiếu thiếu token trả 403; static checks rà các form |
| BUG-003 | Critical | Password plaintext và session có thể giữ password | Hash password mới; nâng cấp legacy; loại password khỏi session | Một phần runtime — login hoạt động; 13 tài khoản QA có bcrypt `$2y$` sau setup |
| BUG-004 | High | Xóa category/device có thể cascade và làm mất dữ liệu | RESTRICT trong schema mới; kiểm tra dữ liệu phụ thuộc trước khi xóa | Static/DDL đạt; migration FK cũ chưa được kiểm tra đủ |
| BUG-005 | High | Approve/return nhiều luồng có thể lệch kho hoặc ghi lặp | POST + CSRF, khóa hàng phiếu/kho, kiểm tra trạng thái, ghi log | Một phần runtime — bàn giao, trả tốt/mất đạt; concurrency chưa chạy |
| BUG-006 | High | Upload chỉ dựa extension, không xác minh MIME/size đầy đủ | MIME `finfo`, allowlist, giới hạn dung lượng và xử lý lỗi | Runtime — PNG upload được; `.txt` và ảnh hơn 5 MB bị từ chối |
| BUG-007 | High | Người đăng nhập có thể thử đọc object tùy ý | Proxy chỉ phục vụ object liên kết entity; phiếu giới hạn theo chủ/admin | Một phần runtime — object key không liên quan trả 404; ma trận quyền chưa đủ |
| BUG-008 | High | CSV có nguy cơ formula injection | Trung hòa cell bắt đầu bằng `=`, `+`, `-`, `@` | Static check đạt; chưa kiểm tra file CSV thực tế |
| BUG-009 | Medium | Giảm quantity có thể thấp hơn số đang mượn | Khóa device, đối chiếu số đang mượn và từ chối tổng thấp hơn | Static check đạt; nhánh runtime chưa chạy |
| BUG-010 | Medium | Session cũ giữ quyền admin sau khi role đổi; rủi ro fixation | Xoay session ID; tải lại user/role; cookie HttpOnly/SameSite | Runtime — session bị hạ role nhận 403; login smoke đạt |
| BUG-011 | Medium | Thiết bị bảo trì có thể vẫn nhận yêu cầu mượn | Kiểm tra status trước và trong transaction | Static check đạt; runtime chưa thử trạng thái maintenance |
| BUG-012 | Medium | Ngày POST giả mạo có thể qua kiểm tra chuỗi | Xác thực định dạng và ngày lịch ở server | Runtime — `2026-02-31` bị từ chối |
| BUG-013 | Medium | Image PHP thiếu cURL/fileinfo dùng bởi ứng dụng | Cài extension trong Dockerfile | Docker build và PHP lint đạt; extension có trong image |
| BUG-014 | Low | Lỗi DB có thể lộ exception cho người dùng | Log nội bộ, trả HTTP 500 với thông báo tổng quát | Static check đạt; chưa mô phỏng DB lỗi |
| BUG-015 | Môi trường | Ban đầu không chạy được runtime test | Dùng stack QA riêng, volume riêng, cổng loopback | Đã khắc phục trong phiên — app/runtime QA chạy được; không đụng volume gốc |
| BUG-016 | High | Luồng trả cần bảo toàn kho theo đúng tình trạng thiết bị | Mất giảm total/không cộng available; damaged và incomplete chuyển trạng thái bảo trì | Runtime — đã xác minh đủ good/lost/damaged/incomplete cùng log và tồn kho |
| BUG-017 | High | `init.sql` thiếu cột/bảng mà các trang thiết bị/phiếu dùng | Thêm status/location/manual document, audit fields và `borrow_logs` | Runtime — import schema mới và setup nâng cấp schema QA cũ chạy được |
| BUG-018 | Medium | CSS làm hiện phần tử có thuộc tính `hidden`, tên sinh viên khó đọc ở dark mode | CSS tôn trọng `[hidden]`; chỉnh tương phản text ở dark mode | Browser runtime — lọc và ẩn đúng; tên/email đọc rõ |

## Rủi ro còn mở

- Demo credentials và MySQL/MinIO secrets mặc định vẫn có trong repository/compose; chỉ dùng local demo.
- `.env.example` chưa được wire vào `compose.yaml`; cần thay secrets trước khi public.
- Chưa có rate limiting/brute-force control hoặc quét malware upload.
- Thay ảnh/tài liệu có thể để lại object cũ; cần chính sách retention/cleanup.
- 13 ca kiểm thử vẫn `BLOCKED`; xem phạm vi cụ thể trong [TEST_CASES.md](TEST_CASES.md).
