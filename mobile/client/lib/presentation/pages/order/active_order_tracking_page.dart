import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import '../chat/chat_with_driver_page.dart';
import '../profile/rating_review_page.dart';

class ActiveOrderTrackingPage extends StatelessWidget {
  const ActiveOrderTrackingPage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final request = controller.activeRequest;
        return Scaffold(
          appBar: AppBar(
            title: const Text('Theo dõi dịch vụ'),
            actions: [
              IconButton(
                tooltip: 'Làm mới',
                onPressed: controller.busy
                    ? null
                    : controller.refreshActiveRequest,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          body: request == null
              ? const Center(child: Text('Không có dịch vụ đang theo dõi.'))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _RoutePanel(request: request),
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
                                    'Trạng thái hiện tại',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium,
                                  ),
                                ),
                                StatusBadge(request.status),
                              ],
                            ),
                            const SizedBox(height: 12),
                            SelectableText(request.id),
                            const SizedBox(height: 8),
                            Text(
                              '${request.customerPayable.toStringAsFixed(0)} VND · '
                              '${formatClientValue(request.paymentMethod.apiValue)}',
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (controller.error case final error?) ...[
                      const SizedBox(height: 12),
                      ErrorBanner(message: error),
                    ],
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (_chatAvailable(request.status))
                          OutlinedButton.icon(
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => ChatWithDriverPage(
                                  controller: controller,
                                  request: request,
                                ),
                              ),
                            ),
                            icon: const Icon(Icons.chat_bubble_outline),
                            label: const Text('Chat'),
                          ),
                        OutlinedButton.icon(
                          onPressed: () => _support(context, request),
                          icon: const Icon(Icons.support_agent_outlined),
                          label: const Text('Hỗ trợ'),
                        ),
                        OutlinedButton.icon(
                          onPressed: () => _sos(context, request),
                          icon: const Icon(Icons.sos_outlined),
                          label: const Text('SOS'),
                        ),
                        if (controller.isTerminal(request.status) &&
                            request.status != 'CANCELLED')
                          OutlinedButton.icon(
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => RatingReviewPage(
                                  controller: controller,
                                  request: request,
                                ),
                              ),
                            ),
                            icon: const Icon(Icons.star_outline),
                            label: const Text('Đánh giá'),
                          ),
                      ],
                    ),
                    if (const {
                      'SCHEDULED',
                      'SEARCHING_DRIVER',
                    }.contains(request.status)) ...[
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: controller.busy
                            ? null
                            : () => _cancel(context),
                        icon: const Icon(Icons.cancel_outlined),
                        label: const Text('Hủy yêu cầu'),
                      ),
                    ],
                  ],
                ),
        );
      },
    );
  }

  bool _chatAvailable(String status) => const {
    'ASSIGNED',
    'DRIVER_ARRIVING_PICKUP',
    'AT_PICKUP',
    'PICKED_UP',
    'IN_DELIVERY',
    'DRIVER_ARRIVING',
    'DRIVER_ARRIVED',
    'IN_TRIP',
  }.contains(status);

  Future<void> _cancel(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hủy yêu cầu?'),
        content: const Text(
          'Ví và voucher hợp lệ sẽ được hoàn theo chính sách.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Giữ lại'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Xác nhận hủy'),
          ),
        ],
      ),
    );
    if (confirmed == true) await controller.cancelActiveRequest();
  }

  Future<void> _support(
    BuildContext context,
    ServiceRequestSummary request,
  ) async {
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
      await controller.supportGateway?.createSupportTicket(
        session: controller.session,
        serviceRequestId: request.id,
        subject: subject.text.trim(),
        description: body.text.trim(),
      );
    }
    subject.dispose();
    body.dispose();
  }

  Future<void> _sos(BuildContext context, ServiceRequestSummary request) async {
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
      await controller.supportGateway?.reportIncident(
        session: controller.session,
        serviceRequestId: request.id,
        incidentType: 'SOS',
      );
    }
  }
}

class _RoutePanel extends StatelessWidget {
  const _RoutePanel({required this.request});
  final ServiceRequestSummary request;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 190),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFE7EFEA),
        border: Border.all(color: const Color(0xFFC8D7D0)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Icon(Icons.radio_button_checked, size: 18),
              const SizedBox(width: 8),
              Expanded(child: Text(request.pickupAddress ?? 'Điểm đón')),
            ],
          ),
          Container(
            width: 2,
            height: 48,
            margin: const EdgeInsets.only(left: 8),
            color: const Color(0xFF7A9E90),
          ),
          Row(
            children: [
              const Icon(Icons.location_on, size: 18),
              const SizedBox(width: 8),
              Expanded(child: Text(request.dropoffAddress ?? 'Điểm đến')),
            ],
          ),
          const SizedBox(height: 28),
          const Align(
            alignment: Alignment.centerRight,
            child: Icon(Icons.near_me, color: Color(0xFF146B52)),
          ),
        ],
      ),
    );
  }
}
