import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverVerificationPage extends StatefulWidget {
  const DriverVerificationPage({
    super.key,
    required this.controller,
    this.initialPhone = '',
  });

  final DriverAppController controller;
  final String initialPhone;

  @override
  State<DriverVerificationPage> createState() => _DriverVerificationPageState();
}

class _DriverVerificationPageState extends State<DriverVerificationPage> {
  late final TextEditingController phone;
  final code = TextEditingController();
  String? validationError;
  bool attempted = false;

  @override
  void initState() {
    super.initState();
    phone = TextEditingController(text: widget.initialPhone);
  }

  @override
  void dispose() {
    phone.dispose();
    code.dispose();
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
          appBar: AppBar(title: const Text('Xác minh tài khoản')),
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
                        Icons.verified_user_outlined,
                        size: 48,
                        color: Color(0xFF215F9A),
                      ),
                      const SizedBox(height: 24),
                      TextField(
                        controller: phone,
                        enabled: !state.busy,
                        keyboardType: TextInputType.phone,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Số điện thoại',
                          prefixIcon: Icon(Icons.phone_outlined),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: code,
                        enabled: !state.busy,
                        autofocus: true,
                        maxLength: 6,
                        keyboardType: TextInputType.number,
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) => _verify(),
                        decoration: const InputDecoration(
                          labelText: 'Mã OTP',
                          prefixIcon: Icon(Icons.password_outlined),
                        ),
                      ),
                      if (error != null) ...[
                        const SizedBox(height: 12),
                        DriverErrorBanner(message: error),
                      ],
                      const SizedBox(height: 20),
                      SizedBox(
                        height: 48,
                        child: FilledButton.icon(
                          key: const Key('driver-verify-button'),
                          onPressed: state.busy ? null : _verify,
                          icon: state.busy
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.verified_outlined),
                          label: const Text('Xác minh'),
                        ),
                      ),
                      const SizedBox(height: 8),
                      TextButton.icon(
                        onPressed: state.busy ? null : _resend,
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

  Future<void> _verify() async {
    if (phone.text.trim().isEmpty || code.text.trim().isEmpty) {
      setState(() {
        attempted = true;
        validationError = 'Nhập số điện thoại và mã OTP.';
      });
      return;
    }

    setState(() {
      attempted = true;
      validationError = null;
    });
    await widget.controller.verifyPhone(phone.text.trim(), code.text.trim());
    if (!mounted || widget.controller.error != null) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  Future<void> _resend() async {
    if (phone.text.trim().isEmpty) {
      setState(() {
        attempted = true;
        validationError = 'Nhập số điện thoại để gửi lại mã.';
      });
      return;
    }

    setState(() {
      attempted = true;
      validationError = null;
    });
    await widget.controller.resendPhone(phone.text.trim());
  }
}
