import 'package:flutter/material.dart';

import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({
    super.key,
    required this.controller,
    this.initialPhone = '',
  });

  final ClientAppController controller;
  final String initialPhone;

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final name = TextEditingController();
  late final phone = TextEditingController(text: widget.initialPhone);
  final password = TextEditingController();
  final code = TextEditingController();
  bool awaitingOtp = false;
  String? validationError;

  @override
  void dispose() {
    name.dispose();
    phone.dispose();
    password.dispose();
    code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) => Scaffold(
        appBar: AppBar(
          title: Text(awaitingOtp ? 'Xác minh tài khoản' : 'Tạo tài khoản'),
        ),
        body: SafeArea(
          child: Align(
            alignment: Alignment.topCenter,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: SizedBox(
                width: 480,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (!awaitingOtp) ...[
                      TextField(
                        controller: name,
                        decoration: const InputDecoration(
                          labelText: 'Họ và tên',
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    TextField(
                      controller: phone,
                      keyboardType: TextInputType.phone,
                      enabled: !awaitingOtp,
                      decoration: const InputDecoration(
                        labelText: 'Số điện thoại',
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (!awaitingOtp)
                      TextField(
                        controller: password,
                        obscureText: true,
                        decoration: const InputDecoration(
                          labelText: 'Mật khẩu',
                        ),
                      )
                    else
                      TextField(
                        controller: code,
                        maxLength: 6,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'Mã OTP'),
                      ),
                    if (validationError ?? widget.controller.error
                        case final error?) ...[
                      const SizedBox(height: 12),
                      ErrorBanner(message: error),
                    ],
                    const SizedBox(height: 20),
                    FilledButton.icon(
                      onPressed: widget.controller.busy ? null : _submit,
                      icon: Icon(
                        awaitingOtp
                            ? Icons.verified_outlined
                            : Icons.person_add_outlined,
                      ),
                      label: Text(awaitingOtp ? 'Xác minh' : 'Đăng ký'),
                    ),
                    if (awaitingOtp)
                      TextButton(
                        onPressed: widget.controller.busy
                            ? null
                            : () => widget.controller.resendPhone(
                                phone.text.trim(),
                              ),
                        child: const Text('Gửi lại mã'),
                      )
                    else
                      TextButton(
                        onPressed: () => setState(() => awaitingOtp = true),
                        child: const Text('Tôi đã có mã OTP'),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (awaitingOtp) {
      if (code.text.trim().isEmpty) {
        setState(() => validationError = 'Nhập mã OTP.');
        return;
      }
      await widget.controller.verifyPhone(phone.text.trim(), code.text.trim());
      if (widget.controller.authenticated && mounted) {
        Navigator.pop(context);
      }
      return;
    }
    if (name.text.trim().isEmpty ||
        phone.text.trim().isEmpty ||
        password.text.isEmpty) {
      setState(() => validationError = 'Nhập đủ họ tên, số điện thoại và mật khẩu.');
      return;
    }
    setState(() => validationError = null);
    await widget.controller.register(
      name: name.text.trim(),
      phone: phone.text.trim(),
      password: password.text,
    );
    if (widget.controller.error == null && mounted) {
      setState(() => awaitingOtp = true);
    }
  }
}
