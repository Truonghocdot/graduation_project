import 'dart:async';

import 'package:flutter/foundation.dart';

import '../api/device_location.dart';
import '../api/driver_api.dart';
import '../api/driver_realtime.dart';
import '../api/goong_navigation_api.dart';
import '../api/request_id.dart';
import '../api/session_store.dart';

class DriverAppController extends ChangeNotifier {
  DriverAppController({
    required this.gateway,
    required DriverSession initialSession,
    required this.locationSource,
    this.goong,
    this.sessionStore,
    this.realtime,
  }) : _session = initialSession;

  final DriverGateway gateway;
  final DriverLocationSource locationSource;
  final GoongNavigationApi? goong;
  final DriverSessionStore? sessionStore;
  final DriverRealtime? realtime;

  DriverSession _session;
  DriverProfileSummary? profile;
  List<Map<String, dynamic>> vehicleTypes = const [];
  List<DriverOfferSummary> offers = const [];
  List<DriverJobSummary> history = const [];
  DriverWalletSummary? wallet;
  List<DriverBankAccountSummary> bankAccounts = const [];
  List<DriverNotificationSummary> notifications = const [];
  List<DriverTicketSummary> tickets = const [];
  final selectedServices = <String>{'DELIVERY'};
  final evidenceIds = <String, String>{};
  bool initializing = true;
  bool busy = false;
  bool _locationBusy = false;
  String? error;
  String? locationError;
  DriverPosition? currentPosition;
  Timer? _offerTimer;
  Timer? _locationTimer;
  final _offerKeys = <String, String>{};
  final _transitionKeys = <String, String>{};
  bool _disposed = false;

  DriverSession get session => _session;
  bool get authenticated => _session.token.isNotEmpty;
  bool get onboarding => _session.onboarding;
  DriverOperationsGateway? get operations => gateway is DriverOperationsGateway
      ? gateway as DriverOperationsGateway
      : null;
  DriverSupportGateway? get support =>
      gateway is DriverSupportGateway ? gateway as DriverSupportGateway : null;
  DriverOfferSummary? get activeOffer =>
      offers.where((offer) => offer.status == 'ACCEPTED').firstOrNull;

  Future<void> prepareLocation() async {
    try {
      if (locationSource case final DeviceLocationSource source) {
        await source.prepare();
      }
      currentPosition = await locationSource.current();
      locationError = null;
    } catch (exception) {
      locationError = exception.toString();
    }
    if (!_disposed) notifyListeners();
  }

  Future<DriverPosition?> refreshPosition() async {
    try {
      currentPosition = await locationSource.current();
      locationError = null;
      if (!_disposed) notifyListeners();
      return currentPosition;
    } catch (exception) {
      locationError = exception.toString();
      if (!_disposed) notifyListeners();
      return null;
    }
  }

  Future<void> initialize() async {
    if (!authenticated) {
      initializing = false;
      notifyListeners();
      return;
    }
    await _guard(() async {
      await operations?.validateSession(_session);
      await _loadApplication();
    }, showBusy: false);
    initializing = false;
    notifyListeners();
  }

  Future<void> authenticate(String phone, String password) async {
    await _guard(() async {
      final ops = operations;
      if (ops == null) {
        final token = await gateway.login(
          baseUrl: _session.baseUrl,
          phone: phone,
          password: password,
        );
        _session = _session.copyWith(token: token, onboarding: false);
      } else {
        final result = await ops.authenticate(
          baseUrl: _session.baseUrl,
          phone: phone,
          password: password,
        );
        _session = _session.copyWith(
          token: result.token,
          onboarding: result.onboarding,
        );
        await sessionStore?.save(result.token, onboarding: result.onboarding);
      }
      await _loadApplication();
    });
  }

  Future<void> register({
    required String name,
    required String phone,
    required String password,
  }) async {
    await _guard(() async {
      await operations?.register(
        baseUrl: _session.baseUrl,
        name: name,
        phone: phone,
        password: password,
      );
    });
  }

  Future<void> resendPhone(String phone) async {
    await _guard(
      () async =>
          operations?.resendPhone(baseUrl: _session.baseUrl, phone: phone),
    );
  }

  Future<void> verifyPhone(String phone, String code) async {
    final ops = operations;
    if (ops == null) return;
    await _guard(() async {
      final token = await ops.verifyPhone(
        baseUrl: _session.baseUrl,
        phone: phone,
        code: code,
      );
      _session = _session.copyWith(token: token, onboarding: true);
      await sessionStore?.save(token, onboarding: true);
      await _loadApplication();
    });
  }

  Future<void> resetPassword({
    required String phone,
    required String code,
    required String password,
  }) async {
    final ops = operations;
    if (ops == null) return;
    await _guard(() async {
      final token = await ops.verifyReset(
        baseUrl: _session.baseUrl,
        phone: phone,
        code: code,
      );
      await ops.resetPassword(
        baseUrl: _session.baseUrl,
        phone: phone,
        resetToken: token,
        password: password,
      );
    });
  }

  Future<void> beginPasswordReset(String phone) async {
    await _guard(() async {
      await operations?.forgotPassword(baseUrl: _session.baseUrl, phone: phone);
    });
  }

  Future<void> refreshApplication() => _guard(_loadApplication);

  Future<void> saveApplication() async {
    final ops = operations;
    if (ops == null) return;
    await _guard(() async {
      profile = await ops.saveApplication(_session);
      await _loadApplication();
    });
  }

  Future<void> createVehicle(String typeId, String plateNumber) async {
    await _guard(() async {
      await operations?.createVehicle(
        session: _session,
        vehicleTypeId: typeId,
        plateNumber: plateNumber,
      );
      await _loadApplication();
    });
  }

  Future<void> updateVehicle(
    String vehicleId,
    String typeId,
    String plateNumber,
  ) async {
    await _guard(() async {
      await operations?.updateVehicle(
        session: _session,
        vehicleId: vehicleId,
        vehicleTypeId: typeId,
        plateNumber: plateNumber,
      );
      await _loadApplication();
    });
  }

  Future<void> uploadDocument({
    required String type,
    required String name,
    required Uint8List bytes,
    String? number,
    String? vehicleId,
  }) async {
    await _guard(() async {
      await operations?.uploadDocument(
        session: _session,
        documentType: type,
        name: name,
        bytes: bytes,
        documentNumber: number,
        vehicleId: vehicleId,
      );
      await _loadApplication();
    });
  }

  Future<void> submitApplication(String vehicleId) async {
    await _guard(() async {
      await operations?.submitApplication(
        session: _session,
        vehicleId: vehicleId,
        serviceTypes: selectedServices.toList(growable: false),
      );
      await _loadApplication();
    });
  }

  void toggleService(String service, bool enabled) {
    enabled ? selectedServices.add(service) : selectedServices.remove(service);
    notifyListeners();
  }

  Future<void> setOnline(bool online) async {
    final ops = operations;
    if (ops == null) return;
    await _guard(() async {
      final position = online
          ? await locationSource.current()
          : const DriverPosition(0, 0, 0);
      if (online) currentPosition = position;
      profile = await ops.setAvailability(
        session: _session,
        online: online,
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
        serviceTypes: selectedServices.toList(growable: false),
      );
      _startLocationPolling();
    });
  }

  Future<void> loadOffers() => _guard(_loadOffers);

  Future<void> respond(DriverOfferSummary offer, String action) async {
    await _guard(() async {
      final key = '${offer.id}:$action';
      final result = await gateway.respond(
        session: _session,
        offer: offer,
        action: action,
        idempotencyKey: _offerKeys.putIfAbsent(key, newRequestId),
      );
      _offerKeys.remove(key);
      offers = offers
          .map((candidate) => candidate.id == result.id ? result : candidate)
          .toList(growable: false);
      realtime?.watch(
        result.status == 'ACCEPTED' ? result.serviceRequestId : null,
      );
      _startLocationPolling();
    });
  }

  Future<void> advance({
    required DriverOfferSummary offer,
    required String action,
    double? cashCollected,
    double? codCollected,
    String? outOfGeofenceReason,
  }) async {
    await _guard(() async {
      final position = await locationSource.current();
      currentPosition = position;
      final key = '${offer.id}:$action';
      final status = await gateway.transition(
        session: _session,
        offer: offer,
        action: action,
        latitude: position.latitude,
        longitude: position.longitude,
        cashCollected: cashCollected,
        codCollected: codCollected,
        evidenceId: evidenceIds[key],
        outOfGeofenceReason: outOfGeofenceReason,
        idempotencyKey: _transitionKeys.putIfAbsent(key, newRequestId),
      );
      _transitionKeys.remove(key);
      evidenceIds.remove(key);
      offers = status == 'COMPLETED'
          ? offers
                .where((candidate) => candidate.id != offer.id)
                .toList(growable: false)
          : offers
                .map(
                  (candidate) => candidate.id == offer.id
                      ? candidate.withServiceStatus(status)
                      : candidate,
                )
                .toList(growable: false);
      realtime?.watch(activeOffer?.serviceRequestId);
      await _loadHistory();
    });
  }

  Future<void> uploadEvidence({
    required DriverOfferSummary offer,
    required String action,
    required String name,
    required Uint8List bytes,
  }) async {
    final ops = operations;
    if (ops == null) return;
    await _guard(() async {
      final position = await locationSource.current();
      currentPosition = position;
      evidenceIds['${offer.id}:$action'] = await ops.uploadEvidence(
        session: _session,
        serviceRequestId: offer.serviceRequestId,
        evidenceType: action == 'pickup' ? 'PICKUP' : 'DELIVERY',
        name: name,
        bytes: bytes,
        latitude: position.latitude,
        longitude: position.longitude,
      );
    });
  }

  Future<void> loadWallet() async {
    await _guard(() async {
      wallet = await gateway.loadWallet(_session);
      bankAccounts = await gateway.loadBankAccounts(_session);
    });
  }

  Future<DriverWalletTopupSummary?> createTopup(double amount) async {
    DriverWalletTopupSummary? result;
    await _guard(() async {
      result = await gateway.createTopup(
        session: _session,
        amount: amount,
        idempotencyKey: newRequestId(),
      );
      await loadWallet();
    });
    return result;
  }

  Future<void> addBankAccount({
    required String bankCode,
    required String accountNumber,
    required String accountName,
  }) async {
    await _guard(() async {
      await operations?.createBankAccount(
        session: _session,
        bankCode: bankCode,
        accountNumber: accountNumber,
        accountName: accountName,
      );
      bankAccounts = await gateway.loadBankAccounts(_session);
    });
  }

  Future<void> withdraw(String accountId, double amount) async {
    await _guard(
      () => gateway.requestWithdrawal(
        session: _session,
        bankAccountId: accountId,
        amount: amount,
        idempotencyKey: newRequestId(),
      ),
    );
  }

  Future<void> loadHistory() => _guard(_loadHistory);

  Future<void> _loadHistory() async {
    if (gateway is DriverHistoryGateway) {
      history = await (gateway as DriverHistoryGateway).loadJobHistory(
        _session,
      );
    }
  }

  Future<void> loadNotifications() async {
    final gateway = support;
    if (gateway == null) return;
    await _guard(
      () async => notifications = await gateway.loadNotifications(_session),
    );
  }

  Future<void> readNotification(String id) async {
    final gateway = support;
    if (gateway == null) return;
    await _guard(() async {
      await gateway.markNotificationRead(_session, id);
      notifications = await gateway.loadNotifications(_session);
    });
  }

  Future<void> loadTickets() async {
    final gateway = support;
    if (gateway == null) return;
    await _guard(() async => tickets = await gateway.loadTickets(_session));
  }

  Future<void> logout() async {
    try {
      await operations?.logout(_session);
    } catch (_) {}
    await clearSession();
  }

  Future<void> clearSession() async {
    await sessionStore?.clear();
    _offerTimer?.cancel();
    _locationTimer?.cancel();
    realtime?.dispose();
    _session = _session.copyWith(token: '', onboarding: false);
    profile = null;
    offers = const [];
    history = const [];
    wallet = null;
    notifyListeners();
  }

  Future<void> _loadApplication() async {
    final ops = operations;
    if (ops == null) {
      if (!onboarding) await _loadDriverWorkspace();
      return;
    }
    profile = await ops.loadApplication(_session);
    final approved = profile?.reviewStatus == 'APPROVED';
    await _setOnboarding(!approved);

    if (!approved) {
      vehicleTypes = await ops.loadVehicleTypes(_session);
      _syncCapabilities();
      return;
    }

    await _loadDriverWorkspace();
  }

  Future<void> _loadDriverWorkspace() async {
    _syncCapabilities();
    await _loadOffers();
    await _loadHistory();
    _startRealtime();
    _startOfferPolling();
    _startLocationPolling();
  }

  Future<void> _setOnboarding(bool value) async {
    if (_session.onboarding == value) return;
    _session = _session.copyWith(onboarding: value);
    await sessionStore?.save(_session.token, onboarding: value);
  }

  void _syncCapabilities() {
    final capabilities = profile?.capabilities ?? const [];
    if (capabilities.isNotEmpty) {
      selectedServices
        ..clear()
        ..addAll(capabilities);
    }
  }

  Future<void> _loadOffers() async {
    offers = await gateway.loadOffers(_session);
    realtime?.watch(activeOffer?.serviceRequestId);
    _startLocationPolling();
  }

  void _startRealtime() {
    if (realtime == null) return;
    const configured = String.fromEnvironment('REALTIME_URL');
    final uri = Uri.parse(_session.baseUrl);
    final url = configured.isNotEmpty
        ? configured
        : uri.replace(port: 3000, path: '', query: '', fragment: '').toString();
    realtime!.connect(
      url: url,
      token: _session.token,
      onChange: () {
        unawaited(_loadOffers());
        unawaited(loadNotifications());
      },
    );
    realtime!.watch(activeOffer?.serviceRequestId);
  }

  void _startOfferPolling() {
    _offerTimer?.cancel();
    _offerTimer = Timer.periodic(const Duration(seconds: 10), (_) async {
      try {
        await _loadOffers();
        notifyListeners();
      } on DriverApiException catch (exception) {
        if (exception.statusCode == 401) await clearSession();
      } catch (_) {}
    });
  }

  void _startLocationPolling() {
    _locationTimer?.cancel();
    if (profile?.availabilityStatus != 'ONLINE' && activeOffer == null) return;
    _locationTimer = Timer.periodic(const Duration(seconds: 5), (_) async {
      if (_locationBusy || !authenticated || onboarding) return;
      _locationBusy = true;
      try {
        final position = await locationSource.current();
        currentPosition = position;
        final ops = operations;
        if (ops == null) return;
        if (activeOffer != null) {
          await ops.updateLocation(
            session: _session,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
          );
        } else if (profile?.availabilityStatus == 'ONLINE') {
          profile = await ops.setAvailability(
            session: _session,
            online: true,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
            serviceTypes: selectedServices.toList(growable: false),
          );
        }
        if (!_disposed) notifyListeners();
      } on DriverApiException catch (exception) {
        if (exception.statusCode == 401) await clearSession();
      } catch (_) {
      } finally {
        _locationBusy = false;
      }
    });
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
      if (exception is DriverApiException && exception.statusCode == 401) {
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
    _offerTimer?.cancel();
    _locationTimer?.cancel();
    realtime?.dispose();
    goong?.dispose();
    super.dispose();
  }
}
