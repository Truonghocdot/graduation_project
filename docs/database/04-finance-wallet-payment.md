# Wallet, payment, voucher và COD

## Nguyên tắc ledger

`wallets.balance` là số dư cache để đọc nhanh. Nguồn tái dựng là `ledger_transactions + ledger_entries`. Mỗi ledger transaction đã `POSTED` phải có tổng debit và credit bằng nhau trong tolerance sau làm tròn VND.

Các system account tối thiểu:

- `SYSTEM_BANK_CLEARING`: tiền nạp/rút qua ngân hàng.
- `SYSTEM_CUSTOMER_PAYMENT_CLEARING`: tiền ví khách đã trừ chờ quyết toán.
- `SYSTEM_PLATFORM_REVENUE`: phí nền tảng.
- `SYSTEM_REFUND_CLEARING`: khoản hoàn/điều chỉnh nếu cần.

Voucher không có ledger account và không tạo wallet entry.

## 1. `ledger_accounts`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `owner_type` | `VARCHAR(20)` | Không | | `USER`, `SYSTEM` |
| `owner_user_id` | `BIGINT` | Có | FK `users` | Bắt buộc với owner USER |
| `code` | `VARCHAR(80)` | Không | UNIQUE | System code hoặc generated wallet code |
| `account_type` | `VARCHAR(30)` | Không | | `WALLET_LIABILITY`, `CLEARING`, `REVENUE`, `EXPENSE` |
| `currency` | `CHAR(3)` | Không | `VND` | |
| `status` | `VARCHAR(20)` | Không | `ACTIVE` | `ACTIVE`, `FROZEN`, `CLOSED` |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

CHECK owner: `USER` yêu cầu `owner_user_id`; `SYSTEM` yêu cầu null.

## 2. `wallets`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `user_id` | `BIGINT` | Không | FK `users` | |
| `ledger_account_id` | `BIGINT` | Không | UNIQUE FK `ledger_accounts` | |
| `currency` | `CHAR(3)` | Không | `VND` | |
| `balance` | `DOUBLE PRECISION` | Không | `0` | Cache số dư sau entry cuối |
| `reserved_withdrawal_amount` | `DOUBLE PRECISION` | Không | `0` | Tiền dành cho withdrawal pending/approved |
| `status` | `VARCHAR(20)` | Không | `ACTIVE` | `ACTIVE`, `FROZEN`, `CLOSED` |
| `version` | `INTEGER` | Không | `1` | Optimistic lock |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(user_id, currency)`. `available_balance = balance - reserved_withdrawal_amount`. Ví khách không được commit balance âm. Ví tài xế có thể âm sau settlement nhưng driver mất eligibility nhận đơn khi `balance < 0`.

## 3. `ledger_transactions` và `ledger_entries`

### `ledger_transactions`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `transaction_type` | `VARCHAR(40)` | Không | INDEX | `TOP_UP`, `CUSTOMER_PAYMENT`, `DRIVER_EARNING`, `PLATFORM_FEE`, `WITHDRAWAL`, `REFUND`, `REVERSAL`, `MANUAL_ADJUSTMENT` |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `POSTED`, `REVERSED`, `FAILED` |
| `reference_type` | `VARCHAR(40)` | Không | INDEX | `PAYMENT`, `SETTLEMENT`, `TOP_UP`, ... |
| `reference_id` | `BIGINT` | Không | INDEX | ID domain, application validates |
| `idempotency_key` | `VARCHAR(191)` | Không | UNIQUE | |
| `correlation_id` | `UUID` | Không | INDEX | |
| `metadata` | `JSONB` | Có | | Provider/ref/reason, không chứa secret |
| `posted_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `ledger_entries`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `ledger_transaction_id` | `BIGINT` | Không | FK, INDEX | |
| `ledger_account_id` | `BIGINT` | Không | FK, INDEX | |
| `direction` | `VARCHAR(10)` | Không | | `DEBIT`, `CREDIT` |
| `amount` | `DOUBLE PRECISION` | Không | | Positive, rounded VND |
| `balance_after` | `DOUBLE PRECISION` | Có | | Chỉ cần cho user wallet account |
| `created_at` | `TIMESTAMPTZ` | Không | | |

CHECK `amount > 0`. Ledger transaction chỉ chuyển `POSTED` sau khi tất cả entry và wallet cache update trong cùng DB transaction.

### Bút toán mẫu

| Nghiệp vụ | Debit | Credit |
|---|---|---|
| Nạp ví qua SePay | `SYSTEM_BANK_CLEARING` | Wallet account của user |
| Khách trả bằng ví khi tạo request | Wallet account của khách | `SYSTEM_CUSTOMER_PAYMENT_CLEARING` |
| Quyết toán phần khách trả cho tài xế | `SYSTEM_CUSTOMER_PAYMENT_CLEARING` | Wallet account của tài xế |
| Thu phí nền tảng | Wallet account của tài xế | `SYSTEM_PLATFORM_REVENUE` |
| Hoàn tiền ví | Clearing/system account phù hợp | Wallet account của khách |
| Rút tiền hoàn tất | Wallet account của tài xế | `SYSTEM_BANK_CLEARING` |

Voucher/discount không xuất hiện trong ledger. Với CASH không có entry cho tiền mặt khách đưa trực tiếp; chỉ có entry phí nền tảng trừ ví tài xế sau hoàn tất.

## 4. `payments`

Một payment/service request, tạo cùng order/booking.

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `service_request_id` | `BIGINT` | Không | UNIQUE FK | |
| `payer_type` | `VARCHAR(20)` | Không | | `ORDERER`, `RECIPIENT` |
| `payer_user_id` | `BIGINT` | Có | FK `users`, INDEX | WALLET bắt buộc có user; cash recipient chưa đăng ký có thể null |
| `method` | `VARCHAR(20)` | Không | | `WALLET`, `CASH` |
| `status` | `VARCHAR(30)` | Không | `PENDING` | `PENDING`, `READY`, `SETTLEMENT_PENDING`, `SETTLED`, `FAILED`, `CANCELLED`, `PARTIALLY_REFUNDED`, `REFUNDED` |
| `currency` | `CHAR(3)` | Không | `VND` | |
| `gross_fare` | `DOUBLE PRECISION` | Không | | Snapshot quote/final |
| `voucher_discount` | `DOUBLE PRECISION` | Không | `0` | |
| `customer_payable` | `DOUBLE PRECISION` | Không | | |
| `customer_payment_ledger_id` | `BIGINT` | Có | FK `ledger_transactions` | Chỉ WALLET |
| `cash_collected` | `DOUBLE PRECISION` | Không | `0` | Xác nhận khi driver complete |
| `cash_confirmed_by` | `BIGINT` | Có | FK `users` | Driver user |
| `cash_confirmed_at` | `TIMESTAMPTZ` | Có | | |
| `version` | `INTEGER` | Không | `1` | Concurrency |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

CHECK:

- `method = WALLET` yêu cầu `payer_user_id` và `customer_payment_ledger_id` trước `READY`.
- `method = CASH` yêu cầu `customer_payment_ledger_id IS NULL`.
- Payment method/payer type không đổi sau khi request được tạo.
- `payments.payer_type` phải khớp `delivery_orders.payer_type` đối với Delivery; Drive luôn `ORDERER`.

## 5. `settlements`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `payment_id` | `BIGINT` | Không | UNIQUE FK | Một settlement chính/payment |
| `assignment_id` | `BIGINT` | Không | FK `assignments` | Driver nhận thu nhập |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | Denormalized để query |
| `status` | `VARCHAR(30)` | Không | `PENDING` | `PENDING`, `PROCESSING`, `SETTLED`, `FAILED` |
| `driver_rate` | `DOUBLE PRECISION` | Không | | Snapshot, mặc định 0.88 |
| `driver_gross_earning` | `DOUBLE PRECISION` | Không | | |
| `cash_collected` | `DOUBLE PRECISION` | Không | `0` | Copy có kiểm soát từ payment |
| `wallet_payment_amount` | `DOUBLE PRECISION` | Không | `0` | Phần thực trả qua wallet |
| `voucher_payment_amount` | `DOUBLE PRECISION` | Không | `0` | Chỉ breakdown, không ledger credit |
| `platform_fee_debited` | `DOUBLE PRECISION` | Không | `0` | |
| `settlement_adjustment` | `DOUBLE PRECISION` | Không | `0` | Có thể âm/dương |
| `driver_net_earning` | `DOUBLE PRECISION` | Không | | Kết quả sau fee/adjustment |
| `earning_ledger_id` | `BIGINT` | Có | FK `ledger_transactions` | WALLET earning nếu có |
| `platform_fee_ledger_id` | `BIGINT` | Có | FK `ledger_transactions` | |
| `settled_at` | `TIMESTAMPTZ` | Có | | |
| `failure_code` | `VARCHAR(50)` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Invariant sau làm tròn/tolerance:

```text
cash_collected
+ wallet_payment_amount
+ voucher_payment_amount
- platform_fee_debited
+ settlement_adjustment
= driver_net_earning
```

Chặng hoàn Delivery tạo thêm settlement revision ở bảng bên dưới hoặc mở rộng thành `settlement_revisions` khi implement; không sửa settlement đã `SETTLED`.

### `settlement_revisions`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `settlement_id` | `BIGINT` | Không | FK, INDEX | |
| `revision_number` | `SMALLINT` | Không | | |
| `revision_type` | `VARCHAR(30)` | Không | | `RETURN_FARE`, `SUPPORT_ADJUSTMENT` |
| `gross_amount`, `platform_fee`, `driver_net_amount` | `DOUBLE PRECISION` | Không | | |
| `ledger_transaction_id` | `BIGINT` | Có | FK | Nếu chạm wallet |
| `reason_code` | `VARCHAR(50)` | Không | | |
| `approved_by` | `BIGINT` | Có | FK `users` | |
| `created_at` | `TIMESTAMPTZ` | Không | | |

Unique: `(settlement_id, revision_number)`.

## 6. Voucher và discount

### `vouchers`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `code` | `VARCHAR(50)` | Không | UNIQUE | Uppercase normalized |
| `name` | `VARCHAR(150)` | Không | | |
| `discount_type` | `VARCHAR(20)` | Không | | `PERCENT`, `FIXED` |
| `discount_value` | `DOUBLE PRECISION` | Không | | % hoặc VND |
| `max_discount_amount` | `DOUBLE PRECISION` | Có | | Bắt buộc với PERCENT nếu cấu hình |
| `service_scope` | `VARCHAR(20)` | Có | | Null, `DELIVERY`, `DRIVE` |
| `minimum_order_amount` | `DOUBLE PRECISION` | Không | `0` | |
| `total_usage_limit` | `INTEGER` | Có | | |
| `per_user_usage_limit` | `INTEGER` | Có | | |
| `max_restore_count` | `INTEGER` | Có | | Giới hạn hoàn lượt |
| `used_count` | `INTEGER` | Không | `0` | Cache counter |
| `starts_at`, `ends_at` | `TIMESTAMPTZ` | Không | INDEX | |
| `is_active` | `BOOLEAN` | Không | INDEX | |
| `created_by` | `BIGINT` | Không | FK `users` | |
| `created_at`, `updated_at`, `deleted_at` | `TIMESTAMPTZ` | Có/Không | | |

### `voucher_redemptions`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `voucher_id` | `BIGINT` | Không | FK, INDEX | |
| `user_id` | `BIGINT` | Không | FK, INDEX | Người chọn voucher |
| `service_request_id` | `BIGINT` | Không | UNIQUE FK | Một voucher/request |
| `status` | `VARCHAR(20)` | Không | `USED` | `USED`, `RESTORED` |
| `discount_amount` | `DOUBLE PRECISION` | Không | | Snapshot |
| `used_at` | `TIMESTAMPTZ` | Không | | Ngay khi tạo request |
| `restored_at` | `TIMESTAMPTZ` | Có | | |
| `restore_reason_code` | `VARCHAR(50)` | Có | | |
| `restore_count` | `SMALLINT` | Không | `0` | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `discount_transactions`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `payment_id` | `BIGINT` | Không | UNIQUE FK | Một discount/payment trong MVP |
| `voucher_redemption_id` | `BIGINT` | Không | UNIQUE FK | |
| `amount` | `DOUBLE PRECISION` | Không | | `voucher_payment_amount` source |
| `status` | `VARCHAR(20)` | Không | `APPLIED` | `APPLIED`, `RESTORED` |
| `applied_at` | `TIMESTAMPTZ` | Không | | |
| `restored_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Không có `wallet_id` hoặc `ledger_transaction_id` trong bảng discount.

## 7. Nạp và rút ví

### `wallet_topups`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `wallet_id` | `BIGINT` | Không | FK, INDEX | |
| `amount` | `DOUBLE PRECISION` | Không | | |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `COMPLETED`, `FAILED`, `EXPIRED` |
| `vietqr_reference` | `VARCHAR(100)` | Không | UNIQUE | Nội dung/mã đối chiếu |
| `vietqr_payload` | `TEXT` | Không | | Data generate QR |
| `sepay_transaction_id` | `VARCHAR(100)` | Có | UNIQUE | Dedup webhook |
| `provider_payload` | `JSONB` | Có | | Payload đã lọc |
| `ledger_transaction_id` | `BIGINT` | Có | UNIQUE FK | Có khi completed |
| `expires_at` | `TIMESTAMPTZ` | Không | INDEX | |
| `completed_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `withdrawal_requests`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `wallet_id` | `BIGINT` | Không | FK, INDEX | Driver wallet |
| `driver_bank_account_id` | `BIGINT` | Không | FK | |
| `amount` | `DOUBLE PRECISION` | Không | | Trong min/max |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `APPROVED`, `COMPLETED`, `REJECTED`, `FAILED`, `CANCELLED` |
| `requested_at` | `TIMESTAMPTZ` | Không | | Trong time window |
| `handled_by` | `BIGINT` | Có | FK `users` | Admin thực hiện thủ công |
| `handled_at` | `TIMESTAMPTZ` | Có | | |
| `bank_transfer_reference` | `VARCHAR(191)` | Có | UNIQUE | Mã chuyển khoản thực tế |
| `ledger_transaction_id` | `BIGINT` | Có | UNIQUE FK | |
| `reason_code` | `VARCHAR(50)` | Có | | Reject/fail |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

Không cho tạo withdrawal nếu wallet balance âm, không đủ số dư, ngoài khung giờ hoặc amount ngoài min/max.

Khi tạo request, transaction khóa wallet và tăng `reserved_withdrawal_amount`. Khi chuyển khoản thủ công thành công, post `WITHDRAWAL` rồi giảm cả balance/reserved; khi request failed/rejected/cancelled, chỉ giải phóng reserved. Nhờ đó hai request không thể rút cùng một số dư.

## 8. `refunds`

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `public_id` | `UUID` | Không | UNIQUE | |
| `payment_id` | `BIGINT` | Không | FK, INDEX | |
| `amount` | `DOUBLE PRECISION` | Không | | Không vượt tiền khách thực trả |
| `method` | `VARCHAR(20)` | Không | | `WALLET`, `CASH_MANUAL` |
| `status` | `VARCHAR(20)` | Không | `PENDING` | `PENDING`, `COMPLETED`, `FAILED` |
| `reason_code` | `VARCHAR(50)` | Không | | |
| `requested_by`, `approved_by` | `BIGINT` | Có | FK `users` | |
| `ledger_transaction_id` | `BIGINT` | Có | FK | Chỉ wallet |
| `evidence` | `JSONB` | Có | | Cash manual proof |
| `completed_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

## 9. COD

### `cod_accounts`

Một row cho Delivery ứng COD.

| Cột | Kiểu | Null | Key/default | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `delivery_order_id` | `BIGINT` | Không | UNIQUE FK | |
| `driver_profile_id` | `BIGINT` | Không | FK, INDEX | Driver ứng tiền |
| `cod_amount` | `DOUBLE PRECISION` | Không | | Snapshot |
| `status` | `VARCHAR(30)` | Không | `PENDING_ADVANCE` | `PENDING_ADVANCE`, `ADVANCED`, `COLLECTED`, `RETURNING`, `RETURNED`, `DISPUTED`, `CLOSED` |
| `advanced_amount` | `DOUBLE PRECISION` | Không | `0` | Đã ứng người gửi |
| `collected_amount` | `DOUBLE PRECISION` | Không | `0` | Thu lại người nhận |
| `advanced_at`, `collected_at`, `closed_at` | `TIMESTAMPTZ` | Có | | |
| `created_at`, `updated_at` | `TIMESTAMPTZ` | Không | | |

### `cod_transactions`

| Cột | Kiểu | Null | Key | Ý nghĩa |
|---|---|---:|---|---|
| `id` | `BIGINT` | Không | PK | |
| `cod_account_id` | `BIGINT` | Không | FK, INDEX | |
| `transaction_type` | `VARCHAR(30)` | Không | | `ADVANCE_TO_SENDER`, `COLLECT_FROM_RECIPIENT`, `RETURN_RECOVERY`, `MANUAL_ADJUSTMENT` |
| `amount` | `DOUBLE PRECISION` | Không | | |
| `actor_user_id` | `BIGINT` | Có | FK `users` | |
| `evidence` | `JSONB` | Có | | |
| `idempotency_key` | `VARCHAR(191)` | Không | UNIQUE | |
| `occurred_at` | `TIMESTAMPTZ` | Không | | |
| `created_at` | `TIMESTAMPTZ` | Không | | |

COD transaction là sổ nghiệp vụ riêng, không cộng vào driver earning/payment amount.
