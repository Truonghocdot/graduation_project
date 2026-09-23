import 'dart:async';

import 'package:flutter/material.dart';

import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.loadNotifications());
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) {
        final state = widget.controller;
        final notifications = state.notifications;
        final loading = state.busy && notifications.isEmpty;
        return Scaffold(
          appBar: AppBar(title: const Text('Thông báo')),
          body: loading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: state.loadNotifications,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    children: [
                      if (state.error case final error?)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                          child: ErrorBanner(message: error),
                        ),
                      if (notifications.isEmpty)
                        const EmptyState(
                          icon: Icons.notifications_none,
                          title: 'Chưa có thông báo',
                          message: 'Các cập nhật mới sẽ xuất hiện ở đây.',
                        ),
                      for (final notification in notifications)
                        ListTile(
                          leading: Icon(
                            notification.isRead
                                ? Icons.notifications_none_outlined
                                : Icons.notifications_active_outlined,
                            color: notification.isRead
                                ? null
                                : Theme.of(context).colorScheme.primary,
                          ),
                          title: Text(formatClientValue(notification.type)),
                          subtitle: Text(
                            notification.isRead ? 'Đã đọc' : 'Chưa đọc',
                          ),
                          trailing: notification.isRead
                              ? null
                              : IconButton(
                                  tooltip: 'Đánh dấu đã đọc',
                                  onPressed: state.busy
                                      ? null
                                      : () => state.readNotification(
                                          notification.id,
                                        ),
                                  icon: const Icon(Icons.done),
                                ),
                          onTap: notification.isRead || state.busy
                              ? null
                              : () => state.readNotification(notification.id),
                        ),
                    ],
                  ),
                ),
        );
      },
    );
  }
}
