# Constraint, index và migration plan

## 1. Database target

- PostgreSQL là database triển khai mục tiêu.
- Laravel connection key: `pgsql`.
- Charset/collation dùng mặc định UTF-8 của PostgreSQL.
- Redis phục vụ cache, presence, geo search và distributed lock bổ sung; transaction chuẩn vẫn ở PostgreSQL.
- Không yêu cầu PostGIS trong MVP vì không lưu route history; geo matching dùng Redis GEO.

## 2. Enum catalog

Các giá trị lưu dạng `VARCHAR`, được khai báo lại bằng PHP backed enum.

| Domain | Enum | Giá trị |
|---|---|---|
| User | `UserStatus` | `PENDING_VERIFICATION`, `ACTIVE`, `SUSPENDED`, `CLOSED` |
| Role | `RoleKey` | `CUSTOMER`, `DRIVER`, `SUPPORT`, `ADMIN` |
| Driver | `DriverReviewStatus` | `DRAFT`, `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `SUSPENDED` |
| Driver | `DriverAvailability` | `OFFLINE`, `ONLINE`, `OFFERED`, `BUSY` |
| Service | `ServiceType` | `DELIVERY`, `DRIVE` |
| Service | `BookingType` | `NOW`, `SCHEDULED` |
| Stop | `StopType` | `PICKUP`, `DROPOFF` |
| Offer | `OfferStatus` | `PENDING`, `ACCEPTED`, `DECLINED`, `EXPIRED`, `CANCELLED` |
| Assignment | `AssignmentStatus` | `ACTIVE`, `COMPLETED`, `CANCELLED`, `REPLACED` |
| Payment | `PaymentMethod` | `WALLET`, `CASH` |
| Payment | `PaymentStatus` | `PENDING`, `READY`, `SETTLEMENT_PENDING`, `SETTLED`, `FAILED`, `CANCELLED`, `PARTIALLY_REFUNDED`, `REFUNDED` |
| Voucher | `DiscountType` | `PERCENT`, `FIXED` |
| Voucher | `RedemptionStatus` | `USED`, `RESTORED` |
| Ledger | `LedgerDirection` | `DEBIT`, `CREDIT` |
| Ledger | `LedgerStatus` | `PENDING`, `POSTED`, `REVERSED`, `FAILED` |
| Ticket | `TicketStatus` | `OPEN`, `IN_REVIEW`, `WAITING_FOR_CUSTOMER`, `RESOLVED`, `REOPENED`, `CLOSED` |
| Withdrawal | `WithdrawalStatus` | `PENDING`, `APPROVED`, `COMPLETED`, `REJECTED`, `FAILED`, `CANCELLED` |

Delivery/Drive statuses dùng hai PHP enum riêng dù cùng lưu ở `service_requests.status`.

## 3. CHECK constraints quan trọng

Tên constraint phải ổn định để lỗi database có thể map thành domain error.

| Bảng | Constraint |
|---|---|
| `users` | `status != 'ACTIVE' OR phone_verified_at IS NOT NULL` |
| `quotes`, `service_requests` | `booking_type != 'SCHEDULED' OR scheduled_at IS NOT NULL` |
| `service_stops` | Latitude/longitude đúng range |
| `delivery_orders` | COD false => amount 0; COD true => amount > 0 |
| `ride_bookings` | `passenger_count > 0` |
| `pricing_rules` | Distance/fare/rate không âm; driver rate trong `[0,1]` |
| `wallets` | Currency không rỗng; customer non-negative; `reserved_withdrawal_amount >= 0` và không vượt số dư khả dụng lúc reserve |
| `ledger_entries` | `amount > 0`, direction hợp lệ |
| `payments` | Các amount không âm; WALLET/CASH reference nhất quán |
| `vouchers` | PERCENT nằm `(0,100]`; FIXED > 0; start < end |
| `ratings` | Score từ 1 đến 5; reviewer khác reviewee |
| `driver_last_locations` | JSON phải có lat/lng hợp lệ, validation chính tại service |

Cross-row invariants như ledger debit=credit, đúng detail table theo service type và đúng hai stops được enforce trong application service + feature test. Nếu cần chặt hơn sau MVP, thêm deferred constraint trigger PostgreSQL.

## 4. Unique và partial unique indexes

| Tên/index | Mục đích |
|---|---|
| `users_phone_unique` | Một tài khoản/số điện thoại chuẩn hóa |
| `user_roles_user_role_unique` | Không cấp role trùng |
| `vehicles_plate_number_unique` | Biển số không trùng |
| `vehicles_one_selected_per_driver` | Một phương tiện selected/driver (`WHERE is_selected AND deleted_at IS NULL`) |
| `pricing_rules_one_current_active` | Một rule active open-ended/service/vehicle |
| `service_stops_request_type_unique` | Một pickup và một dropoff/request |
| `service_requests_quote_unique` | Quote không tạo hai request |
| `assignments_one_active_per_request` | Một assignment active/request |
| `assignments_one_active_per_driver` | Một assignment active/driver |
| `status_history_request_version_unique` | Một transition/version |
| `payments_service_request_unique` | Một payment/request |
| `settlements_payment_unique` | Một settlement chính/payment |
| `wallets_user_currency_unique` | Một wallet/user/currency |
| `ledger_transactions_idempotency_unique` | Không ghi ledger lặp |
| `voucher_redemptions_request_unique` | Một voucher/request trong MVP |
| `discount_transactions_payment_unique` | Một discount/payment |
| `chat_conversations_assignment_unique` | Một conversation/assignment; driver mới không đọc chat cũ |
| `wallet_topups_sepay_transaction_unique` | Webhook SePay không cộng tiền hai lần |
| `chat_messages_conversation_client_unique` | Retry chat không tạo tin nhắn trùng |
| `inbox_consumer_message_unique` | Consumer idempotency |
| `webhook_provider_event_unique` | Webhook dedup |

SQL partial index chính:

```sql
CREATE UNIQUE INDEX vehicles_one_selected_per_driver
ON vehicles (driver_profile_id)
WHERE is_selected = true AND deleted_at IS NULL;

CREATE UNIQUE INDEX pricing_rules_one_current_active
ON pricing_rules (service_type, vehicle_type_id)
WHERE is_active = true AND effective_to IS NULL;

CREATE UNIQUE INDEX assignments_one_active_per_request
ON assignments (service_request_id)
WHERE status = 'ACTIVE';

CREATE UNIQUE INDEX assignments_one_active_per_driver
ON assignments (driver_profile_id)
WHERE status = 'ACTIVE';

CREATE INDEX outbox_publishable
ON outbox_events (available_at, id)
WHERE status IN ('PENDING', 'FAILED');

CREATE INDEX scheduled_service_requests_due
ON service_requests (scheduled_at, id)
WHERE status = 'SCHEDULED';
```

Laravel migrations tạo partial indexes bằng `DB::statement()` và có câu `DROP INDEX IF EXISTS` tương ứng trong `down()`.

## 5. Query indexes

Chỉ tạo index cho query path thật; tránh index mọi foreign key + status tổ hợp không dùng.

| Query path | Index đề xuất |
|---|---|
| Danh sách request của khách | `service_requests(created_by, created_at DESC)` |
| Scheduler | Partial `(scheduled_at, id)` status SCHEDULED |
| Matching offer pending | `driver_offers(driver_profile_id, status, expires_at)` |
| Batch của request | `driver_offers(service_request_id, batch_number, status)` |
| Active assignment | Hai partial unique index ở trên |
| Driver document hết hạn | `driver_documents(status, expires_at)` |
| Pricing rule hiện hành | `pricing_rules(service_type, vehicle_type_id, is_active, effective_from DESC)` |
| Wallet history | `ledger_entries(ledger_account_id, id DESC)` |
| Payment vận hành | `payments(status, updated_at)` |
| Withdrawal queue | `withdrawal_requests(status, requested_at)` |
| Ticket queue | `support_tickets(status, priority, created_at)` |
| Status timeline | `service_status_histories(service_request_id, version)` |
| Audit subject | `audit_logs(subject_type, subject_id, created_at DESC)` |
| Chat pagination | `chat_messages(chat_conversation_id, id DESC)` |
| Outbox publisher | Partial `outbox_publishable` |

Không index JSONB mặc định. Chỉ thêm GIN/expression index khi query thực tế cần filter một JSON property.

## 6. Transaction và lock recipes

### Tạo order/booking

1. Lock quote `FOR UPDATE`.
2. Validate `ACTIVE`, ownership, expiry.
3. Lock wallet/voucher cần dùng và validate balance/counter, nhưng chưa publish side effect ngoài database.
4. Insert service request, stops, detail và payment `PENDING` để có khóa tham chiếu.
5. Nếu WALLET: post `CUSTOMER_PAYMENT` ledger transaction, cập nhật payment reference/status.
6. Nếu voucher: insert redemption `USED` + discount transaction và cập nhật counter.
7. Mark quote `USED`; insert status history và outbox.
8. Commit; bất kỳ lỗi nào rollback toàn bộ request/payment/ledger/discount.

Idempotency record được lấy/khóa trước recipe. Request lặp trả resource cũ.

### Accept offer

1. Lock `driver_offers`, `service_requests`, `driver_profiles` theo thứ tự ổn định.
2. Validate offer pending/chưa hết hạn, request searching, driver online/wallet >= 0.
3. Insert assignment ACTIVE; partial unique indexes là lớp bảo vệ cuối.
4. Update request status/version, driver BUSY, close offers khác.
5. Insert status history + outbox; commit.

### Hoàn thành và settlement

1. Lock request, active assignment, payment, driver wallet.
2. Validate state và idempotency key.
3. Với CASH, command driver complete ghi `cash_collected`.
4. Tính settlement từ snapshot quote/discount/driver rate.
5. Post ledger cho wallet earning/platform fee/adjustment; voucher amount không tạo ledger entry.
6. Update request/payment/assignment/driver availability.
7. Insert status history + outbox; commit.

### SePay top-up webhook

1. Insert/lock `webhook_receipts` theo `(provider,event_id)`.
2. Xác minh chữ ký/nội dung và lock top-up + wallet.
3. Nếu đã completed, trả kết quả cũ.
4. Post TOP_UP ledger, update wallet/top-up/webhook receipt và outbox; commit.

### Driver location

- Redis update ở realtime service với TTL.
- PostgreSQL upsert một row `driver_last_locations`; sample mới chỉ thắng khi `captured_at > last_location_at`.
- Có thể write-throttle PostgreSQL nếu 1,5 giây/update tạo tải không cần thiết; vẫn chỉ giữ snapshot cuối.

### Withdrawal thủ công

1. Lock wallet; validate `available_balance`, min/max/time window và bank account.
2. Insert withdrawal PENDING, tăng `reserved_withdrawal_amount`; commit.
3. Admin chuyển khoản ngoài transaction database.
4. Command approve complete khóa withdrawal/wallet, post ledger, giảm balance và reserved.
5. Reject/fail/cancel chỉ giảm reserved. Tất cả command dùng idempotency key.

### Reassign và chat

1. Đóng assignment/chat conversation cũ trước khi tạo assignment mới.
2. Tạo conversation mới gắn `assignment_id` mới.
3. Driver mới không được query message của conversation cũ dù cùng service request.

## 7. Delete/update policy

| Nhóm bảng | Policy |
|---|---|
| Reference/catalog | Soft delete hoặc `is_active=false` |
| User/driver/vehicle | Soft delete/status; FK giữ lịch sử |
| Quote | Không xóa khi đã used; quote hết hạn có thể archive sau đồ án |
| Service request/detail/stops | Không hard delete |
| Offer | Không xóa; giữ outcome phục vụ rate |
| Ledger/payment/settlement/COD | Append/adjust/reverse, không update phá lịch sử và không delete |
| Ticket/chat/incident | Không delete trong MVP; attachment private |
| Outbox/inbox/idempotency | Có cleanup job theo retention kỹ thuật sau khi xử lý |
| Last location | Update đè; không có history cleanup |

## 8. Thứ tự migrations

Tên timestamp cụ thể do Artisan sinh; số thứ tự dưới đây là dependency order.

1. Chuyển `users` sang phone-first, thêm status/public UUID/soft delete; tạo phone verification/reset.
2. Roles, user roles, devices.
3. Driver profiles và bank accounts.
4. Vehicle types, vehicles, driver documents và capabilities.
5. Driver last locations.
6. Pricing rules, service areas, system settings.
7. Quotes.
8. Service requests, stops, Delivery/Ride detail.
9. Offers, assignments, status histories, return revisions.
10. Ledger accounts, wallets, ledger transactions/entries.
11. Payments, vouchers/redemptions/discounts, settlements/revisions.
12. Top-ups, withdrawals, refunds, COD accounts/transactions.
13. Ratings, support tickets/messages/attachments, incidents.
14. Chat conversations/messages, notifications.
15. Audit logs, outbox, inbox, idempotency keys, webhook receipts.
16. PostgreSQL partial indexes và CHECK constraints không biểu diễn trực tiếp bằng Blueprint.

Mỗi nhóm nên là migration nhỏ theo bounded context, không tạo một migration khổng lồ.

## 9. Seed data tối thiểu

### Roles

`CUSTOMER`, `DRIVER`, `SUPPORT`, `ADMIN`.

### Vehicle types

- `MOTORBIKE`: giá cơ sở tham chiếu 18.000 VND/3 km.
- `CAR_4_SEAT`: giá cơ sở tham chiếu 28.000 hoặc 32.000 VND/3 km theo cấu hình được chọn.

Không seed đồng thời hai pricing rule active cho cùng service + vehicle + effective window.

### System settings

- `cod.max_amount = 8000000`
- `location.interval_ms = 1500`
- `matching.offer_ttl_seconds`
- `matching.batch_size`
- `matching.scheduled_lead_minutes`
- `withdrawal.min_amount`, `withdrawal.max_amount`, `withdrawal.time_windows`
- `money.float_tolerance`
- `otp.expiry_seconds`, `otp.max_attempts`

### System ledger accounts

`SYSTEM_BANK_CLEARING`, `SYSTEM_CUSTOMER_PAYMENT_CLEARING`, `SYSTEM_PLATFORM_REVENUE`, `SYSTEM_REFUND_CLEARING`.

## 10. Chuyển đổi starter schema

Repo chưa có dữ liệu production, do đó ưu tiên chỉnh migration gốc trước khi triển khai thay vì viết data migration phức tạp. Nếu database local đã migrate, recreate database sau khi xác nhận không có dữ liệu cần giữ.

Các thay đổi cần thực hiện khi bắt đầu code:

1. Đổi `DB_CONNECTION=psql` thành `DB_CONNECTION=pgsql`.
2. Sửa `users`: email nullable, thêm phone unique/verified/status/public ID/deleted_at.
3. Thay `password_reset_tokens` theo email bằng `phone_password_reset_tokens`.
4. Cập nhật User model/factory/Fortify action theo phone-first login.
5. Giữ `personal_access_tokens` và các bảng queue.
6. Chạy migration trên PostgreSQL thật trong CI/dev; SQLite không kiểm chứng được partial index và hành vi PostgreSQL đầy đủ.

## 11. Checklist review migration

- Mọi FK có cùng type và delete policy rõ ràng.
- Mọi API resource có `public_id` unique.
- Mọi amount dùng `DOUBLE PRECISION`, round VND và test tolerance đúng baseline.
- Không có voucher FK trên wallet/ledger entry.
- Không có bảng location history.
- Active assignment có partial unique ở cả request và driver.
- Ledger/idempotency/provider reference có unique constraint.
- Migration `down()` xóa index/constraint trước bảng phụ thuộc.
- Seeder idempotent và không ghi đè pricing rule đã được sử dụng.
- Có feature test cho race condition create/accept/settle/webhook.
