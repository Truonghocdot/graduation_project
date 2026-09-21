# Quyết định dự án

> Cập nhật từ phản hồi sản phẩm ngày **2026-09-21**.

Các câu hỏi nghiệp vụ đã được trả lời. Tài liệu này là decision log dùng để khóa phạm vi API, database và flow của đồ án.

## Quyết định đã chốt

### Nền tảng và tài khoản

| ID | Quyết định | Tác động chính |
|---|---|---|
| D-01 | Có hai ứng dụng riêng: một app khách hàng và một app tài xế | Tách navigation, permission, release và push topic; dùng chung API contract |
| D-02 | `worker/` là Laravel API chính, trang quản trị và nơi publish/consume message qua RabbitMQ | Laravel sở hữu transaction nghiệp vụ và outbox |
| D-03 | Dùng [Goong API](https://help.goong.io/) để lấy vị trí, geocode, route, quãng đường và ETA | Cần adapter, cache và xử lý quota/lỗi provider |
| D-04 | Email chỉ là thông tin hồ sơ, không dùng để xác thực | Không cấp credential, OTP hoặc luồng khôi phục qua email |
| D-05 | OTP dùng để xác minh số điện thoại ban đầu; sau đó đăng nhập bằng số điện thoại + mật khẩu | Cần flow verify phone, login password và reset password qua số điện thoại |
| D-06 | OTP không dùng cho lấy/giao hàng, đón khách hoặc bắt đầu chuyến | Các mốc nghiệp vụ dùng xác nhận trong app/evidence |
| D-07 | Tài xế đăng ký hồ sơ thủ công qua một site riêng | Hồ sơ được đưa vào hàng đợi xét duyệt của admin |
| D-08 | Chỉ hỗ trợ Việt Nam, tiếng Việt và múi giờ `Asia/Ho_Chi_Minh` trong giai đoạn hiện tại | VND, nội dung và vận hành giới hạn theo thị trường Việt Nam |

### Dịch vụ và vận hành

| ID | Quyết định | Tác động chính |
|---|---|---|
| D-09 | Admin quản lý loại xe, sức chứa/giới hạn và `unique_key` ổn định | Quote, validation và capability tham chiếu `unique_key` |
| D-10 | Mỗi dịch vụ chỉ có một điểm đi/lấy và một điểm đến/giao | Chưa hỗ trợ multi-stop |
| D-11 | Hỗ trợ đặt ngay và đặt lịch trước | Scheduler kích hoạt matching theo thời gian estimate do admin cấu hình |
| D-12 | Matching phát offer theo các batch nhỏ giọt | Offer hết hạn/không nhận chuyển sang batch sau và được ghi nhận tỷ lệ bỏ qua |
| D-13 | Matching ưu tiên tài xế có tỷ lệ nhận cao và tỷ lệ hủy thấp | Hiện không tự động suspension theo các tỷ lệ này |
| D-14 | Một tài xế chỉ có một đơn/chuyến active tại một thời điểm | Unique active assignment theo driver |
| D-15 | Hiện chưa áp dụng phí hủy hoặc phí chờ tự động | Ngoại lệ đi qua site/chat hỗ trợ |
| D-16 | Liên lạc bằng chat trong app và gọi trực tiếp qua số điện thoại | Chỉ bổ sung số điện thoại ảo/nhà cung cấp gọi khi có requirement mới |
| D-17 | Vị trí tài xế được gửi qua socket mỗi 1,5 giây khi online/đang thực hiện dịch vụ | Redis TTL và rate limit phải phù hợp tần suất |
| D-18 | Không yêu cầu xuất hóa đơn trong giai đoạn hiện tại | Chỉ phát biên nhận nội bộ nếu cần |
| D-19 | Không triển khai referral, loyalty hoặc subscription | Ngoài phạm vi MVP |
| D-20 | Chỉ lưu vị trí cuối cùng của tài xế, không lưu lịch sử hành trình | Mỗi lần định vị ghi đè `last_location` và `last_location_at`; realtime sample không persist thành timeline |
| D-21 | Support/admin dùng queue, phân quyền và audit ở mức MVP | Không cam kết SLA số; support xử lý ticket được giao, admin có quyền cấu hình/can thiệp |
| D-22 | Không đặt mục tiêu RTO/RPO hoặc tải business | Ưu tiên hệ thống chạy đúng và hoàn thành đồ án; benchmark/DR nâng cao ngoài phạm vi |

### Giá, voucher và thanh toán

| ID | Quyết định | Tác động chính |
|---|---|---|
| D-23 | Tiền tệ là VND và số tiền dùng kiểu `float` | Phải quy định làm tròn VND ở mọi boundary và không so sánh float bằng phép bằng tuyệt đối |
| D-24 | Thanh toán gồm voucher, ví lạnh nội bộ và tiền mặt | Voucher giảm giá; phần còn lại trả bằng `WALLET` hoặc `CASH` |
| D-25 | VietQR sinh thông tin chuyển khoản từ cấu hình hệ thống; SePay gửi webhook xác nhận nạp tiền | Top-up chỉ ghi có sau webhook SePay hợp lệ và idempotent |
| D-26 | Giá cơ sở cấu hình theo loại xe cho quãng đường đến 3 km | Xe máy mặc định 18.000 VND; ô tô có thể cấu hình 28.000 hoặc 32.000 VND |
| D-27 | Phần vượt 3 km tính theo đơn giá/km của loại xe | Ví dụ xe máy 5.000 VND/km và ô tô 10.000 VND/km; admin được cấu hình |
| D-28 | Tỷ lệ tài xế được cấu hình, hiện tại là 88%; phí nền tảng tương ứng 12% | Đơn 18.000 VND cho tài xế 15.840 VND và phí nền tảng 2.160 VND |
| D-29 | Payment bằng ví liên kết với wallet transaction; khoản giảm voucher là discount transaction riêng | Voucher/discount transaction không tạo wallet credit cho tài xế |
| D-30 | Với `CASH`, tài xế nhận toàn bộ tiền khách trả; phí nền tảng chỉ bị trừ khỏi ví tài xế sau hoàn tất | Ví có thể âm sau settlement; tài xế có ví âm không được nhận đơn mới |
| D-31 | Phần voucher tài trợ được ghi vào breakdown khoản thanh toán, không ghi vào ví tài xế | `voucher_payment_amount` tham chiếu discount transaction và không tạo wallet credit |
| D-32 | Khách không được đổi `WALLET/CASH` sau khi đã tạo đơn/chuyến | Payment method là snapshot bất biến của booking/order |
| D-33 | Với `WALLET`, trừ tiền khách ngay khi tạo đơn/chuyến, kể cả đặt trước; `CASH` không giữ tiền khách | Khi hủy hợp lệ, hoàn tiền bằng bút toán đảo |
| D-34 | Voucher được chọn và đánh dấu dùng ngay khi tạo đơn/chuyến | Hủy hợp lệ có thể khôi phục lượt theo giới hạn hoàn của campaign |
| D-35 | Voucher có hai loại: phần trăm và số tiền cố định | Loại phần trăm có mức giảm tối đa; hỗ trợ phạm vi dịch vụ, giới hạn apply và hoàn lượt |
| D-36 | Tiền mặt được xác nhận đã thu khi tài xế bấm hoàn thành | Command hoàn thành phải idempotent và lưu actor/thời điểm |
| D-37 | Ví tài xế âm ở bất kỳ mức nào đều bị chặn nhận đơn mới; không đặt giới hạn nợ âm | Settlement hiện tại vẫn hoàn tất và eligibility được cập nhật sau đó |
| D-38 | Rút ví do hệ thống/admin chuyển khoản thủ công đến tài khoản ngân hàng tài xế đã liên kết | Áp dụng mức tối thiểu/tối đa và khung giờ do admin cấu hình |
| D-39 | Không thu thêm khách khi phát sinh chênh lệch giá cuối | Khoản điều chỉnh được ghi vào ví tài xế sau hoàn tất |

### Delivery và hỗ trợ

| ID | Quyết định | Tác động chính |
|---|---|---|
| D-40 | Người tạo đơn hoặc người nhận có thể là người trả phí Delivery | Order lưu `payer_type`; phương thức thanh toán phải phù hợp với payer |
| D-41 | Delivery hỗ trợ ứng COD; tài xế cấu hình hạn mức COD muốn nhận | Hạn mức toàn hệ thống do admin cấu hình, hiện tối đa 8.000.000 VND |
| D-42 | Tài xế ứng tiền COD theo đơn rồi thu lại từ người nhận | COD ledger tách khỏi phí vận chuyển và thu nhập tài xế |
| D-43 | Yêu cầu hoàn hàng nằm trên cùng bản ghi `DeliveryOrder` | Thay đổi status và `list_type`; không tạo order/ReturnTask mới |
| D-44 | Chặng hoàn có giá riêng; thu nhập tài xế áp dụng cùng tỷ lệ chiết khấu | Lưu pricing/settlement revision cho chặng hoàn trên đơn gốc |
| D-45 | Khách không trả đủ tiền mặt: tài xế báo cáo, support xác minh và hệ thống bù nếu đủ điều kiện | Khách vi phạm đã xác minh bị khóa vĩnh viễn |

## Cách cập nhật quyết định

Mỗi quyết định mới cần ghi ngày, lựa chọn, lý do và tác động migration/backward compatibility. Khi quyết định làm thay đổi state/event đã phát hành, tạo version mới thay vì đổi ngữ nghĩa âm thầm.
