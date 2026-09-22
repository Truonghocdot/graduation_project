import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class JobAction {
  const JobAction({
    required this.value,
    required this.label,
    required this.icon,
    this.evidenceType,
    this.terminal = false,
  });

  final String value;
  final String label;
  final IconData icon;
  final String? evidenceType;
  final bool terminal;
}

class UpdateStatusPage extends StatefulWidget {
  const UpdateStatusPage({
    super.key,
    required this.controller,
    required this.offer,
    required this.action,
  });

  final DriverAppController controller;
  final DriverOfferSummary offer;
  final JobAction action;

  @override
  State<UpdateStatusPage> createState() => _UpdateStatusPageState();
}

class _UpdateStatusPageState extends State<UpdateStatusPage> {
  late final cash = TextEditingController(
    text: widget.offer.customerPayable.toStringAsFixed(0),
  );
  final cod = TextEditingController(text: '0');
  final reason = TextEditingController();
  String? selectedFile;

  @override
  void dispose() {
    cash.dispose();
    cod.dispose();
    reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = widget.controller;
    final needsEvidence = widget.action.evidenceType != null;
    final cashRequired =
        widget.action.terminal && widget.offer.paymentMethod == 'CASH';
    return AnimatedBuilder(
      animation: state,
      builder: (context, _) => Scaffold(
        appBar: AppBar(title: Text(widget.action.label)),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Icon(
              widget.action.icon,
              size: 48,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 14),
            Text(
              'Xác nhận cập nhật trạng thái',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            if (needsEvidence) ...[
              const SizedBox(height: 20),
              OutlinedButton.icon(
                onPressed: state.busy ? null : _pickEvidence,
                icon: const Icon(Icons.add_a_photo_outlined),
                label: Text(selectedFile ?? 'Chọn ảnh hoặc PDF bằng chứng'),
              ),
            ],
            if (cashRequired) ...[
              const SizedBox(height: 12),
              TextField(
                controller: cash,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Tiền mặt đã thu'),
              ),
            ],
            if (widget.action.value == 'deliver') ...[
              const SizedBox(height: 12),
              TextField(
                controller: cod,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'COD đã thu'),
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              controller: reason,
              maxLength: 50,
              decoration: const InputDecoration(
                labelText: 'Lý do ngoài khu vực (nếu có)',
              ),
            ),
            if (state.error case final error?) ...[
              const SizedBox(height: 10),
              DriverErrorBanner(message: error),
            ],
            const SizedBox(height: 16),
            SizedBox(
              height: 48,
              child: FilledButton.icon(
                key: Key('transition-${widget.offer.id}'),
                onPressed: state.busy ? null : _submit,
                icon: Icon(widget.action.icon),
                label: Text(widget.action.label),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickEvidence() async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (file == null) return;
    final size = file.lengthSync() ?? await file.length();
    if (size == null || size > 10 * 1024 * 1024) return;
    await widget.controller.uploadEvidence(
      offer: widget.offer,
      action: widget.action.value,
      name: file.name,
      bytes: await file.readAsBytes(),
    );
    if (mounted && widget.controller.error == null) {
      setState(() => selectedFile = file.name);
    }
  }

  Future<void> _submit() async {
    await widget.controller.advance(
      offer: widget.offer,
      action: widget.action.value,
      cashCollected:
          widget.action.terminal && widget.offer.paymentMethod == 'CASH'
          ? double.tryParse(cash.text)
          : null,
      codCollected: widget.action.value == 'deliver'
          ? double.tryParse(cod.text)
          : null,
      outOfGeofenceReason: reason.text.trim().isEmpty
          ? null
          : reason.text.trim(),
    );
    if (mounted && widget.controller.error == null) Navigator.pop(context);
  }
}
