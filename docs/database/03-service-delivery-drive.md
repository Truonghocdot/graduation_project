# Quote, Delivery, Drive và matching

## Mô hình aggregate

`service_requests` là bảng gốc chung cho một yêu cầu dịch vụ. `delivery_orders` và `ride_bookings` là detail one-to-one. API vẫn dùng thuật ngữ DeliveryOrder/RideBooking; bảng gốc chỉ là lựa chọn relational để các domain dùng chung foreign key.

## 1. `quotes`

Quote là snapshot bất biến có thời hạn. Sau khi dùng, không sửa route hoặc pricing snapshot.

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | `quote_id` trong API |
| `requested_by` | `BIGINT` | Không | FK `users`, INDEX | |
| `service_type` | `VARCHAR(20)` | Không | | `DELIVERY`, `DRIVE` |
| `vehicle_type_id` | `BIGINT` | Không | FK | |
| `pricing_rule_id` | `BIGINT` | Không | FK | Rule đã dùng |
| `booking_type` | `VARCHAR(20)` | Không | | `NOW`, `SCHEDULED` |
| `scheduled_at` | `TIMESTAMPTZ` | Có | INDEX | Bắt buộc khi `SCHEDULED` |
| `pickup_snapshot` | `JSONB` | Không | | Address, lat/lng, note |
| `dropoff_snapshot` | `JSONB` | Không | | |
| `service_payload` | `JSONB` | Không | | Passenger/goods/COD input đã validate |
| `route_snapshot` | `JSONB` | Không | | Goong route ID/polyline/metadata cần giữ |
| `distance_meters` | `DOUBLE PRECISION` | Không | | |
| `duration_seconds` | `INTEGER` | Không | | |
| `base_fare` | `DOUBLE PRECISION` | Không | | |
| `extra_distance_fare` | `DOUBLE PRECISION` | Không | `0` | |
| `surcharge_amount` | `DOUBLE PRECISION` | Không | `0` | |
| `gross_fare` | `DOUBLE PRECISION` | Không | | Trước voucher |
| `voucher_discount` | `DOUBLE PRECISION` | Không | `0` | Preview tại thời điểm quote |
| `customer_payable` | `DOUBLE PRECISION` | Không | | Sau discount |
| `driver_rate` | `DOUBLE PRECISION` | Không | | Snapshot, hiện mặc định `0.88` |
| `currency` | `CHAR(3)` | Không | `VND` | |
| `status` | `VARCHAR(20)` | Không | `ACTIVE` | `ACTIVE`, `USED`, `EXPIRED`, `CANCELLED` |
| `expires_at` | `TIMESTAMPTZ` | Không | INDEX | |
| `used_at` | `TIMESTAMPTZ` | Có | | |
| `created_at` | `TIMESTAMPTZ` | Không | | |

CHECK:

- `booking_type = SCHEDULED` yêu cầu `scheduled_at IS NOT NULL`.
- Tất cả amount không âm sau làm tròn.
- `customer_payable = max(gross_fare - voucher_discount, 0)` trong tolerance cấu hình; validation chính ở application.

## 2. `service_requests`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | Aggregate ID chung |
| `public_id` | `UUID` | Không | UNIQUE | Mã đơn/chuyến dùng API |
| `service_type` | `VARCHAR(20)` | Không | INDEX | `DELIVERY`, `DRIVE` |
| `created_by` | `BIGINT` | Không | FK `users`, INDEX | Khách tạo request |
| `vehicle_type_id` | `BIGINT` | Không | FK, INDEX | |
| `quote_id` | `BIGINT` | Không | UNIQUE FK `quotes` | Một quote tạo tối đa một request |
| `status` | `VARCHAR(40)` | Không | INDEX | State machine theo service type |
| `booking_type` | `VARCHAR(20)` | Không | | `NOW`, `SCHEDULED` |
| `scheduled_at` | `TIMESTAMPTZ` | Có | INDEX | |
| `search_started_at` | `TIMESTAMPTZ` | Có | | |
| `completed_at` | `TIMESTAMPTZ` | Có | | |
| `cancelled_at` | `TIMESTAMPTZ` | Có | | |
| `cancelled_by` | `BIGINT` | Có | FK `users` | |
| `cancellation_reason_code` | `VARCHAR(50)` | Có | | |
| `search_attempt` | `SMALLINT` | Không | `0` | Matching round |
| `version` | `INTEGER` | Không | `1` | Optimistic locking/event version |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Index scheduler: `(status, scheduled_at)` với predicate `status = 'SCHEDULED'`.

## 3. `service_stops`

Một request có đúng hai row. Không hỗ trợ multi-stop trong baseline.

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `service_request_id` | `BIGINT` | Không | FK `service_requests` ON DELETE CASCADE | |
| `stop_type` | `VARCHAR(20)` | Không | | `PICKUP`, `DROPOFF` |
| `address` | `TEXT` | Không | | Địa chỉ hiển thị snapshot |
| `latitude`, `longitude` | `DOUBLE PRECISION` | Không | | Tọa độ Goong/pin |
| `contact_name` | `VARCHAR(120)` | Có | | Chủ yếu Delivery |
| `contact_phone` | `VARCHAR(20)` | Có | | Snapshot số liên hệ |
| `note` | `TEXT` | Có | | Cổng đón/hướng dẫn giao |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(service_request_id, stop_type)`. CHECK latitude `[-90, 90]`, longitude `[-180, 180]`.

## 4. `delivery_orders`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `service_request_id` | `BIGINT` | Không | PK/FK | One-to-one detail |
| `sender_user_id` | `BIGINT` | Có | FK `users` | Có thể trùng người tạo |
| `recipient_user_id` | `BIGINT` | Có | FK `users` | Null nếu người nhận chưa có tài khoản |
| `payer_type` | `VARCHAR(20)` | Không | | `ORDERER`, `RECIPIENT` |
| `goods_type` | `VARCHAR(50)` | Không | | Category quản trị |
| `goods_description` | `TEXT` | Có | | |
| `weight_kg` | `DOUBLE PRECISION` | Có | | |
| `length_cm`, `width_cm`, `height_cm` | `DOUBLE PRECISION` | Có | | |
| `declared_value` | `DOUBLE PRECISION` | Không | `0` | Giá trị khai báo hàng |
| `is_cod` | `BOOLEAN` | Không | `false` | Ứng COD |
| `cod_amount` | `DOUBLE PRECISION` | Không | `0` | Tối đa setting, hiện 8 triệu |
| `list_type` | `VARCHAR(20)` | Không | `ORIGINAL` | `ORIGINAL`, `RETURN` |
| `proof_policy` | `JSONB` | Có | | Snapshot yêu cầu ảnh/chữ ký |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

CHECK: `is_cod = false` yêu cầu `cod_amount = 0`; `is_cod = true` yêu cầu `cod_amount > 0`.

`payments` là nguồn chuẩn cho `payer_user_id`; `delivery_orders.payer_type` phải khớp `payments.payer_type`.

## 5. `ride_bookings`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `service_request_id` | `BIGINT` | Không | PK/FK | One-to-one detail |
| `passenger_count` | `SMALLINT` | Không | `1` | Không vượt vehicle capacity |
| `started_at` | `TIMESTAMPTZ` | Có | | |
| `ended_at` | `TIMESTAMPTZ` | Có | | |
| `route_version` | `INTEGER` | Không | `1` | Tăng khi đổi điểm đến |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Không lưu location timeline trong bảng này.

## 6. `driver_offers`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | Offer ID trên app |
| `service_request_id` | `BIGINT` | Không | FK, INDEX | |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | |
| `batch_number` | `SMALLINT` | Không | | Batch nhỏ giọt |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `ACCEPTED`, `DECLINED`, `EXPIRED`, `CANCELLED` |
| `estimated_pickup_distance_meters` | `DOUBLE PRECISION` | Không | | Snapshot ranking |
| `estimated_pickup_seconds` | `INTEGER` | Không | | |
| `estimated_driver_earning` | `DOUBLE PRECISION` | Không | | Sau driver rate, trước adjustment |
| `offered_at` | `TIMESTAMPTZ` | Không | | |
| `expires_at` | `TIMESTAMPTZ` | Không | INDEX | |
| `responded_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(service_request_id, driver_profile_id, batch_number)`. Offer `PENDING` hết hạn được chuyển `EXPIRED` và tăng ignored statistic đúng một lần.

## 7. `assignments`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `service_request_id` | `BIGINT` | Không | FK, INDEX | |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | |
| `vehicle_id` | `BIGINT` | Không | FK | Phương tiện snapshot theo ID |
| `accepted_offer_id` | `BIGINT` | Có | UNIQUE FK `driver_offers` | |
| `status` | `VARCHAR(20)` | Không | `ACTIVE` | `ACTIVE`, `COMPLETED`, `CANCELLED`, `REPLACED` |
| `assigned_at` | `TIMESTAMPTZ` | Không | | |
| `closed_at` | `TIMESTAMPTZ` | Có | | |
| `close_reason_code` | `VARCHAR(50)` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Partial unique indexes:

```sql
CREATE UNIQUE INDEX assignments_one_active_per_request
ON assignments (service_request_id) WHERE status = 'ACTIVE';

CREATE UNIQUE INDEX assignments_one_active_per_driver
ON assignments (driver_profile_id) WHERE status = 'ACTIVE';
```

## 8. `service_status_histories`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `service_request_id` | `BIGINT` | Không | FK, INDEX | |
| `version` | `INTEGER` | Không | | Aggregate version sau transition |
| `from_status` | `VARCHAR(40)` | Có | | Null cho trạng thái đầu |
| `to_status` | `VARCHAR(40)` | Không | INDEX | |
| `actor_user_id` | `BIGINT` | Có | FK `users` | Null nếu system |
| `actor_type` | `VARCHAR(20)` | Không | | `USER`, `DRIVER`, `ADMIN`, `SYSTEM` |
| `reason_code` | `VARCHAR(50)` | Có | | |
| `metadata` | `JSONB` | Có | | Evidence ref/command info đã lọc |
| `correlation_id` | `UUID` | Không | INDEX | Trace xuyên API/queue |
| `created_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(service_request_id, version)`.

## 9. `delivery_return_revisions`

Chặng hoàn nằm trên cùng `DeliveryOrder`; bảng này chỉ lưu revision giá/proof, không phải task/order mới.

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `delivery_order_id` | `BIGINT` | Không | FK `delivery_orders(service_request_id)`, INDEX | |
| `revision_number` | `SMALLINT` | Không | | |
| `reason_code` | `VARCHAR(50)` | Không | | |
| `requested_by` | `BIGINT` | Không | FK `users` | Driver/admin |
| `return_fare` | `DOUBLE PRECISION` | Không | | Giá chặng hoàn |
| `driver_rate` | `DOUBLE PRECISION` | Không | | Snapshot tỷ lệ |
| `driver_earning` | `DOUBLE PRECISION` | Không | | Sau tỷ lệ |
| `pricing_snapshot` | `JSONB` | Không | | |
| `proof` | `JSONB` | Có | | File refs/xác nhận bàn giao |
| `requested_at` | `TIMESTAMPTZ` | Không | | |
| `returned_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(delivery_order_id, revision_number)`.

## 10. State values

### Delivery

`SCHEDULED`, `SEARCHING_DRIVER`, `DRIVER_ASSIGNED`, `DRIVER_ARRIVING_PICKUP`, `AT_PICKUP`, `PICKED_UP`, `IN_DELIVERY`, `DELIVERED`, `RETURN_REQUESTED`, `RETURNING`, `RETURNED`, `COMPLETED`, `NO_DRIVER_FOUND`, `DELIVERY_FAILED`, `CANCELLED`.

### Drive

`SCHEDULED`, `SEARCHING_DRIVER`, `DRIVER_ASSIGNED`, `DRIVER_ARRIVING`, `DRIVER_ARRIVED`, `IN_TRIP`, `COMPLETED`, `NO_DRIVER_FOUND`, `NO_SHOW`, `CANCELLED`.

Application enum chọn allowed transition theo `service_type`; database CHECK bảo đảm status thuộc union của hai tập giá trị.
