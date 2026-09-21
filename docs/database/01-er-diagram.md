# ERD tổng thể

ERD được tách theo domain để dễ đọc. Các bảng framework của Laravel/Sanctum/queue không xuất hiện trong sơ đồ.

## Identity, driver và catalog

```mermaid
erDiagram
    USERS ||--o{ USER_ROLES : has
    ROLES ||--o{ USER_ROLES : grants
    USERS ||--o{ USER_DEVICES : owns
    USERS ||--o{ PHONE_VERIFICATIONS : verifies
    USERS ||--o| DRIVER_PROFILES : becomes
    DRIVER_PROFILES ||--o{ DRIVER_DOCUMENTS : submits
    DRIVER_PROFILES ||--o{ VEHICLES : owns
    VEHICLE_TYPES ||--o{ VEHICLES : classifies
    VEHICLES ||--o{ DRIVER_DOCUMENTS : documented_by
    DRIVER_PROFILES ||--o{ DRIVER_SERVICE_CAPABILITIES : enables
    VEHICLE_TYPES ||--o{ DRIVER_SERVICE_CAPABILITIES : permits
    DRIVER_PROFILES ||--o| DRIVER_LAST_LOCATIONS : has
    DRIVER_PROFILES ||--o{ DRIVER_BANK_ACCOUNTS : links
    VEHICLE_TYPES ||--o{ PRICING_RULES : priced_by
```

## Booking, Delivery và Drive

```mermaid
erDiagram
    USERS ||--o{ QUOTES : requests
    VEHICLE_TYPES ||--o{ QUOTES : quoted_for
    QUOTES ||--o| SERVICE_REQUESTS : creates
    USERS ||--o{ SERVICE_REQUESTS : creates
    VEHICLE_TYPES ||--o{ SERVICE_REQUESTS : requires
    SERVICE_REQUESTS ||--|{ SERVICE_STOPS : contains
    SERVICE_REQUESTS ||--o| DELIVERY_ORDERS : delivery_detail
    SERVICE_REQUESTS ||--o| RIDE_BOOKINGS : ride_detail
    SERVICE_REQUESTS ||--o{ DRIVER_OFFERS : broadcasts
    DRIVER_PROFILES ||--o{ DRIVER_OFFERS : receives
    SERVICE_REQUESTS ||--o{ ASSIGNMENTS : assigned
    DRIVER_PROFILES ||--o{ ASSIGNMENTS : performs
    VEHICLES ||--o{ ASSIGNMENTS : uses
    SERVICE_REQUESTS ||--o{ SERVICE_STATUS_HISTORIES : transitions
    DELIVERY_ORDERS ||--o{ DELIVERY_RETURN_REVISIONS : returns
```

Mỗi `SERVICE_REQUESTS` phải có đúng một detail table theo `service_type`: `DELIVERY_ORDERS` hoặc `RIDE_BOOKINGS`. Mỗi request có đúng hai stop: `PICKUP` và `DROPOFF`.

## Finance

```mermaid
erDiagram
    USERS ||--o{ WALLETS : owns
    WALLETS ||--|| LEDGER_ACCOUNTS : backed_by
    LEDGER_TRANSACTIONS ||--|{ LEDGER_ENTRIES : contains
    LEDGER_ACCOUNTS ||--o{ LEDGER_ENTRIES : posts
    SERVICE_REQUESTS ||--|| PAYMENTS : paid_by
    USERS ||--o{ PAYMENTS : payer
    PAYMENTS ||--o| SETTLEMENTS : settles
    ASSIGNMENTS ||--o| SETTLEMENTS : earns
    VOUCHERS ||--o{ VOUCHER_REDEMPTIONS : redeemed
    USERS ||--o{ VOUCHER_REDEMPTIONS : uses
    PAYMENTS ||--o| DISCOUNT_TRANSACTIONS : discounted_by
    VOUCHER_REDEMPTIONS ||--o| DISCOUNT_TRANSACTIONS : creates
    WALLETS ||--o{ WALLET_TOPUPS : topped_up
    WALLETS ||--o{ WITHDRAWAL_REQUESTS : withdrawn
    DRIVER_BANK_ACCOUNTS ||--o{ WITHDRAWAL_REQUESTS : receives
    PAYMENTS ||--o{ REFUNDS : refunded
    DELIVERY_ORDERS ||--o| COD_ACCOUNTS : tracks
    COD_ACCOUNTS ||--o{ COD_TRANSACTIONS : records
```

`DISCOUNT_TRANSACTIONS` không tạo ledger entry và không liên kết trực tiếp với wallet. Chỉ payment bằng `WALLET`, phí nền tảng, top-up, withdrawal, refund và adjustment mới ghi ledger.

## Support, realtime và integration

```mermaid
erDiagram
    USERS ||--o{ RATINGS : writes
    SERVICE_REQUESTS ||--o{ RATINGS : receives
    ASSIGNMENTS ||--o{ RATINGS : identifies_driver
    USERS ||--o{ SUPPORT_TICKETS : opens
    SERVICE_REQUESTS ||--o{ SUPPORT_TICKETS : concerns
    SUPPORT_TICKETS ||--o{ SUPPORT_TICKET_MESSAGES : contains
    USERS ||--o{ SUPPORT_TICKET_MESSAGES : writes
    SERVICE_REQUESTS ||--o{ INCIDENTS : reports
    USERS ||--o{ INCIDENTS : reports
    SERVICE_REQUESTS ||--o{ CHAT_CONVERSATIONS : has
    ASSIGNMENTS ||--o| CHAT_CONVERSATIONS : opens
    CHAT_CONVERSATIONS ||--o{ CHAT_MESSAGES : contains
    USERS ||--o{ CHAT_MESSAGES : sends
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ AUDIT_LOGS : acts
    OUTBOX_EVENTS }o--|| SERVICE_REQUESTS : may_reference
```

Outbox và audit dùng `aggregate_type/reference_type + id` vì chúng tham chiếu nhiều domain. Đây là polymorphic reference có validation ở application layer, không phải foreign key tổng quát.

## Cardinality bắt buộc

| Quan hệ | Quy tắc |
|---|---|
| User - DriverProfile | Một user có tối đa một hồ sơ tài xế |
| Driver - active assignment | Tối đa một assignment `ACTIVE` |
| ServiceRequest - active assignment | Tối đa một assignment `ACTIVE` |
| ServiceRequest - Payment | Đúng một payment sau khi request được tạo |
| ServiceRequest - stops | Đúng một `PICKUP` và một `DROPOFF` |
| Payment - Settlement | Tối đa một settlement hiện hành |
| Payment - DiscountTransaction | Tối đa một voucher discount trong MVP |
| User - Wallet | Tối đa một wallet cho mỗi currency |
| Driver - LastLocation | Tối đa một snapshot, update/overwrite |
| DeliveryOrder - CodAccount | Tối đa một, chỉ khi `is_cod = true` |
| Assignment - ChatConversation | Tối đa một; reassign tạo conversation mới |
