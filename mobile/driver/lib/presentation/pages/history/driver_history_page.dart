import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverHistoryPage extends StatefulWidget {
  const DriverHistoryPage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  State<DriverHistoryPage> createState() => _DriverHistoryPageState();
}

class _DriverHistoryPageState extends State<DriverHistoryPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => widget.controller.loadHistory(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final jobs = widget.controller.history;
    final earnings = jobs.fold<double>(
      0,
      (sum, job) => sum + (job.driverNetEarning ?? 0),
    );
    return RefreshIndicator(
      onRefresh: widget.controller.loadHistory,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Chuyến hoàn tất',
                  value: '${jobs.length}',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _Metric(
                  label: 'Thu nhập',
                  value: '${earnings.toStringAsFixed(0)} VND',
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          if (jobs.isEmpty)
            const DriverEmptyState(
              icon: Icons.history,
              title: 'Chưa có lịch sử',
              message: 'Các chuyến đã đóng sẽ xuất hiện tại đây.',
            ),
          for (final job in jobs)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                child: ListTile(
                  leading: Icon(
                    job.serviceType == 'DELIVERY'
                        ? Icons.inventory_2_outlined
                        : Icons.directions_car_outlined,
                  ),
                  title: Text(job.pickupAddress ?? job.id),
                  subtitle: Text(
                    '${job.dropoffAddress ?? job.serviceType}\n'
                    '${(job.driverNetEarning ?? 0).toStringAsFixed(0)} VND',
                  ),
                  isThreeLine: true,
                  trailing: DriverStatusBadge(job.status),
                  onTap: job.status == 'COMPLETED'
                      ? () => _rate(context, job.id)
                      : null,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _rate(BuildContext context, String requestId) async {
    var score = 5;
    final comment = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Đánh giá khách hàng'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                initialValue: score,
                decoration: const InputDecoration(labelText: 'Số sao'),
                items: [1, 2, 3, 4, 5]
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text('$value sao'),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (value) => setDialogState(() => score = value ?? 5),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: comment,
                decoration: const InputDecoration(labelText: 'Nhận xét'),
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
      ),
    );
    if (send == true) {
      await widget.controller.support?.submitRating(
        session: widget.controller.session,
        serviceRequestId: requestId,
        score: score,
        comment: comment.text,
      );
    }
    comment.dispose();
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 5),
            Text(value, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
      ),
    );
  }
}
