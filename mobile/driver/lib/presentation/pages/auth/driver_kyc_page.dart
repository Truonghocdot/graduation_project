import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';

class DriverKycPage extends StatefulWidget {
  const DriverKycPage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  State<DriverKycPage> createState() => _DriverKycPageState();
}

class _DriverKycPageState extends State<DriverKycPage> {
  final codLimit = TextEditingController(text: '0');
  final plate = TextEditingController();
  final documentNumber = TextEditingController();
  String? vehicleTypeId;
  String? vehicleId;
  String documentType = 'IDENTITY';

  static const documentTypes = [
    'IDENTITY',
    'DRIVER_LICENSE',
    'VEHICLE_REGISTRATION',
    'INSURANCE',
    'PORTRAIT',
    'VEHICLE_PHOTO',
  ];

  @override
  void dispose() {
    codLimit.dispose();
    plate.dispose();
    documentNumber.dispose();
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
            const {'DRAFT', 'REJECTED'}.contains(profile.reviewStatus);
        final vehicles = profile?.vehicles ?? const <Map<String, dynamic>>[];
        vehicleTypeId ??= state.vehicleTypes.firstOrNull?['id'] as String?;
        if (vehicleId == null && vehicles.isNotEmpty) {
          vehicleId = vehicles.first['id'] as String;
        }
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
                      ? const Text('Hoàn thiện hồ sơ để gửi admin xét duyệt.')
                      : Text('Lý do: ${profile!.reviewReason}'),
                ),
              ),
              if (state.error case final error?) ...[
                const SizedBox(height: 12),
                DriverErrorBanner(message: error),
              ],
              if (profile?.reviewStatus == 'PENDING_REVIEW') ...[
                const SizedBox(height: 18),
                const DriverEmptyState(
                  icon: Icons.hourglass_top,
                  title: 'Đang chờ xét duyệt',
                  message: 'Bạn sẽ đăng nhập lại bằng tài khoản tài xế sau khi admin duyệt.',
                ),
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
                Text(
                  '1. Hồ sơ cơ bản',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: codLimit,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Giới hạn COD (VND)',
                  ),
                ),
                const SizedBox(height: 8),
                FilledButton.icon(
                  onPressed: state.busy ? null : _saveProfile,
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Lưu hồ sơ'),
                ),
                if (profile != null) ...[
                  const SizedBox(height: 24),
                  Text(
                    '2. Phương tiện',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 10),
                  for (final vehicle in vehicles)
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.two_wheeler_outlined),
                        title: Text(vehicle['plate_number']?.toString() ?? ''),
                        subtitle: Text(
                          formatDriverValue(
                            vehicle['status']?.toString() ?? '',
                          ),
                        ),
                        trailing: vehicle['is_selected'] == true
                            ? const Icon(
                                Icons.check_circle,
                                color: Color(0xFF215F9A),
                              )
                            : null,
                      ),
                    ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: vehicleTypeId,
                    decoration: const InputDecoration(labelText: 'Loại xe'),
                    items: state.vehicleTypes
                        .map(
                          (type) => DropdownMenuItem<String>(
                            value: type['id'] as String,
                            child: Text(
                              type['name']?.toString() ??
                                  type['key'].toString(),
                            ),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: (value) => setState(() => vehicleTypeId = value),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: plate,
                    decoration: const InputDecoration(labelText: 'Biển số xe'),
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: state.busy ? null : _addVehicle,
                    icon: const Icon(Icons.add),
                    label: const Text('Thêm xe'),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    '3. Giấy tờ',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  for (final document in profile.documents)
                    ListTile(
                      leading: const Icon(Icons.description_outlined),
                      title: Text(
                        formatDriverValue(document['type']?.toString() ?? ''),
                      ),
                      subtitle: Text(
                        formatDriverValue(document['status']?.toString() ?? ''),
                      ),
                    ),
                  DropdownButtonFormField<String>(
                    initialValue: documentType,
                    decoration: const InputDecoration(
                      labelText: 'Loại giấy tờ',
                    ),
                    items: documentTypes
                        .map(
                          (type) => DropdownMenuItem(
                            value: type,
                            child: Text(formatDriverValue(type)),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: (value) =>
                        setState(() => documentType = value ?? 'IDENTITY'),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: documentNumber,
                    decoration: const InputDecoration(
                      labelText: 'Số giấy tờ (nếu có)',
                    ),
                  ),
                  if (_vehicleDocument && vehicles.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      initialValue: vehicleId,
                      decoration: const InputDecoration(
                        labelText: 'Xe của giấy tờ',
                      ),
                      items: vehicles
                          .map(
                            (vehicle) => DropdownMenuItem<String>(
                              value: vehicle['id'] as String,
                              child: Text(vehicle['plate_number'].toString()),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (value) => setState(() => vehicleId = value),
                    ),
                  ],
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: state.busy ? null : _uploadDocument,
                    icon: const Icon(Icons.attach_file),
                    label: const Text('Chọn tệp giấy tờ'),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    '4. Dịch vụ đăng ký',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  CheckboxListTile(
                    title: const Text('Giao hàng'),
                    value: state.selectedServices.contains('DELIVERY'),
                    onChanged: (value) =>
                        state.toggleService('DELIVERY', value ?? false),
                  ),
                  CheckboxListTile(
                    title: const Text('Đặt xe'),
                    value: state.selectedServices.contains('DRIVE'),
                    onChanged: (value) =>
                        state.toggleService('DRIVE', value ?? false),
                  ),
                  if (vehicles.isNotEmpty)
                    DropdownButtonFormField<String>(
                      initialValue: vehicleId,
                      decoration: const InputDecoration(
                        labelText: 'Xe hoạt động',
                      ),
                      items: vehicles
                          .map(
                            (vehicle) => DropdownMenuItem<String>(
                              value: vehicle['id'] as String,
                              child: Text(vehicle['plate_number'].toString()),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (value) => setState(() => vehicleId = value),
                    ),
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 48,
                    child: FilledButton.icon(
                      onPressed:
                          state.busy ||
                              vehicleId == null ||
                              state.selectedServices.isEmpty
                          ? null
                          : _submit,
                      icon: const Icon(Icons.send_outlined),
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

  bool get _vehicleDocument => const {
    'VEHICLE_REGISTRATION',
    'INSURANCE',
    'VEHICLE_PHOTO',
  }.contains(documentType);

  Future<void> _saveProfile() async {
    final amount = double.tryParse(codLimit.text);
    if (amount != null) await widget.controller.saveApplication(amount);
  }

  Future<void> _addVehicle() async {
    if (vehicleTypeId == null || plate.text.trim().isEmpty) return;
    await widget.controller.createVehicle(vehicleTypeId!, plate.text.trim());
  }

  Future<void> _uploadDocument() async {
    if (_vehicleDocument && vehicleId == null) return;
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (file == null) return;
    final size = file.lengthSync() ?? await file.length();
    if (size == null || size > 5 * 1024 * 1024) return;
    await widget.controller.uploadDocument(
      type: documentType,
      name: file.name,
      bytes: await file.readAsBytes(),
      number: documentNumber.text.trim(),
      vehicleId: _vehicleDocument ? vehicleId : null,
    );
  }

  Future<void> _submit() async {
    if (vehicleId != null) {
      await widget.controller.submitApplication(vehicleId!);
    }
  }
}
