import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'api/driver_api.dart';

void main() {
  const configured = String.fromEnvironment('API_BASE_URL');
  final defaultUrl = defaultTargetPlatform == TargetPlatform.android
      ? 'http://10.0.2.2:8000/api/v1'
      : 'http://127.0.0.1:8000/api/v1';
  runApp(
    DriverApp(
      gateway: DriverApi(),
      initialSession: DriverSession(
        baseUrl: configured.isEmpty ? defaultUrl : configured,
        token: const String.fromEnvironment('API_TOKEN'),
      ),
    ),
  );
}

class DriverApp extends StatelessWidget {
  const DriverApp({
    super.key,
    required this.gateway,
    required this.initialSession,
  });

  final DriverGateway gateway;
  final DriverSession initialSession;

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
      home: DriverOfferInbox(gateway: gateway, initialSession: initialSession),
    );
  }
}

class DriverOfferInbox extends StatefulWidget {
  const DriverOfferInbox({
    super.key,
    required this.gateway,
    required this.initialSession,
  });

  final DriverGateway gateway;
  final DriverSession initialSession;

  @override
  State<DriverOfferInbox> createState() => _DriverOfferInboxState();
}

class _DriverOfferInboxState extends State<DriverOfferInbox> {
  final _phone = TextEditingController();
  final _password = TextEditingController();
  late DriverSession _session = widget.initialSession;
  List<DriverOfferSummary> _offers = const [];
  Timer? _poller;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (_session.token.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _startPolling());
    }
  }

  @override
  void dispose() {
    _poller?.cancel();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _session.token.isEmpty ? 'Đăng nhập tài xế' : 'Đề nghị mới',
        ),
        actions: [
          if (_session.token.isNotEmpty)
            if (widget.gateway is DriverSupportGateway)
              IconButton(
                tooltip: 'Thong bao',
                onPressed: _busy ? null : _showNotifications,
                icon: const Icon(Icons.notifications_outlined),
              ),
          if (_session.token.isNotEmpty)
            IconButton(
              tooltip: 'Thu nhập và rút tiền',
              onPressed: _busy ? null : _showEarnings,
              icon: const Icon(Icons.account_balance_wallet_outlined),
            ),
          if (_session.token.isNotEmpty)
            IconButton(
              tooltip: 'Làm mới',
              onPressed: _busy ? null : _loadOffers,
              icon: const Icon(Icons.refresh),
            ),
          if (_session.token.isNotEmpty)
            IconButton(
              tooltip: 'Đăng xuất',
              onPressed: _logout,
              icon: const Icon(Icons.logout),
            ),
        ],
      ),
      body: SafeArea(child: _session.token.isEmpty ? _login() : _offerList()),
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
            ],
          ),
        ),
      ),
    );
  }

  Widget _offerList() {
    if (_busy && _offers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
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
    );
  }

  Widget _offerCard(DriverOfferSummary offer) {
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
                      onPressed: offer.status == 'PENDING' && !_busy
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
                      onPressed: offer.status == 'PENDING' && !_busy
                          ? () => _respond(offer, 'accept')
                          : null,
                      icon: const Icon(Icons.check),
                      label: const Text('Nhận chuyến'),
                    ),
                  ),
                ],
              )
            else if (_nextAction(offer) case final action?)
              FilledButton.icon(
                key: Key('transition-${offer.id}'),
                onPressed: _busy ? null : () => _advance(offer, action),
                icon: Icon(action.icon),
                label: Text(action.label),
              ),
            if (offer.status == 'ACCEPTED' &&
                widget.gateway is DriverSupportGateway) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _showChat(offer),
                    icon: const Icon(Icons.chat_bubble_outline),
                    label: const Text('Chat'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _openSupport(offer),
                    icon: const Icon(Icons.support_agent),
                    label: const Text('Ho tro'),
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
                      label: const Text('Danh gia'),
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
      final token = await widget.gateway.login(
        baseUrl: _session.baseUrl,
        phone: _phone.text.trim(),
        password: _password.text,
      );
      _session = _session.copyWith(token: token);
      await _loadOffersDirect();
      _startPolling();
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
      } catch (_) {}
      return;
    }
    await _run(_loadOffersDirect);
  }

  Future<void> _loadOffersDirect() async {
    final offers = await widget.gateway.loadOffers(_session);
    if (mounted) setState(() => _offers = offers);
  }

  Future<void> _respond(DriverOfferSummary offer, String action) async {
    await _run(() async {
      final result = await widget.gateway.respond(
        session: _session,
        offer: offer,
        action: action,
        idempotencyKey:
            'driver-$action-${offer.id}-${DateTime.now().microsecondsSinceEpoch}',
      );
      setState(() {
        _offers = _offers
            .map((candidate) => candidate.id == result.id ? result : candidate)
            .toList(growable: false);
      });
    });
  }

  _ExecutionAction? _nextAction(DriverOfferSummary offer) {
    return switch (offer.serviceStatus) {
      'DRIVER_ARRIVING_PICKUP' => const _ExecutionAction(
        value: 'arrive_pickup',
        label: 'Đã đến điểm lấy',
        icon: Icons.location_on_outlined,
        useDropoff: false,
      ),
      'AT_PICKUP' => const _ExecutionAction(
        value: 'pickup',
        label: 'Đã nhận hàng',
        icon: Icons.inventory_2_outlined,
        useDropoff: false,
      ),
      'PICKED_UP' => const _ExecutionAction(
        value: 'start_delivery',
        label: 'Bắt đầu giao',
        icon: Icons.local_shipping_outlined,
        useDropoff: false,
      ),
      'IN_DELIVERY' => const _ExecutionAction(
        value: 'deliver',
        label: 'Hoàn tất giao hàng',
        icon: Icons.check_circle_outline,
        useDropoff: true,
      ),
      'DRIVER_ARRIVING' => const _ExecutionAction(
        value: 'arrive',
        label: 'Đã đến điểm đón',
        icon: Icons.location_on_outlined,
        useDropoff: false,
      ),
      'DRIVER_ARRIVED' => const _ExecutionAction(
        value: 'start',
        label: 'Bắt đầu chuyến',
        icon: Icons.play_arrow,
        useDropoff: false,
      ),
      'IN_TRIP' => const _ExecutionAction(
        value: 'complete',
        label: 'Kết thúc chuyến',
        icon: Icons.flag_outlined,
        useDropoff: true,
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
      return;
    }
    await _run(() async {
      final status = await widget.gateway.transition(
        session: _session,
        offer: offer,
        action: action.value,
        latitude: action.useDropoff
            ? offer.dropoffLatitude
            : offer.pickupLatitude,
        longitude: action.useDropoff
            ? offer.dropoffLongitude
            : offer.pickupLongitude,
        cashCollected: shouldCollectCash
            ? double.tryParse(cashController.text)
            : null,
        codCollected: action.value == 'deliver'
            ? double.tryParse(codController.text)
            : null,
        idempotencyKey:
            'driver-transition-${offer.id}-${action.value}-${DateTime.now().microsecondsSinceEpoch}',
      );
      setState(() {
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
  }

  Future<void> _showEarnings() async {
    final wallet = await widget.gateway.loadWallet(_session);
    final accounts = await widget.gateway.loadBankAccounts(_session);
    if (!mounted) return;
    final amount = TextEditingController(text: '50000');
    String? selected;
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
        builder: (context, setSheetState) => Padding(
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
                onChanged: (value) => setSheetState(() => selected = value),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amount,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Số tiền rút'),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: selected == null
                    ? null
                    : () async {
                        await widget.gateway.requestWithdrawal(
                          session: _session,
                          bankAccountId: selected!,
                          amount: double.parse(amount.text),
                          idempotencyKey:
                              'driver-withdrawal-${DateTime.now().microsecondsSinceEpoch}',
                        );
                        if (context.mounted) Navigator.pop(context);
                      },
                icon: const Icon(Icons.arrow_upward),
                label: const Text('Gửi yêu cầu rút'),
              ),
            ],
          ),
        ),
      ),
    );
    amount.dispose();
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
          title: const Text('Chat chuyen di'),
          content: SizedBox(
            width: 440,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 240),
                  child: messages.isEmpty
                      ? const Text('Chua co tin nhan.')
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
                  decoration: const InputDecoration(labelText: 'Tin nhan'),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Dong'),
            ),
            FilledButton.icon(
              onPressed: () => Navigator.pop(context, true),
              icon: const Icon(Icons.send),
              label: const Text('Gui'),
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
        title: const Text('Tao yeu cau ho tro'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: subject,
              maxLength: 191,
              decoration: const InputDecoration(labelText: 'Tieu de'),
            ),
            TextField(
              controller: description,
              maxLength: 5000,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Mo ta'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Huy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Gui'),
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
        title: const Text('Bao dong SOS?'),
        content: TextField(
          controller: description,
          maxLength: 5000,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Mo ta su co'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Huy'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, true),
            icon: const Icon(Icons.sos_outlined),
            label: const Text('Bao ngay'),
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
          title: const Text('Danh gia khach hang'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                initialValue: score,
                decoration: const InputDecoration(labelText: 'So sao'),
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
                decoration: const InputDecoration(labelText: 'Nhan xet'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Huy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Gui'),
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
            Text('Thong bao', style: Theme.of(context).textTheme.titleLarge),
            if (notifications.isEmpty)
              const ListTile(title: Text('Chua co thong bao.')),
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
                        tooltip: 'Danh dau da doc',
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

  Future<void> _run(Future<void> Function() operation) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await operation();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _logout() {
    _poller?.cancel();
    setState(() {
      _session = _session.copyWith(token: '');
      _offers = const [];
      _password.clear();
    });
  }
}

class _ExecutionAction {
  const _ExecutionAction({
    required this.value,
    required this.label,
    required this.icon,
    required this.useDropoff,
  });

  final String value;
  final String label;
  final IconData icon;
  final bool useDropoff;
}
