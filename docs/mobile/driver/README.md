# Driver mobile

> Trạng thái: `IMPLEMENTED`

`main.dart` chỉ bootstrap dependency. `lib/presentation/driver_app_controller.dart` điều phối session, KYC, GPS heartbeat, offer, active assignment và finance; UI được tách như sau:

```text
lib/presentation/pages/
├── auth/
│   ├── driver_login_page.dart       # Đăng nhập tài xế
│   └── driver_kyc_page.dart         # Upload Bằng lái, CCCD, Giấy tờ xe (Chờ Admin duyệt)
├── main_driver_navigation_page.dart # Bottom Navigation Bar tài xế
├── home/
│   ├── driver_home_page.dart        # [REALTIME] Bật/Tắt online, khu vực hoạt động, popup nhận đơn
│   └── incoming_order_dialog.dart   # Popup đếm ngược 15s chấp nhận/từ chối đơn hàng mới
├── active_job/
│   ├── job_navigation_page.dart     # [REALTIME] Tọa độ route và điều phối trạng thái
│   ├── update_status_page.dart      # Chụp ảnh xác nhận đã lấy hàng / đã giao hàng thành công
│   └── chat_with_customer_page.dart # Chat realtime với khách hàng
├── wallet/
│   ├── wallet_page.dart             # Số dư ví và tài khoản ngân hàng
│   └── withdraw_page.dart           # Rút tiền về ngân hàng
├── history/
│   └── driver_history_page.dart     # Lịch sử chuyến đã đóng và tổng thu nhập
└── profile/
    └── driver_profile_page.dart     # Hồ sơ, capability, notification và support
```

Offer mới tự mở dialog đếm ngược và vẫn xuất hiện trong danh sách fallback. `job_navigation_page.dart` dùng tọa độ pickup/dropoff và GPS thật để điều phối trạng thái; SDK bản đồ/chỉ đường trực quan chưa được tích hợp vì mobile chưa có map provider contract. Rating trung bình cũng chờ read-model tổng hợp từ worker, không tính cục bộ trong Dart.
