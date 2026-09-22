import 'package:flutter/material.dart';

import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';

class ChatWithCustomerPage extends StatefulWidget {
  const ChatWithCustomerPage({
    super.key,
    required this.controller,
    required this.offer,
  });
  final DriverAppController controller;
  final DriverOfferSummary offer;

  @override
  State<ChatWithCustomerPage> createState() => _ChatWithCustomerPageState();
}

class _ChatWithCustomerPageState extends State<ChatWithCustomerPage> {
  final input = TextEditingController();
  List<DriverChatMessage> messages = const [];
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    input.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chat với khách hàng')),
      body: Column(
        children: [
          if (error != null)
            Padding(
              padding: const EdgeInsets.all(8),
              child: Text(
                error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          Expanded(
            child: messages.isEmpty
                ? const Center(child: Text('Chưa có tin nhắn.'))
                : ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: messages.length,
                    itemBuilder: (_, index) => ListTile(
                      title: Text(messages[index].senderName),
                      subtitle: Text(messages[index].body),
                    ),
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: input,
                      decoration: const InputDecoration(
                        hintText: 'Nhập tin nhắn',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    tooltip: 'Gửi',
                    onPressed: _send,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _load() async {
    try {
      final loaded = await widget.controller.support?.loadChat(
        widget.controller.session,
        widget.offer.serviceRequestId,
      );
      if (mounted) setState(() => messages = loaded ?? const []);
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    }
  }

  Future<void> _send() async {
    final body = input.text.trim();
    if (body.isEmpty) return;
    try {
      await widget.controller.support?.sendChat(
        session: widget.controller.session,
        serviceRequestId: widget.offer.serviceRequestId,
        body: body,
      );
      input.clear();
      await _load();
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    }
  }
}
