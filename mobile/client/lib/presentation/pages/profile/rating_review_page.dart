import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';

class RatingReviewPage extends StatefulWidget {
  const RatingReviewPage({
    super.key,
    required this.controller,
    required this.request,
  });

  final ClientAppController controller;
  final ServiceRequestSummary request;

  @override
  State<RatingReviewPage> createState() => _RatingReviewPageState();
}

class _RatingReviewPageState extends State<RatingReviewPage> {
  final comment = TextEditingController();
  int score = 5;
  bool busy = false;
  String? error;

  @override
  void dispose() {
    comment.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Đánh giá tài xế')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Icon(Icons.star_outline, size: 48, color: Color(0xFFB35C21)),
          const SizedBox(height: 12),
          Text(
            'Trải nghiệm chuyến đi của bạn thế nào?',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var value = 1; value <= 5; value++)
                IconButton(
                  tooltip: '$value sao',
                  onPressed: () => setState(() => score = value),
                  icon: Icon(
                    value <= score ? Icons.star : Icons.star_border,
                    color: const Color(0xFFB35C21),
                    size: 32,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: comment,
            maxLines: 4,
            maxLength: 1000,
            decoration: const InputDecoration(labelText: 'Nhận xét'),
          ),
          if (error != null)
            Text(
              error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          const SizedBox(height: 12),
          SizedBox(
            height: 48,
            child: FilledButton.icon(
              onPressed: busy ? null : _submit,
              icon: const Icon(Icons.send_outlined),
              label: const Text('Gửi đánh giá'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    final support = widget.controller.supportGateway;
    if (support == null) return;
    setState(() => busy = true);
    try {
      await support.submitRating(
        session: widget.controller.session,
        serviceRequestId: widget.request.id,
        score: score,
        comment: comment.text,
      );
      if (mounted) Navigator.pop(context);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }
}
