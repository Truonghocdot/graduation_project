import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import '../order/active_order_tracking_page.dart';
import 'service_detail_page.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async {
        await controller.loadHistory();
        await controller.refreshActiveRequest();
      },
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: const Color(0xFF163D35),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Bạn cần đi đâu?',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 21,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 6),
                      Text(
                        'Đặt giao hàng hoặc chuyến xe trong vài bước.',
                        style: TextStyle(color: Color(0xFFD8E8E2)),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.route, size: 46, color: Color(0xFF91D6BE)),
              ],
            ),
          ),
          if (controller.activeRequest case final request?) ...[
            const SizedBox(height: 16),
            Card(
              child: ListTile(
                contentPadding: const EdgeInsets.all(14),
                leading: const Icon(Icons.near_me_outlined),
                title: const Text('Dịch vụ đang theo dõi'),
                subtitle: Text(formatClientValue(request.status)),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        ActiveOrderTrackingPage(controller: controller),
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: 24),
          Text('Dịch vụ', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: MediaQuery.sizeOf(context).width < 520 ? 2 : 3,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: MediaQuery.sizeOf(context).width < 360
                ? 0.85
                : 1.05,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children: [
              _ServiceTile(
                icon: Icons.local_shipping_outlined,
                title: 'Giao hàng',
                subtitle: 'Gửi hàng nội thành',
                onTap: () => _openService(context, ServiceKind.delivery),
              ),
              _ServiceTile(
                icon: Icons.directions_car_outlined,
                title: 'Đặt xe',
                subtitle: 'Di chuyển theo yêu cầu',
                onTap: () => _openService(context, ServiceKind.drive),
              ),
            ],
          ),
          if (controller.error case final error?) ...[
            const SizedBox(height: 16),
            ErrorBanner(message: error),
          ],
        ],
      ),
    );
  }

  void _openService(BuildContext context, ServiceKind service) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            ServiceDetailPage(controller: controller, service: service),
      ),
    );
  }
}

class _ServiceTile extends StatelessWidget {
  const _ServiceTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                icon,
                color: Theme.of(context).colorScheme.primary,
                size: 30,
              ),
              const Spacer(),
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 2),
              Text(
                subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
