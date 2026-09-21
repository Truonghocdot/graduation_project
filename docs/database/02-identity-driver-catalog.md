# Identity, driver và catalog

## 1. `users`

Tài khoản dùng chung cho authentication; quyền truy cập từng app được quyết định bởi role/capability.

| Cột | Kiểu PostgreSQL | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | Khóa nội bộ |
| `public_id` | `UUID` | Không | UNIQUE | ID dùng trong API |
| `name` | `VARCHAR(120)` | Không | | Họ tên hiển thị |
| `phone` | `VARCHAR(20)` | Không | UNIQUE | Số điện thoại chuẩn hóa E.164 |
| `phone_verified_at` | `TIMESTAMPTZ` | Có | | Thời điểm OTP ban đầu thành công |
| `email` | `VARCHAR(255)` | Có | | Thông tin hồ sơ, không dùng đăng nhập |
| `password` | `VARCHAR(255)` | Không | | Password hash |
| `status` | `VARCHAR(30)` | Không | `PENDING_VERIFICATION` | `PENDING_VERIFICATION`, `ACTIVE`, `SUSPENDED`, `CLOSED` |
| `last_login_at` | `TIMESTAMPTZ` | Có | | Lần đăng nhập gần nhất |
| `remember_token` | `VARCHAR(100)` | Có | | Laravel web session nếu dùng |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | Audit cơ bản |
| `deleted_at` | `TIMESTAMPTZ` | Có | | Soft delete; không xóa dữ liệu nghiệp vụ |

Quy tắc:

- Email không unique và không có `email_verified_at`.
- User chưa có `phone_verified_at` không được chuyển `ACTIVE`.
- Token Sanctum phải bị thu hồi khi user chuyển `SUSPENDED/CLOSED`.

## 2. `roles` và `user_roles`

### `roles`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `key` | `VARCHAR(30)` | Không | UNIQUE | `CUSTOMER`, `DRIVER`, `SUPPORT`, `ADMIN` |
| `name` | `VARCHAR(100)` | Không | | Tên hiển thị |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `user_roles`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `user_id` | `BIGINT` | Không | FK `users` | |
| `role_id` | `BIGINT` | Không | FK `roles` | |
| `granted_by` | `BIGINT` | Có | FK `users` | Admin cấp role |
| `granted_at` | `TIMESTAMPTZ` | Không | | |

Primary/unique key: `(user_id, role_id)`.

## 3. Xác minh và thiết bị

### `phone_verifications`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `user_id` | `BIGINT` | Không | FK `users` | User `PENDING_VERIFICATION` |
| `phone` | `VARCHAR(20)` | Không | INDEX | Snapshot số nhận OTP |
| `purpose` | `VARCHAR(30)` | Không | | `REGISTER`, `RESET_PASSWORD`, `CHANGE_PHONE` |
| `code_hash` | `VARCHAR(255)` | Không | | Không lưu OTP plaintext |
| `attempt_count` | `SMALLINT` | Không | `0` | Số lần nhập sai |
| `max_attempts` | `SMALLINT` | Không | | Snapshot cấu hình |
| `expires_at` | `TIMESTAMPTZ` | Không | INDEX | |
| `verified_at` | `TIMESTAMPTZ` | Có | | Mã đã dùng |
| `invalidated_at` | `TIMESTAMPTZ` | Có | | Mã bị thay bởi lần gửi lại |
| `created_at` | `TIMESTAMPTZ` | Không | | |

Chỉ một OTP chưa hết hạn/chưa vô hiệu theo `(user_id, purpose)` được xem là active; application vô hiệu mã cũ khi resend.

### `phone_password_reset_tokens`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `user_id` | `BIGINT` | Không | FK `users`, INDEX | |
| `token_hash` | `VARCHAR(255)` | Không | UNIQUE | Token sau bước xác minh OTP |
| `expires_at` | `TIMESTAMPTZ` | Không | INDEX | |
| `used_at` | `TIMESTAMPTZ` | Có | | |
| `created_at` | `TIMESTAMPTZ` | Không | | |

### `user_devices`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `user_id` | `BIGINT` | Không | FK `users`, INDEX | |
| `device_id` | `VARCHAR(191)` | Không | | ID ổn định do app tạo |
| `app_type` | `VARCHAR(20)` | Không | | `CUSTOMER_APP`, `DRIVER_APP`, `ADMIN_WEB` |
| `platform` | `VARCHAR(20)` | Không | | `ANDROID`, `IOS`, `WEB` |
| `push_token` | `TEXT` | Có | | Token FCM/APNs hiện tại |
| `last_seen_at` | `TIMESTAMPTZ` | Có | | |
| `revoked_at` | `TIMESTAMPTZ` | Có | | Unregister push/logout |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(user_id, device_id, app_type)`.

## 4. `driver_profiles`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | API ID |
| `user_id` | `BIGINT` | Không | UNIQUE FK `users` | Một hồ sơ/user |
| `review_status` | `VARCHAR(30)` | Không | `PENDING_REVIEW` | `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `SUSPENDED` |
| `availability_status` | `VARCHAR(20)` | Không | `OFFLINE` | `OFFLINE`, `ONLINE`, `OFFERED`, `BUSY` |
| `review_reason_code` | `VARCHAR(50)` | Có | | Lý do từ chối/khóa |
| `reviewed_by` | `BIGINT` | Có | FK `users` | Admin |
| `reviewed_at` | `TIMESTAMPTZ` | Có | | |
| `cod_limit` | `DOUBLE PRECISION` | Không | `0` | Hạn mức COD tài xế muốn nhận, tối đa theo setting |
| `offer_count` | `INTEGER` | Không | `0` | Cache thống kê matching |
| `accepted_offer_count` | `INTEGER` | Không | `0` | |
| `ignored_offer_count` | `INTEGER` | Không | `0` | |
| `cancelled_assignment_count` | `INTEGER` | Không | `0` | |
| `acceptance_rate` | `DOUBLE PRECISION` | Không | `0` | Cache phục vụ ranking |
| `cancellation_rate` | `DOUBLE PRECISION` | Không | `0` | Cache phục vụ ranking |
| `online_at`, `offline_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Eligibility nhận offer yêu cầu: `review_status = APPROVED`, `availability_status = ONLINE`, wallet balance `>= 0`, giấy tờ/phương tiện hợp lệ và last location còn TTL.

## 5. Hồ sơ và phương tiện tài xế

### `driver_documents`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | |
| `vehicle_id` | `BIGINT` | Có | FK `vehicles`, INDEX | Bắt buộc cho giấy tờ/ảnh phương tiện |
| `document_type` | `VARCHAR(40)` | Không | | `IDENTITY`, `DRIVER_LICENSE`, `VEHICLE_REGISTRATION`, `INSURANCE`, `PORTRAIT`, `VEHICLE_PHOTO` |
| `document_number` | `VARCHAR(100)` | Có | INDEX | Mã giấy tờ chuẩn hóa |
| `file_path` | `TEXT` | Không | | Private storage |
| `expires_at` | `DATE` | Có | INDEX | |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `APPROVED`, `REJECTED`, `EXPIRED` |
| `reviewed_by` | `BIGINT` | Có | FK `users` | |
| `reviewed_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique có điều kiện cho giấy tờ active theo `(document_type, normalized document_number)` được triển khai ở migration PostgreSQL khi loại giấy tờ yêu cầu duy nhất.

Giấy tờ cá nhân có `vehicle_id = NULL`; `VEHICLE_REGISTRATION`, `INSURANCE`, `VEHICLE_PHOTO` phải có `vehicle_id`.

### `vehicle_types`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `unique_key` | `VARCHAR(50)` | Không | UNIQUE | Ví dụ `MOTORBIKE`, `CAR_4_SEAT` |
| `name` | `VARCHAR(100)` | Không | | |
| `passenger_capacity` | `SMALLINT` | Có | | Drive |
| `max_weight_kg` | `DOUBLE PRECISION` | Có | | Delivery |
| `max_length_cm`, `max_width_cm`, `max_height_cm` | `DOUBLE PRECISION` | Có | | Delivery |
| `is_active` | `BOOLEAN` | Không | `true` | |
| `created_at`, `updated_at`, `deleted_at` | `TIMESTAMPTZ` | Có/Không | | Soft delete |

### `vehicles`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | Chủ phương tiện |
| `vehicle_type_id` | `BIGINT` | Không | FK, INDEX | |
| `plate_number` | `VARCHAR(30)` | Không | UNIQUE | Chuẩn hóa uppercase/no extra spaces |
| `brand`, `model`, `color` | `VARCHAR(100)` | Có | | |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `APPROVED`, `REJECTED`, `SUSPENDED` |
| `is_selected` | `BOOLEAN` | Không | `false` | Phương tiện đang dùng |
| `created_at`, `updated_at`, `deleted_at` | `TIMESTAMPTZ` | Có/Không | | |

PostgreSQL partial unique bảo đảm mỗi driver chỉ có một vehicle `is_selected = true` chưa bị xóa.

### `driver_service_capabilities`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `driver_profile_id` | `BIGINT` | Không | FK | |
| `vehicle_type_id` | `BIGINT` | Không | FK | |
| `service_type` | `VARCHAR(20)` | Không | | `DELIVERY`, `DRIVE` |
| `is_active` | `BOOLEAN` | Không | | Admin cho phép nhận loại dịch vụ |
| `approved_by` | `BIGINT` | Có | FK `users` | |
| `approved_at` | `TIMESTAMPTZ` | Có | | |

Unique: `(driver_profile_id, vehicle_type_id, service_type)`.

## 6. Vị trí và tài khoản ngân hàng

### `driver_last_locations`

Một row/driver, update đè mỗi 1,5 giây hoặc theo chiến lược write-throttle. Không tạo lịch sử.

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `driver_profile_id` | `BIGINT` | Không | PK/FK | |
| `last_location` | `JSONB` | Không | | `{lat, lng, accuracy, heading, speed}` |
| `last_location_at` | `TIMESTAMPTZ` | Không | INDEX | Timestamp do thiết bị capture đã validate |
| `updated_at` | `TIMESTAMPTZ` | Không | | Thời điểm server ghi |

Không dùng bảng này để geo-search tải cao; Node.js cập nhật Redis GEO/presence, PostgreSQL chỉ giữ snapshot cuối.

### `driver_bank_accounts`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | |
| `bank_code` | `VARCHAR(30)` | Không | | Mã ngân hàng |
| `account_number_encrypted` | `TEXT` | Không | | Mã hóa ở application layer |
| `account_number_hash` | `VARCHAR(64)` | Không | INDEX | So trùng mà không giải mã |
| `account_name` | `VARCHAR(150)` | Không | | |
| `is_verified` | `BOOLEAN` | Không | `false` | |
| `is_default` | `BOOLEAN` | Không | `false` | |
| `created_at`, `updated_at`, `deleted_at` | `TIMESTAMPTZ` | Có/Không | | |

Partial unique: một bank account default/driver.

## 7. Pricing và cấu hình

### `pricing_rules`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `service_type` | `VARCHAR(20)` | Không | INDEX | `DELIVERY`, `DRIVE` |
| `vehicle_type_id` | `BIGINT` | Không | FK, INDEX | |
| `base_distance_km` | `DOUBLE PRECISION` | Không | `3` | Ngưỡng giá cơ sở |
| `base_fare` | `DOUBLE PRECISION` | Không | | 18.000 cho xe máy; ô tô theo cấu hình |
| `price_per_extra_km` | `DOUBLE PRECISION` | Không | | Ví dụ 5.000/10.000 |
| `driver_rate` | `DOUBLE PRECISION` | Không | `0.88` | Tỷ lệ thu nhập tài xế |
| `currency` | `CHAR(3)` | Không | `VND` | |
| `effective_from`, `effective_to` | `TIMESTAMPTZ` | Có/Không | | Version rule theo thời gian |
| `is_active` | `BOOLEAN` | Không | `true` | |
| `created_by` | `BIGINT` | Không | FK `users` | Admin |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Không update rule đã được quote sử dụng theo cách làm mất lịch sử. Tạo version mới và đóng `effective_to` của version cũ.

Partial unique đề xuất: một rule open-ended active theo `(service_type, vehicle_type_id)` với predicate `is_active = true AND effective_to IS NULL`.

### `service_areas`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `name` | `VARCHAR(120)` | Không | | |
| `service_type` | `VARCHAR(20)` | Có | | Null = áp dụng cả hai |
| `boundary` | `JSONB` | Không | | Polygon/bounds cấu hình từ Goong |
| `is_active` | `BOOLEAN` | Không | INDEX | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `system_settings`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `key` | `VARCHAR(100)` | Không | PK | Ví dụ `cod.max_amount`, `withdrawal.min_amount` |
| `value` | `JSONB` | Không | | Giá trị typed trong JSON |
| `is_public` | `BOOLEAN` | Không | | Có được expose cho app hay không |
| `updated_by` | `BIGINT` | Có | FK `users` | |
| `updated_at` | `TIMESTAMPTZ` | Không | | |

Setting tối thiểu: COD max 8.000.000, OTP expiry/attempt, offer TTL/batch size, scheduled dispatch lead time, withdrawal min/max/time window, float tolerance và location TTL.

Không lưu API secret của SePay/Goong hoặc credential ngân hàng trong `system_settings`; secret nằm ở environment/secret manager. Setting chỉ chứa tham số nghiệp vụ và thông tin VietQR được phép công khai cho khách nạp tiền.
