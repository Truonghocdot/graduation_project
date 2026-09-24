import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverKycPage extends StatefulWidget {
  const DriverKycPage({super.key, required this.controller});

  final DriverAppController controller;

  @override
  State<DriverKycPage> createState() => _DriverKycPageState();
}

class _DriverKycPageState extends State<DriverKycPage> {
  final plate = TextEditingController();
  final identityNumber = TextEditingController();
  final licenseNumber = TextEditingController();
  final registrationNumber = TextEditingController();
  final pendingDocuments = <String, _PendingDriverDocument>{};
  String? vehicleTypeId;
  String? editingVehicleTypeId;
  bool editingVehicle = false;
  bool uploadingDocuments = false;
  int uploadedDocumentCount = 0;

  static const _documents = [
    _DriverDocumentField(type: 'IDENTITY', numberLabel: 'Số CCCD'),
    _DriverDocumentField(
      type: 'DRIVER_LICENSE',
      numberLabel: 'Số giấy phép lái xe',
    ),
    _DriverDocumentField(type: 'PORTRAIT'),
    _DriverDocumentField(
      type: 'VEHICLE_REGISTRATION',
      numberLabel: 'Số đăng ký xe',
      belongsToVehicle: true,
    ),
    _DriverDocumentField(type: 'INSURANCE', belongsToVehicle: true),
    _DriverDocumentField(type: 'VEHICLE_PHOTO', belongsToVehicle: true),
  ];

  @override
  void dispose() {
    plate.dispose();
    identityNumber.dispose();
    licenseNumber.dispose();
    registrationNumber.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) {
        final state = widget.controller;
        final profile = state.profile;
        final editable =
            profile == null ||
            const {
              'DRAFT',
              'PENDING_REVIEW',
              'REJECTED',
            }.contains(profile.reviewStatus);
        final vehicle = profile?.vehicles.isNotEmpty == true
            ? profile!.vehicles.first
            : null;
        final vehicleId = vehicle?['id']?.toString();
        final storedVehicleType = vehicle?['vehicle_type'];
        final storedVehicleTypeId = storedVehicleType is Map
            ? storedVehicleType['id']?.toString()
            : null;
        vehicleTypeId ??= state.vehicleTypes.firstOrNull?['id'] as String?;
        editingVehicleTypeId ??= storedVehicleTypeId;
        if (vehicle != null && plate.text.isEmpty) {
          plate.text = vehicle['plate_number']?.toString() ?? '';
        }
        _syncDocumentNumbers(profile);

        return Scaffold(
          appBar: AppBar(
            title: const Text('Hồ sơ đối tác'),
            actions: [
              IconButton(
                tooltip: 'Làm mới',
                onPressed: state.busy ? null : state.refreshApplication,
                icon: const Icon(Icons.refresh),
              ),
              IconButton(
                tooltip: 'Đăng xuất',
                onPressed: state.logout,
                icon: const Icon(Icons.logout),
              ),
            ],
          ),
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: ListTile(
                  leading: const Icon(Icons.badge_outlined),
                  title: Text(
                    'Trạng thái: ${profile == null ? 'Chưa tạo' : formatDriverValue(profile.reviewStatus)}',
                  ),
                  subtitle: profile?.reviewReason == null
                      ? const Text('Hoàn thiện hồ sơ để gửi xét duyệt.')
                      : Text('Lý do: ${profile!.reviewReason}'),
                ),
              ),
              if (state.error case final error?) ...[
                const SizedBox(height: 12),
                DriverErrorBanner(message: error),
              ],
              if (profile?.reviewStatus == 'APPROVED') ...[
                const SizedBox(height: 18),
                const DriverEmptyState(
                  icon: Icons.verified_outlined,
                  title: 'Hồ sơ đã được duyệt',
                  message:
                      'Đăng xuất rồi đăng nhập lại để bắt đầu nhận chuyến.',
                ),
              ],
              if (editable) ...[
                const SizedBox(height: 18),
                if (profile == null)
                  _buildStartProfile(state)
                else ...[
                  _sectionTitle(context, '1. Phương tiện'),
                  const SizedBox(height: 10),
                  if (vehicle == null)
                    _buildVehicleForm(context, state)
                  else if (editingVehicle)
                    _buildVehicleEditForm(context, state, vehicle)
                  else
                    _SelectedVehicle(
                      vehicle: vehicle,
                      onEdit: state.busy
                          ? null
                          : () => setState(() => editingVehicle = true),
                    ),
                  const SizedBox(height: 24),
                  _sectionTitle(context, '2. Giấy tờ bắt buộc'),
                  const SizedBox(height: 4),
                  Text(
                    '${_documentCount(profile)}/${_documents.length} giấy tờ đã sẵn sàng',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  if (uploadingDocuments) ...[
                    const SizedBox(height: 8),
                    LinearProgressIndicator(
                      value: _documents.isEmpty
                          ? null
                          : uploadedDocumentCount / _documents.length,
                    ),
                    const SizedBox(height: 4),
                    Text('Đang tải giấy tờ lên máy chủ…'),
                  ],
                  for (final document in _documents)
                    _DocumentInput(
                      field: document,
                      numberController: _numberController(document.type),
                      pending: pendingDocuments[document.type],
                      uploaded: _uploadedDocument(profile, document.type),
                      enabled:
                          !state.busy &&
                          (!document.belongsToVehicle || vehicleId != null),
                      onSelect: () => _selectDocument(document, vehicleId),
                      onRemove:
                          pendingDocuments.containsKey(document.type) &&
                              !state.busy
                          ? () => setState(
                              () => pendingDocuments.remove(document.type),
                            )
                          : null,
                    ),
                  const SizedBox(height: 24),
                  _sectionTitle(context, '3. Dịch vụ đăng ký'),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Giao hàng'),
                    value: state.selectedServices.contains('DELIVERY'),
                    onChanged: state.busy
                        ? null
                        : (value) =>
                              state.toggleService('DELIVERY', value ?? false),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Đặt xe'),
                    value: state.selectedServices.contains('DRIVE'),
                    onChanged: state.busy
                        ? null
                        : (value) =>
                              state.toggleService('DRIVE', value ?? false),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 48,
                    child: FilledButton.icon(
                      key: const Key('driver-submit-application-button'),
                      onPressed: state.busy ? null : _submit,
                      icon: state.busy
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.send_outlined),
                      label: const Text('Gửi xét duyệt'),
                    ),
                  ),
                ],
              ],
            ],
          ),
        );
      },
    );
  }

  Widget _buildStartProfile(DriverAppController state) {
    return SizedBox(
      height: 48,
      child: FilledButton.icon(
        onPressed: state.busy ? null : _saveProfile,
        icon: const Icon(Icons.assignment_outlined),
        label: const Text('Bắt đầu hồ sơ'),
      ),
    );
  }

  Widget _buildVehicleForm(BuildContext context, DriverAppController state) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<String>(
          initialValue: vehicleTypeId,
          decoration: const InputDecoration(labelText: 'Loại xe'),
          items: state.vehicleTypes
              .map(
                (type) => DropdownMenuItem<String>(
                  value: type['id'] as String,
                  child: Text(
                    type['name']?.toString() ?? type['key'].toString(),
                  ),
                ),
              )
              .toList(growable: false),
          onChanged: state.busy
              ? null
              : (value) => setState(() => vehicleTypeId = value),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: plate,
          enabled: !state.busy,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(labelText: 'Biển số xe'),
        ),
        const SizedBox(height: 8),
        FilledButton.icon(
          onPressed: state.busy ? null : _addVehicle,
          icon: const Icon(Icons.two_wheeler_outlined),
          label: const Text('Lưu phương tiện'),
        ),
      ],
    );
  }

  Widget _buildVehicleEditForm(
    BuildContext context,
    DriverAppController state,
    Map<String, dynamic> vehicle,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<String>(
          initialValue: editingVehicleTypeId,
          decoration: const InputDecoration(labelText: 'Loại xe'),
          items: state.vehicleTypes
              .map(
                (type) => DropdownMenuItem<String>(
                  value: type['id'] as String,
                  child: Text(
                    type['name']?.toString() ?? type['key'].toString(),
                  ),
                ),
              )
              .toList(growable: false),
          onChanged: state.busy
              ? null
              : (value) => setState(() => editingVehicleTypeId = value),
        ),
        const SizedBox(height: 8),
        TextField(
          controller: plate,
          enabled: !state.busy,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(labelText: 'Biển số xe'),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed: state.busy
                    ? null
                    : () {
                        final storedType = vehicle['vehicle_type'];
                        setState(() {
                          editingVehicle = false;
                          editingVehicleTypeId = storedType is Map
                              ? storedType['id']?.toString()
                              : null;
                          plate.text =
                              vehicle['plate_number']?.toString() ?? '';
                        });
                      },
                child: const Text('Hủy'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: FilledButton(
                onPressed: state.busy
                    ? null
                    : () => _updateVehicle(vehicle['id']?.toString()),
                child: const Text('Cập nhật xe'),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _sectionTitle(BuildContext context, String title) =>
      Text(title, style: Theme.of(context).textTheme.titleMedium);

  TextEditingController? _numberController(String type) => switch (type) {
    'IDENTITY' => identityNumber,
    'DRIVER_LICENSE' => licenseNumber,
    'VEHICLE_REGISTRATION' => registrationNumber,
    _ => null,
  };

  Map<String, dynamic>? _uploadedDocument(
    DriverProfileSummary? profile,
    String type,
  ) {
    if (profile == null) return null;
    for (final document in profile.documents) {
      if (document['type']?.toString() == type) return document;
    }
    return null;
  }

  int _documentCount(DriverProfileSummary? profile) => _documents
      .where(
        (document) =>
            pendingDocuments.containsKey(document.type) ||
            _uploadedDocument(profile, document.type) != null,
      )
      .length;

  void _syncDocumentNumbers(DriverProfileSummary? profile) {
    if (profile == null) return;
    for (final document in _documents) {
      final controller = _numberController(document.type);
      final uploaded = _uploadedDocument(profile, document.type);
      final number = uploaded?['document_number']?.toString();
      if (controller != null && controller.text.isEmpty && number != null) {
        controller.text = number;
      }
    }
  }

  Future<void> _saveProfile() => widget.controller.saveApplication();

  Future<void> _addVehicle() async {
    if (vehicleTypeId == null || plate.text.trim().isEmpty) {
      _showMessage('Chọn loại xe và nhập biển số xe.');
      return;
    }
    await widget.controller.createVehicle(vehicleTypeId!, plate.text.trim());
  }

  Future<void> _updateVehicle(String? id) async {
    if (id == null ||
        id.isEmpty ||
        editingVehicleTypeId == null ||
        plate.text.trim().isEmpty) {
      _showMessage('Chọn loại xe và nhập biển số xe.');
      return;
    }
    await widget.controller.updateVehicle(
      id,
      editingVehicleTypeId!,
      plate.text.trim(),
    );
    if (mounted && widget.controller.error == null) {
      setState(() => editingVehicle = false);
    }
  }

  Future<void> _selectDocument(
    _DriverDocumentField document,
    String? vehicleId,
  ) async {
    if (document.belongsToVehicle && vehicleId == null) {
      _showMessage('Lưu phương tiện trước khi chọn giấy tờ xe.');
      return;
    }

    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (file == null) return;
    final size = file.lengthSync() ?? await file.length();
    if (size == null || size > 5 * 1024 * 1024) {
      _showMessage('Tệp giấy tờ không được lớn hơn 5 MB.');
      return;
    }

    final bytes = await file.readAsBytes();
    if (!mounted) return;
    setState(() {
      pendingDocuments[document.type] = _PendingDriverDocument(
        type: document.type,
        name: file.name,
        bytes: bytes,
        number: _numberController(document.type)?.text.trim() ?? '',
        vehicleId: document.belongsToVehicle ? vehicleId : null,
      );
    });
  }

  Future<void> _submit() async {
    final profile = widget.controller.profile;
    final vehicle = profile?.vehicles.isNotEmpty == true
        ? profile!.vehicles.first
        : null;
    final vehicleId = vehicle?['id']?.toString();
    if (profile == null || vehicleId == null || vehicleId.isEmpty) {
      _showMessage('Cần lưu một phương tiện trước khi gửi xét duyệt.');
      return;
    }
    if (widget.controller.selectedServices.isEmpty) {
      _showMessage('Chọn ít nhất một dịch vụ đăng ký.');
      return;
    }

    final missingNumbers = _documents
        .where(
          (document) =>
              document.numberLabel != null &&
              (_numberController(document.type)?.text.trim().isEmpty ?? true),
        )
        .toList(growable: false);
    if (missingNumbers.isNotEmpty) {
      _showMessage(
        'Nhập số giấy tờ cho: ${missingNumbers.map((item) => formatDriverValue(item.type)).join(', ')}.',
      );
      return;
    }

    final missingFiles = _documents
        .where(
          (document) =>
              !pendingDocuments.containsKey(document.type) &&
              _uploadedDocument(profile, document.type) == null,
        )
        .toList(growable: false);
    if (missingFiles.isNotEmpty) {
      _showMessage(
        'Chọn đủ 6 giấy tờ: ${missingFiles.map((item) => formatDriverValue(item.type)).join(', ')}.',
      );
      return;
    }

    final pending = List<_PendingDriverDocument>.of(pendingDocuments.values);
    if (pending.isNotEmpty) {
      setState(() {
        uploadingDocuments = true;
        uploadedDocumentCount = _documentCount(profile) - pending.length;
      });
    }
    try {
      for (final document in pending) {
        await widget.controller.uploadDocument(
          type: document.type,
          name: document.name,
          bytes: document.bytes,
          number: _numberController(document.type)?.text.trim() ?? document.number,
          vehicleId: document.vehicleId,
        );
        if (!mounted || widget.controller.error != null) return;
        setState(() {
          pendingDocuments.remove(document.type);
          uploadedDocumentCount++;
        });
      }

      await widget.controller.submitApplication(vehicleId);
    } finally {
      if (mounted) setState(() => uploadingDocuments = false);
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }
}

class _DriverDocumentField {
  const _DriverDocumentField({
    required this.type,
    this.numberLabel,
    this.belongsToVehicle = false,
  });

  final String type;
  final String? numberLabel;
  final bool belongsToVehicle;
}

class _PendingDriverDocument {
  const _PendingDriverDocument({
    required this.type,
    required this.name,
    required this.bytes,
    required this.number,
    required this.vehicleId,
  });

  final String type;
  final String name;
  final Uint8List bytes;
  final String number;
  final String? vehicleId;

  bool get isPdf => name.toLowerCase().endsWith('.pdf');
}

class _SelectedVehicle extends StatelessWidget {
  const _SelectedVehicle({required this.vehicle, this.onEdit});

  final Map<String, dynamic> vehicle;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.two_wheeler_outlined),
      title: Text(vehicle['plate_number']?.toString() ?? ''),
      subtitle: Text(formatDriverValue(vehicle['status']?.toString() ?? '')),
      trailing: IconButton(
        tooltip: 'Chỉnh sửa phương tiện',
        onPressed: onEdit,
        icon: const Icon(Icons.edit_outlined),
      ),
    );
  }
}

class _DocumentInput extends StatelessWidget {
  const _DocumentInput({
    required this.field,
    required this.numberController,
    required this.pending,
    required this.uploaded,
    required this.enabled,
    required this.onSelect,
    required this.onRemove,
  });

  final _DriverDocumentField field;
  final TextEditingController? numberController;
  final _PendingDriverDocument? pending;
  final Map<String, dynamic>? uploaded;
  final bool enabled;
  final VoidCallback onSelect;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final preview = pending;
    final status = uploaded?['status']?.toString();

    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            formatDriverValue(field.type),
            style: Theme.of(context).textTheme.titleSmall,
          ),
          if (numberController != null) ...[
            const SizedBox(height: 8),
            TextField(
              controller: numberController,
              enabled: enabled,
              decoration: InputDecoration(labelText: field.numberLabel),
            ),
          ],
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: enabled ? onSelect : null,
            icon: const Icon(Icons.attach_file),
            label: Text(preview == null ? 'Chọn tệp' : 'Chọn tệp khác'),
          ),
          if (preview != null) ...[
            const SizedBox(height: 8),
            _LocalDocumentPreview(document: preview, onRemove: onRemove),
          ] else if (status != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text('Đã tải lên: ${formatDriverValue(status)}'),
            ),
          const Divider(height: 24),
        ],
      ),
    );
  }
}

class _LocalDocumentPreview extends StatelessWidget {
  const _LocalDocumentPreview({required this.document, this.onRemove});

  final _PendingDriverDocument document;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: SizedBox(
        width: 56,
        height: 56,
        child: document.isPdf
            ? const Icon(Icons.picture_as_pdf_outlined, size: 32)
            : ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: Image.memory(
                  document.bytes,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) =>
                      const Icon(Icons.insert_drive_file_outlined),
                ),
              ),
      ),
      title: Text(document.name),
      subtitle: const Text('Đã chọn trên thiết bị'),
      trailing: IconButton(
        tooltip: 'Bỏ tệp',
        onPressed: onRemove,
        icon: const Icon(Icons.close),
      ),
    );
  }
}
