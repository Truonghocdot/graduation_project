import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class IncomingOrderDialog extends StatefulWidget {
  const IncomingOrderDialog({
    super.key,
    required this.controller,
    required this.offer,
  });

  final DriverAppController controller;
  final DriverOfferSummary offer;

  @override
  State<IncomingOrderDialog> createState() => _IncomingOrderDialogState();
}

class _IncomingOrderDialogState extends State<IncomingOrderDialog> {
  late int seconds = widget.offer.expiresAt
      .difference(DateTime.now())
      .inSeconds
      .clamp(0, 3600);
  Timer? timer;

  @override
  void initState() {
    super.initState();
    HapticFeedback.mediumImpact();
    timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(
        () => seconds = widget.offer.expiresAt
            .difference(DateTime.now())
            .inSeconds
            .clamp(0, 3600),
      );
      if (seconds == 3) HapticFeedback.selectionClick();
      if (seconds == 0) timer?.cancel();
    });
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final offer = widget.offer;
    return AlertDialog(
      title: Row(
        children: [
          const Expanded(child: Text('Đề nghị mới')),
          Semantics(
            label: 'Còn $seconds giây để phản hồi',
            child: CircleAvatar(radius: 20, child: Text('$seconds')),
          ),
        ],
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            formatDriverValue(offer.serviceType),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Text(
            'Cách điểm đón ${(offer.pickupDistanceMeters / 1000).toStringAsFixed(1)} km',
          ),
          Text(
            'Thu nhập dự kiến ${offer.estimatedEarning.toStringAsFixed(0)} VND',
          ),
          Text('Khách thanh toán ${formatDriverValue(offer.paymentMethod)}'),
        ],
      ),
      actions: [
        TextButton(
          onPressed: seconds == 0 ? null : () => _respond(context, 'decline'),
          child: const Text('Từ chối'),
        ),
        FilledButton.icon(
          key: Key('accept-${offer.id}'),
          onPressed: seconds == 0 ? null : () => _respond(context, 'accept'),
          icon: const Icon(Icons.check),
          label: const Text('Nhận chuyến'),
        ),
      ],
    );
  }

  Future<void> _respond(BuildContext context, String action) async {
    await widget.controller.respond(widget.offer, action);
    if (context.mounted) Navigator.pop(context);
  }
}
