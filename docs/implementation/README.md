# Implementation Plan

> Baseline: business v1.0, database v0.1, API v1
>
> Mục tiêu: hoàn thành một hệ thống chạy end-to-end cho đồ án. Business scale, RTO/RPO, benchmark tải lớn và disaster recovery nâng cao nằm ngoài phạm vi nghiệm thu.

## 1. Ownership

| Thành phần | Sở hữu | Không sở hữu |
|---|---|---|
| `worker/` | Database, domain logic, state machine, API `/api/v1`, auth, policy, queue/outbox, transaction, audit | Socket connection tần suất cao, UI mobile |
| `worker/app/Filament/` | Site quản trị: duyệt, cấu hình, giám sát, support, điều chỉnh có audit | Logic nghiệp vụ riêng; luôn gọi Action/Service dùng chung với API |
| `service/` | Socket.IO gateway, Redis presence/last location/geo index, RabbitMQ consumer/publisher, room/event delivery | Nguồn dữ liệu chuẩn, payment, state transition, quyền admin |
| `mobile/client/` | UI/state/API client của khách, wallet/voucher, Delivery/Drive, support | Tính giá chuẩn, quyết định trạng thái, ghi database |
| `mobile/driver/` | UI/state/API client tài xế, onboarding, offer, location, thực hiện chuyến/đơn | Matching winner, settlement, wallet ledger, quyền admin |

### Luồng code bắt buộc

Mỗi capability triển khai theo thứ tự sau:

1. `worker`: schema, model, enum, Action/Service, API contract và test.
2. `Filament`: Resource/Page/Widget/Action dùng lại cùng Service, policy và audit.
3. `service`: chỉ thêm consumer/publisher/socket/Redis khi worker event contract đã ổn định.
4. `mobile/client`: gọi API và subscribe event đã version hóa.
5. `mobile/driver`: gọi API và subscribe event đã version hóa.
6. Chạy contract/integration test giữa các thành phần trước khi đóng phase.

Không viết nghiệp vụ độc lập trong `service`, client hoặc driver app. Nếu UI cần một thao tác mới, thêm command/API ở worker trước.

## 2. Trạng thái hiện tại

| Phase | Trạng thái | Ghi chú |
|---:|---|---|
| 0 | `COMPLETED` | Business docs, database docs, migrations và seed nền |
| 1 | `COMPLETED` | Phone auth, OTP, Sanctum, role, device, reset password |
| 2 | `COMPLETED` | Driver onboarding API và Filament admin review/catalog đã hoàn tất |
| 3 | `PENDING` | Catalog, Goong adapter, service area, pricing và quote |
| 4 | `PENDING` | Tạo Delivery/Drive request, lịch, payer, wallet debit, voucher |
| 5 | `PENDING` | Matching, offer, assignment, Redis GEO và realtime service |
| 6 | `PENDING` | Delivery/Drive execution và bằng chứng hoàn tất |
| 7 | `PENDING` | Settlement, top-up SePay, withdrawal, refund, COD reconciliation |
| 8 | `PENDING` | Support, rating, incident, chat, notification và admin operations |
| 9 | `PENDING` | Customer app integration |
| 10 | `PENDING` | Driver app integration |
| 11 | `PENDING` | End-to-end hardening, demo seed và deployment guide |

## 3. Phase 0 - Foundation

### Worker

- Khóa PostgreSQL schema, migrations, FK, partial indexes, enum và seed role/catalog.
- Giữ `personal_access_tokens`, jobs, outbox/inbox và idempotency.
- Chuẩn hóa `DB_CONNECTION=pgsql`, config/cache/queue.

### Filament

- Xác nhận panel `/admin`, auth middleware, role gate và layout.
- Chưa tạo resource nghiệp vụ ở phase này.

### Service/mobile

- Chưa viết nghiệp vụ. Chỉ thống nhất event envelope, API base URL và environment contract.

### Gate

- `migrate:fresh` trên database test pass.
- Schema contract test, PHPStan và Pint pass.

## 4. Phase 1 - Identity/Auth

### Worker code

| Việc | Vị trí |
|---|---|
| Phone normalize/OTP/password | `app/Support/PhoneNumber.php`, `app/Services/Auth/` |
| Auth API | `app/Http/Controllers/Api/V1/Auth/`, `app/Http/Requests/Api/V1/Auth/` |
| User/role/device | `app/Models/User.php`, `Role.php`, `UserDevice.php`, `app/Enums/` |
| API resource | `app/Http/Resources/Api/V1/UserResource.php` |
| Routes | `routes/api.php` |
| Feature tests | `tests/Feature/PhoneAuthenticationTest.php` |

### Filament code

- Chưa cần business resource.
- Admin login/panel phải dùng user status và role `ADMIN` ở gate chung trước khi mở các phase review.

### Service/mobile

- Chưa triển khai Socket.IO nghiệp vụ.
- Sau khi API ổn định, client/driver thêm API client auth, secure token storage và logout.

### Gate

- Register -> OTP -> token -> `/me` -> logout -> reset password pass.
- User chưa verify/suspended không login được.

## 5. Phase 2 - Driver Onboarding

### Worker code đã có

| Capability | Vị trí |
|---|---|
| Draft/application/document/vehicle | `app/Services/Driver/DriverOnboardingService.php` |
| Approve/reject/suspend + role/wallet | `app/Services/Driver/DriverReviewService.php` |
| Online/offline eligibility | `app/Services/Driver/DriverAvailabilityService.php` |
| API controllers | `app/Http/Controllers/Api/V1/Driver/`, `Admin/`, `Catalog/` |
| Request/resource/middleware | `app/Http/Requests/Api/V1/Driver/`, `Admin/`, `app/Http/Resources/`, `EnsureUserHasRole` |
| Tests | `tests/Feature/DriverOnboardingTest.php`, `AdminDriverReviewTest.php`, `DriverAvailabilityTest.php` |

### Filament code đã triển khai

Các Resource/Page trong `worker/app/Filament/`:

1. `DriverProfileResource`: table filter theo `DRAFT/PENDING_REVIEW/APPROVED/REJECTED/SUSPENDED`, detail hồ sơ, documents, vehicles, last location.
2. `DriverApplicationReviewPage`: approve/reject với reason bắt buộc, gọi `DriverReviewService`, ghi audit.
3. `VehicleTypeResource`: CRUD catalog, capability Delivery/Drive, sức chứa/giới hạn.
4. Relation managers documents/vehicles/capabilities: xem dữ liệu và download private file qua authorized action.

Filament action không được tự `DB::table(...)->update()` để bỏ qua domain service. Resource dùng policy `ADMIN`, form validation và notification kết quả.

### Service/mobile

- Chưa cần matching/realtime.
- Customer app có thể chỉ hiển thị trạng thái hồ sơ khi API contract ổn định.
- Driver app chỉ làm onboarding UI sau khi Filament review flow đã chạy được bằng resource test.

### Gate

- Admin có thể duyệt toàn bộ hồ sơ từ Filament mà không gọi thủ công API.
- Driver chỉ login `DRIVER_APP` sau approve.
- Hồ sơ/document/vehicle của user khác trả `404`.
- Admin REST API không được mở; nghiệp vụ quản trị chỉ đi qua Filament và domain service.

## 6. Phase 3 - Catalog, Goong và Pricing

### Worker

- `app/Contracts/Maps/MapProvider.php`, `app/Services/Maps/GoongMapProvider.php`.
- Config Goong trong `config/services.php`, secrets trong `.env`, timeout/retry/cache.
- `PricingService`: base distance, minimum fare, extra km, driver rate, VND rounding.
- Quote API/resource, service area validation và snapshot route/pricing.
- Tests dùng fake map provider, không gọi Goong thật.

### Filament

- `PricingRuleResource`, `ServiceAreaResource`, `SystemSetting` pricing tab.
- Effective date/version, không sửa rule đã được quote sử dụng.
- Preview quote bằng service dùng chung, audit mọi thay đổi.

### Service/mobile

- Chưa cần business socket.
- Client/driver chưa hiển thị quote cho đến khi contract có fixture/test.

### Gate

- Fake Goong -> route snapshot -> quote đúng.
- Giá xe máy/ô tô, extra km, voucher preview và float tolerance có test.

## 7. Phase 4 - Request và Payment Intent

### Worker

- `Quote -> ServiceRequest` cho Delivery/Drive, stops, payer type, schedule.
- Wallet debit bằng ledger transaction; voucher tạo `DiscountTransaction` riêng.
- Payment method bất biến sau khi tạo.
- API `/delivery/orders`, `/rides/bookings`, cancellation trước assignment.

### Filament

- `ServiceRequestResource` read-only/operational: filter status, payer, payment method, scheduled time.
- Không cho admin sửa trực tiếp amount/status; override là Action có reason/audit.

### Service/mobile

- Service chưa quyết định state; chỉ nhận outbox sau khi worker commit.
- Customer app tích hợp quote/create/cancel sau khi API contract pass.
- Driver app chưa nhận offer.

### Gate

- Wallet debit atomic, voucher usage idempotent, CASH không debit.
- Schedule dispatch state và cancellation test pass.

## 8. Phase 5 - Matching và Realtime

### Worker

- Matching command/job, offer batch, lock assignment, optimistic version.
- Outbox events: `OFFER_CREATED`, `OFFER_EXPIRED`, `DRIVER_ASSIGNED`, state changes.
- Redis GEO/presence interface và RabbitMQ message contract.

### Filament

- `MatchingMonitorPage`: request đang search, batch, offer outcome, assignment.
- Admin reassign/cancel là action có policy, transaction và audit.

### Service code

| Việc | Vị trí |
|---|---|
| HTTP health/config | `service/src/server.ts` hoặc `service/src/http/` |
| Socket.IO auth/rooms | `service/src/realtime/` |
| Redis presence/GEO | `service/src/presence/` |
| RabbitMQ consumer/publisher | `service/src/messaging/` |
| Event envelope/schema | `service/src/contracts/` |
| Unit/integration tests | `service/src/**/*.test.ts` hoặc `service/test/` |

Service nhận event từ worker, phát room event, cập nhật presence/location TTL và trả ACK. Nó không tạo assignment hoặc quyết định winner.

### Client/driver

- Customer app subscribe booking room.
- Driver app nhận offer, accept/decline qua HTTPS API; Socket chỉ hiển thị offer/expiry.
- Cả hai reconnect bằng snapshot API nếu mất event.

### Gate

- Hai driver accept đồng thời chỉ có một assignment.
- Event duplicate/out-of-order không làm UI lùi state.
- Reconnect và unauthorized room test pass.

## 9. Phase 6 - Delivery/Drive Execution

### Worker

- Delivery: arriving pickup, pickup proof, in delivery, delivered, return revision, COD ledger.
- Drive: arriving, arrived, start, destination revision, complete.
- Complete command idempotent; payment/settlement chuyển phase 7.
- Evidence private storage và incident state.

### Filament

- Live operations board cho request/assignment/incident.
- Manual intervention actions gọi service, bắt reason, audit.
- Không expose private evidence bằng public URL.

### Service

- Location ingest 1,5 giây, chỉ snapshot cuối PostgreSQL + Redis TTL.
- Booking room location/ETA, push fallback, chat room nếu phase 8 đã sẵn sàng.

### Client/driver

- Customer app: tracking, cancel, delivery/ride status, evidence view.
- Driver app: navigation state, pickup/arrive/start/complete, cash confirmation, COD.

### Gate

- State transition hợp lệ và không skip state.
- Giao/chuyến hoàn tất tạo đúng evidence/payment event.

## 10. Phase 7 - Settlement và Finance

### Worker

- Settlement service: wallet/cash, driver rate, platform fee, voucher payment breakdown.
- SePay VietQR webhook, top-up dedup, wallet ledger.
- Withdrawal manual, refund/reversal, COD reconciliation.

### Filament

- Wallet/ledger read-only explorer.
- Settlement/withdrawal/refund approval actions.
- Không sửa balance trực tiếp; mọi adjustment tạo ledger transaction và audit.

### Service/mobile

- Service chỉ broadcast payment status sau outbox commit.
- Customer app: top-up, wallet balance, voucher, receipt.
- Driver app: earnings, negative wallet block, withdrawal request/status.

### Gate

- Ledger debit/credit cân bằng.
- Webhook/settlement/refund/withdrawal idempotent.
- Voucher không tạo wallet credit.

## 11. Phase 8 - Support và Admin Operations

### Worker

- Ticket, rating, incident, audit, notification persistence.
- Policies cho customer/driver/support/admin và private evidence.

### Filament

- Ticket queue/assignment/priority.
- Incident/SOS review.
- Rating moderation.
- User/driver suspension và audit timeline.

### Service/mobile

- Service chat rooms, notification/push fallback và unread counters.
- Customer/driver chat, support ticket và incident reporting UI.

### Gate

- Support không sửa ledger trực tiếp.
- Admin action có reason/audit.
- User không liên quan không đọc ticket/chat/evidence.

## 12. Phase 9-10 - Mobile integration

Mobile chỉ bắt đầu sau khi worker contract và service event fixture của phase tương ứng đã pass.

### `mobile/client/`

- `lib/core/network/`: API client, auth interceptor, error mapping.
- `lib/features/auth/`: login/register/OTP/reset.
- `lib/features/catalog/`: vehicle types.
- `lib/features/delivery/`, `lib/features/drive/`: quote/request/tracking.
- `lib/features/wallet/`, `lib/features/support/`: finance/support.
- `lib/core/realtime/`: Socket.IO client/reconnect/snapshot sync.

### `mobile/driver/`

- `lib/core/network/`, `lib/core/realtime/`: shared contract patterns.
- `lib/features/auth/`, `lib/features/onboarding/`: profile/document/vehicle.
- `lib/features/availability/`: online/offline/location permission.
- `lib/features/offers/`: offer list, accept/decline/expiry.
- `lib/features/delivery/`, `lib/features/drive/`: execution commands/evidence.
- `lib/features/earnings/`, `lib/features/support/`: settlement/withdrawal/support.

Mỗi app cần test API parsing, auth expiry, reconnect, permission và offline command retry; không đặt pricing/matching/settlement logic trong Dart.

## 13. Phase 11 - Hardening và demo

### Worker/Filament

- Seed demo deterministic cho roles, vehicle types, pricing, admin, customer, approved driver.
- API/Filament permission matrix, audit review, rate-limit review.
- OpenAPI/API contract export từ routes/resources.

### Service

- Health/readiness, RabbitMQ retry/DLQ, Redis TTL, structured logs và correlation ID.

### Mobile

- Build debug/release theo môi trường, API URL config, smoke test Android/iOS.

### Gate cuối

- Chạy full test worker/service/mobile.
- Demo được các luồng: auth -> driver approval -> quote -> matching -> execution -> settlement.
- Không còn endpoint/field undocumented trong [API Contract](../api/README.md).

## 14. Quy tắc làm việc theo phase

- Mỗi PR/commit chỉ thuộc một phase và ghi rõ component: `worker`, `filament`, `service`, `client`, `driver`.
- Contract thay đổi phải cập nhật database docs/API docs trước code consumer.
- Filament không copy domain logic từ API; service không ghi PostgreSQL business state.
- Không merge client/driver feature nếu API test và service fixture chưa có.
- Một phase chỉ chuyển `COMPLETED` khi tất cả track bắt buộc của phase đã đạt gate; API xong nhưng Filament thiếu thì dùng `BACKEND_DONE_ADMIN_PENDING`.
