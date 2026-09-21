# Flow 12 - Realtime, vị trí và thông báo

## Mục tiêu

Đồng bộ trạng thái/ETA/vị trí giữa khách và tài xế với độ trễ thấp, nhưng vẫn giữ API và database là nguồn dữ liệu chuẩn.

## Phân vai kênh

| Kênh | Dùng cho | Không dùng làm |
|---|---|---|
| HTTPS API | Command nghiệp vụ, query trạng thái chuẩn, upload evidence | Luồng vị trí tần suất cao |
| Socket.IO | Trạng thái tức thời, vị trí, ETA, presence | Nguồn quyết định transition cuối cùng |
| In-app chat | Tin nhắn giữa khách và tài xế trong đơn/chuyến active | Kênh quyết định trạng thái hoặc lưu thông tin thanh toán nhạy cảm |
| Push notification | Đánh thức app/thông báo khi background hoặc mất socket | Bảo đảm thứ tự event |
| RabbitMQ | Chuyển event giữa backend và realtime worker | Database nghiệp vụ |
| Redis | Presence, room metadata, vị trí gần nhất, geo search, lock/cache ngắn | Lịch sử chuẩn dài hạn |

## Xác thực và room

1. Client mở socket bằng access token ngắn hạn/đang hiệu lực.
2. Realtime service xác minh token và trạng thái user.
3. Client tự động vào room cá nhân `user:{user_id}`.
4. Room `booking:{booking_id}` chỉ được join khi user là customer, assigned driver hoặc admin có quyền.
5. Khi assignment đóng, token bị thu hồi hoặc quyền thay đổi, server phải remove socket khỏi room.
6. Không nhận `user_id`, `driver_id` hoặc room id do client tự khai mà chưa kiểm tra quyền.

Chat chỉ mở cho hai bên của assignment liên quan. Gọi điện dùng số điện thoại trực tiếp trong giai đoạn hiện tại; hệ thống chưa tích hợp nhà cung cấp gọi hoặc số ảo.

## Luồng A - Phát trạng thái

1. Laravel commit transition và outbox record.
2. Publisher gửi domain event vào RabbitMQ với `event_id`, `aggregate_id`, `aggregate_version`, `occurred_at`.
3. Realtime service consume và deduplicate event.
4. Service phát event vào room liên quan.
5. Client chỉ áp dụng event có version mới hơn state đang giữ.
6. Nếu thấy thiếu version hoặc reconnect, client gọi API lấy snapshot mới nhất.

## Luồng B - Vị trí tài xế

1. Khi online hoặc đang thực hiện dịch vụ, tài xế gửi `lat`, `lng`, `accuracy`, `heading`, `speed`, `captured_at` mỗi 1,5 giây.
2. Service validate quyền, range, timestamp, accuracy và rate limit cho chu kỳ 1,5 giây.
3. Vị trí gần nhất/presence được cập nhật trong Redis với TTL; database cập nhật một snapshot `last_location` và `last_location_at` cho tài xế.
4. Khi có assignment, vị trí đã làm mờ/đủ dùng được phát vào đúng booking room.
5. Sample mới ghi đè vị trí cũ; không persist timeline hoặc lịch sử hành trình.
6. Khi offline hoặc TTL hết hạn, tài xế bị loại khỏi geo search; snapshot cuối vẫn dùng để hiển thị lần định vị cuối cùng cùng timestamp.

## Event envelope đề xuất

```json
{
  "event_id": "uuid",
  "event_type": "RIDE_DRIVER_ARRIVED",
  "aggregate_type": "ride_booking",
  "aggregate_id": "uuid",
  "aggregate_version": 7,
  "occurred_at": "2026-09-21T10:00:00Z",
  "data": {}
}
```

Payload gửi client chỉ chứa dữ liệu cần hiển thị. Event nội bộ có dữ liệu nhạy cảm phải được chuyển thành public event đã lọc trước khi broadcast.

## Reconnect và fallback

1. Client dùng exponential backoff có jitter khi reconnect.
2. Sau reconnect, client authenticate lại và gọi API sync snapshot.
3. Event quan trọng như offer, assigned, arrived, cancelled, completed có push fallback.
4. Push chỉ báo có thay đổi; khi mở app client lấy trạng thái chuẩn qua API.
5. Command nghiệp vụ khi socket lỗi luôn đi qua HTTPS, không phụ thuộc socket.

## Tính tin cậy

- Consumer xử lý at-least-once, do đó mọi handler phải idempotent.
- `event_id` có kho dedup/unique phù hợp.
- `aggregate_version` xử lý event đến sai thứ tự.
- Dead-letter queue cho event lỗi quá số lần retry; có cảnh báo và replay có kiểm soát.
- Outbox bảo đảm không mất event giữa database commit và publish.
- Correlation id đi xuyên API, outbox, queue và log.

## Sự kiện public tối thiểu

- `OFFER_CREATED`, `OFFER_EXPIRED`
- `DRIVER_ASSIGNED`, `DRIVER_LOCATION_UPDATED`
- `DRIVER_ARRIVED`
- `DELIVERY_PICKED_UP`, `DELIVERY_DELIVERED`, `DELIVERY_COMPLETED`
- `RIDE_STARTED`, `RIDE_COMPLETED`
- `BOOKING_CANCELLED`, `PAYMENT_STATUS_CHANGED`

Tên public event có thể thêm namespace/version (`delivery.picked_up.v1`) khi chốt contract.

## Tiêu chí nghiệm thu

- User không liên quan không thể join room hoặc nhận vị trí.
- Event lặp/sai thứ tự không làm UI lùi trạng thái.
- Reconnect luôn đồng bộ lại snapshot chuẩn.
- Vị trí hết TTL loại tài xế khỏi matching.
- Server rate limit và Redis TTL phải tương thích với chu kỳ vị trí 1,5 giây.
- Database chỉ có một snapshot vị trí cuối trên mỗi tài xế; không phát sinh bản ghi lịch sử theo từng sample.
- RabbitMQ/realtime tạm ngừng không làm mất transaction nghiệp vụ; event được phát lại từ outbox.
