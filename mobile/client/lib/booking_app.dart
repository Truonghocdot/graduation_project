import 'dart:async';

import 'package:flutter/material.dart';

import 'api/booking_api.dart';
import 'api/booking_realtime.dart';
import 'api/request_id.dart';
import 'api/session_store.dart';

class BookingApp extends StatelessWidget {
  const BookingApp({
    super.key,
    required this.gateway,
    required this.initialSession,
    this.sessionStore,
    this.realtime,
  });

  final BookingGateway gateway;
  final BookingSession initialSession;
  final BookingSessionStore? sessionStore;
  final BookingRealtime? realtime;

  @override
  Widget build(BuildContext context) {
    const colors = ColorScheme.light(
      primary: Color(0xFF146B52),
      onPrimary: Colors.white,
      secondary: Color(0xFFB35C21),
      onSecondary: Colors.white,
      surface: Color(0xFFF7F8F5),
      onSurface: Color(0xFF1B2420),
      error: Color(0xFFB42318),
      onError: Colors.white,
    );

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Delivery & Drive',
      theme: ThemeData(
        colorScheme: colors,
        scaffoldBackgroundColor: colors.surface,
        useMaterial3: true,
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(8)),
            side: BorderSide(color: Color(0xFFD9DEDA)),
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
      home: BookingWorkspace(
        gateway: gateway,
        initialSession: initialSession,
        sessionStore: sessionStore,
        realtime: realtime,
      ),
    );
  }
}

class BookingWorkspace extends StatefulWidget {
  const BookingWorkspace({
    super.key,
    required this.gateway,
    required this.initialSession,
    this.sessionStore,
    this.realtime,
  });

  final BookingGateway gateway;
  final BookingSession initialSession;
  final BookingSessionStore? sessionStore;
  final BookingRealtime? realtime;

  @override
  State<BookingWorkspace> createState() => _BookingWorkspaceState();
}

class _BookingWorkspaceState extends State<BookingWorkspace> {
  final _formKey = GlobalKey<FormState>();
  final _pickupAddress = TextEditingController(text: '1 Nguyễn Huệ, Quận 1');
  final _pickupLatitude = TextEditingController(text: '10.773');
  final _pickupLongitude = TextEditingController(text: '106.704');
  final _dropoffAddress = TextEditingController(text: '1 Võ Văn Tần, Quận 3');
  final _dropoffLatitude = TextEditingController(text: '10.780');
  final _dropoffLongitude = TextEditingController(text: '106.690');
  final _goodsType = TextEditingController(text: 'GENERAL');
  final _weight = TextEditingController(text: '5');
  final _passengerCount = TextEditingController(text: '1');
  final _voucher = TextEditingController();
  final _recipientUserId = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();

  late BookingSession _session = widget.initialSession;
  List<VehicleOption> _vehicles = const [];
  ServiceKind _service = ServiceKind.delivery;
  PaymentChoice _payment = PaymentChoice.wallet;
  PayerChoice _payer = PayerChoice.orderer;
  DateTime? _scheduledAt;
  QuoteSummary? _quote;
  ServiceRequestSummary? _serviceRequest;
  String? _lastRequestId;
  String? _createIdempotencyKey;
  String? _cancelIdempotencyKey;
  bool _busy = false;
  String? _error;
  Timer? _snapshotTimer;
  Timer? _authTimer;

  @override
  void initState() {
    super.initState();
    if (_session.token.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _restoreSession());
    }
  }

  @override
  void dispose() {
    _snapshotTimer?.cancel();
    _authTimer?.cancel();
    widget.realtime?.dispose();
    for (final controller in [
      _pickupAddress,
      _pickupLatitude,
      _pickupLongitude,
      _dropoffAddress,
      _dropoffLatitude,
      _dropoffLongitude,
      _goodsType,
      _weight,
      _passengerCount,
      _voucher,
      _recipientUserId,
      _phone,
      _password,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_session.token.isEmpty ? 'Đăng nhập' : 'Đặt dịch vụ'),
        actions: [
          if (_session.token.isNotEmpty &&
              widget.gateway is BookingSupportGateway)
            IconButton(
              tooltip: 'Thông báo',
              onPressed: _busy ? null : _showNotifications,
              icon: const Icon(Icons.notifications_outlined),
            ),
          if (_session.token.isNotEmpty)
            IconButton(
              tooltip: 'Ví lạnh',
              onPressed: _busy ? null : _showWallet,
              icon: const Icon(Icons.account_balance_wallet_outlined),
            ),
          if (_session.token.isNotEmpty)
            PopupMenuButton<String>(
              tooltip: 'Thêm',
              enabled: !_busy,
              icon: const Icon(Icons.more_vert),
              onSelected: (value) {
                switch (value) {
                  case 'history':
                    _loadLastRequest();
                  case 'support':
                    _showTickets();
                  case 'logout':
                    _logout();
                }
              },
              itemBuilder: (context) => [
                if (_lastRequestId != null)
                  const PopupMenuItem(
                    value: 'history',
                    child: Text('Yêu cầu gần nhất'),
                  ),
                if (widget.gateway is BookingSupportGateway)
                  const PopupMenuItem(
                    value: 'support',
                    child: Text('Yêu cầu hỗ trợ'),
                  ),
                const PopupMenuItem(value: 'logout', child: Text('Đăng xuất')),
              ],
            ),
        ],
      ),
      body: _session.token.isEmpty
          ? _loginBody()
          : SafeArea(
              child: LayoutBuilder(
                builder: (context, constraints) {
                  return Align(
                    alignment: Alignment.topCenter,
                    child: SizedBox(
                      width: constraints.maxWidth > 760
                          ? 760
                          : constraints.maxWidth,
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _serviceSelector(),
                              const SizedBox(height: 12),
                              _vehicleSelector(),
                              const SizedBox(height: 24),
                              _sectionTitle('Lộ trình', Icons.route_outlined),
                              const SizedBox(height: 12),
                              _locationFields(
                                icon: Icons.radio_button_checked,
                                address: _pickupAddress,
                                latitude: _pickupLatitude,
                                longitude: _pickupLongitude,
                                label: 'Điểm đón / lấy hàng',
                              ),
                              const SizedBox(height: 12),
                              _locationFields(
                                icon: Icons.location_on_outlined,
                                address: _dropoffAddress,
                                latitude: _dropoffLatitude,
                                longitude: _dropoffLongitude,
                                label: 'Điểm đến / giao hàng',
                              ),
                              const SizedBox(height: 24),
                              _sectionTitle(
                                _service == ServiceKind.delivery
                                    ? 'Hàng hóa'
                                    : 'Hành khách',
                                _service == ServiceKind.delivery
                                    ? Icons.inventory_2_outlined
                                    : Icons.people_outline,
                              ),
                              const SizedBox(height: 12),
                              if (_service == ServiceKind.delivery)
                                Row(
                                  children: [
                                    Expanded(
                                      flex: 2,
                                      child: _textField(
                                        _goodsType,
                                        'Loại hàng',
                                        required: true,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: _textField(
                                        _weight,
                                        'Khối lượng (kg)',
                                        numeric: true,
                                        required: true,
                                      ),
                                    ),
                                  ],
                                )
                              else
                                _textField(
                                  _passengerCount,
                                  'Số hành khách',
                                  numeric: true,
                                  required: true,
                                ),
                              const SizedBox(height: 12),
                              _textField(_voucher, 'Mã giảm giá'),
                              const SizedBox(height: 12),
                              _scheduleControl(),
                              if (_error case final error?) ...[
                                const SizedBox(height: 16),
                                _errorBanner(error),
                              ],
                              const SizedBox(height: 20),
                              _primaryButton(
                                key: const Key('quote-button'),
                                icon: Icons.calculate_outlined,
                                label: 'Nhận báo giá',
                                onPressed: _requestQuote,
                              ),
                              if (_quote case final quote?) ...[
                                const SizedBox(height: 24),
                                _quotePanel(quote),
                              ],
                              if (_serviceRequest case final request?) ...[
                                const SizedBox(height: 16),
                                _requestPanel(request),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }

  Widget _loginBody() {
    return SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 36, 20, 24),
          child: SizedBox(
            width: 420,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.route, size: 42, color: Color(0xFF146B52)),
                const SizedBox(height: 16),
                Text(
                  'Delivery & Drive',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 28),
                TextField(
                  controller: _phone,
                  enabled: !_busy,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Số điện thoại',
                    prefixIcon: Icon(Icons.phone_outlined),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  enabled: !_busy,
                  obscureText: true,
                  onSubmitted: (_) => _login(),
                  decoration: const InputDecoration(
                    labelText: 'Mật khẩu',
                    prefixIcon: Icon(Icons.lock_outline),
                  ),
                ),
                if (_error case final error?) ...[
                  const SizedBox(height: 16),
                  _errorBanner(error),
                ],
                const SizedBox(height: 20),
                _primaryButton(
                  key: const Key('login-button'),
                  icon: Icons.login,
                  label: 'Đăng nhập',
                  onPressed: _login,
                ),
                if (widget.gateway is CustomerAccountGateway) ...[
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: _busy ? null : _register,
                    child: const Text('Tạo tài khoản'),
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
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _vehicleSelector() {
    if (_vehicles.isEmpty) {
      return ListTile(
        contentPadding: EdgeInsets.zero,
        leading: const Icon(Icons.two_wheeler_outlined),
        title: const Text('Loại phương tiện'),
        subtitle: Text(
          _session.vehicleTypeId.isEmpty
              ? 'Chưa có phương tiện'
              : 'Phương tiện hiện tại',
        ),
        trailing: IconButton(
          tooltip: 'Tải lại',
          onPressed: _busy ? null : _loadVehicleTypes,
          icon: const Icon(Icons.refresh),
        ),
      );
    }

    final selectedId =
        _vehicles.any((vehicle) => vehicle.id == _session.vehicleTypeId)
        ? _session.vehicleTypeId
        : _vehicles.first.id;
    return DropdownButtonFormField<String>(
      initialValue: selectedId,
      decoration: const InputDecoration(
        labelText: 'Loại phương tiện',
        prefixIcon: Icon(Icons.two_wheeler_outlined),
      ),
      items: _vehicles
          .map(
            (vehicle) =>
                DropdownMenuItem(value: vehicle.id, child: Text(vehicle.name)),
          )
          .toList(growable: false),
      onChanged: _busy
          ? null
          : (vehicleId) {
              if (vehicleId == null) return;
              setState(() {
                _session = _session.copyWith(vehicleTypeId: vehicleId);
                _quote = null;
                _serviceRequest = null;
                _createIdempotencyKey = null;
              });
            },
    );
  }

  Widget _serviceSelector() {
    return SegmentedButton<ServiceKind>(
      segments: const [
        ButtonSegment(
          value: ServiceKind.delivery,
          icon: Icon(Icons.local_shipping_outlined),
          label: Text('Delivery'),
        ),
        ButtonSegment(
          value: ServiceKind.drive,
          icon: Icon(Icons.directions_car_outlined),
          label: Text('Drive'),
        ),
      ],
      selected: {_service},
      onSelectionChanged: _busy
          ? null
          : (selection) {
              setState(() {
                _service = selection.first;
                _quote = null;
                _serviceRequest = null;
                _createIdempotencyKey = null;
                _payer = PayerChoice.orderer;
              });
            },
    );
  }

  Widget _sectionTitle(String label, IconData icon) {
    return Row(
      children: [
        Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(label, style: Theme.of(context).textTheme.titleMedium),
        ),
      ],
    );
  }

  Widget _locationFields({
    required IconData icon,
    required TextEditingController address,
    required TextEditingController latitude,
    required TextEditingController longitude,
    required String label,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 16),
            const SizedBox(width: 6),
            Expanded(
              child: Text(label, style: Theme.of(context).textTheme.labelLarge),
            ),
          ],
        ),
        const SizedBox(height: 8),
        _textField(address, 'Địa chỉ', required: true),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: _textField(
                latitude,
                'Vĩ độ',
                numeric: true,
                required: true,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _textField(
                longitude,
                'Kinh độ',
                numeric: true,
                required: true,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _textField(
    TextEditingController controller,
    String label, {
    bool numeric = false,
    bool required = false,
  }) {
    return TextFormField(
      controller: controller,
      enabled: !_busy,
      keyboardType: numeric
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      decoration: InputDecoration(labelText: label),
      validator: (value) {
        if (required && (value == null || value.trim().isEmpty)) {
          return 'Bắt buộc';
        }
        if (numeric && value != null && double.tryParse(value) == null) {
          return 'Số không hợp lệ';
        }
        return null;
      },
    );
  }

  Widget _scheduleControl() {
    final value = _scheduledAt;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.schedule_outlined),
      title: const Text('Thời gian thực hiện'),
      subtitle: Text(
        value == null
            ? 'Ngay bây giờ'
            : value.toLocal().toString().substring(0, 16),
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (value != null)
            IconButton(
              tooltip: 'Đặt ngay',
              onPressed: _busy
                  ? null
                  : () => setState(() => _scheduledAt = null),
              icon: const Icon(Icons.close),
            ),
          IconButton(
            tooltip: 'Chọn thời gian',
            onPressed: _busy ? null : _pickSchedule,
            icon: const Icon(Icons.edit_calendar_outlined),
          ),
        ],
      ),
    );
  }

  Widget _quotePanel(QuoteSummary quote) {
    final expired = !quote.expiresAt.isAfter(DateTime.now());
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Text('Báo giá', style: Theme.of(context).textTheme.titleMedium),
                const Spacer(),
                _statusLabel(expired ? 'Hết hạn' : 'Còn hiệu lực'),
              ],
            ),
            const SizedBox(height: 16),
            _amountRow('Giá dịch vụ', quote.grossFare),
            if (quote.voucherDiscount > 0)
              _amountRow('Voucher', -quote.voucherDiscount),
            const Divider(height: 24),
            _amountRow(
              'Cần thanh toán',
              quote.customerPayable,
              emphasized: true,
            ),
            const SizedBox(height: 8),
            Text(
              '${(quote.distanceMeters / 1000).toStringAsFixed(1)} km  ·  ${(quote.durationSeconds / 60).ceil()} phút',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 20),
            SegmentedButton<PaymentChoice>(
              segments: [
                ButtonSegment(
                  value: PaymentChoice.wallet,
                  enabled: _payer != PayerChoice.recipient,
                  icon: const Icon(Icons.account_balance_wallet_outlined),
                  label: const Text('Ví lạnh'),
                ),
                const ButtonSegment(
                  value: PaymentChoice.cash,
                  icon: Icon(Icons.payments_outlined),
                  label: Text('Tiền mặt'),
                ),
              ],
              selected: {_payment},
              onSelectionChanged: _busy
                  ? null
                  : (selection) => setState(() => _payment = selection.first),
            ),
            if (_service == ServiceKind.delivery) ...[
              const SizedBox(height: 12),
              SegmentedButton<PayerChoice>(
                segments: const [
                  ButtonSegment(
                    value: PayerChoice.orderer,
                    label: Text('Người đặt trả'),
                  ),
                  ButtonSegment(
                    value: PayerChoice.recipient,
                    label: Text('Người nhận trả'),
                  ),
                ],
                selected: {_payer},
                onSelectionChanged: _busy
                    ? null
                    : (selection) {
                        setState(() {
                          _payer = selection.first;
                          if (_payer == PayerChoice.recipient) {
                            _payment = PaymentChoice.cash;
                          }
                        });
                      },
              ),
              if (_payer == PayerChoice.recipient) ...[
                const SizedBox(height: 12),
                _textField(_recipientUserId, 'Mã người nhận', required: true),
              ],
            ],
            const SizedBox(height: 16),
            _primaryButton(
              key: const Key('create-button'),
              icon: Icons.check_circle_outline,
              label: _service == ServiceKind.delivery
                  ? 'Đặt giao hàng'
                  : 'Đặt chuyến',
              onPressed: expired ? null : _createServiceRequest,
            ),
          ],
        ),
      ),
    );
  }

  Widget _requestPanel(ServiceRequestSummary request) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.receipt_long_outlined),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Mã yêu cầu',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                _statusLabel(request.status),
              ],
            ),
            const SizedBox(height: 8),
            SelectableText(request.id),
            if (request.driverNetEarning != null) ...[
              const SizedBox(height: 8),
              Text(
                'Đã quyết toán · Thu nhập tài xế ${request.driverNetEarning!.toStringAsFixed(0)} VND',
              ),
            ],
            const SizedBox(height: 16),
            if (widget.gateway is BookingSupportGateway) ...[
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  if (const {
                    'ASSIGNED',
                    'DRIVER_ARRIVING_PICKUP',
                    'AT_PICKUP',
                    'PICKED_UP',
                    'IN_DELIVERY',
                    'DRIVER_ARRIVING',
                    'DRIVER_ARRIVED',
                    'IN_TRIP',
                  }.contains(request.status))
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _showChat(request),
                      icon: const Icon(Icons.chat_bubble_outline),
                      label: const Text('Chat'),
                    ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _openSupport(request),
                    icon: const Icon(Icons.support_agent),
                    label: const Text('Hỗ trợ'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _reportIncident(request),
                    icon: const Icon(Icons.sos_outlined),
                    label: const Text('SOS'),
                  ),
                  if (request.status == 'DELIVERED' ||
                      request.status == 'TRIP_ENDED' ||
                      request.status == 'COMPLETED')
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _rate(request),
                      icon: const Icon(Icons.star_outline),
                      label: const Text('Đánh giá'),
                    ),
                ],
              ),
              const SizedBox(height: 8),
            ],
            if (request.status == 'SCHEDULED' ||
                request.status == 'SEARCHING_DRIVER')
              OutlinedButton.icon(
                onPressed: _busy ? null : _cancelServiceRequest,
                icon: const Icon(Icons.cancel_outlined),
                label: const Text('Hủy yêu cầu'),
              ),
          ],
        ),
      ),
    );
  }

  Widget _statusLabel(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFE5F2EC),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Color(0xFF0D5A42),
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _amountRow(String label, double amount, {bool emphasized = false}) {
    final style = emphasized
        ? Theme.of(context).textTheme.titleMedium
        : Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text('${amount.toStringAsFixed(0)} VND', style: style),
        ],
      ),
    );
  }

  Widget _errorBanner(String error) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFDECEA),
        border: Border.all(color: const Color(0xFFF2B8B5)),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Color(0xFFB42318)),
          const SizedBox(width: 8),
          Expanded(child: Text(error)),
        ],
      ),
    );
  }

  Widget _primaryButton({
    required Key key,
    required IconData icon,
    required String label,
    required VoidCallback? onPressed,
  }) {
    return SizedBox(
      height: 48,
      child: FilledButton.icon(
        key: key,
        onPressed: _busy ? null : onPressed,
        icon: _busy
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Icon(icon),
        label: Text(label),
      ),
    );
  }

  Future<void> _pickSchedule() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      firstDate: now,
      lastDate: now.add(const Duration(days: 30)),
      initialDate: _scheduledAt ?? now.add(const Duration(days: 1)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(
        _scheduledAt ?? now.add(const Duration(hours: 1)),
      ),
    );
    if (time == null) return;
    setState(() {
      _scheduledAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
      _quote = null;
      _serviceRequest = null;
    });
  }

  Future<void> _requestQuote() async {
    if (!_validate()) return;
    await _run(() async {
      final quote = await widget.gateway.createQuote(
        _session,
        BookingDraft(
          service: _service,
          pickup: LocationDraft(
            address: _pickupAddress.text.trim(),
            latitude: double.parse(_pickupLatitude.text),
            longitude: double.parse(_pickupLongitude.text),
          ),
          dropoff: LocationDraft(
            address: _dropoffAddress.text.trim(),
            latitude: double.parse(_dropoffLatitude.text),
            longitude: double.parse(_dropoffLongitude.text),
          ),
          goodsType: _goodsType.text.trim(),
          weightKg: double.tryParse(_weight.text) ?? 0,
          passengerCount: int.tryParse(_passengerCount.text) ?? 1,
          voucherCode: _voucher.text.trim().isEmpty
              ? null
              : _voucher.text.trim(),
          scheduledAt: _scheduledAt,
        ),
      );
      setState(() {
        _quote = quote;
        _serviceRequest = null;
        _createIdempotencyKey = null;
      });
    });
  }

  Future<void> _createServiceRequest() async {
    final quote = _quote;
    if (quote == null || !_validate()) return;
    await _run(() async {
      _createIdempotencyKey ??=
          'mobile-create-${DateTime.now().microsecondsSinceEpoch}';
      final request = await widget.gateway.createServiceRequest(
        session: _session,
        quote: quote,
        payment: _payment,
        payer: _payer,
        recipientUserId: _payer == PayerChoice.recipient
            ? _recipientUserId.text.trim()
            : null,
        idempotencyKey: _createIdempotencyKey!,
      );
      setState(() => _serviceRequest = request);
      _lastRequestId = request.id;
      await widget.sessionStore?.writeLastRequestId(request.id);
      widget.realtime?.watch(request.id);
      _startSnapshotPolling();
    });
  }

  Future<void> _cancelServiceRequest() async {
    final request = _serviceRequest;
    if (request == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hủy yêu cầu?'),
        content: const Text('Khoản ví và voucher hợp lệ sẽ được hoàn lại.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Giữ lại'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Xác nhận hủy'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _run(() async {
      _cancelIdempotencyKey ??= newRequestId();
      final cancelled = await widget.gateway.cancelServiceRequest(
        session: _session,
        serviceRequest: request,
        idempotencyKey: _cancelIdempotencyKey!,
        reasonCode: 'CUSTOMER_CHANGED_MIND',
      );
      setState(() => _serviceRequest = cancelled);
      _cancelIdempotencyKey = null;
      _snapshotTimer?.cancel();
    });
  }

  Future<void> _showChat(ServiceRequestSummary request) async {
    final support = widget.gateway as BookingSupportGateway;
    final input = TextEditingController();
    try {
      final messages = await support.loadChat(_session, request.id);
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
            serviceRequestId: request.id,
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

  Future<void> _openSupport(ServiceRequestSummary request) async {
    final support = widget.gateway as BookingSupportGateway;
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
            const SizedBox(height: 8),
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
          serviceRequestId: request.id,
          subject: subject.text.trim(),
          description: description.text.trim(),
        ),
      );
    }
    subject.dispose();
    description.dispose();
  }

  Future<void> _reportIncident(ServiceRequestSummary request) async {
    final support = widget.gateway as BookingSupportGateway;
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
          serviceRequestId: request.id,
          incidentType: 'SOS',
          description: description.text,
        ),
      );
    }
    description.dispose();
  }

  Future<void> _rate(ServiceRequestSummary request) async {
    final support = widget.gateway as BookingSupportGateway;
    final comment = TextEditingController();
    var score = 5;
    final submit = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Đánh giá tài xế'),
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
              const SizedBox(height: 8),
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
          serviceRequestId: request.id,
          score: score,
          comment: comment.text,
        ),
      );
    }
    comment.dispose();
  }

  Future<void> _showNotifications() async {
    final support = widget.gateway as BookingSupportGateway;
    try {
      final notifications = await support.loadNotifications(_session);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        builder: (context) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Thông báo', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
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
    final support = widget.gateway as BookingSupportGateway;
    try {
      final tickets = await support.loadTickets(_session);
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (context) => SafeArea(
          child: SizedBox(
            height: MediaQuery.sizeOf(context).height * 0.65,
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
        ),
      );
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    }
  }

  Future<void> _showTicket(String id) async {
    final support = widget.gateway as BookingSupportGateway;
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

  bool _validate() {
    setState(() => _error = null);
    if (_session.token.trim().isEmpty ||
        _session.vehicleTypeId.trim().isEmpty) {
      setState(() => _error = 'Chưa có loại phương tiện khả dụng.');
      return false;
    }
    return _formKey.currentState?.validate() ?? false;
  }

  Future<void> _run(Future<void> Function() operation) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await operation();
    } catch (error) {
      if (error is BookingApiException && error.statusCode == 401) {
        await _clearSession();
      }
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _login() async {
    if (_phone.text.trim().isEmpty || _password.text.isEmpty) {
      setState(() => _error = 'Vui lòng nhập số điện thoại và mật khẩu.');
      return;
    }

    await _run(() async {
      final token = await widget.gateway.login(
        baseUrl: _session.baseUrl,
        phone: _phone.text.trim(),
        password: _password.text,
      );
      final authenticated = _session.copyWith(token: token, vehicleTypeId: '');
      final vehicles = await widget.gateway.loadVehicleTypes(authenticated);

      if (vehicles.isEmpty) {
        throw const BookingApiException('Chưa có loại phương tiện khả dụng.');
      }

      setState(() {
        _vehicles = vehicles;
        _session = authenticated.copyWith(vehicleTypeId: vehicles.first.id);
      });
      await widget.sessionStore?.writeToken(token);
      _connectRealtime();
    });
  }

  Future<void> _register() async {
    final account = widget.gateway as CustomerAccountGateway;
    final name = TextEditingController();
    final password = TextEditingController();
    final confirm = TextEditingController();
    final submitted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tạo tài khoản'),
        content: SizedBox(
          width: 400,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Họ tên'),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Mật khẩu'),
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
    if (submitted == true) {
      if (_phone.text.trim().isEmpty ||
          name.text.trim().isEmpty ||
          password.text.isEmpty ||
          password.text != confirm.text) {
        setState(
          () => _error = 'Nhập số điện thoại, họ tên và mật khẩu trùng khớp.',
        );
      } else {
        await _run(
          () => account.register(
            baseUrl: _session.baseUrl,
            name: name.text.trim(),
            phone: _phone.text.trim(),
            password: password.text,
          ),
        );
        if (_error == null && mounted) await _verifyPhone();
      }
    }
    name.dispose();
    password.dispose();
    confirm.dispose();
  }

  Future<void> _verifyPhone() async {
    if (_phone.text.trim().isEmpty) {
      setState(() => _error = 'Nhập số điện thoại để xác minh.');
      return;
    }
    final account = widget.gateway as CustomerAccountGateway;
    final code = TextEditingController();
    final submitted = await showDialog<bool>(
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
                await account.resendPhone(
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
    if (submitted == true && code.text.trim().length == 6) {
      await _run(() async {
        final token = await account.verifyPhone(
          baseUrl: _session.baseUrl,
          phone: _phone.text.trim(),
          code: code.text.trim(),
        );
        final authenticated = _session.copyWith(
          token: token,
          vehicleTypeId: '',
        );
        final vehicles = await widget.gateway.loadVehicleTypes(authenticated);
        if (!mounted) return;
        setState(() {
          _vehicles = vehicles;
          _session = authenticated.copyWith(
            vehicleTypeId: vehicles.isNotEmpty ? vehicles.first.id : '',
          );
        });
        await widget.sessionStore?.writeToken(token);
        _connectRealtime();
      });
    }
    code.dispose();
  }

  Future<void> _forgotPassword() async {
    final account = widget.gateway as CustomerAccountGateway;
    if (_phone.text.trim().isEmpty) {
      setState(() => _error = 'Nhập số điện thoại trước khi đặt lại mật khẩu.');
      return;
    }
    await _run(
      () => account.forgotPassword(
        baseUrl: _session.baseUrl,
        phone: _phone.text.trim(),
      ),
    );
    if (_error != null || !mounted) return;
    final code = TextEditingController();
    final password = TextEditingController();
    final confirm = TextEditingController();
    final submitted = await showDialog<bool>(
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
    if (submitted == true) {
      if (code.text.trim().length != 6 ||
          password.text.isEmpty ||
          password.text != confirm.text) {
        setState(() => _error = 'Mã OTP hoặc xác nhận mật khẩu không hợp lệ.');
      } else {
        await _run(() async {
          final resetToken = await account.verifyReset(
            baseUrl: _session.baseUrl,
            phone: _phone.text.trim(),
            code: code.text.trim(),
          );
          await account.resetPassword(
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

  Future<void> _loadVehicleTypes() async {
    await _run(() async {
      final vehicles = await widget.gateway.loadVehicleTypes(_session);
      setState(() {
        _vehicles = vehicles;
        if (_session.vehicleTypeId.isEmpty && vehicles.isNotEmpty) {
          _session = _session.copyWith(vehicleTypeId: vehicles.first.id);
        }
      });
    });
  }

  Future<void> _restoreSession() async {
    await _run(() async {
      if (widget.gateway is CustomerAccountGateway) {
        await (widget.gateway as CustomerAccountGateway).validateSession(
          _session,
        );
      }
      final vehicles = await widget.gateway.loadVehicleTypes(_session);
      if (!mounted) return;
      setState(() {
        _vehicles = vehicles;
        if (_session.vehicleTypeId.isEmpty && vehicles.isNotEmpty) {
          _session = _session.copyWith(vehicleTypeId: vehicles.first.id);
        }
      });
      final requestId = await widget.sessionStore?.readLastRequestId();
      _lastRequestId = requestId;
      if (requestId != null) {
        try {
          final snapshot = await widget.gateway.loadServiceRequest(
            _session,
            requestId,
          );
          if (mounted) setState(() => _serviceRequest = snapshot);
          _startSnapshotPolling();
        } on BookingApiException catch (error) {
          if (error.statusCode == 401) rethrow;
        }
      }
      _connectRealtime();
    });
  }

  void _connectRealtime() {
    _authTimer?.cancel();
    if (widget.gateway is CustomerAccountGateway) {
      _authTimer = Timer.periodic(const Duration(seconds: 30), (_) async {
        try {
          await (widget.gateway as CustomerAccountGateway).validateSession(
            _session,
          );
        } on BookingApiException catch (error) {
          if (error.statusCode == 401) await _clearSession();
        } catch (_) {
          // A transient network failure does not invalidate the local session.
        }
      });
    }
    if (widget.realtime == null) return;
    const configured = String.fromEnvironment('REALTIME_URL');
    final apiUri = Uri.parse(_session.baseUrl);
    final url = configured.isNotEmpty
        ? configured
        : apiUri
              .replace(port: 3000, path: '', query: '', fragment: '')
              .toString();
    widget.realtime!.connect(
      url: url,
      token: _session.token,
      onChange: () => unawaited(_refreshSnapshot()),
    );
    widget.realtime!.watch(_serviceRequest?.id);
  }

  Future<void> _loadLastRequest() async {
    final requestId = _lastRequestId;
    if (requestId == null) return;
    await _run(() async {
      final snapshot = await widget.gateway.loadServiceRequest(
        _session,
        requestId,
      );
      if (mounted) setState(() => _serviceRequest = snapshot);
      widget.realtime?.watch(requestId);
      _startSnapshotPolling();
    });
  }

  Future<void> _logout() async {
    if (widget.gateway is CustomerAccountGateway) {
      try {
        await (widget.gateway as CustomerAccountGateway).logout(_session);
      } catch (_) {
        // The local token must still be cleared when the network is unavailable.
      }
    }
    await _clearSession();
  }

  Future<void> _clearSession() async {
    await widget.sessionStore?.clear();
    _snapshotTimer?.cancel();
    _authTimer?.cancel();
    widget.realtime?.dispose();
    if (!mounted) return;
    setState(() {
      _session = _session.copyWith(token: '', vehicleTypeId: '');
      _vehicles = const [];
      _quote = null;
      _serviceRequest = null;
      _createIdempotencyKey = null;
      _cancelIdempotencyKey = null;
      _lastRequestId = null;
      _error = null;
      _password.clear();
    });
  }

  Future<void> _showWallet() async {
    final amount = TextEditingController(text: '100000');
    WalletSummary? wallet;
    WalletTopupSummary? topup;
    String? topupKey;
    String? sheetError;

    try {
      wallet = await widget.gateway.loadWallet(_session);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
      amount.dispose();
      return;
    }
    if (!mounted) return;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => StatefulBuilder(
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
              Text('Ví lạnh', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              _amountRow('Số dư', wallet!.balance, emphasized: true),
              _amountRow('Đang giữ để rút', wallet.reserved),
              _amountRow('Khả dụng', wallet.available),
              const SizedBox(height: 16),
              TextField(
                controller: amount,
                onChanged: (_) {
                  topupKey = null;
                  setSheetState(() => sheetError = null);
                },
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'Số tiền nạp'),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () async {
                  final value = double.tryParse(amount.text);
                  if (value == null || value <= 0) {
                    setSheetState(
                      () => sheetError = 'Số tiền nạp không hợp lệ.',
                    );
                    return;
                  }
                  try {
                    topupKey ??= newRequestId();
                    final created = await widget.gateway.createTopup(
                      session: _session,
                      amount: value,
                      idempotencyKey: topupKey!,
                    );
                    setSheetState(() {
                      topup = created;
                      sheetError = null;
                    });
                    topupKey = null;
                  } catch (error) {
                    setSheetState(() => sheetError = error.toString());
                  }
                },
                icon: const Icon(Icons.qr_code_2),
                label: const Text('Tạo mã nạp tiền'),
              ),
              if (sheetError != null)
                Text(
                  sheetError!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              if (topup != null) ...[
                const SizedBox(height: 16),
                SelectableText('Nội dung: ${topup!.reference}'),
                const SizedBox(height: 4),
                SelectableText(topup!.vietQrPayload),
              ],
            ],
          ),
        ),
      ),
    );
    amount.dispose();
  }

  void _startSnapshotPolling() {
    _snapshotTimer?.cancel();
    _snapshotTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      unawaited(_refreshSnapshot());
    });
  }

  Future<void> _refreshSnapshot() async {
    final request = _serviceRequest;
    if (request == null || _busy || request.status == 'CANCELLED') {
      return;
    }

    try {
      final snapshot = await widget.gateway.loadServiceRequest(
        _session,
        request.id,
      );
      if (!mounted) return;
      setState(() => _serviceRequest = snapshot);
    } on BookingApiException catch (error) {
      if (error.statusCode == 401) await _clearSession();
    } catch (_) {
      // The next interval retries from the authoritative worker snapshot.
    }
  }
}
