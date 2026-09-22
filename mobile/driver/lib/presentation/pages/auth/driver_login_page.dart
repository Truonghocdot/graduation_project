import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverLoginPage extends StatefulWidget {
  const DriverLoginPage({super.key, required this.controller});

  final DriverAppController controller;

  @override
  State<DriverLoginPage> createState() => _DriverLoginPageState();
}

class _DriverLoginPageState extends State<DriverLoginPage> {
  final phone = TextEditingController();
  final password = TextEditingController();

  @override
  void dispose() {
    phone.dispose();
    password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = widget.controller;
    return Scaffold(
      body: SafeArea(
        child: Align(
          alignment: Alignment.topCenter,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 40, 20, 24),
            child: SizedBox(
              width: 420,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(
                    Icons.local_shipping_outlined,
                    size: 52,
                    color: Color(0xFF215F9A),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Ứng dụng tài xế',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Số điện thoại',
                      prefixIcon: Icon(Icons.phone_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: password,
                    obscureText: true,
                    onSubmitted: (_) => _login(),
                    decoration: const InputDecoration(
                      labelText: 'Mật khẩu',
                      prefixIcon: Icon(Icons.lock_outline),
                    ),
                  ),
                  if (state.error case final error?) ...[
                    const SizedBox(height: 12),
                    DriverErrorBanner(message: error),
                  ],
                  if (state.locationError case final locationError?) ...[
                    const SizedBox(height: 12),
                    DriverErrorBanner(message: locationError),
                  ],
                  const SizedBox(height: 20),
                  SizedBox(
                    height: 48,
                    child: FilledButton.icon(
                      key: const Key('driver-login-button'),
                      onPressed: state.busy ? null : _login,
                      icon: const Icon(Icons.login),
                      label: const Text('Đăng nhập'),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: state.busy ? null : _register,
                    child: const Text('Đăng ký đối tác'),
                  ),
                  TextButton(
                    onPressed: state.busy ? null : _verify,
                    child: const Text('Xác minh tài khoản'),
                  ),
                  TextButton(
                    onPressed: state.busy ? null : _forgot,
                    child: const Text('Quên mật khẩu'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _login() async {
    if (phone.text.trim().isEmpty || password.text.isEmpty) return;
    await widget.controller.authenticate(phone.text.trim(), password.text);
  }

  Future<void> _register() async {
    final name = TextEditingController();
    try {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Đăng ký đối tác'),
          content: TextField(
            controller: name,
            autofocus: true,
            textInputAction: TextInputAction.done,
            decoration: const InputDecoration(labelText: 'Họ và tên'),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Đăng ký'),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
      if (name.text.trim().isEmpty ||
          phone.text.trim().isEmpty ||
          password.text.isEmpty) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Nhập đủ họ tên, số điện thoại và mật khẩu.'),
            ),
          );
        }
        return;
      }
      await widget.controller.register(
        name: name.text.trim(),
        phone: phone.text.trim(),
        password: password.text,
      );
      if (mounted && widget.controller.error == null) await _verify();
    } finally {
      name.dispose();
    }
  }

  Future<void> _verify() async {
    if (phone.text.trim().isEmpty) return;
    final code = TextEditingController();
    try {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Xác minh tài khoản'),
          content: TextField(
            controller: code,
            maxLength: 6,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Mã OTP'),
          ),
          actions: [
            TextButton(
              onPressed: () => widget.controller.resendPhone(phone.text.trim()),
              child: const Text('Gửi lại'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Xác minh'),
            ),
          ],
        ),
      );
      if (confirmed == true) {
        await widget.controller.verifyPhone(
          phone.text.trim(),
          code.text.trim(),
        );
      }
    } finally {
      code.dispose();
    }
  }

  Future<void> _forgot() async {
    if (phone.text.trim().isEmpty) return;
    await widget.controller.beginPasswordReset(phone.text.trim());
    if (widget.controller.error != null || !mounted) return;
    final code = TextEditingController();
    final nextPassword = TextEditingController();
    try {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Đặt lại mật khẩu'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: code,
                maxLength: 6,
                decoration: const InputDecoration(labelText: 'Mã OTP'),
              ),
              TextField(
                controller: nextPassword,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Mật khẩu mới'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Cập nhật'),
            ),
          ],
        ),
      );
      if (confirmed == true) {
        await widget.controller.resetPassword(
          phone: phone.text.trim(),
          code: code.text.trim(),
          password: nextPassword.text,
        );
      }
    } finally {
      code.dispose();
      nextPassword.dispose();
    }
  }
}
