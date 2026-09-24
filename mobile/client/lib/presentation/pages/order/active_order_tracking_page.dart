import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../../api/goong_location_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import '../../widgets/goong_map_preview.dart';
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
        final tracking = controller.tracking;
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
                    if (request.pickupLatitude != null &&
                        request.pickupLongitude != null &&
                        request.dropoffLatitude != null &&
                        request.dropoffLongitude != null)
                      GoongMapPreview(
                        pickup: GoongCoordinate(
                          latitude: request.pickupLatitude!,
                          longitude: request.pickupLongitude!,
                        ),
                        dropoff: GoongCoordinate(
                          latitude: request.dropoffLatitude!,
                          longitude: request.dropoffLongitude!,
                        ),
                        current: tracking?.liveLocation == null
                            ? null
                            : GoongCoordinate(
                                latitude: tracking!.liveLocation!.latitude,
                                longitude: tracking.liveLocation!.longitude,
                              ),
                        mapKey: const String.fromEnvironment('GOONG_MAP_KEY'),
                      ),
                    if (tracking?.locationStale == true) ...[
                      const SizedBox(height: 8),
                      const Text('Vị trí tài xế đang chậm cập nhật.'),
                    ],
                    _RoutePanel(request: request),
                    if (tracking != null) ...[
                      const SizedBox(height: 14),
                      _TrackingStatus(tracking: tracking),
                    ],
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
    await Future.wait([
      disposeTextControllerAfterRoute(subject),
      disposeTextControllerAfterRoute(body),
    ]);
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

class _TrackingStatus extends StatelessWidget {
  const _TrackingStatus({required this.tracking});

  final TrackingSummary tracking;

  @override
  Widget build(BuildContext context) {
    final meta = tracking.statusMeta;
    final label = meta['label']?.toString() ?? formatClientValue(tracking.status);
    final progress = (meta['progress_index'] as num?)?.toDouble() ?? 0;
    final total = (meta['progress_total'] as num?)?.toDouble() ?? 1;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(label, style: Theme.of(context).textTheme.titleMedium),
            if (tracking.status == 'SEARCHING_DRIVER') ...[
              const SizedBox(height: 6),
              const Text('Đang tìm tài xế gần điểm đón của bạn.'),
            ],
            const SizedBox(height: 10),
            LinearProgressIndicator(value: total == 0 ? null : progress / total),
            if (tracking.driverName case final name?) ...[
              const SizedBox(height: 12),
              Text('Tài xế: $name'),
              if (tracking.vehiclePlate case final plate?)
                Text(
                  'Xe ${tracking.vehicleType ?? ''} · $plate',
                ),
            ],
            if (tracking.liveLocation case final location?) ...[
              const SizedBox(height: 6),
              Text(
                'Vị trí cập nhật ${_ageLabel(location.capturedAt)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _ageLabel(DateTime capturedAt) {
    final seconds = DateTime.now().difference(capturedAt).inSeconds;
    if (seconds <= 1) return 'vừa xong';
    if (seconds < 60) return '$seconds giây trước';
    return '${(seconds / 60).floor()} phút trước';
  }
}
