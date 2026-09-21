# Flow 10 - Hủy và xử lý sự cố

## Mục tiêu

Xử lý hủy, no-show, giao thất bại và sự cố an toàn theo trạng thái thực tế; bảo vệ khách, tài xế và tài sản đang được vận chuyển.

## Ma trận hủy đề xuất

Phiên bản hiện tại **không áp dụng phí hủy hoặc phí chờ tự động**. Các tình huống tranh chấp/ngoại lệ được chuyển qua site hỗ trợ và chat hỗ trợ.

| Dịch vụ | Trạng thái | Khách hủy | Tài xế hủy | Hành động hệ thống |
|---|---|---|---|---|
| Cả hai | `SCHEDULED`/`SEARCHING_DRIVER` | Cho phép, không phí | Không áp dụng | Dừng scheduler/matching, hoàn ví và khôi phục voucher theo policy |
| Cả hai | `DRIVER_ASSIGNED`/đang đến | Cho phép, không phí | Cho phép, bắt buộc reason | Hủy hoặc reassign; hoàn ví/khôi phục voucher nếu kết thúc |
| Delivery | `AT_PICKUP` trước nhận hàng | Cho phép, không phí tự động | Có reason, cần hỗ trợ nếu tranh chấp | Đóng assignment, hoàn payment/khôi phục voucher |
| Delivery | Từ `PICKED_UP` | Không hủy thông thường | Không hủy thông thường | Tạo incident/quy trình hoàn hàng |
| Drive | `DRIVER_ARRIVED` | Cho phép hoặc `NO_SHOW` sau ngưỡng | Có reason | Không thu phí; ghi nhận và giải phóng tài xế |
| Drive | `IN_TRIP` | Không hủy thông thường | Không hủy thông thường | Dừng chuyến sớm qua incident |

## Luồng A - Khách hủy

1. Khách yêu cầu hủy và chọn lý do.
2. Server kiểm tra actor, trạng thái và version hiện tại.
3. Client gửi command với idempotency key và lý do hủy.
4. Trong transaction, server chuyển `CANCELLED`, đóng scheduler/offers/assignment và ghi outbox.
5. Hệ thống đưa tài xế về trạng thái phù hợp, hoàn khoản ví khách đã trừ bằng bút toán đảo, khôi phục lượt voucher theo campaign và thông báo hai phía.

## Luồng B - Tài xế hủy trước khi bắt đầu dịch vụ

1. Tài xế chọn mã lý do; lý do nghiêm trọng cho phép thêm mô tả/bằng chứng.
2. Server kiểm tra booking/order chưa `PICKED_UP/IN_TRIP`.
3. Server đóng assignment và ghi cancellation record.
4. Nếu khách vẫn muốn tiếp tục và trạng thái còn cho phép tìm lại, hệ thống chuyển về `SEARCHING_DRIVER`; nếu không, chuyển `CANCELLED`.
5. Hệ thống ghi nhận tỷ lệ hủy/bỏ offer phục vụ vận hành nhưng không tự động thu phí tài xế.

## Luồng C - Drive no-show

1. Booking ở `DRIVER_ARRIVED`, tài xế đã ở geofence hoặc có ngoại lệ được audit.
2. Hết ngưỡng thời gian chờ vận hành, tài xế thử liên hệ qua chat in-app và số điện thoại trực tiếp.
3. Tài xế gửi `MARK_PASSENGER_NO_SHOW` cùng vị trí và contact-attempt evidence.
4. Server kiểm tra timer, chuyển `NO_SHOW`, hoàn tiền ví khách, khôi phục voucher theo policy và giải phóng tài xế; không thu phí tự động.
5. Khách được thông báo và có thể khiếu nại.

## Luồng D - Delivery giao thất bại/hoàn hàng

Trước `PICKED_UP`, đơn có thể kết thúc `DELIVERY_FAILED`. Sau `PICKED_UP`, hệ thống phải luôn biết kiện hàng đang ở đâu.

1. Tài xế báo không giao được, chọn reason và tải evidence.
2. Server giữ order ở trạng thái kiểm soát hàng, tạo `DeliveryIncident`.
3. Điều phối/chính sách chọn giao lại, đổi điểm giao hợp lệ hoặc hoàn về người gửi.
4. Nếu hoàn hàng, cập nhật chính `DeliveryOrder` sang status/list type hoàn hàng; lưu route, giá hoàn và proof dưới revision riêng, không tạo `ReturnTask` mới.
5. Tài xế xác nhận yêu cầu hoàn; hệ thống tính giá chặng hoàn và thu nhập sau tỷ lệ chiết khấu như một settlement revision.
6. Chỉ đóng incident khi có bằng chứng bàn giao lại người gửi hoặc quyết định xử lý của admin.

## Luồng E - SOS/sự cố an toàn

1. Khách hoặc tài xế bấm SOS/gọi hỗ trợ khẩn cấp.
2. Client gửi booking/order id, actor, vị trí gần nhất và loại sự cố.
3. Hệ thống tạo incident ưu tiên cao, lưu timeline và thông báo đội vận hành.
4. UI ưu tiên hướng dẫn liên hệ dịch vụ khẩn cấp địa phương; hệ thống không tuyên bố thay thế cơ quan khẩn cấp.
5. Nhân viên hỗ trợ chỉ được xem dữ liệu cần thiết theo quyền và mọi truy cập đều audit.
6. Kết quả chuyến/đơn và settlement được xử lý riêng sau khi an toàn đã được ưu tiên.

## Race condition

- Accept offer và cancel xảy ra cùng lúc: chỉ transition thắng lock/version có hiệu lực.
- Start trip/pickup và cancel cùng lúc: nếu start/pickup đã commit trước, cancel thông thường phải thất bại.
- Reassign job đến sau khi khách hủy: job kiểm tra trạng thái rồi no-op.
- Hủy gửi lặp: trả cancellation record cũ, không thu/hoàn lần hai.

## Sự kiện

- `BOOKING_CANCELLED_BY_CUSTOMER`
- `BOOKING_CANCELLED_BY_DRIVER`
- `DRIVER_REASSIGN_REQUESTED`
- `RIDE_PASSENGER_NO_SHOW`
- `DELIVERY_FAILED`
- `DELIVERY_RETURN_REQUESTED`
- `DELIVERY_RETURNING`
- `DELIVERY_RETURNED`
- `SAFETY_INCIDENT_REPORTED`

## Tiêu chí nghiệm thu

- Không thể hủy thông thường sau khi tài xế đã kiểm soát hàng hoặc chuyến đã bắt đầu.
- Hủy hoàn đúng khoản tiền ví đã trừ, khôi phục voucher đúng policy và không phát sinh phí tự động.
- Reassign không tạo hai assignment active.
- Delivery sau pickup luôn có owner/location/status cho kiện hàng.
- SOS tạo incident ngay cả khi dịch vụ realtime đang lỗi, thông qua HTTPS fallback.
