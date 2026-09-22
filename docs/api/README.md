# API Contract v1

> Base path: `/api/v1`
>
> Trạng thái: **Implemented cho Phase 1 đến Phase 8**

Tài liệu này mô tả các API đang có trong `worker/routes/api.php`, từ xác thực, booking, execution và finance đến support, chat, incident, rating và notification của Phase 8.

Nghiệp vụ quản trị không mở REST API. Admin đăng nhập và thao tác tại Filament `/admin`; Filament gọi trực tiếp domain service trong worker để duyệt/từ chối/khóa tài xế và ghi audit.

## Quy ước chung

### Request

- Gửi `Accept: application/json`.
- Endpoint JSON dùng `Content-Type: application/json`.
- Endpoint upload dùng `multipart/form-data`.
- API xác thực dùng Sanctum bearer token:

```http
Authorization: Bearer {token}
```

- Số điện thoại được normalize về dạng `+84xxxxxxxxx`. Có thể gửi dạng `09xxxxxxxxx` ở các endpoint có normalize phone.
- ID resource là `public_id` UUID, không dùng khóa số nội bộ.
- Thời gian dùng ISO-8601; server lưu UTC, client hiển thị `Asia/Ho_Chi_Minh`.

### Response

Resource thành công thường có dạng:

```json
{
  "data": {},
  "meta": {}
}
```

Các response đặc biệt:

- Đăng nhập/xác minh phone: `data`, `token`, `token_type`.
- Logout/reset password: `204 No Content`.
- Forgot/resend OTP: `202 Accepted` với message trung tính.
- Các danh sách resource dùng Laravel pagination: `data`, `links`, `meta`.

Validation lỗi dùng HTTP `422`:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "phone": ["The phone field is required."]
  }
}
```

Các status thường gặp:

| Status | Ý nghĩa |
|---:|---|
| `200` | Đọc/cập nhật thành công |
| `201` | Tạo resource thành công |
| `202` | Request được chấp nhận, xử lý xác minh bất đồng bộ/local sender |
| `204` | Thành công, không có body |
| `401` | Thiếu hoặc token không hợp lệ |
| `403` | Đã đăng nhập nhưng thiếu role/quyền |
| `404` | Resource không tồn tại hoặc không thuộc actor hiện tại |
| `409` | Idempotency key đã được dùng với payload khác hoặc request đang xử lý |
| `422` | Validation hoặc trạng thái nghiệp vụ không hợp lệ |
| `429` | Vượt rate limit |

## Endpoint index

| Method | Path | Auth | Phase |
|---|---|---|---:|
| `POST` | `/auth/register` | Public + `auth-otp` throttle | 1 |
| `POST` | `/auth/phone/verify` | Public + `auth-otp` throttle | 1 |
| `POST` | `/auth/phone/resend` | Public + `auth-otp` throttle | 1 |
| `POST` | `/auth/login` | Public + `auth-login` throttle | 1 |
| `POST` | `/auth/password/forgot` | Public + `auth-otp` throttle | 1 |
| `POST` | `/auth/password/verify` | Public + `auth-otp` throttle | 1 |
| `POST` | `/auth/password/reset` | Public + `auth-otp` throttle | 1 |
| `POST` | `/auth/logout` | `auth:sanctum` | 1 |
| `GET` | `/me` | `auth:sanctum` | 1 |
| `GET` | `/catalog/vehicle-types` | `auth:sanctum` | 2 |
| `GET` | `/driver/application` | `auth:sanctum` | 2 |
| `POST` | `/driver/application` | `auth:sanctum` | 2 |
| `POST` | `/driver/application/submit` | `auth:sanctum` | 2 |
| `POST` | `/driver/documents` | `auth:sanctum` | 2 |
| `DELETE` | `/driver/documents/{document}` | `auth:sanctum` + owner | 2 |
| `GET` | `/driver/documents/{document}/file` | `auth:sanctum` + owner/admin | 2 |
| `POST` | `/driver/vehicles` | `auth:sanctum` | 2 |
| `PATCH` | `/driver/vehicles/{vehicle}` | `auth:sanctum` + owner | 2 |
| `PUT` | `/driver/vehicles/{vehicle}/selected` | `auth:sanctum` + owner | 2 |
| `PUT` | `/driver/availability/online` | `auth:sanctum` + `role:DRIVER` | 2 |
| `PUT` | `/driver/availability/offline` | `auth:sanctum` + `role:DRIVER` | 2 |
| `POST` | `/quotes` | `auth:sanctum` + `quotes` throttle | 3 |
| `POST` | `/delivery/orders` | `auth:sanctum` + `Idempotency-Key` | 4 |
| `POST` | `/rides/bookings` | `auth:sanctum` + `Idempotency-Key` | 4 |
| `POST` | `/service-requests/{serviceRequest}/cancel` | `auth:sanctum` + owner + `Idempotency-Key` | 4 |
| `GET` | `/service-requests/{serviceRequest}` | `auth:sanctum` + owner | 5 |
| `GET` | `/driver/offers` | `auth:sanctum` + `role:DRIVER` | 5 |
| `POST` | `/driver/offers/{driverOffer}/respond` | `auth:sanctum` + owner + `Idempotency-Key` | 5 |
| `PUT` | `/driver/location` | `auth:sanctum` + `role:DRIVER` | 6 |
| `POST` | `/driver/service-requests/{serviceRequest}/evidence` | `auth:sanctum` + assigned driver | 6 |
| `POST` | `/driver/service-requests/{serviceRequest}/transition` | `auth:sanctum` + assigned driver + `Idempotency-Key` | 6 |
| `GET` | `/service-evidence/{evidence}/file` | owner/assigned driver/admin | 6 |
| `GET` | `/wallet` | `auth:sanctum` | 7 |
| `GET, POST` | `/wallet/topups` | `auth:sanctum` + idempotency khi POST | 7 |
| `POST` | `/webhooks/sepay` | `X-SePay-Secret` | 7 |
| `GET, POST` | `/driver/bank-accounts` | `auth:sanctum` + `role:DRIVER` | 7 |
| `GET, POST` | `/driver/withdrawals` | `auth:sanctum` + `role:DRIVER` | 7 |
| `GET` | `/service-requests/{serviceRequest}/realtime-access` | customer/assigned driver/admin | 8 |
| `GET, POST` | `/service-requests/{serviceRequest}/chat` | customer/assigned driver | 8 |
| `GET` | `/chat/unread` | `auth:sanctum` | 8 |
| `GET, POST` | `/support/tickets` | owner; `Idempotency-Key` khi POST | 8 |
| `GET` | `/support/tickets/{supportTicket}` | owner/assigned support/admin | 8 |
| `POST` | `/support/tickets/{supportTicket}/messages` | owner/assigned support/admin | 8 |
| `GET` | `/support/attachments/{attachment}/file` | ticket authorization | 8 |
| `GET, POST` | `/incidents`, `/service-requests/{serviceRequest}/incidents` | reporter/participant; `Idempotency-Key` khi POST | 8 |
| `POST` | `/service-requests/{serviceRequest}/ratings` | participant + completed service | 8 |
| `GET` | `/notifications`, `/notifications/unread` | owner | 8 |
| `PUT` | `/notifications/{notification}/read` | owner | 8 |

## Phase 1 - Authentication

### `POST /auth/register`

Tạo user ở `PENDING_VERIFICATION` và phát OTP xác minh phone.

Request:

```json
{
  "name": "Nguyen Van A",
  "phone": "0901234567",
  "email": "a@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Rules chính: `name` tối đa 120 ký tự, phone Việt Nam, email optional, password theo `Password::defaults()`.

Response `201`:

```json
{
  "data": {
    "id": "uuid",
    "name": "Nguyen Van A",
    "phone": "+84901234567",
    "email": "a@example.com",
    "status": "PENDING_VERIFICATION"
  },
  "meta": {
    "verification_required": true,
    "expires_at": "2026-09-21T15:00:00Z"
  }
}
```

### `POST /auth/phone/verify`

Xác minh OTP lần đầu và tạo session/token cho app.

```json
{
  "phone": "0901234567",
  "code": "123456",
  "device_id": "customer-device-1",
  "app_type": "CUSTOMER_APP",
  "platform": "ANDROID",
  "push_token": "optional-fcm-token"
}
```

`app_type`: `CUSTOMER_APP` hoặc `DRIVER_APP`. Driver app chỉ được cấp token nếu user đã có role `DRIVER` và hồ sơ đã `APPROVED`.

Response `200`:

```json
{
  "data": { "id": "uuid", "status": "ACTIVE", "roles": ["CUSTOMER"] },
  "token": "1|sanctum-token",
  "token_type": "Bearer"
}
```

OTP sai/hết hạn/vượt attempts: `422` tại field `code`.

### `POST /auth/phone/resend`

Phát lại OTP cho user đang `PENDING_VERIFICATION`. Response luôn trung tính để không lộ số điện thoại có tồn tại.

```json
{ "phone": "0901234567" }
```

Response `202`:

```json
{ "message": "If the phone number is pending verification, a new code has been issued." }
```

### `POST /auth/login`

Đăng nhập sau khi phone đã verify.

```json
{
  "phone": "0901234567",
  "password": "password123",
  "device_id": "driver-device-1",
  "app_type": "DRIVER_APP",
  "platform": "ANDROID",
  "push_token": "optional-fcm-token"
}
```

Response `200` giống verify phone. Sai credential, user chưa active hoặc driver chưa approved: `422` tại field `phone`/`app_type`.

### `POST /auth/password/forgot`

```json
{ "phone": "0901234567" }
```

Response `202` luôn giống nhau cho phone tồn tại và không tồn tại:

```json
{ "message": "If the phone number is active, a verification code has been issued." }
```

### `POST /auth/password/verify`

Xác minh OTP reset và tạo reset token dùng một lần.

```json
{ "phone": "0901234567", "code": "123456" }
```

Response `200`:

```json
{ "reset_token": "64-character-token", "expires_in": 600 }
```

### `POST /auth/password/reset`

```json
{
  "phone": "0901234567",
  "token": "reset-token",
  "password": "new-password123",
  "password_confirmation": "new-password123"
}
```

Response `204`. Token reset đã dùng/hết hạn: `422`. Tất cả Sanctum token và device session cũ bị thu hồi.

### `POST /auth/logout`

Header bearer bắt buộc. Thu hồi token hiện tại và đánh dấu device hiện tại `revoked_at`.

Response `204`.

### `GET /me`

Header bearer bắt buộc. Response `200` trả `UserResource`:

```json
{
  "data": {
    "id": "uuid",
    "name": "Nguyen Van A",
    "phone": "+84901234567",
    "email": null,
    "phone_verified_at": "2026-09-21T15:00:00Z",
    "status": "ACTIVE",
    "roles": ["CUSTOMER"]
  }
}
```

## Phase 2 - Catalog và driver onboarding

### `GET /catalog/vehicle-types`

Trả các loại xe `is_active = true`, sắp xếp theo `name`. User phải đăng nhập; không yêu cầu role.

Response `200`:

```json
{
  "data": [
    {
      "id": "uuid",
      "key": "MOTORBIKE",
      "name": "Xe máy",
      "passenger_capacity": 1,
      "max_weight_kg": 30,
      "max_dimensions_cm": { "length": 60, "width": 50, "height": 50 }
    }
  ]
}
```

### `GET /driver/application`

Lấy hồ sơ driver của user hiện tại. Nếu chưa tạo hồ sơ: `422`.

### `POST /driver/application`

Tạo hoặc cập nhật draft khi hồ sơ ở `DRAFT`/`REJECTED`.

```json
{ "cod_limit": 1000000 }
```

Response `201` khi tạo mới, `200` khi cập nhật; trả `DriverProfileResource` với `review_status`, `availability_status`, `cod_limit`, `documents`, `vehicles`, `capabilities`.

### `POST /driver/vehicles`

```json
{
  "vehicle_type_id": "vehicle-type-public-uuid",
  "plate_number": "59 a1 12345",
  "brand": "Honda",
  "model": "Wave",
  "color": "Black"
}
```

Server normalize biển số thành uppercase/no spaces. Response `201` với `VehicleResource`.

### `PATCH /driver/vehicles/{vehicle}`

`{vehicle}` là `public_id` của vehicle thuộc user hiện tại. Body nhận các field vehicle tương tự nhưng optional. Response `200`.

### `PUT /driver/vehicles/{vehicle}/selected`

Chọn một vehicle active cho hồ sơ. Response `200`. User khác hoặc vehicle không thuộc hồ sơ trả `404`.

### `POST /driver/documents`

Multipart upload private:

| Field | Bắt buộc | Ghi chú |
|---|---:|---|
| `document_type` | Có | `IDENTITY`, `DRIVER_LICENSE`, `VEHICLE_REGISTRATION`, `INSURANCE`, `PORTRAIT`, `VEHICLE_PHOTO` |
| `file` | Có | jpg/jpeg/png/webp/pdf, tối đa 5 MB |
| `document_number` | Theo loại | Bắt buộc cho identity/license/registration |
| `vehicle_id` | Theo loại | Bắt buộc cho registration/insurance/vehicle photo |
| `expires_at` | Không | Ngày hết hạn phải sau hôm nay |

Response `201`:

```json
{
  "data": {
    "id": "document-public-uuid",
    "type": "IDENTITY",
    "document_number": "CCCD-123",
    "vehicle_id": null,
    "expires_at": "2027-09-21",
    "status": "PENDING",
    "file_url": "/api/v1/driver/documents/{document}/file"
  }
}
```

File được lưu ở disk private; không dùng URL public.

### `DELETE /driver/documents/{document}`

Xóa document của chính user khi hồ sơ còn editable. Response `204`; user khác hoặc hồ sơ đã submit trả `404/422`.

### `GET /driver/documents/{document}/file`

Download file private. Owner hoặc `ADMIN` được phép; user không liên quan nhận `404`.

### `POST /driver/application/submit`

```json
{
  "vehicle_id": "vehicle-public-uuid",
  "service_types": ["DELIVERY", "DRIVE"]
}
```

Yêu cầu đủ 6 nhóm giấy tờ, xe thuộc hồ sơ, service capability phù hợp với loại xe và không có giấy tờ hết hạn. Chuyển `DRAFT/REJECTED -> PENDING_REVIEW`. Response `200`.

### `PUT /driver/availability/online`

Chỉ user có role `DRIVER`, hồ sơ `APPROVED` mới dùng được.

```json
{
  "service_types": ["DELIVERY", "DRIVE"],
  "latitude": 10.7769,
  "longitude": 106.7009,
  "accuracy": 5,
  "heading": 90,
  "speed": 0,
  "captured_at": "2026-09-21T15:00:00Z"
}
```

Eligibility: vehicle approved/selected, documents approved/not expired, capability active, wallet balance `>= 0`, không có assignment active. Response `200` với `availability_status = ONLINE` và snapshot `last_location`.

### `PUT /driver/availability/offline`

Chỉ `DRIVER`. Driver đang `BUSY` hoặc có assignment active không được offline. Response `200`.

## Filament admin

- URL: `/admin`.
- Đăng nhập bằng số điện thoại + mật khẩu.
- Chỉ user `ACTIVE` có role `ADMIN` được truy cập panel.
- `DriverProfileResource`: list/filter/detail, documents, vehicles, capabilities, approve/reject/suspend.
- `VehicleTypeResource`: CRUD catalog loại xe và giới hạn vận hành.
- `ServiceRequestResource`: list/filter/detail read-only theo service, trạng thái, payer, payment và lịch đặt.
- Các quyết định review gọi `DriverReviewService`, tạo role/wallet/capability và ghi `audit_logs`; không cập nhật trực tiếp từ UI.
- Tạo admin bằng command:

```bash
php artisan app:create-admin-user 0901234567 --name="Administrator"
```

## Phase 3 - Quote, Goong và Pricing

### `POST /quotes`

Tạo quote bất biến có thời hạn cho `DELIVERY` hoặc `DRIVE`. Endpoint kiểm tra điểm lấy/giao trong service area đang active, gọi MapProvider (Goong ở runtime), lấy pricing rule hiện hành và chỉ preview voucher; voucher chưa được redemption cho tới Phase 4 tạo đơn.

Request Delivery:

```json
{
  "service_type": "DELIVERY",
  "vehicle_type_id": "vehicle-type-public-uuid",
  "booking_type": "NOW",
  "pickup": {"address": "1 Nguyen Hue", "latitude": 10.773, "longitude": 106.704},
  "dropoff": {"address": "1 Vo Van Tan", "latitude": 10.780, "longitude": 106.690},
  "service_payload": {"goods_type": "GENERAL", "weight_kg": 5},
  "voucher_code": "SAVE5K"
}
```

`DRIVE` dùng `service_payload.passenger_count`; `DELIVERY` dùng `goods_type`, trọng lượng/kích thước và COD tùy chọn. `SCHEDULED` bắt buộc `scheduled_at` ở tương lai.

Response `201` trả `data.id`, pickup/dropoff snapshot, route snapshot (`provider`, distance, duration, polyline/metadata), pricing breakdown (`gross_fare`, `voucher_discount`, `customer_payable`, `driver_rate`, `currency`), `status = ACTIVE` và `expires_at`.

Các lỗi riêng:

- `422`: thiếu pricing rule, ngoài service area, voucher không hợp lệ, quá tải/sức chứa hoặc payload sai service type.
- `503 MAP_ROUTE_UNAVAILABLE`: Goong timeout, lỗi upstream hoặc response route không hợp lệ; không tạo quote.
- Endpoint bị giới hạn `30 requests/phút/user` bằng limiter `quotes`.

## Phase 4 - Service Request và Payment Intent

Các endpoint tạo/hủy yêu cầu bắt buộc header:

```http
Idempotency-Key: client-generated-stable-key
```

Retry cùng key và cùng payload trả lại đúng resource đã tạo. Dùng lại key với payload khác trả `409`.

### `POST /delivery/orders`

```json
{
  "quote_id": "quote-public-uuid",
  "payment_method": "WALLET",
  "payer_type": "ORDERER",
  "recipient_user_id": null,
  "stops": {
    "pickup": {"contact_name": "Sender", "contact_phone": "0900000001"},
    "dropoff": {"contact_name": "Receiver", "contact_phone": "0900000002"}
  }
}
```

- Quote phải thuộc user, còn `ACTIVE`, chưa hết hạn và có service `DELIVERY`.
- `ORDERER` hỗ trợ `WALLET`/`CASH`.
- `RECIPIENT` yêu cầu `recipient_user_id` của user `ACTIVE` và hiện chỉ hỗ trợ `CASH`; server không tự trừ ví người nhận.
- WALLET khóa ví, kiểm tra available balance và tạo ledger transaction cân bằng; CASH không ghi ledger.

### `POST /rides/bookings`

```json
{
  "quote_id": "quote-public-uuid",
  "payment_method": "CASH"
}
```

Quote phải có service `DRIVE`; payer luôn là `ORDERER`. Response `201` của cả hai endpoint trả `ServiceRequestResource`, gồm status, stops, detail Delivery/Drive và payment snapshot.

`NOW` tạo `SEARCHING_DRIVER`; `SCHEDULED` tạo `SCHEDULED` và chưa đặt `search_started_at`. Server ghi status history cùng outbox event `DELIVERY_ORDER_CREATED`/`RIDE_BOOKING_CREATED`; request tức thời có thêm `DELIVERY_SEARCH_REQUESTED`/`RIDE_SEARCH_REQUESTED`.

Voucher trong quote được kiểm tra lại, tạo một `VoucherRedemption` và `DiscountTransaction`; không tạo wallet entry cho phần voucher.

### `POST /service-requests/{serviceRequest}/cancel`

```json
{"reason_code": "CUSTOMER_CHANGED_MIND"}
```

Chỉ chủ request được hủy khi status còn `SCHEDULED`/`SEARCHING_DRIVER` và chưa có active assignment. WALLET được refund bằng ledger transaction đảo cân bằng; CASH không có refund; voucher được restore theo giới hạn campaign. Response `200` trả request ở `CANCELLED`.

## Phase 5 - Matching và Realtime

### `GET /driver/offers`

Trả tối đa 50 offer `PENDING`/`ACCEPTED` của driver hiện tại, gồm khoảng cách/ETA tới pickup, thu nhập dự kiến, thời hạn offer và snapshot service request.

### `POST /driver/offers/{driverOffer}/respond`

Header `Idempotency-Key` bắt buộc.

```json
{"action": "accept"}
```

`action` nhận `accept` hoặc `decline`. Driver khác nhận `404`. Accept khóa offer và service request trong transaction; winner tạo đúng một `Assignment ACTIVE`, request sang `DRIVER_ARRIVING_PICKUP` hoặc `DRIVER_ARRIVING`, driver sang `BUSY` và các offer pending khác bị `CANCELLED`.

Offer quá hạn chuyển `EXPIRED`. Hai driver accept gần đồng thời chỉ request đầu tiên còn `SEARCHING_DRIVER` thành công; partial unique indexes ở database bảo vệ thêm active assignment/request và active assignment/driver.

Scheduler chuyển request `SCHEDULED` đã đến hạn sang `SEARCHING_DRIVER`, ghi `SCHEDULED_SEARCH_STARTED` rồi tạo offer batch.

### `GET /service-requests/{serviceRequest}`

Snapshot authoritative cho customer reconnect sau khi mất Socket event. Chỉ owner được xem; user khác nhận `404`.

### Realtime contract

- Worker ghi transactional outbox và command `outbox:publish` phát envelope vào Redis channel `worker.outbox`.
- `service/` có thể nhận Redis Pub/Sub và RabbitMQ khi cấu hình.
- Socket room: `service-request:{public_id}`; handshake token được xác thực qua `GET /me` và mỗi join được worker xác nhận owner qua snapshot API.
- Event `booking:event` chứa `event_id`, `event_type`, `aggregate_version`, `payload`, `occurred_at`.
- Service bỏ event trùng và event có version thấp hơn version đã phát.
- Socket chỉ dùng push; accept/decline luôn đi qua HTTPS worker API.

## Phase 6 - Delivery/Drive Execution

### `POST /driver/service-requests/{serviceRequest}/transition`

Header `Idempotency-Key` bắt buộc. Payload chung:

```json
{
  "action": "arrive_pickup",
  "latitude": 10.77,
  "longitude": 106.68,
  "out_of_geofence_reason": null,
  "evidence_id": null,
  "cash_collected": null,
  "cod_collected": null
}
```

Delivery action theo thứ tự: `arrive_pickup → pickup → start_delivery → deliver`. Drive: `arrive → start → complete`. Không được skip state. Ngoài geofence yêu cầu reason. `pickup`/`deliver` yêu cầu evidence khi `proof_policy` của order bật.

Với CASH, terminal action bắt buộc xác nhận đúng `cash_collected`. COD advance/collection dùng `cod_accounts/cod_transactions` riêng và không cộng vào driver earning.

### Evidence và location

- `POST /driver/service-requests/{id}/evidence`: multipart, jpg/png/webp/pdf tối đa 10 MB; lưu private với SHA-256.
- `GET /service-evidence/{id}/file`: chỉ owner, assigned driver hoặc admin.
- `PUT /driver/location`: chỉ assigned driver; sample cũ bị từ chối, PostgreSQL giữ đúng snapshot cuối, Redis phát `DRIVER_LOCATION_UPDATED`.

## Phase 7 - Settlement và Finance

### Settlement

Terminal execution tự tạo settlement idempotent. WALLET ghi có phần khách thực trả vào ví tài xế rồi trừ platform fee; CASH chỉ trừ platform fee sau hoàn tất. `voucher_payment_amount` chỉ là breakdown, không tạo wallet credit.

### Wallet và top-up

- `GET /wallet`: balance, reserved, available và 50 ledger entries gần nhất.
- `POST /wallet/topups`: tạo VietQR request, bắt buộc `Idempotency-Key`.
- `POST /webhooks/sepay`: xác thực `X-SePay-Secret`, dedup `event_id/transaction_id`, post TOP_UP cân bằng.

### Bank account và withdrawal

- Driver tạo/xem bank account; account mới chưa verified.
- Admin verify bank account trong Filament.
- `POST /driver/withdrawals` reserve available balance; admin complete/reject qua Filament.
- Complete tạo ledger withdrawal cân bằng; reject chỉ giải phóng reserved.

Admin Finance có Wallet/Ledger, Payment, Settlement read-only; refund/withdrawal là action service có reason/audit. WALLET refund tạo ledger credit; CASH refund bắt buộc evidence thủ công.

## Phase 8 - Support, chat, incident, rating va notification

Tat ca endpoint duoi day yeu cau `auth:sanctum`. User chi doc duoc ticket, chat, incident va attachment ma minh la participant; support/admin dung Filament cho thao tac van hanh.

### Chat theo chuyen

| Method | Endpoint | Contract |
|---|---|---|
| `GET` | `/service-requests/{serviceRequest}/chat` | Customer/assigned driver; tra danh sach message va dong dau message cua doi phuong la da doc |
| `POST` | `/service-requests/{serviceRequest}/chat` | `client_message_id` UUID, `body`; idempotent theo conversation |
| `GET` | `/chat/unread` | Tra `data.unread_count` cua user hien tai |
| `GET` | `/service-requests/{serviceRequest}/realtime-access` | Probe `204` cho customer, assigned driver hoac admin; realtime service dung de authorize room |

### Support ticket

| Method | Endpoint | Contract |
|---|---|---|
| `GET` | `/support/tickets` | Danh sach ticket do user hien tai mo |
| `POST` | `/support/tickets` | Bat buoc `Idempotency-Key`; `category`, `subject`, `description`, optional `service_request_id` va private attachment |
| `GET` | `/support/tickets/{supportTicket}` | Owner, assignee support hoac admin; user khong lien quan nhan `404` |
| `POST` | `/support/tickets/{supportTicket}/messages` | Owner/assignee/admin gui message; optional private attachment |
| `GET` | `/support/attachments/{attachment}/file` | Authenticated download theo ticket authorization, khong public URL |

`category` nhan `PRICING`, `PAYMENT`, `ATTITUDE`, `LOST_ITEM`, `DAMAGE`, `SAFETY`, `OTHER`. Ticket `SAFETY`/`LOST_ITEM` duoc uu tien `HIGH`.

### Incident va rating

| Method | Endpoint | Contract |
|---|---|---|
| `POST` | `/service-requests/{serviceRequest}/incidents` | Bat buoc `Idempotency-Key`; `incident_type`, optional description/location/evidence ids; SOS duoc uu tien `CRITICAL` |
| `GET` | `/incidents` | Chi incident do user hien tai bao cao |
| `POST` | `/service-requests/{serviceRequest}/ratings` | Sau khi hoan tat; `score` 1-5, optional `tags`/`comment`; moi actor chi danh gia mot chieu |

Rating 1-2 sao duoc gan `FLAGGED` va tao outbox `LOW_RATING_FLAGGED`; khong tu dong sua settlement.

### Notification

| Method | Endpoint | Contract |
|---|---|---|
| `GET` | `/notifications` | Danh sach notification cua user hien tai |
| `GET` | `/notifications/unread` | Tra `data.unread_count` |
| `PUT` | `/notifications/{notification}/read` | Chi owner notification; tra resource trong `data` |

Notification outbox chi phat `notification_id`, `user_id`, `type`; body chat va du lieu tai chinh khong duoc dua vao event realtime.

## Client integration checklist

- Lưu bearer token trong secure storage, không log token/OTP.
- Sau `401`, xóa session và yêu cầu login lại; không tự retry vô hạn.
- Sau `422`, hiển thị `errors` theo field.
- Driver app không hiện màn hình nhận cuốc khi `review_status != APPROVED` hoặc `availability_status != ONLINE`.
- Upload dùng multipart và chỉ hiển thị `file_url` qua request đã authenticated.
- Khi gọi online, gửi location sample tăng dần `captured_at`; sample cũ hơn snapshot server sẽ bị từ chối.
