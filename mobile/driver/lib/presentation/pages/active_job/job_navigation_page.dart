import 'package:flutter/material.dart';

import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';
import 'chat_with_customer_page.dart';
import 'update_status_page.dart';

class JobNavigationPage extends StatelessWidget {
  const JobNavigationPage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final offer = controller.activeOffer;
        if (offer == null) {
          return const Scaffold(
            body: Center(child: Text('Không có chuyến đang chạy.')),
          );
        }
        final action = _nextAction(offer.serviceStatus);
        return Scaffold(
          appBar: AppBar(
            title: const Text('Điều hướng chuyến'),
            actions: [
              IconButton(
                tooltip: 'Chat với khách',
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => ChatWithCustomerPage(
                      controller: controller,
                      offer: offer,
                    ),
                  ),
                ),
                icon: const Icon(Icons.chat_bubble_outline),
              ),
            ],
          ),
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                height: 260,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: const Color(0xFFE7EEF5),
                  border: Border.all(color: const Color(0xFFC9D6E2)),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Stack(
                  children: [
                    const Positioned(
                      left: 28,
                      top: 35,
                      child: Icon(
                        Icons.radio_button_checked,
                        color: Color(0xFF215F9A),
                      ),
                    ),
                    const Positioned(
                      right: 30,
                      bottom: 38,
                      child: Icon(
                        Icons.location_on,
                        size: 32,
                        color: Color(0xFFB35C21),
                      ),
                    ),
                    Positioned.fill(
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.navigation,
                              size: 38,
                              color: Color(0xFF215F9A),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              '${offer.pickupLatitude}, ${offer.pickupLongitude}',
                            ),
                            const Text('đến'),
                            Text(
                              '${offer.dropoffLatitude}, ${offer.dropoffLongitude}',
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              offer.serviceType,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          DriverStatusBadge(offer.serviceStatus),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Thanh toán ${offer.paymentMethod} · '
                        '${offer.customerPayable.toStringAsFixed(0)} VND',
                      ),
                      Text(
                        'Thu nhập dự kiến ${offer.estimatedEarning.toStringAsFixed(0)} VND',
                      ),
                    ],
                  ),
                ),
              ),
              if (controller.error case final error?) ...[
                const SizedBox(height: 10),
                DriverErrorBanner(message: error),
              ],
              const SizedBox(height: 14),
              if (action != null)
                SizedBox(
                  height: 48,
                  child: FilledButton.icon(
                    onPressed: controller.busy
                        ? null
                        : () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => UpdateStatusPage(
                                controller: controller,
                                offer: offer,
                                action: action,
                              ),
                            ),
                          ),
                    icon: Icon(action.icon),
                    label: Text(action.label),
                  ),
                ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: () => _sos(context, offer),
                icon: const Icon(Icons.sos_outlined),
                label: const Text('Báo SOS'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => _support(context, offer),
                icon: const Icon(Icons.support_agent_outlined),
                label: const Text('Yêu cầu hỗ trợ'),
              ),
            ],
          ),
        );
      },
    );
  }

  JobAction? _nextAction(String status) => switch (status) {
    'DRIVER_ARRIVING_PICKUP' => const JobAction(
      value: 'arrive_pickup',
      label: 'Đã đến điểm lấy',
      icon: Icons.location_on_outlined,
    ),
    'AT_PICKUP' => const JobAction(
      value: 'pickup',
      label: 'Đã nhận hàng',
      icon: Icons.inventory_2_outlined,
      evidenceType: 'PICKUP',
    ),
    'PICKED_UP' => const JobAction(
      value: 'start_delivery',
      label: 'Bắt đầu giao',
      icon: Icons.local_shipping_outlined,
    ),
    'IN_DELIVERY' => const JobAction(
      value: 'deliver',
      label: 'Hoàn tất giao hàng',
      icon: Icons.check_circle_outline,
      evidenceType: 'DELIVERY',
      terminal: true,
    ),
    'DRIVER_ARRIVING' => const JobAction(
      value: 'arrive',
      label: 'Đã đến điểm đón',
      icon: Icons.location_on_outlined,
    ),
    'DRIVER_ARRIVED' => const JobAction(
      value: 'start',
      label: 'Bắt đầu chuyến',
      icon: Icons.play_arrow,
    ),
    'IN_TRIP' => const JobAction(
      value: 'complete',
      label: 'Kết thúc chuyến',
      icon: Icons.flag_outlined,
      terminal: true,
    ),
    _ => null,
  };

  Future<void> _sos(BuildContext context, DriverOfferSummary offer) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Báo động SOS?'),
        content: const Text(
          'Nếu nguy hiểm tức thời, hãy liên hệ dịch vụ khẩn cấp địa phương.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, true),
            icon: const Icon(Icons.sos_outlined),
            label: const Text('Báo ngay'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await controller.support?.reportIncident(
        session: controller.session,
        serviceRequestId: offer.serviceRequestId,
        incidentType: 'SOS',
      );
    }
  }

  Future<void> _support(BuildContext context, DriverOfferSummary offer) async {
    final subject = TextEditingController();
    final body = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tạo yêu cầu hỗ trợ'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: subject,
              decoration: const InputDecoration(labelText: 'Tiêu đề'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: body,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Mô tả'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Gửi'),
          ),
        ],
      ),
    );
    if (send == true &&
        subject.text.trim().isNotEmpty &&
        body.text.trim().isNotEmpty) {
      await controller.support?.createSupportTicket(
        session: controller.session,
        serviceRequestId: offer.serviceRequestId,
        subject: subject.text.trim(),
        description: body.text.trim(),
      );
    }
    subject.dispose();
    body.dispose();
  }
}
