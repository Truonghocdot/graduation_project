import 'package:flutter/material.dart';

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
                  '${wallet?.balance.toStringAsFixed(0) ?? '0'} VND',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Khả dụng ${wallet?.available.toStringAsFixed(0) ?? '0'} VND',
                  style: const TextStyle(color: Color(0xFFCFE2F1)),
                ),
              ],
            ),
          ),
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
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => WithdrawPage(controller: state),
                    ),
                  ),
                  icon: const Icon(Icons.arrow_upward),
                  label: const Text('Rút tiền'),
                ),
              ),
            ],
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
    bank.dispose();
    number.dispose();
    name.dispose();
  }
}
