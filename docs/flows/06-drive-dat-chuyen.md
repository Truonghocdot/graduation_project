# Flow 06 - Drive: Báo giá và đặt chuyến

## Mục tiêu

Khách chọn điểm đón/điểm đến, nhận danh sách loại xe và giá, sau đó xác nhận chuyến để hệ thống tìm tài xế.

## Tiền điều kiện

- Khách đã đăng nhập và tài khoản `ACTIVE`.
- Điểm đón/điểm đến nằm trong vùng phục vụ.
- Số hành khách không vượt sức chứa của loại xe.

## Dữ liệu đầu vào

- Điểm đón: pin, địa chỉ hiển thị, ghi chú/cổng đón.
- Điểm đến: pin, địa chỉ hiển thị.
- Loại xe và số hành khách.
- Phương thức thanh toán phần còn lại: `WALLET` hoặc `CASH`.
- Voucher/mã giảm giá tùy chọn.
- Thời điểm: `NOW` hoặc `SCHEDULED` kèm `scheduled_at` hợp lệ.

## Luồng A - Báo giá

1. Khách chọn Drive, đặt pin đón và điểm đến.
2. API chuẩn hóa địa chỉ, kiểm tra vùng phục vụ và lấy route/ETA từ Goong API.
3. Pricing engine áp dụng giá cơ sở đến 3 km theo `vehicle_type.unique_key`; phần vượt ngưỡng tính theo `price_per_extra_km`, rồi trả các lựa chọn xe đủ sức chứa cùng giá dự kiến, ETA và breakdown VND.
4. Server kiểm tra voucher và tính `customer_payable = gross_fare - voucher_discount` nhưng chỉ đánh dấu dùng khi khách xác nhận booking.
5. Server lưu mỗi `Quote` với route/pricing snapshot và `expires_at`.
6. Client hiển thị giá gộp, giảm giá, số còn phải trả, loại giá và phương thức `WALLET/CASH`.

## Luồng B - Xác nhận chuyến

1. Khách chọn loại xe và kiểm tra điểm đón.
2. Client gửi `quote_id`, payment method (`WALLET` hoặc `CASH`) và idempotency key.
3. Server kiểm tra quote thuộc user, còn hạn, chưa dùng và dữ liệu thanh toán hợp lệ.
4. Nếu có voucher, server tạo `VoucherRedemption` ở `USED` và một `DiscountTransaction`. Với `WALLET`, server trừ ngay `customer_payable` bằng bút toán `CUSTOMER_PAYMENT`; với `CASH`, không trừ tiền khách.
5. Trong transaction, server tạo `RideBooking` ở `SEARCHING_DRIVER` cho chuyến ngay hoặc `SCHEDULED` cho chuyến đặt trước, lưu payment/discount reference, đánh dấu quote đã dùng và ghi outbox.
6. Client nhận booking code và trạng thái tương ứng.
7. Chuyến ngay phát `RIDE_SEARCH_REQUESTED`; chuyến đặt trước được scheduler phát event vào thời điểm matching được cấu hình.

## Quy tắc giá

- Quote lưu `currency = VND`, các giá trị `float`, `gross_fare`, khoảng cách/thời gian dự kiến, rule giá đã áp dụng, phụ phí, surge nếu có, `voucher_discount` và `customer_payable`; tiền được làm tròn về đơn vị VND tại boundary.
- Rule chi tiết do admin cấu hình và được snapshot vào quote.
- Xe máy mặc định 18.000 VND đến 3 km và 5.000 VND/km vượt ngưỡng.
- Ô tô có giá cơ sở cấu hình theo loại, ví dụ 28.000 hoặc 32.000 VND đến 3 km, và đơn giá vượt ngưỡng ví dụ 10.000 VND/km.
- Nếu lưu giá thực tế sau chuyến, UI và receipt phải tách `estimated_total` và `final_total`; phần tăng không được thu thêm từ khách mà được ghi thành adjustment theo chính sách.
- Thay đổi điểm đến sau khi có tài xế là một command riêng, phải re-route và hiển thị giá mới để khách xác nhận.
- Không thay quote cũ tại chỗ; tạo revision/snapshot mới để audit.
- Payment method không được thay đổi sau khi booking được tạo.

## Nhánh lỗi

| Tình huống | Xử lý |
|---|---|
| Pin đón không an toàn/không thể tiếp cận | Gợi ý điểm đón hợp lệ gần nhất |
| Ngoài vùng phục vụ | Không tạo quote |
| Quote hết hạn | Yêu cầu báo giá lại |
| Không đủ sức chứa | Ẩn/từ chối loại xe không phù hợp |
| Lịch đặt trước không hợp lệ | Từ chối thời điểm quá gần/quá xa theo cấu hình |
| Voucher không hợp lệ/hết lượt | Yêu cầu bỏ hoặc chọn voucher khác |
| Ví không đủ số dư khả dụng | Yêu cầu nạp thêm hoặc đổi sang `CASH` |
| Xác nhận gửi lặp | Trả booking đã tạo theo idempotency key |

## Sự kiện

- `RIDE_QUOTE_CREATED`
- `RIDE_BOOKING_CREATED`
- `RIDE_SEARCH_REQUESTED`
- `VOUCHER_USED`
- `CUSTOMER_WALLET_DEBITED`

## Tiêu chí nghiệm thu

- Quote và booking lưu snapshot route/giá nhất quán.
- Quote hết hạn/đã dùng không tạo booking mới.
- Hai request cùng idempotency key không tạo hai booking, bút toán trừ ví hoặc discount transaction.
- Booking dùng `WALLET` chỉ được tạo khi trừ tiền thành công; booking `CASH` không trừ tiền khách.
- Không có bút toán trừ ví hoặc chiết khấu tài xế khi tài xế nhận chuyến.
- Mỗi chuyến chỉ có một điểm đón và một điểm đến.
