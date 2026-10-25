<?php /* Cột thương hiệu bên trái của trang đăng nhập / đăng ký (ẩn trên mobile) */ ?>
<aside class="auth-aside" aria-hidden="false">
    <a class="auth-brand" href="/login.php">
        <span class="t-brand-icon"><?= ic('box', 24) ?></span>
        <span>Alt F4<span class="auth-brand-hub"> Hub</span></span>
    </a>

    <div class="auth-hero">
        <p class="auth-kicker">Quản lý mượn thiết bị phòng Lab</p>
        <h2>Đúng thiết bị, đúng thời điểm.</h2>
        <p class="auth-lead">Đăng ký mượn trực tuyến, theo dõi hạn trả và lưu biên bản minh chứng an toàn trên MinIO S3.</p>

        <ul class="auth-points">
            <li><span><?= ic('clock', 20) ?></span><div><b>Mượn trong vài bước</b><small>Chọn thiết bị, chọn ngày, gửi phiếu chờ duyệt.</small></div></li>
            <li><span><?= ic('bell', 20) ?></span><div><b>Nhắc hạn trả</b><small>Cảnh báo thiết bị sắp hoặc đã quá hạn.</small></div></li>
            <li><span><?= ic('cloud', 20) ?></span><div><b>Lưu trữ minh chứng</b><small>Tệp được giữ bền vững qua Docker volume.</small></div></li>
        </ul>
    </div>

    <p class="auth-foot">Đại học Bình Dương · Phân hiệu Cà Mau<br>Đồ án Điện toán đám mây</p>
</aside>
