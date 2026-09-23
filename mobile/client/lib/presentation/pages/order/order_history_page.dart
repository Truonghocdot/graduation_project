import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import 'active_order_tracking_page.dart';
import 'order_detail_page.dart';

class OrderHistoryPage extends StatefulWidget {
  const OrderHistoryPage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  State<OrderHistoryPage> createState() => _OrderHistoryPageState();
}

class _OrderHistoryPageState extends State<OrderHistoryPage>
    with SingleTickerProviderStateMixin {
  late final TabController tabs = TabController(length: 2, vsync: this);

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        TabBar(
          controller: tabs,
          tabs: const [
            Tab(text: 'Đang chạy'),
            Tab(text: 'Đã hoàn thành'),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: tabs,
            children: [
              _list(
                widget.controller.history
                    .where(
                      (request) =>
                          !widget.controller.isTerminal(request.status),
                    )
                    .toList(),
              ),
              _list(
                widget.controller.history
                    .where(
                      (request) => widget.controller.isTerminal(request.status),
                    )
                    .toList(),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _list(List<ServiceRequestSummary> requests) {
    return RefreshIndicator(
      onRefresh: widget.controller.loadHistory,
      child: requests.isEmpty
          ? ListView(
              children: [
                EmptyState(
                  icon: Icons.receipt_long_outlined,
                  title: 'Chưa có dịch vụ',
                  message: 'Các đơn và chuyến xe sẽ xuất hiện tại đây.',
                ),
              ],
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: requests.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, index) => _tile(requests[index]),
            ),
    );
  }

  Widget _tile(ServiceRequestSummary request) {
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(14),
        leading: Icon(
          request.service == ServiceKind.delivery
              ? Icons.inventory_2_outlined
              : Icons.directions_car_outlined,
        ),
        title: Text(request.pickupAddress ?? request.id),
        subtitle: Text(
          '${request.dropoffAddress ?? formatClientValue(request.service.apiValue)}\n'
          '${request.customerPayable.toStringAsFixed(0)} VND',
        ),
        isThreeLine: true,
        trailing: StatusBadge(request.status),
        onTap: () async {
          await widget.controller.selectHistoryRequest(request);
          if (!mounted) return;
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => widget.controller.isTerminal(request.status)
                  ? OrderDetailPage(
                      controller: widget.controller,
                      request: request,
                    )
                  : ActiveOrderTrackingPage(controller: widget.controller),
            ),
          );
        },
      ),
    );
  }
}
