import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';
import 'driver_forgot_password_page.dart';
import 'driver_register_page.dart';
import 'driver_verification_page.dart';

class DriverLoginPage extends StatefulWidget {
  const DriverLoginPage({super.key, required this.controller});

  final DriverAppController controller;

  @override
  State<DriverLoginPage> createState() => _DriverLoginPageState();
}

class _DriverLoginPageState extends State<DriverLoginPage> {
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
                    key: const Key('driver-register-link'),
                    onPressed: state.busy ? null : _openRegister,
                    child: const Text('Đăng ký đối tác'),
                  ),
                  TextButton(
                    key: const Key('driver-verification-link'),
                    onPressed: state.busy ? null : _openVerification,
                    child: const Text('Xác minh tài khoản'),
                  ),
                  TextButton(
                    key: const Key('driver-forgot-password-link'),
                    onPressed: state.busy ? null : _openForgotPassword,
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
    await widget.controller.authenticate(phone.text.trim(), password.text);
  }

  void _openRegister() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DriverRegisterPage(
          controller: widget.controller,
          initialPhone: phone.text.trim(),
        ),
      ),
    );
  }

  void _openVerification() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DriverVerificationPage(
          controller: widget.controller,
          initialPhone: phone.text.trim(),
        ),
      ),
    );
  }

  void _openForgotPassword() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DriverForgotPasswordPage(
          controller: widget.controller,
          initialPhone: phone.text.trim(),
        ),
      ),
    );
  }
}
