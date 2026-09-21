# Flow 01 - Tài khoản và xác thực

## Mục tiêu

Cho phép người dùng tạo tài khoản, xác minh số điện thoại bằng OTP lần đầu, sau đó đăng nhập bằng số điện thoại + mật khẩu. Hệ thống có app khách hàng và app tài xế riêng; email chỉ là dữ liệu hồ sơ, không dùng để xác thực hoặc khôi phục tài khoản.

## Actor

- Người dùng.
- Laravel API/Fortify/Sanctum.
- Dịch vụ SMS/OTP xác minh số điện thoại.
- Admin trong trường hợp khóa hoặc mở khóa tài khoản.

## Tiền điều kiện

- Số điện thoại chưa thuộc một tài khoản active khác.
- Thiết bị có thể nhận SMS/OTP.

## Luồng A - Đăng ký

1. Người dùng nhập họ tên, số điện thoại và mật khẩu; chấp nhận điều khoản.
2. Client gửi yêu cầu đăng ký kèm `device_id` và idempotency key.
3. Server chuẩn hóa số điện thoại, validate dữ liệu và kiểm tra trùng.
4. Server tạo `User` ở trạng thái `PENDING_VERIFICATION`, lưu mật khẩu đã hash.
5. Server phát OTP có thời hạn, lưu bản hash của OTP và gửi qua kênh đã chọn.
6. Người dùng nhập OTP.
7. Server kiểm tra thời hạn, số lần thử và tính đúng của OTP.
8. Server chuyển user sang `ACTIVE`, tạo token Sanctum cho thiết bị/app và trả hồ sơ tối thiểu.
9. Hệ thống phát `USER_REGISTERED` và `USER_VERIFIED`.

## Luồng B - Đăng nhập

1. Người dùng nhập số điện thoại và mật khẩu.
2. Server kiểm tra rate limit, trạng thái tài khoản và thông tin đăng nhập.
3. Nếu hợp lệ, server thu hồi token cũ của cùng thiết bị theo chính sách và cấp token mới.
4. Client lấy hồ sơ và capability; app tài xế chỉ cho hoạt động khi có role/hồ sơ tài xế hợp lệ.
5. Server ghi nhận lần đăng nhập gần nhất, không ghi mật khẩu/token vào log.

## Luồng C - Quên mật khẩu

1. Người dùng yêu cầu đặt lại mật khẩu.
2. Server luôn trả response trung tính để tránh lộ tài khoản có tồn tại hay không.
3. Nếu tài khoản hợp lệ, server gửi OTP một lần đến số điện thoại có thời hạn.
4. Người dùng xác minh và nhập mật khẩu mới.
5. Server thay mật khẩu, thu hồi toàn bộ token hiện tại và phát `PASSWORD_RESET`.

## Luồng D - Đăng xuất

1. Đăng xuất thiết bị hiện tại: thu hồi token đang dùng và unregister push token tương ứng.
2. Đăng xuất tất cả thiết bị: thu hồi toàn bộ token của user và ngắt các socket room của user.

## Nhánh lỗi và quy tắc

| Tình huống | Xử lý |
|---|---|
| Sai OTP | Tăng bộ đếm; khóa lượt xác minh khi quá giới hạn |
| OTP hết hạn | Không dùng lại; cho phép yêu cầu mã mới sau thời gian chờ |
| Gửi lại OTP | Vô hiệu mã cũ; rate limit theo user, IP và thiết bị |
| Tài khoản `SUSPENDED` | Không cấp token; trả mã lỗi nghiệp vụ và kênh hỗ trợ |
| Token hết hạn/thu hồi | API trả `401`; client xóa session cục bộ |
| User đổi số điện thoại | Xác minh lại số mới trước khi thay dữ liệu chuẩn |
| User đổi email | Chỉ cập nhật thông tin hồ sơ theo validation; không thay đổi credential |

## Sự kiện

- `USER_REGISTERED`
- `USER_VERIFIED`
- `USER_LOGGED_IN`
- `USER_LOGGED_OUT`
- `PASSWORD_RESET`
- `USER_SUSPENDED`

## Tiêu chí nghiệm thu

- Không thể tạo hai tài khoản active với cùng định danh đã chuẩn hóa.
- OTP hết hạn, đã dùng hoặc vượt số lần thử không thể xác minh.
- Token bị thu hồi không truy cập được API bảo vệ.
- User bị khóa không thể đăng nhập hoặc mở socket authenticated.
- Request đăng ký lặp với cùng idempotency key không tạo user trùng.
