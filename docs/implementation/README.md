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
| 3 | `COMPLETED` | Catalog, Goong adapter, service area, pricing và quote |
| 4 | `COMPLETED` | Delivery/Drive request, lịch, payer, payment intent, cancellation và customer app |
| 5 | `COMPLETED` | Matching, offer, assignment, Redis GEO, realtime service và mobile offer flow |
| 6 | `COMPLETED` | Delivery/Drive execution, private evidence, COD và location snapshot |
| 7 | `COMPLETED` | Settlement, SePay top-up, withdrawal, refund và finance admin |
| 8 | `COMPLETED` | Support, rating, incident, chat, notification persistence, Filament operations và mobile support actions |
| 9 | `COMPLETED` | Customer auth/session, Delivery/Drive, wallet, support và reconnect snapshot |
| 10 | `COMPLETED` | Driver onboarding, availability/GPS, offer/execution, evidence, earning và support |
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
| API controllers | `app/Http/Controllers/Api/V1/Driver/`, `Catalog/`, `QuoteController.php` |
| Request/resource/middleware | `app/Http/Requests/Api/V1/Driver/`, `Quote/`, `app/Http/Resources/`, `EnsureUserHasRole` |
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

## 6. Phase 3 - Catalog, Goong và Pricing — `COMPLETED`

### Worker

- `app/Contracts/Maps/MapProvider.php`, `app/Services/Maps/GoongMapProvider.php`.
- Config Goong trong `config/services.php`, secrets trong `.env`, timeout/retry/cache.
- `PricingService`: base distance, minimum fare, extra km, driver rate, VND rounding.
- `POST /api/v1/quotes` và `QuoteResource`: service area validation, vehicle capacity, voucher preview, route/pricing snapshot.
- Tests dùng fake map provider, không gọi Goong thật; lỗi upstream trả `MAP_ROUTE_UNAVAILABLE`.

### Filament

- `PricingRuleResource`, `ServiceAreaResource`, `SystemSetting` pricing tab đã triển khai.
- Effective date/version, không sửa rule đã được quote sử dụng.
- Preview quote bằng service dùng chung, audit mọi thay đổi.

### Service/mobile

- Chưa cần business socket trong phase này.
- Client/driver chưa tích hợp UI quote; API contract và fixture/test đã sẵn sàng cho Phase 4.

### Gate

- Fake Goong -> route snapshot -> quote đúng.
- Giá xe máy/ô tô, extra km, voucher preview, service area, capacity, timeout và float tolerance có test.

## 7. Phase 4 - Request và Payment Intent — `COMPLETED`

### Worker

- `Quote -> ServiceRequest` cho Delivery/Drive, hai stops, payer type và schedule đã triển khai.
- Wallet debit/refund bằng ledger transaction cân bằng; voucher tạo `VoucherRedemption` và `DiscountTransaction` riêng.
- Payment method bất biến sau khi tạo; CASH không ghi debit.
- API `/delivery/orders`, `/rides/bookings`, cancellation trước assignment dùng `Idempotency-Key`.
- Status history và transactional outbox được ghi cùng aggregate.

### Filament

- `ServiceRequestResource` read-only/operational đã triển khai: filter status, payer, payment method, scheduled time.
- Không cho admin sửa trực tiếp amount/status/payment; mọi can thiệp vận hành sau assignment để Phase 5+ có policy và audit riêng.

### Service/mobile

- Service chưa quyết định state; chỉ nhận outbox sau khi worker commit.
- `mobile/client` đã tích hợp login, catalog xe, quote, create Delivery/Drive, WALLET/CASH và cancel.
- Driver app chưa nhận offer.

### Gate

- Wallet debit atomic/cân bằng, voucher usage idempotent, CASH không debit.
- Schedule state, owner isolation, cancellation refund/restore và outbox test pass.

## 8. Phase 5 - Matching và Realtime — `COMPLETED`

### Worker

- Commands `matching:dispatch`, `matching:expire-offers` và `outbox:publish` đã triển khai, được scheduler chạy mỗi 5s/10s/1s.
- `matching:dispatch` kích hoạt request `SCHEDULED` đến hạn trước khi tạo offer batch.
- Offer batch lọc theo presence, capability, vehicle, status và COD; accept khóa request trước khi tạo assignment.
- Outbox events: `OFFER_CREATED`, `OFFER_EXPIRED`, `DRIVER_ASSIGNED`, matching restart/cancel.
- Redis GEO/presence interface, TTL và event envelope cho Redis Pub/Sub/RabbitMQ.
- Driver offer API và customer snapshot API là nguồn khôi phục state sau reconnect.

### Filament

- `MatchingMonitor` đã triển khai cho request đang search/assigned, batch, offer count và assignment.
- Admin restart matching/cancel là Filament action có reason, transaction và audit.

### Service code

| Việc | Vị trí |
|---|---|
| HTTP health/config | `service/src/server.ts` hoặc `service/src/http/` |
| Socket.IO auth/rooms | `service/src/realtime/` |
| Redis presence/GEO | `service/src/presence/` |
| RabbitMQ consumer/publisher | `service/src/messaging/` |
| Event envelope/schema | `service/src/contracts/` |
| Unit/integration tests | `service/src/**/*.test.ts` hoặc `service/test/` |

Service đã có health endpoint, Redis/RabbitMQ consumer, Socket.IO room authorization và event dedupe/version guard. Nó không tạo assignment hoặc quyết định winner.

### Client/driver

- Customer app refresh snapshot từ worker sau khi tạo request.
- Driver app login, lấy offer và accept/decline qua HTTPS API có idempotency; polling là reconnect fallback.
- Socket service chỉ phát push event/expiry; state cuối lấy từ worker API.

### Gate

- Request row lock và partial unique index bảo đảm chỉ một assignment active.
- Event duplicate/out-of-order không phát lại hoặc làm UI lùi state.
- Reconnect snapshot, offer ownership và unauthorized room tests pass.

## 9. Phase 6 - Delivery/Drive Execution — `COMPLETED`

### Worker

- Delivery state machine: arriving pickup, at pickup, picked up, in delivery và delivered.
- Drive state machine: arriving, arrived, in trip và trip ended.
- Transition command idempotent, kiểm tra assigned driver, thứ tự state và geofence/reason.
- Evidence lưu private kèm checksum/metadata; owner, assigned driver và admin mới tải được.
- COD advance/collection dùng ledger nghiệp vụ riêng, không tính vào driver earning.
- Location endpoint chỉ lưu snapshot cuối PostgreSQL và phát Redis location event.

### Filament

- Live operations board hiển thị request/assignment/evidence/payment.
- Manual restart/cancel gọi service, bắt reason và audit.
- Evidence chỉ mở qua authenticated download action.

### Service

- Realtime service nhận `worker.location` và phát vào đúng booking room.
- Chỉ snapshot cuối được persist; không tạo timeline vị trí.

### Client/driver

- Customer app polling authoritative snapshot, hiển thị trạng thái và receipt settlement.
- Driver app có action theo state, cash/COD confirmation và polling fallback.

### Gate

- State transition không skip state; command lặp không tạo history/settlement trùng.
- Delivery proof policy, private evidence, geofence reason, cash và COD có test.

## 10. Phase 7 - Settlement và Finance — `COMPLETED`

### Worker

- Settlement atomic cho WALLET/CASH: driver rate, platform fee, wallet/cash/voucher breakdown.
- Voucher chỉ nằm trong settlement breakdown, không tạo wallet credit.
- VietQR top-up request và SePay webhook secret/dedup đã triển khai.
- Withdrawal reserve/complete/reject và refund WALLET/CASH_MANUAL đã triển khai.
- Mọi ledger transaction `POSTED` được kiểm tra debit = credit.

### Filament

- Wallet/ledger, payment và settlement resources read-only.
- Withdrawal complete/reject, refund và bank-account verification gọi domain service có audit.
- Không có form sửa balance trực tiếp.

### Service/mobile

- Service broadcast payment/top-up/withdrawal/refund sau outbox commit.
- Customer app có wallet balance, top-up VietQR và settlement receipt.
- Driver app có earning wallet và withdrawal request với tài khoản đã admin verify.

### Gate

- Ledger debit/credit cân bằng và wallet cache khớp entry cuối.
- Webhook, settlement, top-up, withdrawal và refund có test idempotency/limit.
- Voucher không tạo wallet credit; COD không đi vào settlement earning.

## 11. Phase 8 - Support và Admin Operations - `COMPLETED`

### Worker

- Models/enums/services tại `app/Models/`, `app/Enums/`, `app/Services/Support/`, `app/Services/Chat/`, `app/Services/Notification/`.
- API controllers/requests/resources tại `app/Http/Controllers/Api/V1/`, `app/Http/Requests/Api/V1/Support/`, `app/Http/Resources/Api/V1/`.
- Ticket, rating, incident, chat message, notification persistence đã có idempotency phù hợp và outbox event.
- Policies kiểm tra participant/assignee; attachment ticket và service evidence lưu private.
- Support không có quyền sửa ledger; suspend user chỉ admin và bắt buộc reason + audit.

### Filament

- `SupportTicketResource`: queue unassigned/assigned, claim, resolve, priority/status filter, message và finance snapshot.
- `IncidentResource`: review/resolve SOS và incident với audit.
- `RatingResource`: visible/hidden/flagged moderation với reason.
- `UserResource`: admin-only suspension/revoke token và audit.
- Support chỉ truy cập ticket queue của mình; finance/catalog/user resources bị chặn bởi role gate.

### Service/mobile

- `service/src/realtime/roomGateway.ts` và `src/auth/workerAuthorizer.ts`: user room xác thực từ `/me`, booking room authorization, notification event, dedupe/version guard.
- `mobile/client/lib/api/booking_api.dart` và `mobile/driver/lib/api/driver_api.dart`: chat, ticket, incident, rating, notification/unread API contract.
- Customer/driver UI có thao tác Chat, Hỗ trợ, SOS, Đánh giá và đánh dấu thông báo.
- HTTPS API là fallback khi socket/push không sẵn sàng; notification payload không chứa body chat hay dữ liệu tài chính.

### Gate

- Support không sửa ledger trực tiếp.
- Admin action có reason/audit.
- User không liên quan không đọc ticket/chat/evidence.
- Worker tests: `PhaseEightSupportTest`, `PhaseEightChatNotificationTest`, `PhaseEightAuthorizationTest`, `FilamentSupportOperationsTest`.
- Realtime tests: `service/src/realtime/roomGateway.test.ts`; mobile API/UI tests và `flutter analyze` pass.

## 12. Phase 9-10 - Mobile integration - `COMPLETED`

Hai app gọi API worker để quyết định trạng thái; Socket.IO chỉ báo có thay đổi và mỗi lần reconnect đều nạp lại snapshot qua HTTPS. Bản cài giữ token và ID thiết bị ngẫu nhiên trong secure storage, không đưa token vào log hay URL.

### Phase 9 - `mobile/client/`

- `lib/api/booking_api.dart`: login/register/verify/resend OTP, quên/đặt lại mật khẩu, catalog, quote, tạo/hủy Delivery/Drive, ví/nạp tiền, ticket/reply, chat, SOS, rating và notification.
- `lib/api/session_store.dart`: giữ token, ID bản cài và ID yêu cầu gần nhất trong secure storage; logout gọi worker thu hồi token rồi xóa phiên cục bộ, lỗi `401` cũng xóa phiên.
- `lib/api/booking_realtime.dart`: nhận `booking:event`/`notification:event`, join lại booking room khi reconnect; `lib/booking_app.dart` đồng bộ lại snapshot qua API và có polling fallback.
- `lib/api/api_transport_web.dart`: trả cả JSON lỗi `401/422` cho UI xử lý; không coi HTTP lỗi nghiệp vụ là lỗi kết nối.
- UI có đăng ký/xác minh/reset, chọn xe, báo giá/thanh toán, chuyến gần nhất, wallet/top-up, ticket, chat, SOS, rating và notification.

### Phase 10 - `mobile/driver/`

- `lib/api/driver_api.dart`: phiên onboarding bằng `CUSTOMER_APP` khi chưa được duyệt; đăng nhập lại `DRIVER_APP` sau duyệt. Có hồ sơ, xe, multipart giấy tờ/evidence, catalog, availability, location, offer, execution, ngân hàng/rút tiền và support.
- `lib/api/device_location.dart`: xin quyền vị trí, lấy GPS thật; không gửi tọa độ điểm đón/điểm trả giả làm vị trí hiện tại. Manifest Android và Info.plist iOS khai báo quyền dùng khi app mở.
- `lib/main.dart`: màn hình hồ sơ trước duyệt; sau duyệt có bật/tắt nhận chuyến, GPS heartbeat khi rảnh, location snapshot khi đang có assignment, bằng chứng pickup/delivery, xác nhận cash/COD, thu nhập và ngân hàng.
- `lib/api/driver_realtime.dart` và `lib/api/session_store.dart`: join lại booking room, nạp lại offer sau reconnect, lưu phiên và xóa khi logout/`401`.
- Worker `DriverAvailabilityService` đã sửa presence: chỉ ghi Redis khi online, xóa Redis khi offline. Heartbeat online gia hạn TTL 15 giây; `/driver/location` chỉ dùng cho assignment active.

### Gate đã kiểm tra

- Customer: API/widget tests gồm auth, error `401`, khôi phục chuyến, reconnect, form 320px, retry chat giữ nguyên `client_message_id`; `flutter analyze` sạch và build web thành công.
- Driver: API/widget tests gồm onboarding, upload, availability, permission denied, reconnect, retry SOS giữ nguyên `Idempotency-Key`; `flutter analyze` sạch và APK debug build thành công.
- Command đang chờ giữ nguyên key khi người dùng retry trong phiên đang mở; sau relaunch app lấy lại trạng thái worker trước khi cho thao tác mới. Chưa có hàng đợi command offline bền vững hoặc push provider: thuộc hardening Phase 11.
- iOS chưa thể smoke/build trên máy Windows này; cần kiểm tra trên macOS cùng cấu hình signing/Keychain trong Phase 11. Business pricing/matching/settlement không nằm trong Dart.

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
