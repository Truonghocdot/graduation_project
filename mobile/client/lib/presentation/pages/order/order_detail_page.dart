import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import '../profile/rating_review_page.dart';

class OrderDetailPage extends StatelessWidget {
  const OrderDetailPage({
    super.key,
    required this.controller,
    required this.request,
  });

  final ClientAppController controller;
  final ServiceRequestSummary request;

  @override
  Widget build(BuildContext context) {
    final current = controller.activeRequest?.id == request.id
        ? controller.activeRequest!
        : request;
    return Scaffold(
      appBar: AppBar(title: const Text('Chi tiết dịch vụ')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  current.service == ServiceKind.delivery
                      ? 'Đơn giao hàng'
                      : 'Chuyến xe',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              StatusBadge(current.status),
            ],
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _row('Mã yêu cầu', current.id),
                  _row('Điểm đón', current.pickupAddress ?? 'Không có dữ liệu'),
                  _row(
                    'Điểm đến',
                    current.dropoffAddress ?? 'Không có dữ liệu',
                  ),
                  _row('Thanh toán', current.paymentMethod.apiValue),
                  _row(
                    'Tổng tiền',
                    '${current.customerPayable.toStringAsFixed(0)} VND',
                  ),
                  if (current.driverNetEarning != null)
                    _row(
                      'Đã quyết toán',
                      '${current.driverNetEarning!.toStringAsFixed(0)} VND',
                    ),
                ],
              ),
            ),
          ),
          if (current.status == 'COMPLETED') ...[
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => RatingReviewPage(
                    controller: controller,
                    request: current,
                  ),
                ),
              ),
              icon: const Icon(Icons.star_outline),
              label: const Text('Đánh giá tài xế'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(width: 110, child: Text(label)),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}
