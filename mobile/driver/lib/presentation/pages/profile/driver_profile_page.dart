import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverProfilePage extends StatelessWidget {
  const DriverProfilePage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  Widget build(BuildContext context) {
    final profile = controller.profile;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: const Color(0xFFE7EEF5),
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
                      const Text(
                        'Đối tác tài xế',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        profile == null
                            ? 'Không có hồ sơ'
                            : formatDriverValue(profile.reviewStatus),
                      ),
                      Text(
                        'Trạng thái ${formatDriverValue(profile?.availabilityStatus ?? 'OFFLINE')}',
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Dịch vụ đã duyệt',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 8),
                if (profile?.capabilities.isEmpty ?? true)
                  const Text('Chưa có dịch vụ.')
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final capability in profile!.capabilities)
                        Chip(label: Text(formatDriverValue(capability))),
                    ],
                  ),
                const SizedBox(height: 12),
                Text(
                  'Đánh giá trung bình sẽ hiển thị khi API tổng hợp rating được triển khai.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        _item(
          context,
          Icons.notifications_outlined,
          'Thông báo',
          'Cập nhật chuyến và vận hành',
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
          const SizedBox(height: 10),
          DriverErrorBanner(message: error),
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
            const DriverEmptyState(
              icon: Icons.notifications_none,
              title: 'Chưa có thông báo',
              message: 'Cập nhật mới sẽ xuất hiện tại đây.',
            ),
          for (final item in controller.notifications)
            ListTile(
              leading: Icon(
                item.isRead
                    ? Icons.notifications_none
                    : Icons.notifications_active_outlined,
              ),
              title: Text(formatDriverValue(item.type)),
              trailing: item.isRead
                  ? null
                  : IconButton(
                      tooltip: 'Đánh dấu đã đọc',
                      icon: const Icon(Icons.done),
                      onPressed: () => controller.readNotification(item.id),
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
            const DriverEmptyState(
              icon: Icons.support_agent_outlined,
              title: 'Chưa có yêu cầu',
              message: 'Tạo yêu cầu từ chuyến đang thực hiện.',
            ),
          for (final ticket in controller.tickets)
            ListTile(
              title: Text(ticket.subject),
              subtitle: Text(formatDriverValue(ticket.status)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _ticketDetail(context, ticket.id),
            ),
        ],
      ),
    );
  }

  Future<void> _ticketDetail(BuildContext context, String id) async {
    final support = controller.support;
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
                constraints: const BoxConstraints(maxHeight: 250),
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
    reply.dispose();
  }
}
