# Driver mobile

Ứng dụng tài xế điều phối đăng ký đối tác, KYC, GPS heartbeat, offer, chuyến đang chạy, ví và hỗ trợ.

## Bản đồ Goong

Trang chủ hiển thị vị trí GPS hiện tại. Khi có chuyến được nhận, màn hình điều hướng hiển thị:

- Vị trí hiện tại của tài xế.
- Điểm đón và điểm đến.
- Tuyến Goong Directions từ tài xế đến điểm cần tới tiếp theo.
- Khoảng cách và thời gian dự kiến.

Đích điều hướng thay đổi theo trạng thái:

```text
DRIVER_ARRIVING / DRIVER_ARRIVING_PICKUP / AT_PICKUP -> điểm đón
PICKED_UP / IN_DELIVERY / IN_TRIP                  -> điểm đến
```

Map dùng `maplibre_gl` với style Goong. Directions dùng `bike` cho giao hàng và `car` cho đặt xe.

## Cấu hình

Tạo `mobile/driver/.env` từ `.env.example`:

```env
API_BASE_URL=http://10.0.2.2:8000/api/v1
REALTIME_URL=http://10.0.2.2:3000
GOONG_API_KEY=your_goong_rest_api_key
GOONG_MAP_KEY=your_goong_map_tile_key
```

Chạy trên đúng emulator để tránh phiên khác cài đè package:

```powershell
flutter run -d emulator-5556 --dart-define-from-file=.env
```

Không commit `.env`. `GOONG_API_KEY` dùng cho Directions; `GOONG_MAP_KEY` dùng tải style và tile.

## Cấu trúc liên quan

```text
lib/
|-- api/
|   |-- device_location.dart       # Permission và GPS
|   `-- goong_navigation_api.dart  # Directions + polyline decoder
`-- presentation/
    |-- driver_app_controller.dart # Location heartbeat và state
    |-- pages/
    |   |-- home/driver_home_page.dart
    |   `-- active_job/job_navigation_page.dart
    `-- widgets/driver_goong_map.dart
```

Offer mới vẫn tự mở dialog đếm ngược. Map chỉ hỗ trợ quan sát tuyến trong app; nó không cung cấp turn-by-turn voice navigation. Nút làm mới trên màn hình chuyến sẽ lấy lại GPS và gọi Directions mới.
