import 'package:flutter/material.dart';

import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';

class ProfilePage extends StatelessWidget {
  const ProfilePage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  Widget build(BuildContext context) {
    final account = controller.customerProfile;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 26,
                  backgroundColor: const Color(0xFFE5F2EC),
                  child: Icon(
                    Icons.person_outline,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        account?.name ?? 'Tài khoản khách hàng',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 2),
                      Text(account?.phone ?? 'Số điện thoại chưa cập nhật'),
                      if (account?.email?.isNotEmpty ?? false)
                        Text(
                          account!.email!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        _item(
          context,
          Icons.account_balance_wallet_outlined,
          'Ví lạnh',
          'Xem số dư và tạo mã nạp tiền',
          () => _wallet(context),
        ),
        _item(
          context,
          Icons.notifications_outlined,
          'Thông báo',
          'Cập nhật chuyến và hỗ trợ',
          () => _notifications(context),
        ),
        _item(
          context,
          Icons.support_agent_outlined,
          'Yêu cầu hỗ trợ',
          'Theo dõi và phản hồi ticket',
          () => _tickets(context),
        ),
        _item(
          context,
          Icons.logout,
          'Đăng xuất',
          'Thu hồi phiên trên thiết bị này',
          controller.logout,
          danger: true,
        ),
        if (controller.error case final error?) ...[
          const SizedBox(height: 12),
          ErrorBanner(message: error),
        ],
      ],
    );
  }

  Widget _item(
    BuildContext context,
    IconData icon,
    String title,
    String subtitle,
    VoidCallback onTap, {
    bool danger = false,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Card(
        child: ListTile(
          leading: Icon(
            icon,
            color: danger ? Theme.of(context).colorScheme.error : null,
          ),
          title: Text(
            title,
            style: TextStyle(
              color: danger ? Theme.of(context).colorScheme.error : null,
            ),
          ),
          subtitle: Text(subtitle),
          trailing: const Icon(Icons.chevron_right),
          onTap: onTap,
        ),
      ),
    );
  }

  Future<void> _wallet(BuildContext context) async {
    await controller.loadWallet();
    if (!context.mounted || controller.wallet == null) return;
    final amount = TextEditingController(text: '100000');
    await showModalBottomSheet<void>(
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
            Text('Ví lạnh', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Text(
              '${controller.wallet!.available.toStringAsFixed(0)} VND',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 14),
            TextField(
              controller: amount,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Số tiền nạp'),
            ),
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: () async {
                final value = double.tryParse(amount.text);
                if (value == null || value <= 0) return;
                final topup = await controller.createTopup(value);
                if (context.mounted && topup != null) {
                  Navigator.pop(context);
                  showDialog<void>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('Thông tin VietQR'),
                      content: SelectableText(
                        '${topup.reference}\n${topup.vietQrPayload}',
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Đóng'),
                        ),
                      ],
                    ),
                  );
                }
              },
              icon: const Icon(Icons.qr_code_2),
              label: const Text('Tạo mã nạp tiền'),
            ),
          ],
        ),
      ),
    );
    await disposeTextControllerAfterRoute(amount);
  }

  Future<void> _notifications(BuildContext context) async {
    await controller.loadNotifications();
    if (!context.mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Thông báo', style: Theme.of(context).textTheme.titleLarge),
          if (controller.notifications.isEmpty)
            const EmptyState(
              icon: Icons.notifications_none,
              title: 'Chưa có thông báo',
              message: 'Các cập nhật mới sẽ xuất hiện ở đây.',
            ),
          for (final notification in controller.notifications)
            ListTile(
              leading: Icon(
                notification.isRead
                    ? Icons.notifications_none
                    : Icons.notifications_active_outlined,
              ),
              title: Text(formatClientValue(notification.type)),
              trailing: notification.isRead
                  ? null
                  : IconButton(
                      tooltip: 'Đánh dấu đã đọc',
                      icon: const Icon(Icons.done),
                      onPressed: () =>
                          controller.readNotification(notification.id),
                    ),
            ),
        ],
      ),
    );
  }

  Future<void> _tickets(BuildContext context) async {
    await controller.loadTickets();
    if (!context.mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Yêu cầu hỗ trợ', style: Theme.of(context).textTheme.titleLarge),
          if (controller.tickets.isEmpty)
            const EmptyState(
              icon: Icons.support_agent_outlined,
              title: 'Chưa có yêu cầu',
              message: 'Bạn có thể tạo yêu cầu từ chi tiết chuyến.',
            ),
          for (final ticket in controller.tickets)
            ListTile(
              title: Text(ticket.subject),
              subtitle: Text(formatClientValue(ticket.status)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _ticketDetail(context, ticket.id),
            ),
        ],
      ),
    );
  }

  Future<void> _ticketDetail(BuildContext context, String id) async {
    final support = controller.supportGateway;
    if (support == null) return;
    final ticket = await support.loadTicket(controller.session, id);
    if (!context.mounted) return;
    final reply = TextEditingController();
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(ticket.subject),
        content: SizedBox(
          width: 440,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ConstrainedBox(
                constraints: const BoxConstraints(maxHeight: 260),
                child: ListView(
                  shrinkWrap: true,
                  children: [
                    for (final message in ticket.messages)
                      ListTile(
                        title: Text(message.senderName),
                        subtitle: Text(message.body),
                      ),
                  ],
                ),
              ),
              if (ticket.status != 'CLOSED')
                TextField(
                  controller: reply,
                  decoration: const InputDecoration(labelText: 'Phản hồi'),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Đóng'),
          ),
          if (ticket.status != 'CLOSED')
            FilledButton(
              onPressed: () async {
                if (reply.text.trim().isEmpty) return;
                await support.replyToTicket(
                  session: controller.session,
                  id: id,
                  body: reply.text.trim(),
                );
                if (context.mounted) Navigator.pop(context);
              },
              child: const Text('Gửi'),
            ),
        ],
      ),
    );
    await disposeTextControllerAfterRoute(reply);
  }
}
