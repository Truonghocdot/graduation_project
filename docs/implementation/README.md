# Implementation phases

> Baseline: business v1.0, database v0.1

Dự án được triển khai theo vertical slice: mỗi phase phải có database, API, authorization, test và event cần thiết trước khi chuyển sang phase kế tiếp.

| Phase | Trạng thái | Phạm vi |
|---:|---|---|
| 0 | `COMPLETED` | Business flow, decision log, database document và migrations |
| 1 | `COMPLETED` | Phone authentication, role, device, Sanctum token và reset password |
| 2 | `COMPLETED` | Driver onboarding, documents, vehicle catalog, approval và online eligibility |
| 3 | `PENDING` | Goong adapter, service area, pricing rule và quote Delivery/Drive |
| 4 | `PENDING` | Tạo request, đặt lịch, stops, wallet debit và voucher usage |
| 5 | `PENDING` | Matching batch, offer, assignment, Redis GEO và realtime rooms |
| 6 | `PENDING` | Delivery pickup/delivery/return/COD và Drive arrive/start/complete |
| 7 | `PENDING` | Settlement, platform fee, top-up SePay, withdrawal và refund |
| 8 | `PENDING` | Rating, support, incident, chat, notification và admin operations |
| 9 | `PENDING` | End-to-end hardening, security review, seed/demo data và deployment guide |

## Phase 1 - Identity/Auth

### Deliverables

- User model phone-first, email chỉ là hồ sơ.
- Role `CUSTOMER`, `DRIVER`, `SUPPORT`, `ADMIN`.
- Đăng ký và OTP xác minh số điện thoại lần đầu.
- Resend OTP với rate limit.
- Đăng nhập số điện thoại + mật khẩu.
- Sanctum bearer token theo device/app.
- `/me`, logout current device.
- Quên mật khẩu, verify OTP, reset token một lần và thu hồi access token cũ.
- Contract `PhoneOtpSender`; local/testing dùng mã cấu hình, production bắt buộc SMS adapter thật.

### Definition of Done

1. API validation và normalization số Việt Nam.
2. OTP chỉ lưu hash, có expiry/max attempts và resend vô hiệu mã cũ.
3. Response forgot password không làm lộ tài khoản tồn tại.
4. User chưa verify/suspended không đăng nhập được.
5. Token/device được tạo và thu hồi đúng phạm vi.
6. Feature tests cho happy path và failure paths chính.
7. Pint, PHPStan và affected tests pass.

## Nguyên tắc chuyển phase

- Không triển khai UI/mobile trước khi API contract phase tương ứng ổn định.
- Không dùng Redis làm nguồn dữ liệu chuẩn.
- Không thêm provider ngoài hệ thống khi chưa có contract/fake để test.
- Không chuyển phase nếu migration hoặc test của phase hiện tại còn lỗi.
- Thay đổi quyết định business phải cập nhật flow, database document và implementation phase trước code.

## Phase 2 - Driver onboarding

### Deliverables

- Hồ sơ tài xế `DRAFT -> PENDING_REVIEW -> APPROVED/REJECTED/SUSPENDED`.
- Upload giấy tờ vào private storage và endpoint tải file có authorization.
- Catalog loại xe, phương tiện, chọn phương tiện active và capability Delivery/Drive.
- Checklist bắt buộc cho giấy tờ cá nhân và phương tiện trước khi submit.
- API admin danh sách/chi tiết/duyệt/từ chối/khóa tài xế.
- Khi duyệt: cấp role `DRIVER`, kích hoạt capability và tạo wallet VND số dư 0.
- Driver app login chỉ dành cho hồ sơ `APPROVED`.
- Online/offline kiểm tra hồ sơ, xe, giấy tờ, capability, wallet và assignment active.
- Chỉ lưu snapshot vị trí cuối cùng khi tài xế online.

### Definition of Done

1. Applicant không thể sửa hồ sơ khi đang review/đã duyệt.
2. User khác không truy cập/xóa được giấy tờ hoặc phương tiện.
3. Admin role bắt buộc cho review và suspension.
4. Giấy tờ hết hạn hoặc checklist thiếu không được submit/approve/online.
5. Ví âm, thiếu capability hoặc active assignment chặn online.
6. Feature tests, full suite, Pint và PHPStan pass.
