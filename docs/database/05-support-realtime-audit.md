# Support, realtime, audit và integration

## 1. `ratings`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `service_request_id` | `BIGINT` | Không | FK, INDEX | Request completed |
| `assignment_id` | `BIGINT` | Không | FK `assignments` | Xác định đúng driver hoàn thành |
| `reviewer_user_id` | `BIGINT` | Không | FK `users` | |
| `reviewee_user_id` | `BIGINT` | Không | FK `users`, INDEX | |
| `direction` | `VARCHAR(30)` | Không | | `CUSTOMER_TO_DRIVER`, `DRIVER_TO_CUSTOMER` |
| `score` | `SMALLINT` | Không | | 1..5 |
| `tags` | `JSONB` | Có | | Danh sách reason tag chuẩn |
| `comment` | `TEXT` | Có | | |
| `moderation_status` | `VARCHAR(20)` | Không | `VISIBLE` | `VISIBLE`, `HIDDEN`, `FLAGGED` |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(service_request_id, reviewer_user_id, direction)`. CHECK score `[1,5]`, reviewer khác reviewee.

## 2. Support ticket

### `support_tickets`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | Mã ticket |
| `opened_by` | `BIGINT` | Không | FK `users`, INDEX | |
| `service_request_id` | `BIGINT` | Có | FK, INDEX | Null cho vấn đề tài khoản chung |
| `category` | `VARCHAR(40)` | Không | INDEX | `PRICE`, `PAYMENT`, `BEHAVIOR`, `LOST_ITEM`, `DAMAGE`, `SAFETY`, `COD`, `OTHER` |
| `priority` | `VARCHAR(20)` | Không | `NORMAL` | `LOW`, `NORMAL`, `HIGH`, `URGENT` |
| `status` | `VARCHAR(30)` | Không | `OPEN` | `OPEN`, `IN_REVIEW`, `WAITING_FOR_CUSTOMER`, `RESOLVED`, `REOPENED`, `CLOSED` |
| `subject` | `VARCHAR(191)` | Không | | |
| `description` | `TEXT` | Không | | |
| `assigned_to` | `BIGINT` | Có | FK `users`, INDEX | User có role SUPPORT/ADMIN |
| `resolution_code` | `VARCHAR(50)` | Có | | |
| `resolution_note` | `TEXT` | Có | | |
| `resolved_at`, `closed_at` | `TIMESTAMPTZ` | Có | | |
| `version` | `INTEGER` | Không | `1` | Concurrency |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Không cam kết SLA số. Queue mặc định sort `(priority DESC, created_at ASC)`; urgent safety incident có hàng đợi riêng ở application.

### `support_ticket_messages`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `support_ticket_id` | `BIGINT` | Không | FK ON DELETE CASCADE, INDEX | |
| `sender_user_id` | `BIGINT` | Không | FK `users` | |
| `message_type` | `VARCHAR(20)` | Không | | `USER_MESSAGE`, `INTERNAL_NOTE`, `SYSTEM` |
| `body` | `TEXT` | Không | | |
| `created_at` | `TIMESTAMPTZ` | Không | INDEX | |

`INTERNAL_NOTE` chỉ SUPPORT/ADMIN được đọc.

### `ticket_attachments`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `support_ticket_id` | `BIGINT` | Không | FK, INDEX | |
| `uploaded_by` | `BIGINT` | Không | FK `users` | |
| `storage_path` | `TEXT` | Không | | Private disk |
| `original_name` | `VARCHAR(255)` | Không | | |
| `mime_type` | `VARCHAR(100)` | Không | | |
| `size_bytes` | `BIGINT` | Không | | |
| `sha256` | `VARCHAR(64)` | Không | INDEX | Integrity/dedup |
| `created_at` | `TIMESTAMPTZ` | Không | | |

## 3. `incidents`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `service_request_id` | `BIGINT` | Không | FK, INDEX | |
| `reported_by` | `BIGINT` | Không | FK `users` | |
| `incident_type` | `VARCHAR(40)` | Không | INDEX | `SOS`, `DELIVERY_FAILED`, `PAYMENT_DISPUTE`, `COD_DISPUTE`, `EARLY_TRIP_END`, `OTHER` |
| `severity` | `VARCHAR(20)` | Không | | `LOW`, `MEDIUM`, `HIGH`, `CRITICAL` |
| `status` | `VARCHAR(20)` | Không | `OPEN` | `OPEN`, `IN_REVIEW`, `RESOLVED`, `CLOSED` |
| `description` | `TEXT` | Có | | |
| `evidence` | `JSONB` | Có | | Private file refs |
| `assigned_to` | `BIGINT` | Có | FK `users` | |
| `resolution_code` | `VARCHAR(50)` | Có | | |
| `resolved_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

## 4. Chat

### `chat_conversations`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `service_request_id` | `BIGINT` | Không | FK, INDEX | Có thể có conversation mới khi reassign |
| `assignment_id` | `BIGINT` | Không | UNIQUE FK `assignments` | Một conversation/assignment |
| `customer_user_id` | `BIGINT` | Không | FK `users` | |
| `driver_user_id` | `BIGINT` | Không | FK `users` | Assigned driver |
| `status` | `VARCHAR(20)` | Không | `ACTIVE` | `ACTIVE`, `CLOSED` |
| `closed_at` | `TIMESTAMPTZ` | Có | | Khi assignment/request đóng |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `chat_messages`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | Cursor ordering |
| `public_id` | `UUID` | Không | UNIQUE | Client dedup/reference |
| `chat_conversation_id` | `BIGINT` | Không | FK, INDEX | |
| `sender_user_id` | `BIGINT` | Không | FK `users` | Phải là participant |
| `client_message_id` | `UUID` | Không | | Offline/retry ID |
| `message_type` | `VARCHAR(20)` | Không | | `TEXT`, `IMAGE`, `SYSTEM` |
| `body` | `TEXT` | Có | | |
| `attachment_path` | `TEXT` | Có | | Private file |
| `sent_at` | `TIMESTAMPTZ` | Không | INDEX | Server accepted time |
| `read_at` | `TIMESTAMPTZ` | Có | | MVP single recipient |
| `created_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(chat_conversation_id, client_message_id)`.

## 5. `notifications`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `UUID` | Không | PK | Tương thích Laravel notification UUID |
| `user_id` | `BIGINT` | Không | FK, INDEX | Recipient |
| `type` | `VARCHAR(100)` | Không | INDEX | Notification class/public type |
| `channel` | `VARCHAR(20)` | Không | | `IN_APP`, `PUSH`, `SMS` |
| `data` | `JSONB` | Không | | Payload không chứa secret/OTP plaintext |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `SENT`, `FAILED` |
| `sent_at`, `read_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Push token không copy vào notification; resolve từ `user_devices` lúc dispatch.

## 6. `audit_logs`

Append-only audit cho thao tác nhạy cảm.

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `actor_user_id` | `BIGINT` | Có | FK `users`, INDEX | Null nếu system |
| `actor_role` | `VARCHAR(30)` | Không | | Role tại thời điểm thao tác |
| `action` | `VARCHAR(100)` | Không | INDEX | Ví dụ `DRIVER_APPROVED`, `WALLET_ADJUSTED` |
| `subject_type` | `VARCHAR(50)` | Không | INDEX | |
| `subject_id` | `BIGINT` | Không | INDEX | Application reference |
| `before` | `JSONB` | Có | | Lọc secret/PII không cần thiết |
| `after` | `JSONB` | Có | | |
| `reason_code` | `VARCHAR(50)` | Có | | Bắt buộc cho override/adjustment |
| `ip_address` | `INET` | Có | | |
| `user_agent` | `TEXT` | Có | | |
| `correlation_id` | `UUID` | Không | INDEX | |
| `created_at` | `TIMESTAMPTZ` | Không | INDEX | |

Không có `updated_at/deleted_at`.

## 7. Outbox, inbox và idempotency

### `outbox_events`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | Publish ordering |
| `event_id` | `UUID` | Không | UNIQUE | Dedup xuyên service |
| `event_type` | `VARCHAR(100)` | Không | INDEX | Domain event name |
| `aggregate_type` | `VARCHAR(50)` | Không | INDEX | |
| `aggregate_id` | `BIGINT` | Không | INDEX | |
| `aggregate_version` | `INTEGER` | Có | | Bắt buộc cho service request state |
| `payload` | `JSONB` | Không | | Internal payload đã kiểm soát |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `PUBLISHED`, `FAILED` |
| `attempt_count` | `SMALLINT` | Không | `0` | |
| `available_at` | `TIMESTAMPTZ` | Không | INDEX | Retry schedule |
| `published_at` | `TIMESTAMPTZ` | Có | | |
| `last_error` | `TEXT` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Partial index publisher: `(available_at, id) WHERE status IN ('PENDING','FAILED')`.

### `inbox_messages`

Redis Pub/Sub consumer dedup.

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `consumer` | `VARCHAR(100)` | Không | | Handler/consumer name |
| `message_id` | `UUID` | Không | | Event ID |
| `status` | `VARCHAR(20)` | Không | | `PROCESSING`, `PROCESSED`, `FAILED` |
| `processed_at` | `TIMESTAMPTZ` | Có | | |
| `last_error` | `TEXT` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(consumer, message_id)`.

### `idempotency_keys`

HTTP command dedup cho tạo order, accept offer, complete, settlement, refund và withdrawal.

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `actor_type` | `VARCHAR(20)` | Không | | `USER`, `PROVIDER`, `SYSTEM` |
| `actor_key` | `VARCHAR(100)` | Không | | User ID/provider/system stable key |
| `user_id` | `BIGINT` | Có | FK `users` | Null cho provider/system |
| `scope` | `VARCHAR(80)` | Không | | Command name |
| `key` | `VARCHAR(191)` | Không | | Client/provider key |
| `request_hash` | `VARCHAR(64)` | Không | | Chặn reuse key với payload khác |
| `status` | `VARCHAR(20)` | Không | | `PROCESSING`, `COMPLETED`, `FAILED` |
| `response_code` | `SMALLINT` | Có | | |
| `response_body` | `JSONB` | Có | | Response an toàn để replay |
| `resource_type` | `VARCHAR(50)` | Có | | |
| `resource_id` | `BIGINT` | Có | | |
| `expires_at` | `TIMESTAMPTZ` | Có | INDEX | Cleanup non-financial keys |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(actor_type, actor_key, scope, key)`. Với actor USER, application kiểm tra `actor_key` khớp `user_id`.

### `webhook_receipts`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `provider` | `VARCHAR(30)` | Không | | `SEPAY` |
| `provider_event_id` | `VARCHAR(191)` | Không | | |
| `signature_valid` | `BOOLEAN` | Không | | |
| `payload` | `JSONB` | Không | | Đã lọc dữ liệu nhạy cảm |
| `status` | `VARCHAR(20)` | Không | | `RECEIVED`, `PROCESSED`, `IGNORED`, `FAILED` |
| `processed_at` | `TIMESTAMPTZ` | Có | | |
| `created_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(provider, provider_event_id)`.

## 8. Ma trận quyền database-facing

| Thao tác | Customer/Driver | SUPPORT | ADMIN |
|---|---:|---:|---:|
| Xem ticket của mình/được giao | Có | Có | Có |
| Ghi user-facing ticket message | Có | Có | Có |
| Ghi internal note | Không | Có | Có |
| Xem snapshot vị trí cuối liên quan ticket | Không mặc định | Khi ticket được giao | Có audit |
| Sửa wallet ledger trực tiếp | Không | Không | Không; phải tạo adjustment command |
| Phê duyệt adjustment/withdrawal | Không | Không | Có |
| Duyệt/khóa tài xế hoặc user | Không | Không | Có |
| Thay đổi pricing/system setting | Không | Không | Có |
