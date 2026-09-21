# Tài liệu luồng nghiệp vụ Delivery & Drive

> Trạng thái: **Baseline v1.0**
>
> Cập nhật: **2026-09-21**

Thư mục này mô tả luồng nghiệp vụ dự kiến cho ứng dụng đặt giao hàng (Delivery) và đặt xe chở khách (Drive). Code hiện tại mới là scaffold, vì vậy tài liệu là baseline để thống nhất sản phẩm, thiết kế dữ liệu, API và test trước khi triển khai.

## Quy ước

- **Delivery**: tài xế nhận hàng tại điểm lấy và giao đến điểm nhận.
- **Drive**: tài xế đón khách và chở khách đến điểm đến.
- **Khách hàng**: người tạo đơn Delivery hoặc chuyến Drive.
- **Tài xế**: đối tác thực hiện đơn/chuyến.
- **Điều phối viên/Admin**: người giám sát, can thiệp và xử lý ngoại lệ.
- Các trạng thái và sự kiện viết bằng `UPPER_SNAKE_CASE` là mã ổn định dùng giữa database, API, queue và realtime.
- Mỗi thao tác làm thay đổi trạng thái phải có kiểm tra quyền, kiểm tra trạng thái hiện tại và cơ chế chống xử lý lặp.

## Thứ tự đọc và triển khai đề xuất

| STT | Tài liệu | Kết quả chính |
|---:|---|---|
| 0 | [Tổng quan dự án](./00-tong-quan.md) | Phạm vi, actor, kiến trúc và state machine tổng thể |
| 1 | [Tài khoản và xác thực](./flows/01-tai-khoan-va-xac-thuc.md) | Đăng ký, đăng nhập, phiên truy cập và khóa tài khoản |
| 2 | [Đăng ký đối tác tài xế](./flows/02-doi-tac-tai-xe.md) | Hồ sơ tài xế, phương tiện, xét duyệt và bật nhận cuốc |
| 3 | [Delivery - Tạo đơn](./flows/03-delivery-tao-don.md) | Báo giá và xác nhận đơn giao hàng |
| 4 | [Delivery - Ghép tài xế](./flows/04-delivery-ghep-tai-xe.md) | Tìm, mời, nhận và ghép tài xế |
| 5 | [Delivery - Giao nhận](./flows/05-delivery-giao-nhan.md) | Đến điểm lấy, lấy hàng, giao hàng, xác nhận hoàn tất |
| 6 | [Drive - Đặt chuyến](./flows/06-drive-dat-chuyen.md) | Báo giá và xác nhận chuyến chở khách |
| 7 | [Drive - Ghép tài xế](./flows/07-drive-ghep-tai-xe.md) | Tìm, mời, nhận và ghép tài xế |
| 8 | [Drive - Thực hiện chuyến](./flows/08-drive-thuc-hien-chuyen.md) | Đón khách, bắt đầu, theo dõi và kết thúc chuyến |
| 9 | [Ví lạnh, voucher và tiền mặt](./flows/09-thanh-toan-va-hoan-tien.md) | Nạp ví, trừ tiền, áp voucher, quyết toán và hoàn tiền |
| 10 | [Hủy và xử lý sự cố](./flows/10-huy-va-xu-ly-su-co.md) | Chính sách hủy, no-show, giao thất bại và SOS |
| 11 | [Đánh giá và khiếu nại](./flows/11-danh-gia-va-khieu-nai.md) | Rating, ticket hỗ trợ và điều chỉnh giao dịch |
| 12 | [Realtime và thông báo](./flows/12-realtime-va-thong-bao.md) | Vị trí, room, event, retry và fallback |
| - | [Decision log](./open-questions.md) | Toàn bộ quyết định phạm vi đã chốt |
| - | [Thiết kế database](./database/README.md) | ERD, data dictionary, constraint, index và migration plan |
| - | [Implementation phases](./implementation/README.md) | Thứ tự phát triển, phạm vi và tiêu chí hoàn tất từng phase |
| - | [API Contract v1](./api/README.md) | Danh sách endpoint Phase 1/2/3, auth, quote, request, response và lỗi |

## Phạm vi MVP

MVP đã chốt gồm:

- Hai ứng dụng mobile riêng: khách hàng và tài xế.
- Một điểm lấy/đón và một điểm giao/đến cho mỗi đơn hoặc chuyến.
- Hỗ trợ đặt ngay và đặt lịch trước.
- Loại xe, sức chứa/giới hạn và giá tối thiểu được admin cấu hình theo `unique_key`.
- Thanh toán bằng tiền mặt hoặc ví lạnh nội bộ; khách có thể áp dụng voucher giảm giá.
- Sau khi hoàn tất, tài xế nhận tiền mặt trực tiếp hoặc nhận phần thanh toán qua ví; phần voucher tài trợ được ghi vào khoản thanh toán của tài xế, không tự động ghi vào ví.
- Delivery hỗ trợ COD; tiền COD tách khỏi phí vận chuyển.
- Phí Delivery có thể do người tạo đơn hoặc người nhận thanh toán.
- Theo dõi tài xế theo thời gian thực sau khi ghép thành công.
- Admin có thể xem và can thiệp thủ công vào đơn/chuyến.

Ngoài phạm vi MVP: giao nhiều điểm, ghép nhiều đơn, đi chung xe, đấu giá giá cước, referral, loyalty, subscription và định giá động nâng cao.

Đây là đồ án hướng đến hệ thống hoạt động đúng và hoàn thành đầy đủ flow. RTO/RPO, disaster recovery, benchmark tải business và lưu lịch sử hành trình không phải tiêu chí nghiệm thu hiện tại.

## Definition of Done cho một flow

Một flow chỉ được coi là hoàn tất khi có đủ:

1. API contract và validation.
2. Transaction/state transition hợp lệ.
3. Authorization theo actor.
4. Event bất đồng bộ và realtime cần thiết.
5. Idempotency cho thao tác có thể gửi lại.
6. Log/audit cho thao tác quan trọng.
7. Test happy path, lỗi nghiệp vụ và race condition chính.
