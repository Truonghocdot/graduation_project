import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';

class ChatWithDriverPage extends StatefulWidget {
  const ChatWithDriverPage({
    super.key,
    required this.controller,
    required this.request,
  });

  final ClientAppController controller;
  final ServiceRequestSummary request;

  @override
  State<ChatWithDriverPage> createState() => _ChatWithDriverPageState();
}

class _ChatWithDriverPageState extends State<ChatWithDriverPage> {
  final input = TextEditingController();
  List<SupportChatMessage> messages = const [];
  bool loading = true;
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
      appBar: AppBar(title: const Text('Chat với tài xế')),
      body: Column(
        children: [
          if (error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: ErrorBanner(message: error!),
            ),
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : messages.isEmpty
                ? const Center(child: Text('Chưa có tin nhắn.'))
                : ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: messages.length,
                    itemBuilder: (context, index) {
                      final message = messages[index];
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            constraints: const BoxConstraints(maxWidth: 420),
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              border: Border.all(
                                color: const Color(0xFFD9DEDA),
                              ),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  message.senderName,
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelMedium,
                                ),
                                Text(message.body),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
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
    final support = widget.controller.supportGateway;
    if (support == null) return;
    try {
      final loaded = await support.loadChat(
        widget.controller.session,
        widget.request.id,
      );
      if (mounted) {
        setState(() {
          messages = loaded;
          loading = false;
          error = null;
        });
      }
    } catch (exception) {
      if (mounted) {
        setState(() {
          loading = false;
          error = exception.toString();
        });
      }
    }
  }

  Future<void> _send() async {
    final body = input.text.trim();
    final support = widget.controller.supportGateway;
    if (body.isEmpty || support == null) return;
    try {
      await support.sendChat(
        session: widget.controller.session,
        serviceRequestId: widget.request.id,
        body: body,
      );
      input.clear();
      await _load();
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    }
  }
}
