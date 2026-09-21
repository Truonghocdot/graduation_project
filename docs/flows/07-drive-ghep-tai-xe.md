# Flow 07 - Drive: Ghép tài xế

## Mục tiêu

Ghép chuyến Drive với đúng một tài xế và phương tiện phù hợp, thông báo kết quả nhanh và an toàn cho cả khách lẫn tài xế.

## Tiền điều kiện và lọc ứng viên

- Booking ở `SEARCHING_DRIVER`; booking đặt trước chỉ vào trạng thái này khi scheduler kích hoạt matching.
- Tài xế `APPROVED + ONLINE`, có capability `DRIVE`.
- Phương tiện active đúng loại, đủ sức chứa và giấy tờ còn hạn.
- Tài xế không có assignment active; vị trí còn mới và nằm trong vùng tìm kiếm.
- Số dư ví tài xế lớn hơn hoặc bằng 0.

## Luồng chính

1. Matching job nhận `RIDE_SEARCH_REQUESTED` và xác minh booking vẫn đang tìm.
2. Hệ thống lấy ứng viên theo ETA đến điểm đón, sau đó ưu tiên tỷ lệ nhận cao/tỷ lệ hủy thấp và áp dụng rule fairness. Hiện không tự động suspension theo các tỷ lệ này.
3. Hệ thống tạo offer có thời hạn cho một batch nhỏ ứng viên theo cơ chế nhỏ giọt.
4. Tài xế nhận thông tin tối thiểu: khoảng cách đến điểm đón, hướng/quãng đường chuyến, loại xe, phương thức thanh toán và thu nhập dự kiến.
5. Tài xế nhận, từ chối hoặc bỏ qua offer. Offer hết hạn được ghi nhận là bỏ qua để tính tỷ lệ bỏ trôi; không mặc định coi là hủy chuyến.
6. Accept được xử lý trong transaction/lock như sau:
   - Offer còn hạn và thuộc tài xế.
   - Booking vẫn `SEARCHING_DRIVER`.
   - Tài xế vẫn khả dụng.
   - Tạo assignment duy nhất, booking `-> DRIVER_ASSIGNED`, driver `-> BUSY`.
   - Expire offer khác và ghi outbox.
7. Khách nhận tên/ảnh/rating tài xế, phương tiện, biển số và ETA.
8. Tài xế nhận điểm đón chính xác, ghi chú, chat in-app và số điện thoại trực tiếp trong phạm vi chuyến.
9. Khi bắt đầu đến điểm đón, booking chuyển `DRIVER_ASSIGNED -> DRIVER_ARRIVING`.

## Tìm lại tài xế

Chỉ cho phép reassign trước `IN_TRIP`:

1. Assignment cũ bị hủy do tài xế hủy, mất kết nối quá ngưỡng hoặc admin can thiệp.
2. Hệ thống đóng assignment cũ, ghi reason/thống kê hủy và đưa driver về trạng thái phù hợp; hiện không thu penalty tài chính tự động.
3. Nếu khách chưa hủy và booking còn hiệu lực, chuyển lại `SEARCHING_DRIVER` với `search_attempt` tăng.
4. Thông báo rõ cho khách rằng hệ thống đang tìm tài xế khác.
5. Không reuse offer cũ; tạo offer mới với version mới của booking.

## Race condition bắt buộc xử lý

- Hai tài xế nhận cùng offer batch.
- Một tài xế nhận hai booking gần đồng thời.
- Khách hủy đúng lúc tài xế nhận.
- Offer hết hạn trên server nhưng client vẫn hiển thị.
- Matching event bị queue giao lại nhiều lần.

Giải pháp tối thiểu: unique constraint cho assignment active, update trạng thái có điều kiện, transaction, idempotency key và kiểm tra lại dữ liệu chuẩn sau khi lấy ứng viên từ Redis.

## Kết quả không tìm thấy tài xế

Sau khi hết số vòng/thời gian cấu hình:

1. Booking chuyển `NO_DRIVER_FOUND`.
2. Toàn bộ offer được đóng.
3. Tiền đã trừ được hoàn về ví khách bằng bút toán đảo; voucher được khôi phục lượt nếu còn trong chính sách/giới hạn hoàn của campaign.
4. Khách nhận lựa chọn thử lại, đổi loại xe hoặc sửa điểm đón; mỗi lựa chọn tạo quote/booking mới khi cần.

Sau mỗi batch không có người nhận, hệ thống đóng offer hết hạn, cập nhật thống kê nhận/từ chối/bỏ qua rồi mới phát batch kế tiếp. Tỷ lệ bỏ qua phục vụ vận hành và rule matching; việc dùng tỷ lệ này để đình chỉ tài xế vẫn cần chính sách riêng.

## Sự kiện

- `RIDE_OFFERED`
- `RIDE_OFFER_ACCEPTED`
- `RIDE_OFFER_EXPIRED`
- `RIDE_DRIVER_ASSIGNED`
- `RIDE_DRIVER_REASSIGN_REQUESTED`
- `RIDE_NO_DRIVER_FOUND`

## Tiêu chí nghiệm thu

- Một booking chỉ có một assignment active và một driver chỉ có một công việc active.
- Accept đến sau cancel/expire luôn thất bại an toàn.
- Reassign không làm sống lại offer hoặc assignment cũ.
- Khách và tài xế nhận cùng một assignment version qua API/realtime.
