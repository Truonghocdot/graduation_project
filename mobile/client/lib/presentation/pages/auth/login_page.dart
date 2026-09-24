import 'package:flutter/material.dart';

import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import 'register_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key, required this.controller});

  final ClientAppController controller;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final phone = TextEditingController();
  final password = TextEditingController();
  String? validationError;

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
                  const Icon(Icons.route, size: 48, color: Color(0xFF146B52)),
                  const SizedBox(height: 16),
                  Text(
                    'Giao hàng & Đặt xe',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Đăng nhập để đặt giao hàng hoặc chuyến xe',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: phone,
                    enabled: !state.busy,
                    keyboardType: TextInputType.phone,
                    autofillHints: const [AutofillHints.telephoneNumber],
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Số điện thoại',
                      prefixIcon: Icon(Icons.phone_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: password,
                    enabled: !state.busy,
                    obscureText: true,
                    autofillHints: const [AutofillHints.password],
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _login(),
                    decoration: const InputDecoration(
                      labelText: 'Mật khẩu',
                      prefixIcon: Icon(Icons.lock_outline),
                    ),
                  ),
                  if (validationError ?? state.error case final error?) ...[
                    const SizedBox(height: 12),
                    ErrorBanner(message: error),
                  ],
                  const SizedBox(height: 20),
                  SizedBox(
                    height: 48,
                    child: FilledButton.icon(
                      key: const Key('login-button'),
                      onPressed: state.busy ? null : _login,
                      icon: state.busy
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.login),
                      label: const Text('Đăng nhập'),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: state.busy
                        ? null
                        : () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => RegisterPage(
                                controller: state,
                                initialPhone: phone.text.trim(),
                              ),
                            ),
                          ),
                    child: const Text('Tạo hoặc xác minh tài khoản'),
                  ),
                  TextButton(
                    onPressed: state.busy ? null : _forgotPassword,
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
    if (phone.text.trim().isEmpty || password.text.isEmpty) {
      setState(() => validationError = 'Nhập số điện thoại và mật khẩu.');
      return;
    }
    setState(() => validationError = null);
    await widget.controller.login(phone.text.trim(), password.text);
  }

  Future<void> _forgotPassword() async {
    if (phone.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Nhập số điện thoại trước.')),
      );
      return;
    }
    await widget.controller.beginPasswordReset(phone.text.trim());
    if (widget.controller.error != null || !mounted) return;
    final code = TextEditingController();
    final nextPassword = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Đặt lại mật khẩu'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: code,
              maxLength: 6,
              keyboardType: TextInputType.number,
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
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cập nhật'),
          ),
        ],
      ),
    );
    if (!mounted) return;
    if (confirmed == true) {
      await widget.controller.resetPassword(
        phone: phone.text.trim(),
        code: code.text.trim(),
        password: nextPassword.text,
      );
    }
    await Future.wait([
      disposeTextControllerAfterRoute(code),
      disposeTextControllerAfterRoute(nextPassword),
    ]);
  }
}
