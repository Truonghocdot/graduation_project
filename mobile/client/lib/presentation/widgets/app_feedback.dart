import 'package:flutter/material.dart';

Future<void> disposeTextControllerAfterRoute(
  TextEditingController controller,
) async {
  await Future<void>.delayed(const Duration(milliseconds: 250));
  controller.dispose();
}

String formatClientValue(String value) {
  return switch (value) {
    'DELIVERY' => 'Giao hàng',
    'DRIVE' => 'Đặt xe',
    'WALLET' => 'Ví',
    'CASH' => 'Tiền mặt',
    'ORDERER' => 'Người đặt',
    'RECIPIENT' => 'Người nhận',
    'SCHEDULED' => 'Đã đặt lịch',
    'SEARCHING_DRIVER' => 'Đang tìm tài xế',
    'CANCELLED' => 'Đã hủy',
    'ASSIGNED' => 'Đã phân công tài xế',
    'DRIVER_ARRIVING_PICKUP' => 'Tài xế đang đến điểm lấy hàng',
    'AT_PICKUP' => 'Tài xế đã đến điểm lấy hàng',
    'PICKED_UP' => 'Đã lấy hàng',
    'IN_DELIVERY' => 'Đang giao hàng',
    'DELIVERED' => 'Đã giao hàng',
    'DELIVERY_FAILED' => 'Giao hàng thất bại',
    'RETURNING' => 'Đang hoàn hàng',
    'RETURNED' => 'Đã hoàn hàng',
    'DRIVER_ARRIVING' => 'Tài xế đang đến',
    'DRIVER_ARRIVED' => 'Tài xế đã đến',
    'IN_TRIP' => 'Đang trong chuyến đi',
    'TRIP_ENDED' => 'Đã kết thúc chuyến đi',
    'IN_PROGRESS' => 'Đang thực hiện',
    'COMPLETED' => 'Đã hoàn thành',
    'OPEN' => 'Đang mở',
    'IN_REVIEW' => 'Đang xử lý',
    'WAITING_FOR_CUSTOMER' => 'Chờ khách hàng phản hồi',
    'RESOLVED' => 'Đã xử lý',
    'REOPENED' => 'Đã mở lại',
    'CLOSED' => 'Đã đóng',
    'PENDING' => 'Chờ xử lý',
    'APPROVED' => 'Đã phê duyệt',
    'REJECTED' => 'Đã từ chối',
    'FAILED' => 'Thất bại',
    _ => value,
  };
}

class ErrorBanner extends StatelessWidget {
  const ErrorBanner({super.key, required this.message});

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

class EmptyState extends StatelessWidget {
  const EmptyState({
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
          Icon(icon, size: 40, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.status, {super.key});

  final String status;

  @override
  Widget build(BuildContext context) {
    final terminal = const {
      'COMPLETED',
      'DELIVERED',
      'TRIP_ENDED',
    }.contains(status);
    final cancelled = status == 'CANCELLED';
    final background = cancelled
        ? const Color(0xFFFDECEA)
        : terminal
        ? const Color(0xFFE5F2EC)
        : const Color(0xFFFFF1D6);
    final foreground = cancelled
        ? const Color(0xFFB42318)
        : terminal
        ? const Color(0xFF0D5A42)
        : const Color(0xFF7A4B00);
    return Container(
      constraints: const BoxConstraints(minHeight: 28),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        formatClientValue(status),
        style: TextStyle(
          color: foreground,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
