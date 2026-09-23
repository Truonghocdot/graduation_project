import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverForgotPasswordPage extends StatefulWidget {
  const DriverForgotPasswordPage({
    super.key,
    required this.controller,
    this.initialPhone = '',
  });

  final DriverAppController controller;
  final String initialPhone;

  @override
  State<DriverForgotPasswordPage> createState() =>
      _DriverForgotPasswordPageState();
}

class _DriverForgotPasswordPageState extends State<DriverForgotPasswordPage> {
  late final TextEditingController phone;
  final code = TextEditingController();
  final nextPassword = TextEditingController();
  String? validationError;
  bool attempted = false;
  bool resetRequested = false;

  @override
  void initState() {
    super.initState();
    phone = TextEditingController(text: widget.initialPhone);
  }

  @override
  void dispose() {
    phone.dispose();
    code.dispose();
    nextPassword.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) {
        final state = widget.controller;
        final error = attempted ? state.error ?? validationError : null;
        return Scaffold(
          appBar: AppBar(title: const Text('Đặt lại mật khẩu')),
          body: SafeArea(
            top: false,
            child: Align(
              alignment: Alignment.topCenter,
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                child: SizedBox(
                  width: 420,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Icon(
                        Icons.lock_reset_outlined,
                        size: 48,
                        color: Color(0xFF215F9A),
                      ),
                      const SizedBox(height: 24),
                      TextField(
                        controller: phone,
                        enabled: !state.busy,
                        autofocus: true,
                        keyboardType: TextInputType.phone,
                        textInputAction: resetRequested
                            ? TextInputAction.next
                            : TextInputAction.done,
                        onSubmitted: (_) => _submit(),
                        decoration: const InputDecoration(
                          labelText: 'Số điện thoại',
                          prefixIcon: Icon(Icons.phone_outlined),
                        ),
                      ),
                      if (resetRequested) ...[
                        const SizedBox(height: 12),
                        TextField(
                          controller: code,
                          enabled: !state.busy,
                          maxLength: 6,
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            labelText: 'Mã OTP',
                            prefixIcon: Icon(Icons.password_outlined),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: nextPassword,
                          enabled: !state.busy,
                          obscureText: true,
                          textInputAction: TextInputAction.done,
                          onSubmitted: (_) => _submit(),
                          decoration: const InputDecoration(
                            labelText: 'Mật khẩu mới',
                            prefixIcon: Icon(Icons.lock_outline),
                          ),
                        ),
                      ],
                      if (error != null) ...[
                        const SizedBox(height: 12),
                        DriverErrorBanner(message: error),
                      ],
                      const SizedBox(height: 20),
                      SizedBox(
                        height: 48,
                        child: FilledButton.icon(
                          key: const Key('driver-reset-password-button'),
                          onPressed: state.busy ? null : _submit,
                          icon: state.busy
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : Icon(
                                  resetRequested
                                      ? Icons.save_outlined
                                      : Icons.send_outlined,
                                ),
                          label: Text(
                            resetRequested ? 'Cập nhật mật khẩu' : 'Gửi mã OTP',
                          ),
                        ),
                      ),
                      if (resetRequested)
                        TextButton.icon(
                          onPressed: state.busy ? null : _requestReset,
                          icon: const Icon(Icons.refresh),
                          label: const Text('Gửi lại mã'),
                        ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Future<void> _submit() async {
    if (!resetRequested) {
      await _requestReset();
      return;
    }

    if (phone.text.trim().isEmpty ||
        code.text.trim().isEmpty ||
        nextPassword.text.isEmpty) {
      setState(() {
        attempted = true;
        validationError = 'Nhập số điện thoại, mã OTP và mật khẩu mới.';
      });
      return;
    }

    setState(() {
      attempted = true;
      validationError = null;
    });
    await widget.controller.resetPassword(
      phone: phone.text.trim(),
      code: code.text.trim(),
      password: nextPassword.text,
    );
    if (!mounted || widget.controller.error != null) return;
    Navigator.of(context).pop();
  }

  Future<void> _requestReset() async {
    if (phone.text.trim().isEmpty) {
      setState(() {
        attempted = true;
        validationError = 'Nhập số điện thoại để nhận mã OTP.';
      });
      return;
    }

    setState(() {
      attempted = true;
      validationError = null;
    });
    await widget.controller.beginPasswordReset(phone.text.trim());
    if (!mounted || widget.controller.error != null) return;
    setState(() => resetRequested = true);
  }
}
