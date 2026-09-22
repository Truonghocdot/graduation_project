# Customer mobile

Ứng dụng khách hàng được tổ chức theo page, còn state và điều phối API tập trung tại `ClientAppController`.

## Chức năng

- Đăng ký, xác minh OTP, đăng nhập và đặt lại mật khẩu.
- Xin quyền vị trí khi mở app để gợi ý điểm đón và hỗ trợ theo dõi chuyến.
- Chọn giao hàng hoặc đặt xe, loại phương tiện và lịch thực hiện.
- Tìm địa chỉ bằng Goong Autocomplete; lấy tọa độ bằng Place Detail hoặc Geocode.
- Lấy Directions và hiển thị tuyến đường bằng MapLibre với style Goong.
- Nhận báo giá từ backend, chọn thanh toán, tạo yêu cầu và theo dõi realtime.
- Xem lịch sử, ví, thông báo, hỗ trợ và đánh giá tài xế.

## Cấu hình Goong

Không ghi API key trực tiếp vào source code. Chạy app bằng hai biến build-time:

```powershell
flutter run -d emulator-5554 `
  --dart-define=GOONG_API_KEY=your_rest_api_key `
  --dart-define=GOONG_MAP_KEY=your_map_tile_key
```

`GOONG_API_KEY` dùng cho Autocomplete, Place Detail, Geocode và Directions. `GOONG_MAP_KEY` dùng để tải style/tile bản đồ. Khi thiếu map key, form vẫn hoạt động và hiển thị trạng thái chưa cấu hình thay vì khởi tạo native map.

## Tổ chức mã nguồn

```text
lib/
|-- api/
|   |-- client_location.dart       # Permission và vị trí thiết bị
|   `-- goong_location_api.dart    # Goong REST API + polyline decoder
`-- presentation/
    |-- client_app_controller.dart # Session và nghiệp vụ dùng chung
    |-- pages/order/
    |   `-- create_order_page.dart # Tìm địa chỉ, route, payload báo giá
    `-- widgets/
        `-- goong_map_preview.dart # MapLibre + style Goong
```

Xem [luồng nghiệp vụ đặt dịch vụ](customer-booking-flow.md) để biết state, API và các nhánh lỗi.
