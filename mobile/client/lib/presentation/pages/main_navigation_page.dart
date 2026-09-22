import 'package:flutter/material.dart';

import '../client_app_controller.dart';
import 'home/home_page.dart';
import 'order/order_history_page.dart';
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
      appBar: AppBar(title: Text(titles[index])),
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
