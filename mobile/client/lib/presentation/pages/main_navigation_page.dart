import 'package:flutter/material.dart';

import '../client_app_controller.dart';
import 'home/home_page.dart';
import 'order/order_history_page.dart';
import 'profile/notifications_page.dart';
import 'profile/profile_page.dart';

class MainNavigationPage extends StatefulWidget {
  const MainNavigationPage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  State<MainNavigationPage> createState() => _MainNavigationPageState();
}

class _MainNavigationPageState extends State<MainNavigationPage> {
  int index = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      HomePage(controller: widget.controller),
      OrderHistoryPage(controller: widget.controller),
      ProfilePage(controller: widget.controller),
    ];
    const titles = ['Trang chủ', 'Đơn hàng', 'Tài khoản'];
    return Scaffold(
      appBar: AppBar(
        title: Text(titles[index]),
        actions: [
          IconButton(
            key: const Key('customer-notifications-button'),
            tooltip: 'Thông báo',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    NotificationsPage(controller: widget.controller),
              ),
            ),
            icon: _NotificationBell(
              unreadCount: widget.controller.unreadNotificationCount,
            ),
          ),
        ],
      ),
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (value) => setState(() => index = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Trang chủ',
          ),
          NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long),
            label: 'Đơn hàng',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Tài khoản',
          ),
        ],
      ),
    );
  }
}

class _NotificationBell extends StatelessWidget {
  const _NotificationBell({required this.unreadCount});

  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    if (unreadCount <= 0) return const Icon(Icons.notifications_none_outlined);

    final label = unreadCount > 99 ? '99+' : unreadCount.toString();
    return SizedBox(
      width: 30,
      height: 30,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          const Center(child: Icon(Icons.notifications_outlined)),
          Positioned(
            top: -2,
            right: -5,
            child: Container(
              constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
              padding: const EdgeInsets.symmetric(horizontal: 3),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.error,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: Theme.of(context).colorScheme.surface,
                  width: 1.5,
                ),
              ),
              child: Text(
                label,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onError,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
