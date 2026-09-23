import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';
import 'driver_verification_page.dart';

class DriverRegisterPage extends StatefulWidget {
  const DriverRegisterPage({
    super.key,
    required this.controller,
    this.initialPhone = '',
  });

  final DriverAppController controller;
  final String initialPhone;

  @override
  State<DriverRegisterPage> createState() => _DriverRegisterPageState();
}

class _DriverRegisterPageState extends State<DriverRegisterPage> {
  final name = TextEditingController();
  late final TextEditingController phone;
  final password = TextEditingController();
  String? validationError;
  bool attempted = false;

  @override
  void initState() {
    super.initState();
    phone = TextEditingController(text: widget.initialPhone);
  }

  @override
  void dispose() {
    name.dispose();
    phone.dispose();
    password.dispose();
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
          appBar: AppBar(title: const Text('Đăng ký đối tác')),
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
                        Icons.person_add_alt_1_outlined,
                        size: 48,
                        color: Color(0xFF215F9A),
                      ),
                      const SizedBox(height: 24),
                      TextField(
                        controller: name,
                        enabled: !state.busy,
                        autofocus: true,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Họ và tên',
                          prefixIcon: Icon(Icons.person_outline),
                        ),
                      ),
                      const SizedBox(height: 12),
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
                        controller: password,
                        enabled: !state.busy,
                        obscureText: true,
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) => _register(),
                        decoration: const InputDecoration(
                          labelText: 'Mật khẩu',
                          prefixIcon: Icon(Icons.lock_outline),
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
                          key: const Key('driver-register-button'),
                          onPressed: state.busy ? null : _register,
                          icon: state.busy
                              ? const SizedBox.square(
                                  dimension: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.person_add_alt_1_outlined),
                          label: const Text('Đăng ký'),
                        ),
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

  Future<void> _register() async {
    if (name.text.trim().isEmpty ||
        phone.text.trim().isEmpty ||
        password.text.isEmpty) {
      setState(() {
        attempted = true;
        validationError = 'Nhập đủ họ tên, số điện thoại và mật khẩu.';
      });
      return;
    }

    setState(() {
      attempted = true;
      validationError = null;
    });
    await widget.controller.register(
      name: name.text.trim(),
      phone: phone.text.trim(),
      password: password.text,
    );
    if (!mounted || widget.controller.error != null) return;

    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => DriverVerificationPage(
          controller: widget.controller,
          initialPhone: phone.text.trim(),
        ),
      ),
    );
  }
}
