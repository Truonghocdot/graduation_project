import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api/booking_api.dart';
import '../api/booking_realtime.dart';
import '../api/client_location.dart';
import '../api/goong_location_api.dart';
import '../api/request_id.dart';
import '../api/session_store.dart';

class ClientAppController extends ChangeNotifier {
  ClientAppController({
    required this.gateway,
    required BookingSession initialSession,
    this.locationSource,
    this.goong,
    this.sessionStore,
    this.realtime,
  }) : _session = initialSession;

  final BookingGateway gateway;
  final ClientLocationSource? locationSource;
  final GoongLocationApi? goong;
  final BookingSessionStore? sessionStore;
  final BookingRealtime? realtime;

  BookingSession _session;
  List<VehicleOption> vehicles = const [];
  List<ServiceRequestSummary> history = const [];
  QuoteSummary? quote;
  ServiceRequestSummary? activeRequest;
  TrackingSummary? tracking;
  WalletSummary? wallet;
  CustomerProfileSummary? customerProfile;
  List<AppNotificationSummary> notifications = const [];
  int unreadNotificationCount = 0;
  List<SupportTicketSummary> tickets = const [];
  bool initializing = true;
  bool busy = false;
  String? error;
  ClientPosition? currentPosition;
  String? locationError;
  String? _createKey;
  String? _cancelKey;
  Timer? _snapshotTimer;
  Timer? _authTimer;
  bool _disposed = false;

  BookingSession get session => _session;
  bool get authenticated => _session.token.isNotEmpty;

  Future<void> prepareLocation() async {
    final source = locationSource;
    if (source == null) return;
    try {
      if (source case final DeviceClientLocationSource deviceSource) {
        await deviceSource.prepare();
      }
      currentPosition = await source.current();
      locationError = null;
    } catch (exception) {
      locationError = exception.toString();
    }
    if (!_disposed) notifyListeners();
  }

  Future<ClientPosition?> refreshLocation() async {
    final source = locationSource;
    if (source == null) return currentPosition;
    try {
      currentPosition = await source.current();
      locationError = null;
      if (!_disposed) notifyListeners();
      return currentPosition;
    } catch (exception) {
      locationError = exception.toString();
      if (!_disposed) notifyListeners();
      return null;
    }
  }

  BookingSupportGateway? get supportGateway => gateway is BookingSupportGateway
      ? gateway as BookingSupportGateway
      : null;
  CustomerAccountGateway? get accountGateway =>
      gateway is CustomerAccountGateway
      ? gateway as CustomerAccountGateway
      : null;

  Future<void> initialize() async {
    if (!authenticated) {
      initializing = false;
      notifyListeners();
      return;
    }
    await _guard(() async {
      customerProfile = await accountGateway?.validateSession(_session);
      await _loadCatalog();
      await _loadHistory();
      final lastId = await sessionStore?.readLastRequestId();
      if (lastId != null) {
        try {
          activeRequest = await gateway.loadServiceRequest(_session, lastId);
        } on BookingApiException catch (exception) {
          if (exception.statusCode == 401) rethrow;
        }
      }
      await _loadTracking();
      _startRealtime();
      _startPolling();
    }, showBusy: false);
    if (authenticated) unawaited(loadNotifications());
    initializing = false;
    notifyListeners();
  }

  Future<void> login(String phone, String password) async {
    await _guard(() async {
      final token = await gateway.login(
        baseUrl: _session.baseUrl,
        phone: phone,
        password: password,
      );
      _session = _session.copyWith(token: token, vehicleTypeId: '');
      await sessionStore?.writeToken(token);
      customerProfile = await accountGateway?.validateSession(_session);
      await _loadCatalog();
      await _loadHistory();
      _startRealtime();
      _startPolling();
    });
    if (authenticated) unawaited(loadNotifications());
  }

  Future<void> register({
    required String name,
    required String phone,
    required String password,
  }) async {
    final account = accountGateway;
    if (account == null) return;
    await _guard(
      () => account.register(
        baseUrl: _session.baseUrl,
        name: name,
        phone: phone,
        password: password,
      ),
    );
  }

  Future<void> resendPhone(String phone) async {
    await _guard(() async {
      await accountGateway?.resendPhone(
        baseUrl: _session.baseUrl,
        phone: phone,
      );
    });
  }

  Future<void> verifyPhone(String phone, String code) async {
    final account = accountGateway;
    if (account == null) return;
    await _guard(() async {
      final token = await account.verifyPhone(
        baseUrl: _session.baseUrl,
        phone: phone,
        code: code,
      );
      _session = _session.copyWith(token: token, vehicleTypeId: '');
      await sessionStore?.writeToken(token);
      customerProfile = await accountGateway?.validateSession(_session);
      await _loadCatalog();
      await _loadHistory();
      _startRealtime();
      _startPolling();
    });
    if (authenticated) unawaited(loadNotifications());
  }

  Future<void> resetPassword({
    required String phone,
    required String code,
    required String password,
  }) async {
    final account = accountGateway;
    if (account == null) return;
    await _guard(() async {
      final token = await account.verifyReset(
        baseUrl: _session.baseUrl,
        phone: phone,
        code: code,
      );
      await account.resetPassword(
        baseUrl: _session.baseUrl,
        phone: phone,
        resetToken: token,
        password: password,
      );
    });
  }

  Future<void> beginPasswordReset(String phone) async {
    await _guard(() async {
      await accountGateway?.forgotPassword(
        baseUrl: _session.baseUrl,
        phone: phone,
      );
    });
  }

  void selectVehicle(String id) {
    _session = _session.copyWith(vehicleTypeId: id);
    quote = null;
    notifyListeners();
  }

  Future<void> requestQuote(BookingDraft draft) async {
    await _guard(() async {
      quote = await gateway.createQuote(_session, draft);
      _createKey = null;
    });
  }

  Future<void> createRequest({
    required PaymentChoice payment,
    required PayerChoice payer,
    String? recipientUserId,
  }) async {
    final currentQuote = quote;
    if (currentQuote == null) return;
    await _guard(() async {
      _createKey ??= newRequestId();
      activeRequest = await gateway.createServiceRequest(
        session: _session,
        quote: currentQuote,
        payment: payment,
        payer: payer,
        recipientUserId: recipientUserId,
        idempotencyKey: _createKey!,
      );
      _createKey = null;
      await sessionStore?.writeLastRequestId(activeRequest!.id);
      realtime?.watch(activeRequest!.id);
      await _loadTracking();
      await _loadHistory();
      _startPolling();
    });
  }

  Future<void> refreshActiveRequest() async {
    final request = activeRequest;
    if (request == null) return;
    try {
      activeRequest = await gateway.loadServiceRequest(_session, request.id);
      await _loadTracking();
      notifyListeners();
    } on BookingApiException catch (exception) {
      if (exception.statusCode == 401) await clearSession();
    }
  }

  Future<void> cancelActiveRequest() async {
    final request = activeRequest;
    if (request == null) return;
    await _guard(() async {
      _cancelKey ??= newRequestId();
      activeRequest = await gateway.cancelServiceRequest(
        session: _session,
        serviceRequest: request,
        idempotencyKey: _cancelKey!,
        reasonCode: 'CUSTOMER_CHANGED_MIND',
      );
      _cancelKey = null;
      await _loadHistory();
    });
  }

  Future<void> loadHistory() => _guard(_loadHistory);

  Future<void> _loadHistory() async {
    if (gateway is BookingHistoryGateway) {
      history = await (gateway as BookingHistoryGateway).loadServiceRequests(
        _session,
      );
      activeRequest ??= history
          .where((request) => !isTerminal(request.status))
          .firstOrNull;
    }
  }

  Future<void> selectHistoryRequest(ServiceRequestSummary request) async {
    await _guard(() async {
      activeRequest = await gateway.loadServiceRequest(_session, request.id);
      await sessionStore?.writeLastRequestId(request.id);
      realtime?.watch(request.id);
      await _loadTracking();
      _startPolling();
    });
  }

  Future<void> loadWallet() async {
    await _guard(() async => wallet = await gateway.loadWallet(_session));
  }

  Future<WalletTopupSummary?> createTopup(double amount) async {
    WalletTopupSummary? result;
    await _guard(() async {
      result = await gateway.createTopup(
        session: _session,
        amount: amount,
        idempotencyKey: newRequestId(),
      );
    });
    return result;
  }

  Future<void> loadNotifications() async {
    final support = supportGateway;
    if (support == null) return;
    await _guard(() async {
      notifications = await support.loadNotifications(_session);
      unreadNotificationCount = await support.loadUnreadNotificationCount(
        _session,
      );
    });
  }

  Future<void> readNotification(String id) async {
    final support = supportGateway;
    if (support == null) return;
    await _guard(() async {
      await support.markNotificationRead(_session, id);
      notifications = await support.loadNotifications(_session);
      unreadNotificationCount = await support.loadUnreadNotificationCount(
        _session,
      );
    });
  }

  Future<void> loadTickets() async {
    final support = supportGateway;
    if (support == null) return;
    await _guard(() async => tickets = await support.loadTickets(_session));
  }

  Future<void> logout() async {
    try {
      await accountGateway?.logout(_session);
    } catch (_) {
      // Local logout must remain available while offline.
    }
    await clearSession();
  }

  Future<void> clearSession() async {
    await sessionStore?.clear();
    _snapshotTimer?.cancel();
    _authTimer?.cancel();
    realtime?.dispose();
    _session = _session.copyWith(token: '', vehicleTypeId: '');
    vehicles = const [];
    history = const [];
    quote = null;
    activeRequest = null;
    tracking = null;
    wallet = null;
    customerProfile = null;
    notifications = const [];
    unreadNotificationCount = 0;
    tickets = const [];
    notifyListeners();
  }

  bool isTerminal(String status) => const {
    'COMPLETED',
    'CANCELLED',
    'DELIVERY_FAILED',
    'RETURNED',
  }.contains(status);

  Future<void> _loadCatalog() async {
    vehicles = await gateway.loadVehicleTypes(_session);
    if (vehicles.isEmpty) {
      throw const BookingApiException('Chưa có loại phương tiện khả dụng.');
    }
    if (_session.vehicleTypeId.isEmpty ||
        !vehicles.any((vehicle) => vehicle.id == _session.vehicleTypeId)) {
      _session = _session.copyWith(vehicleTypeId: vehicles.first.id);
    }
  }

  void _startRealtime() {
    _authTimer?.cancel();
    final account = accountGateway;
    if (account != null) {
      _authTimer = Timer.periodic(const Duration(seconds: 30), (_) async {
        try {
          await account.validateSession(_session);
        } on BookingApiException catch (exception) {
          if (exception.statusCode == 401) await clearSession();
        } catch (_) {}
      });
    }
    if (realtime == null) return;
    const configured = String.fromEnvironment('REALTIME_URL');
    final uri = Uri.parse(_session.baseUrl);
    final url = configured.isNotEmpty
        ? configured
        : uri.replace(port: 3000, path: '', query: '', fragment: '').toString();
    realtime!.eventHandler = (_) => unawaited(_loadTracking());
    realtime!.connect(
      url: url,
      token: _session.token,
      onChange: () {
        unawaited(refreshActiveRequest());
        unawaited(loadNotifications());
      },
    );
    realtime!.watch(activeRequest?.id);
  }

  void _startPolling() {
    _snapshotTimer?.cancel();
    _snapshotTimer = Timer.periodic(
      const Duration(seconds: 10),
      (_) => unawaited(refreshActiveRequest()),
    );
  }

  Future<void> _loadTracking() async {
    final request = activeRequest;
    final trackingGateway = gateway is BookingTrackingGateway
        ? gateway as BookingTrackingGateway
        : null;
    if (request == null || trackingGateway == null) return;
    try {
      tracking = await trackingGateway.loadTracking(_session, request.id);
      if (!_disposed) notifyListeners();
    } on BookingApiException catch (exception) {
      if (exception.statusCode == 401) await clearSession();
    } catch (_) {
      // The snapshot remains usable when the live projection is unavailable.
    }
  }

  Future<void> _guard(
    Future<void> Function() operation, {
    bool showBusy = true,
  }) async {
    if (showBusy) busy = true;
    error = null;
    notifyListeners();
    try {
      await operation();
    } catch (exception) {
      if (exception is BookingApiException && exception.statusCode == 401) {
        await clearSession();
      }
      error = exception.toString();
    } finally {
      if (showBusy) busy = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _snapshotTimer?.cancel();
    _authTimer?.cancel();
    realtime?.dispose();
    goong?.dispose();
    super.dispose();
  }
}
