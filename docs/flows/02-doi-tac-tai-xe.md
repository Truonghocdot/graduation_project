# Flow 02 - Đăng ký và hoạt động của tài xế

## Mục tiêu

Cho phép user đăng ký làm tài xế, khai báo phương tiện, được admin duyệt và chuyển online để nhận yêu cầu Delivery/Drive phù hợp.

## Dữ liệu bắt buộc đề xuất

- Thông tin cá nhân và ảnh chân dung.
- CCCD/hộ chiếu, giấy phép lái xe và ngày hết hạn.
- Loại phương tiện, hãng/dòng xe, màu, biển số.
- Đăng ký xe, bảo hiểm và ảnh phương tiện.
- Dịch vụ đăng ký: `DELIVERY`, `DRIVE` hoặc cả hai.
- Ví lạnh nội bộ để nhận thu nhập và các khoản điều chỉnh sau khi hoàn tất đơn/chuyến.

Loại phương tiện không nhập tự do. Tài xế chọn từ danh mục do admin quản lý; mỗi loại có `unique_key` ổn định, sức chứa/giới hạn và capability Delivery/Drive.

## Luồng A - Nộp hồ sơ

1. User mở site đăng ký tài xế riêng và chọn đăng ký đối tác.
2. Client hiển thị checklist giấy tờ theo loại phương tiện/dịch vụ.
3. User nhập dữ liệu, tải ảnh và gửi hồ sơ.
4. Server validate định dạng, trùng CCCD/giấy phép/biển số và ngày hết hạn.
5. Server tạo/cập nhật `DriverProfile` ở `PENDING_REVIEW` và phát `DRIVER_APPLICATION_SUBMITTED`.
6. Admin nhận hàng đợi duyệt hồ sơ.

## Luồng B - Duyệt hồ sơ

1. Admin mở hồ sơ và kết quả kiểm tra giấy tờ.
2. Admin chọn:
   - Duyệt: `PENDING_REVIEW -> APPROVED`.
   - Yêu cầu bổ sung: giữ `PENDING_REVIEW`, lưu danh sách trường cần sửa.
   - Từ chối: `PENDING_REVIEW -> REJECTED`, bắt buộc có mã lý do.
3. Hệ thống ghi audit và thông báo cho user.
4. Khi hồ sơ đã duyệt bị sửa ở trường nhạy cảm, tài xế phải được xét duyệt lại theo chính sách.

## Luồng C - Bật online

1. Tài xế chọn phương tiện và dịch vụ muốn nhận.
2. Client kiểm tra quyền vị trí, GPS và kết nối mạng.
3. Server xác minh:
   - Hồ sơ `APPROVED`.
   - Không bị khóa hoặc có công việc active.
   - Giấy tờ/phương tiện còn hiệu lực.
   - Loại phương tiện hỗ trợ dịch vụ đã chọn.
   - Số dư ví tài xế lớn hơn hoặc bằng 0.
4. Client gửi vị trí đầu tiên.
5. Server chuyển availability `OFFLINE -> ONLINE`, cập nhật presence trong Redis và phát `DRIVER_ONLINE`.

## Luồng D - Offline

1. Tài xế chủ động tắt nhận cuốc hoặc hệ thống phát hiện mất heartbeat quá ngưỡng.
2. Nếu không có công việc active, server chuyển sang `OFFLINE` và loại khỏi tập ghép.
3. Nếu đang `BUSY`, client có thể tắt tự động nhận cuốc tiếp theo nhưng vẫn phải gửi vị trí cho công việc hiện tại.

## Quy tắc

- Một tài xế chỉ sử dụng một phương tiện active tại một thời điểm.
- Không dùng vị trí cũ hơn ngưỡng cấu hình để ghép tài xế.
- Tài xế `SUSPENDED`, giấy phép hết hạn hoặc phương tiện bị vô hiệu hóa phải bị đưa offline ngay.
- Tài xế có ví nhỏ hơn 0 bị dừng nhận offer/đơn mới cho đến khi số dư trở lại lớn hơn hoặc bằng 0; không áp dụng giới hạn nợ âm khác.
- Hiện không tự động đình chỉ theo tỷ lệ nhận/hủy; các tỷ lệ này chỉ được dùng để xếp hạng matching.
- `OFFERED` là trạng thái ngắn hạn. Khi offer hết hạn/từ chối, tài xế trở lại `ONLINE` nếu vẫn đủ điều kiện.
- Khi được assign, chuyển tài xế sang `BUSY` trong cùng transaction/lock với assignment.
- Nhận offer không làm thay đổi số dư ví tài xế và không phát sinh chiết khấu/phí. Quyết toán chỉ xảy ra sau khi hoàn tất dịch vụ.

## Sự kiện

- `DRIVER_APPLICATION_SUBMITTED`
- `DRIVER_APPLICATION_APPROVED`
- `DRIVER_APPLICATION_REJECTED`
- `DRIVER_ONLINE`
- `DRIVER_OFFLINE`
- `DRIVER_SUSPENDED`
- `DRIVER_VEHICLE_CHANGED`

## Tiêu chí nghiệm thu

- Hồ sơ chưa duyệt hoặc có giấy tờ hết hạn không thể online.
- Hai tài xế không thể dùng cùng giấy tờ/biển số khi chính sách không cho phép.
- Tài xế đang có công việc active không được assign thêm công việc trong MVP.
- Mất heartbeat đưa tài xế rảnh về offline sau ngưỡng cấu hình.
