import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import 'active_order_tracking_page.dart';

class OrderCheckoutPage extends StatefulWidget {
  const OrderCheckoutPage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  State<OrderCheckoutPage> createState() => _OrderCheckoutPageState();
}

class _OrderCheckoutPageState extends State<OrderCheckoutPage> {
  PaymentChoice payment = PaymentChoice.wallet;
  PayerChoice payer = PayerChoice.orderer;
  final recipient = TextEditingController();

  @override
  void dispose() {
    recipient.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) {
        final quote = widget.controller.quote;
        if (quote == null) {
          return const Scaffold(
            body: Center(child: Text('Báo giá không còn khả dụng.')),
          );
        }
        final expired = !quote.expiresAt.isAfter(DateTime.now());
        return Scaffold(
          appBar: AppBar(title: const Text('Xác nhận dịch vụ')),
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'Báo giá',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 12),
                      _AmountRow('Giá dịch vụ', quote.grossFare),
                      if (quote.voucherDiscount > 0)
                        _AmountRow('Voucher', -quote.voucherDiscount),
                      const Divider(height: 24),
                      _AmountRow(
                        'Cần thanh toán',
                        quote.customerPayable,
                        emphasized: true,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${(quote.distanceMeters / 1000).toStringAsFixed(1)} km · '
                        '${(quote.durationSeconds / 60).ceil()} phút',
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Thanh toán',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              SegmentedButton<PaymentChoice>(
                segments: const [
                  ButtonSegment(
                    value: PaymentChoice.wallet,
                    icon: Icon(Icons.account_balance_wallet_outlined),
                    label: Text('Ví lạnh'),
                  ),
                  ButtonSegment(
                    value: PaymentChoice.cash,
                    icon: Icon(Icons.payments_outlined),
                    label: Text('Tiền mặt'),
                  ),
                ],
                selected: {payment},
                onSelectionChanged: (values) =>
                    setState(() => payment = values.first),
              ),
              if (quote.service == ServiceKind.delivery) ...[
                const SizedBox(height: 16),
                SegmentedButton<PayerChoice>(
                  segments: const [
                    ButtonSegment(
                      value: PayerChoice.orderer,
                      label: Text('Người đặt trả'),
                    ),
                    ButtonSegment(
                      value: PayerChoice.recipient,
                      label: Text('Người nhận trả'),
                    ),
                  ],
                  selected: {payer},
                  onSelectionChanged: (values) => setState(() {
                    payer = values.first;
                    if (payer == PayerChoice.recipient) {
                      payment = PaymentChoice.cash;
                    }
                  }),
                ),
                if (payer == PayerChoice.recipient) ...[
                  const SizedBox(height: 10),
                  TextField(
                    controller: recipient,
                    decoration: const InputDecoration(
                      labelText: 'Mã người nhận',
                    ),
                  ),
                ],
              ],
              if (widget.controller.error case final error?) ...[
                const SizedBox(height: 12),
                ErrorBanner(message: error),
              ],
              const SizedBox(height: 20),
              SizedBox(
                height: 48,
                child: FilledButton.icon(
                  key: const Key('create-button'),
                  onPressed: expired || widget.controller.busy ? null : _create,
                  icon: const Icon(Icons.check_circle_outline),
                  label: Text(
                    expired ? 'Báo giá đã hết hạn' : 'Xác nhận đặt dịch vụ',
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _create() async {
    await widget.controller.createRequest(
      payment: payment,
      payer: payer,
      recipientUserId: payer == PayerChoice.recipient
          ? recipient.text.trim()
          : null,
    );
    if (widget.controller.activeRequest != null && mounted) {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(
          builder: (_) =>
              ActiveOrderTrackingPage(controller: widget.controller),
        ),
        (route) => route.isFirst,
      );
    }
  }
}

class _AmountRow extends StatelessWidget {
  const _AmountRow(this.label, this.amount, {this.emphasized = false});
  final String label;
  final double amount;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final style = emphasized
        ? Theme.of(context).textTheme.titleMedium
        : Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text('${amount.toStringAsFixed(0)} VND', style: style),
        ],
      ),
    );
  }
}
