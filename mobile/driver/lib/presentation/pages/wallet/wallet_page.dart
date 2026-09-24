import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';
import 'withdraw_page.dart';

class WalletPage extends StatefulWidget {
  const WalletPage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  State<WalletPage> createState() => _WalletPageState();
}

class _WalletPageState extends State<WalletPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => widget.controller.loadWallet(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = widget.controller;
    final wallet = state.wallet;
    return RefreshIndicator(
      onRefresh: state.loadWallet,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: const Color(0xFF173F61),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Số dư ví tài xế',
                  style: TextStyle(color: Color(0xFFCFE2F1)),
                ),
                const SizedBox(height: 6),
                Text(
                  wallet == null && state.busy
                      ? 'Đang tải…'
                      : '${wallet?.balance.toStringAsFixed(0) ?? '—'} VND',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  wallet == null && state.busy
                      ? 'Đang đồng bộ số dư'
                      : 'Khả dụng ${wallet?.available.toStringAsFixed(0) ?? '—'} VND',
                  style: const TextStyle(color: Color(0xFFCFE2F1)),
                ),
              ],
            ),
          ),
          if (wallet case final current? when current.balance < 0) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.errorContainer,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.warning_amber_rounded,
                    color: Theme.of(context).colorScheme.onErrorContainer,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Ví đang âm ${current.balance.abs().toStringAsFixed(0)} VND. '
                      'Nạp tiền để có thể online và nhận chuyến.',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onErrorContainer,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (state.error case final error?) ...[
            const SizedBox(height: 10),
            DriverErrorBanner(message: error),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _addAccount,
                  icon: const Icon(Icons.add),
                  label: const Text('Thêm ngân hàng'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilledButton.icon(
                  onPressed: state.busy ? null : () => _topUp(context),
                  icon: const Icon(Icons.qr_code_2),
                  label: const Text('Nạp ví'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: state.busy || wallet == null || wallet.balance < 0
                ? null
                : () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => WithdrawPage(controller: state),
                    ),
                  ),
            icon: const Icon(Icons.arrow_upward),
            label: const Text('Rút tiền'),
          ),
          const SizedBox(height: 22),
          Text(
            'Tài khoản ngân hàng',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          if (state.bankAccounts.isEmpty)
            const DriverEmptyState(
              icon: Icons.account_balance_outlined,
              title: 'Chưa có tài khoản',
              message:
                  'Thêm tài khoản và chờ admin xác minh trước khi rút tiền.',
            ),
          for (final account in state.bankAccounts)
            Card(
              child: ListTile(
                leading: const Icon(Icons.account_balance_outlined),
                title: Text(account.bankCode),
                subtitle: Text(account.accountName),
                trailing: DriverStatusBadge(
                  account.verified ? 'VERIFIED' : 'PENDING',
                ),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _addAccount() async {
    final bank = TextEditingController();
    final number = TextEditingController();
    final name = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Thêm tài khoản ngân hàng'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: bank,
              decoration: const InputDecoration(labelText: 'Mã ngân hàng'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: number,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Số tài khoản'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Tên chủ tài khoản'),
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
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
    if (confirmed == true &&
        bank.text.trim().isNotEmpty &&
        number.text.trim().isNotEmpty &&
        name.text.trim().isNotEmpty) {
      await widget.controller.addBankAccount(
        bankCode: bank.text.trim(),
        accountNumber: number.text.trim(),
        accountName: name.text.trim(),
      );
    }
    await Future.wait([
      disposeTextControllerAfterRoute(bank),
      disposeTextControllerAfterRoute(number),
      disposeTextControllerAfterRoute(name),
    ]);
  }

  Future<void> _topUp(BuildContext context) async {
    final wallet = widget.controller.wallet;
    final suggested = wallet != null && wallet.balance < 0
        ? (wallet.balance.abs().ceil() < 10000
              ? 10000
              : wallet.balance.abs().ceil()).toString()
        : '100000';
    final amount = TextEditingController(text: suggested);
    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          16,
          20,
          16,
          MediaQuery.viewInsetsOf(context).bottom + 20,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Nạp ví tài xế', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            const Text('Tạo mã VietQR, sau đó chuyển khoản đúng số tiền và nội dung.'),
            const SizedBox(height: 14),
            TextField(
              controller: amount,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Số tiền nạp (VND)',
                prefixIcon: Icon(Icons.payments_outlined),
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () => Navigator.pop(context, true),
              icon: const Icon(Icons.qr_code_2),
              label: const Text('Tạo mã VietQR'),
            ),
          ],
        ),
      ),
    );
    if (confirmed != true || !mounted) {
      await disposeTextControllerAfterRoute(amount);
      return;
    }
    final value = double.tryParse(amount.text.replaceAll(',', '').trim());
    if (value == null || value < 10000) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Số tiền nạp tối thiểu là 10.000 VND.')),
      );
      await disposeTextControllerAfterRoute(amount);
      return;
    }
    final topup = await widget.controller.createTopup(value);
    if (context.mounted && topup != null) await _showTopupCode(context, topup);
    await disposeTextControllerAfterRoute(amount);
  }

  Future<void> _showTopupCode(
    BuildContext context,
    DriverWalletTopupSummary topup,
  ) async {
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Mã nạp ví VietQR'),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Số tiền: ${topup.amount.toStringAsFixed(0)} VND'),
              const SizedBox(height: 8),
              Text('Nội dung chuyển khoản: ${topup.reference}'),
              const SizedBox(height: 12),
              SelectableText(topup.vietQrPayload),
              const SizedBox(height: 8),
              Text(
                'Mã có hiệu lực đến ${topup.expiresAt.toLocal()}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
        actions: [
          TextButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: topup.vietQrPayload));
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Đã sao chép nội dung VietQR.')),
                );
              }
            },
            icon: const Icon(Icons.copy_outlined),
            label: const Text('Sao chép'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Đóng'),
          ),
          TextButton(
            onPressed: () async {
              await widget.controller.loadWallet();
              if (context.mounted) Navigator.pop(context);
            },
            child: const Text('Làm mới số dư'),
          ),
        ],
      ),
    );
  }
}
