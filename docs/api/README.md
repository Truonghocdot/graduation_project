# API Contract v1

> Base path: `/api/v1`
>
> Trạng thái: **Implemented cho Phase 1 và Phase 2**

Tài liệu này mô tả các API đang có trong `worker/routes/api.php`. Các endpoint Delivery/Drive, quote, matching, wallet payment và realtime nghiệp vụ chưa nằm trong danh sách này; chúng sẽ được bổ sung theo implementation phase tương ứng.

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
- Danh sách admin dùng Laravel pagination: `data`, `links`, `meta`.

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
- Các quyết định review gọi `DriverReviewService`, tạo role/wallet/capability và ghi `audit_logs`; không cập nhật trực tiếp từ UI.
- Tạo admin bằng command:

```bash
php artisan app:create-admin-user 0901234567 --name="Administrator"
```

## Client integration checklist

- Lưu bearer token trong secure storage, không log token/OTP.
- Sau `401`, xóa session và yêu cầu login lại; không tự retry vô hạn.
- Sau `422`, hiển thị `errors` theo field.
- Driver app không hiện màn hình nhận cuốc khi `review_status != APPROVED` hoặc `availability_status != ONLINE`.
- Upload dùng multipart và chỉ hiển thị `file_url` qua request đã authenticated.
- Khi gọi online, gửi location sample tăng dần `captured_at`; sample cũ hơn snapshot server sẽ bị từ chối.
