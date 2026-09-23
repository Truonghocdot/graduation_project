import 'package:flutter/material.dart';

Future<void> disposeTextControllerAfterRoute(
  TextEditingController controller,
) async {
  await Future<void>.delayed(const Duration(milliseconds: 250));
  controller.dispose();
}

String formatDriverValue(String value) {
  return switch (value) {
    'DELIVERY' => 'Giao hàng',
    'DRIVE' => 'Đặt xe',
    'WALLET' => 'Ví',
    'CASH' => 'Tiền mặt',
    'DRAFT' => 'Bản nháp',
    'PENDING_REVIEW' => 'Chờ xét duyệt',
    'APPROVED' => 'Đã phê duyệt',
    'REJECTED' => 'Đã từ chối',
    'SUSPENDED' => 'Tạm ngưng',
    'OFFLINE' => 'Ngoại tuyến',
    'ONLINE' => 'Trực tuyến',
    'OFFERED' => 'Đang nhận đề nghị',
    'BUSY' => 'Đang bận',
    'PENDING' => 'Chờ xử lý',
    'VERIFIED' => 'Đã xác minh',
    'ACCEPTED' => 'Đã nhận',
    'DECLINED' => 'Đã từ chối',
    'EXPIRED' => 'Đã hết hạn',
    'CANCELLED' => 'Đã hủy',
    'SCHEDULED' => 'Đã đặt lịch',
    'SEARCHING_DRIVER' => 'Đang tìm tài xế',
    'ASSIGNED' => 'Đã phân công tài xế',
    'DRIVER_ARRIVING_PICKUP' => 'Đang đến điểm lấy hàng',
    'AT_PICKUP' => 'Đã đến điểm lấy hàng',
    'PICKED_UP' => 'Đã lấy hàng',
    'IN_DELIVERY' => 'Đang giao hàng',
    'DELIVERED' => 'Đã giao hàng',
    'DELIVERY_FAILED' => 'Giao hàng thất bại',
    'RETURNING' => 'Đang hoàn hàng',
    'RETURNED' => 'Đã hoàn hàng',
    'DRIVER_ARRIVING' => 'Đang đến điểm đón',
    'DRIVER_ARRIVED' => 'Đã đến điểm đón',
    'IN_TRIP' => 'Đang trong chuyến đi',
    'TRIP_ENDED' => 'Đã kết thúc chuyến đi',
    'IN_PROGRESS' => 'Đang thực hiện',
    'COMPLETED' => 'Đã hoàn thành',
    'IDENTITY' => 'Căn cước công dân',
    'DRIVER_LICENSE' => 'Giấy phép lái xe',
    'VEHICLE_REGISTRATION' => 'Đăng ký xe',
    'INSURANCE' => 'Bảo hiểm',
    'PORTRAIT' => 'Ảnh chân dung',
    'VEHICLE_PHOTO' => 'Ảnh xe',
    'OPEN' => 'Đang mở',
    'IN_REVIEW' => 'Đang xử lý',
    'WAITING_FOR_CUSTOMER' => 'Chờ khách hàng phản hồi',
    'RESOLVED' => 'Đã xử lý',
    'REOPENED' => 'Đã mở lại',
    'CLOSED' => 'Đã đóng',
    _ => value,
  };
}

class DriverErrorBanner extends StatelessWidget {
  const DriverErrorBanner({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFDECEA),
        border: Border.all(color: const Color(0xFFF2B8B5)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        message,
        style: TextStyle(color: Theme.of(context).colorScheme.error),
      ),
    );
  }
}

class DriverEmptyState extends StatelessWidget {
  const DriverEmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
  });
  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 24),
      child: Column(
        children: [
          Icon(icon, size: 42, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class DriverStatusBadge extends StatelessWidget {
  const DriverStatusBadge(this.status, {super.key});
  final String status;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFE7EEF5),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        formatDriverValue(status),
        style: const TextStyle(
          color: Color(0xFF215F9A),
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
