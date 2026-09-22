import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import 'api/device_location.dart';
import 'api/driver_api.dart';
import 'api/driver_realtime.dart';
import 'api/request_id.dart';
import 'api/session_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  const sessionStore = SecureDriverSessionStore();
  final savedToken = await sessionStore.readToken();
  final deviceId = await sessionStore.installationId();
  final onboarding = await sessionStore.readOnboarding();
  const configured = String.fromEnvironment('API_BASE_URL');
  final defaultUrl = defaultTargetPlatform == TargetPlatform.android
      ? 'http://10.0.2.2:8000/api/v1'
      : 'http://127.0.0.1:8000/api/v1';
  runApp(
    DriverApp(
      gateway: DriverApi(deviceId: deviceId),
      sessionStore: sessionStore,
      realtime: DriverRealtime(),
      initialSession: DriverSession(
        baseUrl: configured.isEmpty ? defaultUrl : configured,
        token: savedToken ?? const String.fromEnvironment('API_TOKEN'),
        onboarding: onboarding,
      ),
    ),
  );
}

class DriverApp extends StatelessWidget {
  const DriverApp({
    super.key,
    required this.gateway,
    required this.initialSession,
    this.sessionStore,
    this.realtime,
    this.locationSource,
  });

  final DriverGateway gateway;
  final DriverSession initialSession;
  final DriverSessionStore? sessionStore;
  final DriverRealtime? realtime;
  final DriverLocationSource? locationSource;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: const ColorScheme.light(
          primary: Color(0xFF215F9A),
          onPrimary: Colors.white,
          secondary: Color(0xFFB35C21),
          surface: Color(0xFFF6F7F8),
          onSurface: Color(0xFF202428),
        ),
        useMaterial3: true,
        cardTheme: const CardThemeData(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(8)),
            side: BorderSide(color: Color(0xFFD8DDE2)),
          ),
        ),
        inputDecorationTheme: const InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(6)),
          ),
        ),
      ),
      home: DriverOfferInbox(
        gateway: gateway,
        initialSession: initialSession,
        sessionStore: sessionStore,
        realtime: realtime,
        locationSource: locationSource ?? DeviceLocationSource(),
      ),
    );
  }
}

class DriverOfferInbox extends StatefulWidget {
  const DriverOfferInbox({
    super.key,
    required this.gateway,
    required this.initialSession,
    this.sessionStore,
    this.realtime,
    required this.locationSource,
  });

  final DriverGateway gateway;
  final DriverSession initialSession;
  final DriverSessionStore? sessionStore;
  final DriverRealtime? realtime;
  final DriverLocationSource locationSource;

  @override
  State<DriverOfferInbox> createState() => _DriverOfferInboxState();
}

class _DriverOfferInboxState extends State<DriverOfferInbox> {
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _codLimit = TextEditingController(text: '0');
  final _plate = TextEditingController();
  final _documentNumber = TextEditingController();
  late DriverSession _session = widget.initialSession;
  List<DriverOfferSummary> _offers = const [];
  DriverProfileSummary? _profile;
  List<Map<String, dynamic>> _vehicleTypes = const [];
  String? _vehicleTypeId;
  String? _documentType;
  String? _selectedVehicleId;
  bool _delivery = true;
  bool _drive = false;
  final _evidenceByOffer = <String, String>{};
  final _transitionKeys = <String, String>{};
  final _offerActionKeys = <String, String>{};
  Timer? _poller;
  Timer? _locationPoller;
  bool _busy = false;
  bool _locationBusy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (_session.token.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _restoreSession());
    }
  }

  @override
  void dispose() {
    _poller?.cancel();
    _locationPoller?.cancel();
    widget.realtime?.dispose();
    _phone.dispose();
    _password.dispose();
    _codLimit.dispose();
    _plate.dispose();
    _documentNumber.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _session.token.isEmpty
              ? 'Đăng nhập tài xế'
              : _session.onboarding
              ? 'Hồ sơ đối tác'
              : 'Đề nghị mới',
        ),
        actions: [
          if (_session.token.isNotEmpty && !_session.onboarding)
            if (widget.gateway is DriverSupportGateway)
              IconButton(
                tooltip: 'Thông báo',
                onPressed: _busy ? null : _showNotifications,
                icon: const Icon(Icons.notifications_outlined),
              ),
          if (_session.token.isNotEmpty && !_session.onboarding)
            IconButton(
              tooltip: 'Thu nhập và rút tiền',
              onPressed: _busy ? null : _showEarnings,
              icon: const Icon(Icons.account_balance_wallet_outlined),
            ),
          if (_session.token.isNotEmpty && !_session.onboarding)
            PopupMenuButton<String>(
              tooltip: 'Thêm',
              enabled: !_busy,
              icon: const Icon(Icons.more_vert),
              onSelected: (value) {
                switch (value) {
                  case 'support':
                    _showTickets();
                  case 'refresh':
                    _loadOffers();
                  case 'logout':
                    _logout();
                }
              },
              itemBuilder: (context) => [
                if (widget.gateway is DriverSupportGateway)
                  const PopupMenuItem(
                    value: 'support',
                    child: Text('Yêu cầu hỗ trợ'),
                  ),
                const PopupMenuItem(value: 'refresh', child: Text('Làm mới')),
                const PopupMenuItem(value: 'logout', child: Text('Đăng xuất')),
              ],
            ),
          if (_session.token.isNotEmpty && _session.onboarding)
            IconButton(
              tooltip: 'Đăng xuất',
              onPressed: _logout,
              icon: const Icon(Icons.logout),
            ),
        ],
      ),
      body: SafeArea(
        child: _session.token.isEmpty
            ? _login()
            : _session.onboarding
            ? _onboardingView()
            : _offerList(),
      ),
    );
  }

  Widget _login() {
    return Align(
      alignment: Alignment.topCenter,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: SizedBox(
          width: 420,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 24),
              const Icon(Icons.local_shipping_outlined, size: 44),
              const SizedBox(height: 24),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Số điện thoại',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _password,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Mật khẩu',
                  prefixIcon: Icon(Icons.lock_outline),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 20),
              SizedBox(
                height: 48,
                child: FilledButton.icon(
                  key: const Key('driver-login-button'),
                  onPressed: _busy ? null : _signIn,
                  icon: const Icon(Icons.login),
                  label: const Text('Đăng nhập'),
                ),
              ),
              if (widget.gateway is DriverOperationsGateway)
                Column(
                  children: [
                    TextButton(
                      onPressed: _busy ? null : _register,
                      child: const Text('Đăng ký đối tác'),
                    ),
                    TextButton(
                      onPressed: _busy ? null : _verifyPhone,
                      child: const Text('Xác minh tài khoản'),
                    ),
                    TextButton(
                      onPressed: _busy ? null : _forgotPassword,
                      child: const Text('Quên mật khẩu'),
                    ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _onboardingView() {
    final profile = _profile;
    final editable =
        profile == null ||
        profile.reviewStatus == 'DRAFT' ||
        profile.reviewStatus == 'REJECTED';
    final vehicles = profile?.vehicles ?? const <Map<String, dynamic>>[];
    final selected =
        vehicles.any((vehicle) => vehicle['id'] == _selectedVehicleId)
        ? _selectedVehicleId
        : null;
    const types = [
      'IDENTITY',
      'DRIVER_LICENSE',
      'VEHICLE_REGISTRATION',
      'INSURANCE',
      'PORTRAIT',
      'VEHICLE_PHOTO',
    ];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Đăng ký tài xế', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        Text('Trạng thái: ${profile?.reviewStatus ?? 'Chưa tạo hồ sơ'}'),
        if (profile?.reviewReason case final reason?) Text('Lý do: $reason'),
        Align(
          alignment: Alignment.centerLeft,
          child: IconButton(
            tooltip: 'Làm mới hồ sơ',
            onPressed: _busy ? null : _loadApplication,
            icon: const Icon(Icons.refresh),
          ),
        ),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        if (profile?.reviewStatus == 'APPROVED') ...[
          const Text(
            'Hồ sơ đã được duyệt. Đăng xuất và đăng nhập lại để nhận chuyến.',
          ),
          FilledButton.icon(
            onPressed: _logout,
            icon: const Icon(Icons.login),
            label: const Text('Đăng nhập tài xế'),
          ),
        ],
        if (editable) ...[
          const SizedBox(height: 12),
          TextField(
            controller: _codLimit,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Giới hạn COD (VND)'),
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: _busy ? null : _saveApplication,
            icon: const Icon(Icons.save_outlined),
            label: const Text('Lưu hồ sơ'),
          ),
          if (profile != null) ...[
            const SizedBox(height: 20),
            Text('Phương tiện', style: Theme.of(context).textTheme.titleMedium),
            for (final vehicle in vehicles)
              ListTile(
                title: Text(vehicle['plate_number']?.toString() ?? ''),
                subtitle: Text(vehicle['status']?.toString() ?? ''),
                trailing: vehicle['is_selected'] == true
                    ? const Icon(Icons.check_circle_outline)
                    : null,
              ),
            if (_vehicleTypes.isNotEmpty)
              DropdownButtonFormField<String>(
                initialValue: _vehicleTypeId,
                decoration: const InputDecoration(labelText: 'Loại xe'),
                items: _vehicleTypes
                    .map(
                      (type) => DropdownMenuItem<String>(
                        value: type['id'] as String,
                        child: Text(
                          type['name']?.toString() ?? type['key'].toString(),
                        ),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (value) => setState(() => _vehicleTypeId = value),
              ),
            const SizedBox(height: 8),
            TextField(
              controller: _plate,
              decoration: const InputDecoration(labelText: 'Biển số xe'),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _busy ? null : _addVehicle,
              icon: const Icon(Icons.add),
              label: const Text('Thêm xe'),
            ),
            const SizedBox(height: 20),
            Text('Giấy tờ', style: Theme.of(context).textTheme.titleMedium),
            for (final document in profile.documents)
              ListTile(
                title: Text(document['type']?.toString() ?? ''),
                subtitle: Text(document['status']?.toString() ?? ''),
              ),
            DropdownButtonFormField<String>(
              initialValue: _documentType,
              decoration: const InputDecoration(labelText: 'Loại giấy tờ'),
              items: types
                  .map(
                    (type) => DropdownMenuItem(value: type, child: Text(type)),
                  )
                  .toList(growable: false),
              onChanged: (value) => setState(() => _documentType = value),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _documentNumber,
              decoration: const InputDecoration(
                labelText: 'Số giấy tờ (nếu có)',
              ),
            ),
            if (const [
                  'VEHICLE_REGISTRATION',
                  'INSURANCE',
                  'VEHICLE_PHOTO',
                ].contains(_documentType) &&
                vehicles.isNotEmpty) ...[
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                initialValue: selected,
                decoration: const InputDecoration(labelText: 'Xe của giấy tờ'),
                items: vehicles
                    .map(
                      (vehicle) => DropdownMenuItem<String>(
                        value: vehicle['id'] as String,
                        child: Text(vehicle['plate_number'].toString()),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (value) =>
                    setState(() => _selectedVehicleId = value),
              ),
            ],
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _busy ? null : _addDocument,
              icon: const Icon(Icons.attach_file),
              label: const Text('Tải giấy tờ'),
            ),
            const SizedBox(height: 20),
            Text(
              'Dịch vụ đăng ký',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            CheckboxListTile(
              title: const Text('Delivery'),
              value: _delivery,
              onChanged: (value) => setState(() => _delivery = value ?? false),
            ),
            CheckboxListTile(
              title: const Text('Drive'),
              value: _drive,
              onChanged: (value) => setState(() => _drive = value ?? false),
            ),
            DropdownButtonFormField<String>(
              initialValue: selected,
              decoration: const InputDecoration(labelText: 'Xe hoạt động'),
              items: vehicles
                  .map(
                    (vehicle) => DropdownMenuItem<String>(
                      value: vehicle['id'] as String,
                      child: Text(vehicle['plate_number'].toString()),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) => setState(() => _selectedVehicleId = value),
            ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _busy ? null : _submitApplication,
              icon: const Icon(Icons.send_outlined),
              label: const Text('Gửi xét duyệt'),
            ),
          ],
        ],
      ],
    );
  }

  List<String> get _serviceTypes => [
    if (_delivery) 'DELIVERY',
    if (_drive) 'DRIVE',
  ];

  Future<void> _loadApplication() async {
    await _run(_loadApplicationDirect);
  }

  Future<void> _saveApplication() async {
    final limit = double.tryParse(_codLimit.text.trim());
    if (limit == null) {
      setState(() => _error = 'Giới hạn COD không hợp lệ.');
      return;
    }
    await _run(() async {
      final profile = await (widget.gateway as DriverOperationsGateway)
          .saveApplication(_session, limit);
      if (mounted) setState(() => _profile = profile);
    });
    if (_error == null && mounted) await _loadApplication();
  }

  Future<void> _addVehicle() async {
    if (_vehicleTypeId == null || _plate.text.trim().isEmpty) return;
    await _run(
      () => (widget.gateway as DriverOperationsGateway).createVehicle(
        session: _session,
        vehicleTypeId: _vehicleTypeId!,
        plateNumber: _plate.text.trim(),
      ),
    );
    if (_error == null && mounted) await _loadApplication();
  }

  Future<void> _addDocument() async {
    if (_documentType == null) return;
    final vehicleDocument = const [
      'VEHICLE_REGISTRATION',
      'INSURANCE',
      'VEHICLE_PHOTO',
    ].contains(_documentType);
    if (vehicleDocument && _selectedVehicleId == null) {
      setState(() => _error = 'Chọn xe cho giấy tờ này.');
      return;
    }
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (file == null) return;
    final size = file.lengthSync() ?? await file.length();
    if (size == null || size > 5 * 1024 * 1024) {
      setState(
        () => _error = 'Không xác định được kích thước hoặc tệp lớn hơn 5 MB.',
      );
      return;
    }
    await _run(() async {
      await (widget.gateway as DriverOperationsGateway).uploadDocument(
        session: _session,
        documentType: _documentType!,
        name: file.name,
        bytes: await file.readAsBytes(),
        documentNumber: _documentNumber.text.trim(),
        vehicleId: vehicleDocument ? _selectedVehicleId : null,
      );
    });
    if (_error == null && mounted) await _loadApplication();
  }

  Future<void> _submitApplication() async {
    if (_selectedVehicleId == null || _serviceTypes.isEmpty) {
      setState(() => _error = 'Chọn xe và ít nhất một dịch vụ.');
      return;
    }
    await _run(
      () => (widget.gateway as DriverOperationsGateway).submitApplication(
        session: _session,
        vehicleId: _selectedVehicleId!,
        serviceTypes: _serviceTypes,
      ),
    );
    if (_error == null && mounted) await _loadApplication();
  }

  Widget _offerList() {
    return Column(
      children: [
        if (widget.gateway is DriverOperationsGateway)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    'Nhận chuyến: ${_profile?.availabilityStatus ?? 'OFFLINE'}',
                  ),
                ),
                Switch.adaptive(
                  value: _profile?.availabilityStatus == 'ONLINE',
                  onChanged: _busy ? null : (value) => _setAvailability(value),
                ),
              ],
            ),
          ),
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _loadOffers,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _offers.isEmpty ? 1 : _offers.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                if (_offers.isEmpty) {
                  return const Padding(
                    padding: EdgeInsets.only(top: 100),
                    child: Center(child: Text('Chưa có đề nghị phù hợp.')),
                  );
                }
                return _offerCard(_offers[index]);
              },
            ),
          ),
        ),
      ],
    );
  }

  Widget _offerCard(DriverOfferSummary offer) {
    final expired =
        offer.status == 'PENDING' && !offer.expiresAt.isAfter(DateTime.now());
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(
                  offer.serviceType == 'DELIVERY'
                      ? Icons.inventory_2_outlined
                      : Icons.directions_car_outlined,
                ),
                const SizedBox(width: 8),
                Text(
                  offer.serviceType,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const Spacer(),
                Text(offer.status),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              'Cách điểm đón ${(offer.pickupDistanceMeters / 1000).toStringAsFixed(1)} km',
            ),
            Text(
              'Thu nhập dự kiến ${offer.estimatedEarning.toStringAsFixed(0)} VND',
            ),
            if (expired)
              Text(
                'Đề nghị đã hết hạn',
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            if (offer.status == 'ACCEPTED') ...[
              const SizedBox(height: 8),
              Text(
                'Tiến trình: ${offer.serviceStatus}',
                style: Theme.of(context).textTheme.labelLarge,
              ),
            ],
            const SizedBox(height: 16),
            if (offer.status == 'PENDING')
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: !expired && !_busy
                          ? () => _respond(offer, 'decline')
                          : null,
                      icon: const Icon(Icons.close),
                      label: const Text('Từ chối'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton.icon(
                      key: Key('accept-${offer.id}'),
                      onPressed: !expired && !_busy
                          ? () => _respond(offer, 'accept')
                          : null,
                      icon: const Icon(Icons.check),
                      label: const Text('Nhận chuyến'),
                    ),
                  ),
                ],
              )
            else if (_nextAction(offer) case final action?)
              Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (widget.gateway is DriverOperationsGateway &&
                      (action.value == 'pickup' ||
                          action.value == 'deliver')) ...[
                    OutlinedButton.icon(
                      onPressed: _busy
                          ? null
                          : () => _uploadProof(offer, action.value),
                      icon: const Icon(Icons.add_a_photo_outlined),
                      label: Text(
                        _evidenceByOffer.containsKey(
                              '${offer.id}:${action.value}',
                            )
                            ? 'Đã tải bằng chứng'
                            : 'Tải bằng chứng',
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                  FilledButton.icon(
                    key: Key('transition-${offer.id}'),
                    onPressed: _busy ? null : () => _advance(offer, action),
                    icon: Icon(action.icon),
                    label: Text(action.label),
                  ),
                ],
              ),
            if (offer.status == 'ACCEPTED' &&
                widget.gateway is DriverSupportGateway) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (!const {
                    'DELIVERED',
                    'TRIP_ENDED',
                    'COMPLETED',
                  }.contains(offer.serviceStatus))
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _showChat(offer),
                      icon: const Icon(Icons.chat_bubble_outline),
                      label: const Text('Chat'),
                    ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _openSupport(offer),
                    icon: const Icon(Icons.support_agent),
                    label: const Text('Hỗ trợ'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _reportIncident(offer),
                    icon: const Icon(Icons.sos_outlined),
                    label: const Text('SOS'),
                  ),
                  if (offer.serviceStatus == 'DELIVERED' ||
                      offer.serviceStatus == 'TRIP_ENDED' ||
                      offer.serviceStatus == 'COMPLETED')
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _rate(offer),
                      icon: const Icon(Icons.star_outline),
                      label: const Text('Đánh giá'),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _signIn() async {
    if (_phone.text.trim().isEmpty || _password.text.isEmpty) {
      setState(() => _error = 'Vui lòng nhập số điện thoại và mật khẩu.');
      return;
    }
    await _run(() async {
      if (widget.gateway is DriverOperationsGateway) {
        final result = await (widget.gateway as DriverOperationsGateway)
            .authenticate(
              baseUrl: _session.baseUrl,
              phone: _phone.text.trim(),
              password: _password.text,
            );
        _session = _session.copyWith(
          token: result.token,
          onboarding: result.onboarding,
        );
        await widget.sessionStore?.save(
          result.token,
          onboarding: result.onboarding,
        );
        if (result.onboarding) {
          await _loadApplicationDirect();
        } else {
          _profile = await (widget.gateway as DriverOperationsGateway)
              .loadApplication(_session);
          _updateCapabilities();
          await _loadOffersDirect();
          _startPolling();
          _connectRealtime();
        }
      } else {
        final token = await widget.gateway.login(
          baseUrl: _session.baseUrl,
          phone: _phone.text.trim(),
          password: _password.text,
        );
        _session = _session.copyWith(token: token);
        await _loadOffersDirect();
        _startPolling();
      }
    });
  }

  Future<void> _register() async {
    final operations = widget.gateway as DriverOperationsGateway;
    final name = TextEditingController();
    final confirm = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Đăng ký đối tác'),
        content: SizedBox(
          width: 400,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Họ tên'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: confirm,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Xác nhận mật khẩu',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Tiếp tục'),
          ),
        ],
      ),
    );
    if (submit == true) {
      if (_phone.text.trim().isEmpty ||
          name.text.trim().isEmpty ||
          _password.text.isEmpty ||
          _password.text != confirm.text) {
        setState(
          () => _error = 'Nhập số điện thoại, họ tên và mật khẩu trùng khớp.',
        );
      } else {
        await _run(
          () => operations.register(
            baseUrl: _session.baseUrl,
            name: name.text.trim(),
            phone: _phone.text.trim(),
            password: _password.text,
          ),
        );
        if (_error == null && mounted) await _verifyPhone();
      }
    }
    name.dispose();
    confirm.dispose();
  }

  Future<void> _verifyPhone() async {
    if (_phone.text.trim().isEmpty) {
      setState(() => _error = 'Nhập số điện thoại để xác minh.');
      return;
    }
    final operations = widget.gateway as DriverOperationsGateway;
    final code = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Xác minh số điện thoại'),
        content: TextField(
          controller: code,
          maxLength: 6,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Mã OTP'),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              try {
                await operations.resendPhone(
                  baseUrl: _session.baseUrl,
                  phone: _phone.text.trim(),
                );
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Đã yêu cầu gửi lại mã.')),
                  );
                }
              } catch (error) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context)
                      .showSnackBar(SnackBar(content: Text(error.toString())));
                }
              }
            },
            child: const Text('Gửi lại'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Xác minh'),
          ),
        ],
      ),
    );
    if (submit == true && code.text.trim().length == 6) {
      await _run(() async {
        final token = await operations.verifyPhone(
          baseUrl: _session.baseUrl,
          phone: _phone.text.trim(),
          code: code.text.trim(),
        );
        _session = _session.copyWith(token: token, onboarding: true);
        await widget.sessionStore?.save(token, onboarding: true);
        await _loadApplicationDirect();
      });
    }
    code.dispose();
  }

  Future<void> _forgotPassword() async {
    final operations = widget.gateway as DriverOperationsGateway;
    if (_phone.text.trim().isEmpty) {
      setState(() => _error = 'Nhập số điện thoại trước khi đặt lại mật khẩu.');
      return;
    }
    await _run(
      () => operations.forgotPassword(
        baseUrl: _session.baseUrl,
        phone: _phone.text.trim(),
      ),
    );
    if (_error != null || !mounted) return;
    final code = TextEditingController();
    final password = TextEditingController();
    final confirm = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Đặt lại mật khẩu'),
        content: SizedBox(
          width: 400,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: code,
                maxLength: 6,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Mã OTP'),
              ),
              TextField(
                controller: password,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Mật khẩu mới'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: confirm,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Xác nhận mật khẩu',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
    if (submit == true) {
      if (code.text.trim().length != 6 ||
          password.text.isEmpty ||
          password.text != confirm.text) {
        setState(() => _error = 'Mã OTP hoặc xác nhận mật khẩu không hợp lệ.');
      } else {
        await _run(() async {
          final resetToken = await operations.verifyReset(
            baseUrl: _session.baseUrl,
            phone: _phone.text.trim(),
            code: code.text.trim(),
          );
          await operations.resetPassword(
            baseUrl: _session.baseUrl,
            phone: _phone.text.trim(),
            resetToken: resetToken,
            password: password.text,
          );
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Mật khẩu đã cập nhật. Hãy đăng nhập.'),
              ),
            );
          }
        });
      }
    }
    code.dispose();
    password.dispose();
    confirm.dispose();
  }

  Future<void> _restoreSession() async {
    if (widget.gateway is DriverOperationsGateway) {
      await _run(() async {
        final operations = widget.gateway as DriverOperationsGateway;
        await operations.validateSession(_session);
        if (_session.onboarding) {
          await _loadApplicationDirect();
        } else {
          _profile = await operations.loadApplication(_session);
          _updateCapabilities();
          await _loadOffersDirect();
          _startPolling();
          _connectRealtime();
        }
      });
    } else {
      _startPolling();
    }
  }

  Future<void> _loadApplicationDirect() async {
    final operations = widget.gateway as DriverOperationsGateway;
    final profile = await operations.loadApplication(_session);
    final vehicleTypes = await operations.loadVehicleTypes(_session);
    if (!mounted) return;
    setState(() {
      _profile = profile;
      _vehicleTypes = vehicleTypes;
      _vehicleTypeId ??= vehicleTypes.isEmpty
          ? null
          : vehicleTypes.first['id'] as String;
      _selectedVehicleId =
          profile?.vehicles.any(
                (vehicle) => vehicle['id'] == _selectedVehicleId,
              ) ==
              true
          ? _selectedVehicleId
          : profile?.vehicles.firstOrNull?['id'] as String?;
      if (profile != null && profile.capabilities.isNotEmpty) {
        _updateCapabilities();
      }
    });
  }

  void _updateCapabilities() {
    final profile = _profile;
    if (profile == null || profile.capabilities.isEmpty) return;
    _delivery = profile.capabilities.contains('DELIVERY');
    _drive = profile.capabilities.contains('DRIVE');
  }

  void _connectRealtime() {
    if (widget.realtime == null) return;
    const configured = String.fromEnvironment('REALTIME_URL');
    final uri = Uri.parse(_session.baseUrl);
    final url = configured.isNotEmpty
        ? configured
        : uri.replace(port: 3000, path: '', query: '', fragment: '').toString();
    widget.realtime!.connect(
      url: url,
      token: _session.token,
      onChange: () => unawaited(_loadOffers(silent: true)),
    );
    widget.realtime!.watch(
      _offers
          .where((offer) => offer.status == 'ACCEPTED')
          .firstOrNull
          ?.serviceRequestId,
    );
  }

  Future<void> _setAvailability(bool online) async {
    await _run(() async {
      final position = online
          ? await widget.locationSource.current()
          : const DriverPosition(0, 0, 0);
      final profile = await (widget.gateway as DriverOperationsGateway)
          .setAvailability(
            session: _session,
            online: online,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
            serviceTypes: _serviceTypes,
          );
      if (mounted) setState(() => _profile = profile);
      _startLocationPolling();
    });
  }

  void _startLocationPolling() {
    _locationPoller?.cancel();
    if (_profile?.availabilityStatus != 'ONLINE' &&
        !_offers.any(
          (offer) =>
              offer.status == 'ACCEPTED' && offer.serviceStatus != 'COMPLETED',
        )) {
      return;
    }
    _locationPoller = Timer.periodic(const Duration(seconds: 5), (_) async {
      if (!mounted ||
          _session.token.isEmpty ||
          _session.onboarding ||
          _locationBusy) {
        return;
      }
      _locationBusy = true;
      try {
        final position = await widget.locationSource.current();
        final operations = widget.gateway as DriverOperationsGateway;
        final active = _offers.any(
          (offer) =>
              offer.status == 'ACCEPTED' && offer.serviceStatus != 'COMPLETED',
        );
        if (active) {
          await operations.updateLocation(
            session: _session,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
          );
        } else if (_profile?.availabilityStatus == 'ONLINE') {
          final profile = await operations.setAvailability(
            session: _session,
            online: true,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
            serviceTypes: _serviceTypes,
          );
          if (mounted) setState(() => _profile = profile);
        }
      } on DriverApiException catch (error) {
        if (error.statusCode == 401) await _clearSession();
      } catch (_) {
        // Polling resumes when device location or network becomes available.
      } finally {
        _locationBusy = false;
      }
    });
  }

  void _startPolling() {
    _poller?.cancel();
    unawaited(_loadOffers());
    _poller = Timer.periodic(
      const Duration(seconds: 10),
      (_) => unawaited(_loadOffers(silent: true)),
    );
  }

  Future<void> _loadOffers({bool silent = false}) async {
    if (silent) {
      try {
        await _loadOffersDirect();
      } on DriverApiException catch (error) {
        if (error.statusCode == 401) await _clearSession();
      } catch (_) {}
      return;
    }
    await _run(_loadOffersDirect);
  }

  Future<void> _loadOffersDirect() async {
    final offers = await widget.gateway.loadOffers(_session);
    if (!mounted) return;
    setState(() => _offers = offers);
    widget.realtime?.watch(
      offers
          .where((offer) => offer.status == 'ACCEPTED')
          .firstOrNull
          ?.serviceRequestId,
    );
    if (widget.gateway is DriverOperationsGateway) _startLocationPolling();
  }

  Future<void> _respond(DriverOfferSummary offer, String action) async {
    await _run(() async {
      final result = await widget.gateway.respond(
        session: _session,
        offer: offer,
        action: action,
        idempotencyKey: _offerActionKeys.putIfAbsent(
          '${offer.id}:$action',
          newRequestId,
        ),
      );
      setState(() {
        _offerActionKeys.remove('${offer.id}:$action');
        _offers = _offers
            .map((candidate) => candidate.id == result.id ? result : candidate)
            .toList(growable: false);
      });
      widget.realtime?.watch(
        result.status == 'ACCEPTED' ? result.serviceRequestId : null,
      );
      if (widget.gateway is DriverOperationsGateway) _startLocationPolling();
    });
  }

  _ExecutionAction? _nextAction(DriverOfferSummary offer) {
    return switch (offer.serviceStatus) {
      'DRIVER_ARRIVING_PICKUP' => const _ExecutionAction(
        value: 'arrive_pickup',
        label: 'Đã đến điểm lấy',
        icon: Icons.location_on_outlined,
      ),
      'AT_PICKUP' => const _ExecutionAction(
        value: 'pickup',
        label: 'Đã nhận hàng',
        icon: Icons.inventory_2_outlined,
      ),
      'PICKED_UP' => const _ExecutionAction(
        value: 'start_delivery',
        label: 'Bắt đầu giao',
        icon: Icons.local_shipping_outlined,
      ),
      'IN_DELIVERY' => const _ExecutionAction(
        value: 'deliver',
        label: 'Hoàn tất giao hàng',
        icon: Icons.check_circle_outline,
      ),
      'DRIVER_ARRIVING' => const _ExecutionAction(
        value: 'arrive',
        label: 'Đã đến điểm đón',
        icon: Icons.location_on_outlined,
      ),
      'DRIVER_ARRIVED' => const _ExecutionAction(
        value: 'start',
        label: 'Bắt đầu chuyến',
        icon: Icons.play_arrow,
      ),
      'IN_TRIP' => const _ExecutionAction(
        value: 'complete',
        label: 'Kết thúc chuyến',
        icon: Icons.flag_outlined,
      ),
      _ => null,
    };
  }

  Future<void> _advance(
    DriverOfferSummary offer,
    _ExecutionAction action,
  ) async {
    final cashController = TextEditingController(
      text: offer.customerPayable.toStringAsFixed(0),
    );
    final codController = TextEditingController(text: '0');
    final reasonController = TextEditingController();
    final terminal = action.value == 'deliver' || action.value == 'complete';
    final shouldCollectCash = terminal && offer.paymentMethod == 'CASH';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(action.label),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (shouldCollectCash)
              TextField(
                controller: cashController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Tiền mặt đã thu'),
              ),
            if (action.value == 'deliver') ...[
              const SizedBox(height: 12),
              TextField(
                controller: codController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'COD đã thu'),
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              maxLength: 50,
              decoration: const InputDecoration(
                labelText: 'Lý do ngoài khu vực (nếu có)',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Quay lại'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Xác nhận'),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      cashController.dispose();
      codController.dispose();
      reasonController.dispose();
      return;
    }
    await _run(() async {
      final position = await widget.locationSource.current();
      final status = await widget.gateway.transition(
        session: _session,
        offer: offer,
        action: action.value,
        latitude: position.latitude,
        longitude: position.longitude,
        cashCollected: shouldCollectCash
            ? double.tryParse(cashController.text)
            : null,
        codCollected: action.value == 'deliver'
            ? double.tryParse(codController.text)
            : null,
        evidenceId: _evidenceByOffer['${offer.id}:${action.value}'],
        outOfGeofenceReason: reasonController.text.trim().isEmpty
            ? null
            : reasonController.text.trim(),
        idempotencyKey: _transitionKeys.putIfAbsent(
          '${offer.id}:${action.value}',
          newRequestId,
        ),
      );
      setState(() {
        _transitionKeys.remove('${offer.id}:${action.value}');
        _evidenceByOffer.remove('${offer.id}:${action.value}');
        _offers = _offers
            .map(
              (candidate) => candidate.id == offer.id
                  ? candidate.withServiceStatus(status)
                  : candidate,
            )
            .toList(growable: false);
      });
    });
    cashController.dispose();
    codController.dispose();
    reasonController.dispose();
  }

  Future<void> _uploadProof(DriverOfferSummary offer, String action) async {
    final file = await FilePicker.pickFile(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    );
    if (file == null) return;
    final size = file.lengthSync() ?? await file.length();
    if (size == null || size > 10 * 1024 * 1024) {
      setState(
        () => _error = 'Không xác định được kích thước hoặc tệp lớn hơn 10 MB.',
      );
      return;
    }
    await _run(() async {
      final position = await widget.locationSource.current();
      final id = await (widget.gateway as DriverOperationsGateway)
          .uploadEvidence(
            session: _session,
            serviceRequestId: offer.serviceRequestId,
            evidenceType: action == 'pickup' ? 'PICKUP' : 'DELIVERY',
            name: file.name,
            bytes: await file.readAsBytes(),
            latitude: position.latitude,
            longitude: position.longitude,
          );
      if (mounted) setState(() => _evidenceByOffer['${offer.id}:$action'] = id);
    });
  }

  Future<void> _showEarnings() async {
    DriverWalletSummary wallet;
    List<DriverBankAccountSummary> accounts;
    try {
      wallet = await widget.gateway.loadWallet(_session);
      accounts = await widget.gateway.loadBankAccounts(_session);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
      return;
    }
    if (!mounted) return;
    final amount = TextEditingController(text: '50000');
    String? selected;
    String? withdrawalKey;
    String? sheetError;
    for (final account in accounts) {
      if (account.verified) {
        selected = account.id;
        break;
      }
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(
            16,
            20,
            16,
            MediaQuery.viewInsetsOf(context).bottom + 20,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Thu nhập và rút tiền',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              Text('Số dư: ${wallet.balance.toStringAsFixed(0)} VND'),
              Text('Khả dụng: ${wallet.available.toStringAsFixed(0)} VND'),
              const SizedBox(height: 12),
              if (widget.gateway is DriverOperationsGateway) ...[
                OutlinedButton.icon(
                  onPressed: () {
                    Navigator.pop(context);
                    _addBankAccount();
                  },
                  icon: const Icon(Icons.add),
                  label: const Text('Thêm tài khoản ngân hàng'),
                ),
                const SizedBox(height: 8),
              ],
              DropdownButtonFormField<String>(
                initialValue: selected,
                decoration: const InputDecoration(
                  labelText: 'Tài khoản đã xác minh',
                ),
                items: accounts
                    .where((account) => account.verified)
                    .map(
                      (account) => DropdownMenuItem(
                        value: account.id,
                        child: Text(
                          '${account.bankCode} · ${account.accountName}',
                        ),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (value) {
                  withdrawalKey = null;
                  setSheetState(() => selected = value);
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amount,
                onChanged: (_) {
                  withdrawalKey = null;
                  setSheetState(() => sheetError = null);
                },
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Số tiền rút'),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: selected == null
                    ? null
                    : () async {
                        final value = double.tryParse(amount.text);
                        if (value == null || value <= 0) {
                          setSheetState(
                            () => sheetError = 'Số tiền rút không hợp lệ.',
                          );
                          return;
                        }
                        try {
                          withdrawalKey ??= newRequestId();
                          await widget.gateway.requestWithdrawal(
                            session: _session,
                            bankAccountId: selected!,
                            amount: value,
                            idempotencyKey: withdrawalKey!,
                          );
                          withdrawalKey = null;
                          if (context.mounted) Navigator.pop(context);
                        } catch (error) {
                          setSheetState(() => sheetError = error.toString());
                        }
                      },
                icon: const Icon(Icons.arrow_upward),
                label: const Text('Gửi yêu cầu rút'),
              ),
              if (sheetError != null)
                Text(
                  sheetError!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
            ],
          ),
        ),
      ),
    );
    amount.dispose();
  }

  Future<void> _addBankAccount() async {
    final bank = TextEditingController();
    final number = TextEditingController();
    final name = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Thêm tài khoản ngân hàng'),
        content: SizedBox(
          width: 400,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: bank,
                decoration: const InputDecoration(labelText: 'Mã ngân hàng'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: number,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Số tài khoản'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: name,
                decoration: const InputDecoration(
                  labelText: 'Tên chủ tài khoản',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
    if (confirmed == true &&
        bank.text.trim().isNotEmpty &&
        number.text.trim().isNotEmpty &&
        name.text.trim().isNotEmpty) {
      await _run(
        () => (widget.gateway as DriverOperationsGateway).createBankAccount(
          session: _session,
          bankCode: bank.text.trim(),
          accountNumber: number.text.trim(),
          accountName: name.text.trim(),
        ),
      );
    }
    bank.dispose();
    number.dispose();
    name.dispose();
  }

  Future<void> _showChat(DriverOfferSummary offer) async {
    final support = widget.gateway as DriverSupportGateway;
    final input = TextEditingController();
    try {
      final messages = await support.loadChat(_session, offer.serviceRequestId);
      if (!mounted) return;
      final send = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Chat chuyến đi'),
          content: SizedBox(
            width: 440,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 240),
                  child: messages.isEmpty
                      ? const Text('Chưa có tin nhắn.')
                      : ListView.builder(
                          shrinkWrap: true,
                          itemCount: messages.length,
                          itemBuilder: (_, index) => ListTile(
                            dense: true,
                            title: Text(messages[index].senderName),
                            subtitle: Text(messages[index].body),
                          ),
                        ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: input,
                  maxLength: 2000,
                  decoration: const InputDecoration(labelText: 'Tin nhắn'),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Đóng'),
            ),
            FilledButton.icon(
              onPressed: () => Navigator.pop(context, true),
              icon: const Icon(Icons.send),
              label: const Text('Gửi'),
            ),
          ],
        ),
      );
      if (send == true && input.text.trim().isNotEmpty) {
        await _run(
          () => support.sendChat(
            session: _session,
            serviceRequestId: offer.serviceRequestId,
            body: input.text.trim(),
          ),
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      input.dispose();
    }
  }

  Future<void> _openSupport(DriverOfferSummary offer) async {
    final support = widget.gateway as DriverSupportGateway;
    final subject = TextEditingController();
    final description = TextEditingController();
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tạo yêu cầu hỗ trợ'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: subject,
              maxLength: 191,
              decoration: const InputDecoration(labelText: 'Tiêu đề'),
            ),
            TextField(
              controller: description,
              maxLength: 5000,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Mô tả'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Gửi'),
          ),
        ],
      ),
    );
    if (submit == true &&
        subject.text.trim().isNotEmpty &&
        description.text.trim().isNotEmpty) {
      await _run(
        () => support.createSupportTicket(
          session: _session,
          serviceRequestId: offer.serviceRequestId,
          subject: subject.text.trim(),
          description: description.text.trim(),
        ),
      );
    }
    subject.dispose();
    description.dispose();
  }

  Future<void> _reportIncident(DriverOfferSummary offer) async {
    final support = widget.gateway as DriverSupportGateway;
    final description = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Báo động SOS?'),
        scrollable: true,
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Nếu nguy hiểm tức thời, hãy liên hệ dịch vụ khẩn cấp địa phương.',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: description,
              maxLength: 5000,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Mô tả sự cố'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, true),
            icon: const Icon(Icons.sos_outlined),
            label: const Text('Báo ngay'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await _run(
        () => support.reportIncident(
          session: _session,
          serviceRequestId: offer.serviceRequestId,
          incidentType: 'SOS',
          description: description.text,
        ),
      );
    }
    description.dispose();
  }

  Future<void> _rate(DriverOfferSummary offer) async {
    final support = widget.gateway as DriverSupportGateway;
    final comment = TextEditingController();
    var score = 5;
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Đánh giá khách hàng'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                initialValue: score,
                decoration: const InputDecoration(labelText: 'Số sao'),
                items: [1, 2, 3, 4, 5]
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text('$value sao'),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (value) => setDialogState(() => score = value ?? 5),
              ),
              TextField(
                controller: comment,
                maxLength: 1000,
                decoration: const InputDecoration(labelText: 'Nhận xét'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Gửi'),
            ),
          ],
        ),
      ),
    );
    if (submit == true) {
      await _run(
        () => support.submitRating(
          session: _session,
          serviceRequestId: offer.serviceRequestId,
          score: score,
          comment: comment.text,
        ),
      );
    }
    comment.dispose();
  }

  Future<void> _showNotifications() async {
    final support = widget.gateway as DriverSupportGateway;
    try {
      final notifications = await support.loadNotifications(_session);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        builder: (context) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Thông báo', style: Theme.of(context).textTheme.titleLarge),
            if (notifications.isEmpty)
              const ListTile(title: Text('Chưa có thông báo.')),
            for (final notification in notifications)
              ListTile(
                leading: Icon(
                  notification.isRead
                      ? Icons.notifications_none
                      : Icons.notifications_active_outlined,
                ),
                title: Text(notification.type),
                trailing: notification.isRead
                    ? null
                    : IconButton(
                        tooltip: 'Đánh dấu đã đọc',
                        onPressed: () async {
                          await support.markNotificationRead(
                            _session,
                            notification.id,
                          );
                          if (context.mounted) Navigator.pop(context);
                        },
                        icon: const Icon(Icons.done),
                      ),
              ),
          ],
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    }
  }

  Future<void> _showTickets() async {
    final support = widget.gateway as DriverSupportGateway;
    try {
      final tickets = await support.loadTickets(_session);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        builder: (context) => SafeArea(
          child: ListView(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  'Yêu cầu hỗ trợ',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (tickets.isEmpty)
                const ListTile(title: Text('Chưa có yêu cầu.')),
              for (final ticket in tickets)
                ListTile(
                  title: Text(ticket.subject),
                  subtitle: Text(ticket.status),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    Navigator.pop(context);
                    _showTicket(ticket.id);
                  },
                ),
            ],
          ),
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    }
  }

  Future<void> _showTicket(String id) async {
    final support = widget.gateway as DriverSupportGateway;
    final input = TextEditingController();
    try {
      final ticket = await support.loadTicket(_session, id);
      if (!mounted) return;
      final send = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(ticket.subject),
          content: SizedBox(
            width: 440,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(ticket.status),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 250),
                  child: ListView(
                    shrinkWrap: true,
                    children: [
                      for (final message in ticket.messages)
                        ListTile(
                          title: Text(message.senderName),
                          subtitle: Text(message.body),
                        ),
                    ],
                  ),
                ),
                if (ticket.status != 'CLOSED')
                  TextField(
                    controller: input,
                    maxLength: 5000,
                    decoration: const InputDecoration(labelText: 'Phản hồi'),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Đóng'),
            ),
            if (ticket.status != 'CLOSED')
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Gửi'),
              ),
          ],
        ),
      );
      if (send == true && input.text.trim().isNotEmpty) {
        await _run(
          () => support.replyToTicket(
            session: _session,
            id: id,
            body: input.text.trim(),
          ),
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      input.dispose();
    }
  }

  Future<void> _run(Future<void> Function() operation) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await operation();
    } catch (error) {
      if (error is DriverApiException && error.statusCode == 401) {
        await _clearSession();
      }
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _logout() async {
    if (widget.gateway is DriverOperationsGateway) {
      try {
        await (widget.gateway as DriverOperationsGateway).logout(_session);
      } catch (_) {
        // Clear the local session even when the connection has been lost.
      }
    }
    await _clearSession();
  }

  Future<void> _clearSession() async {
    await widget.sessionStore?.clear();
    _poller?.cancel();
    _locationPoller?.cancel();
    widget.realtime?.dispose();
    if (!mounted) return;
    setState(() {
      _session = _session.copyWith(token: '', onboarding: false);
      _offers = const [];
      _profile = null;
      _evidenceByOffer.clear();
      _transitionKeys.clear();
      _offerActionKeys.clear();
      _password.clear();
    });
  }
}

class _ExecutionAction {
  const _ExecutionAction({
    required this.value,
    required this.label,
    required this.icon,
  });

  final String value;
  final String label;
  final IconData icon;
}
