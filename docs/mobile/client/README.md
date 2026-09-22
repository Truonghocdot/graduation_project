# Customer mobile

> Trạng thái: `IMPLEMENTED`

`main.dart` chỉ bootstrap API, secure session và realtime. State dùng chung nằm ở `lib/presentation/client_app_controller.dart`; mỗi workflow được tách thành page riêng:

```text
lib/presentation/pages/
├── auth/
│   ├── login_page.dart              # Đăng nhập SĐT/mật khẩu, reset mật khẩu
│   └── register_page.dart           # Đăng ký thông tin tài khoản
├── main_navigation_page.dart        # Bottom Navigation Bar chính
├── home/
│   ├── home_page.dart               # Trang chủ: Delivery, Drive và đặt lịch
│   └── service_detail_page.dart     # Chọn loại hình giao hàng/loại xe
├── order/
│   ├── create_order_page.dart       # Nhập điểm đi/đến, payload dịch vụ, voucher và lịch
│   ├── order_checkout_page.dart     # Xác nhận đơn, chọn Voucher, phương thức thanh toán
│   ├── active_order_tracking_page.dart # [REALTIME] Route snapshot + Socket status
│   ├── order_history_page.dart      # Danh sách lịch sử đơn hàng (Tab: Đang chạy / Đã hoàn thành)
│   └── order_detail_page.dart       # Chi tiết đơn hàng cũ, hóa đơn
├── chat/
│   └── chat_with_driver_page.dart   # [REALTIME] Chat với tài xế nhận đơn
└── profile/
    ├── profile_page.dart            # Thông tin cá nhân, cài đặt
    └── rating_review_page.dart      # Đánh giá tài xế (Sao + Comment) sau khi hoàn thành đơn
```

Các page gọi worker API qua `BookingGateway`; không chứa logic tính giá, matching hoặc settlement. `active_order_tracking_page.dart` hiện hiển thị route snapshot và trạng thái realtime. Bản đồ tile/SDK điều hướng thật chưa được thêm vì backend chưa cung cấp map token/style contract cho mobile.
