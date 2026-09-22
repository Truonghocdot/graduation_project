import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class WithdrawPage extends StatefulWidget {
  const WithdrawPage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  State<WithdrawPage> createState() => _WithdrawPageState();
}

class _WithdrawPageState extends State<WithdrawPage> {
  final amount = TextEditingController(text: '50000');
  String? accountId;

  @override
  void initState() {
    super.initState();
    accountId = widget.controller.bankAccounts
        .where((account) => account.verified)
        .firstOrNull
        ?.id;
  }

  @override
  void dispose() {
    amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final verified = widget.controller.bankAccounts
        .where((account) => account.verified)
        .toList(growable: false);
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) => Scaffold(
        appBar: AppBar(title: const Text('Rút tiền')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Số dư khả dụng',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            Text(
              '${widget.controller.wallet?.available.toStringAsFixed(0) ?? '0'} VND',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 18),
            DropdownButtonFormField<String>(
              initialValue: accountId,
              decoration: const InputDecoration(
                labelText: 'Tài khoản đã xác minh',
              ),
              items: verified
                  .map(
                    (account) => DropdownMenuItem(
                      value: account.id,
                      child: Text(
                        '${account.bankCode} · ${account.accountName}',
                      ),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) => setState(() => accountId = value),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: amount,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Số tiền rút'),
            ),
            if (widget.controller.error case final error?) ...[
              const SizedBox(height: 10),
              DriverErrorBanner(message: error),
            ],
            const SizedBox(height: 16),
            SizedBox(
              height: 48,
              child: FilledButton.icon(
                onPressed: widget.controller.busy || accountId == null
                    ? null
                    : _withdraw,
                icon: const Icon(Icons.arrow_upward),
                label: const Text('Gửi yêu cầu rút'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _withdraw() async {
    final value = double.tryParse(amount.text);
    if (value == null || value <= 0 || accountId == null) return;
    await widget.controller.withdraw(accountId!, value);
    if (mounted && widget.controller.error == null) Navigator.pop(context);
  }
}
