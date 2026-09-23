import 'package:client/api/booking_api.dart';
import 'package:client/booking_app.dart';
import 'package:client/api/session_store.dart';
import 'package:client/api/booking_realtime.dart';
import 'package:client/presentation/pages/order/create_order_page.dart';
import 'package:client/presentation/pages/profile/notifications_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('notification bell opens the notification page', (tester) async {
    await tester.pumpWidget(
      BookingApp(
        gateway: FakeBookingGateway(),
        initialSession: const BookingSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'test-token',
          vehicleTypeId: 'vehicle-uuid',
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('customer-notifications-button')));
    await tester.pumpAndSettle();
    expect(find.byType(NotificationsPage), findsOneWidget);
  });

  testWidgets('logs in and loads the vehicle catalog', (tester) async {
    final gateway = FakeBookingGateway();
    await tester.pumpWidget(
      BookingApp(
        gateway: gateway,
        initialSession: const BookingSession(
          baseUrl: 'http://localhost/api/v1',
          token: '',
          vehicleTypeId: '',
        ),
      ),
    );

    await tester.enterText(
      find.widgetWithText(TextField, 'Số điện thoại'),
      '0901234567',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Mật khẩu'),
      'password',
    );
    await tester.tap(find.byKey(const Key('login-button')));
    await tester.pumpAndSettle();

    expect(find.text('Trang chủ'), findsWidgets);
    expect(find.text('Giao hàng'), findsOneWidget);
    await tester.tap(find.text('Giao hàng'));
    await tester.pumpAndSettle();
    expect(find.text('Xe máy'), findsOneWidget);
    expect(gateway.loginCalls, 1);
  });

  testWidgets('quotes creates and cancels a delivery request', (tester) async {
    final gateway = FakeBookingGateway();
    await tester.pumpWidget(
      BookingApp(
        gateway: gateway,
        initialSession: const BookingSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'test-token',
          vehicleTypeId: 'vehicle-uuid',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trang chủ'), findsWidgets);
    await tester.tap(find.text('Giao hàng'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(
      find.byKey(const Key('continue-service-button')),
    );
    expect(
      tester
          .widget<FilledButton>(
            find.byKey(const Key('continue-service-button')),
          )
          .onPressed,
      isNotNull,
    );
    tester
        .widget<FilledButton>(find.byKey(const Key('continue-service-button')))
        .onPressed!();
    await tester.pumpAndSettle();
    expect(find.byType(CreateOrderPage), findsOneWidget);

    await tester.drag(find.byType(ListView).last, const Offset(0, -500));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('quote-button')));
    await tester.pumpAndSettle();

    expect(find.text('Xác nhận dịch vụ'), findsOneWidget);
    expect(find.text('18000 VND'), findsNWidgets(2));
    expect(gateway.quoteCalls, 1);

    await tester.drag(find.byType(ListView).last, const Offset(0, -300));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('create-button')));
    await tester.pumpAndSettle();

    expect(find.text('request-uuid'), findsOneWidget);
    expect(find.text('Đang tìm tài xế'), findsOneWidget);
    expect(gateway.createCalls, 1);

    await tester.ensureVisible(find.text('Hủy yêu cầu'));
    await tester.tap(find.text('Hủy yêu cầu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Xác nhận hủy'));
    await tester.pumpAndSettle();

    expect(find.text('Đã hủy'), findsOneWidget);
    expect(gateway.cancelCalls, 1);
  });

  testWidgets(
    'restores the most recent request and clears the session on logout',
    (tester) async {
      final store = FakeBookingSessionStore();
      await tester.pumpWidget(
        BookingApp(
          gateway: FakeBookingGateway(),
          sessionStore: store,
          initialSession: const BookingSession(
            baseUrl: 'http://localhost/api/v1',
            token: 'test-token',
            vehicleTypeId: 'vehicle-uuid',
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Dịch vụ đang theo dõi'), findsOneWidget);
      await tester.tap(find.text('Dịch vụ đang theo dõi'));
      await tester.pumpAndSettle();
      expect(find.text('request-uuid'), findsOneWidget);
      await tester.tap(find.byType(BackButton));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tài khoản'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Đăng xuất'));
      await tester.pumpAndSettle();
      expect(store.cleared, isTrue);
      expect(find.byKey(const Key('login-button')), findsOneWidget);
    },
  );

  testWidgets('booking controls fit on a narrow phone', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      BookingApp(
        gateway: FakeBookingGateway(),
        initialSession: const BookingSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'test-token',
          vehicleTypeId: 'vehicle-uuid',
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Tài khoản'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Ví lạnh'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('reconnect refreshes the authoritative booking snapshot', (
    tester,
  ) async {
    final gateway = FakeBookingGateway();
    final realtime = FakeBookingRealtime();
    await tester.pumpWidget(
      BookingApp(
        gateway: gateway,
        sessionStore: FakeBookingSessionStore(),
        realtime: realtime,
        initialSession: const BookingSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'test-token',
          vehicleTypeId: 'vehicle-uuid',
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(realtime.watchedRequest, 'request-uuid');
    gateway.snapshotStatus = 'COMPLETED';
    realtime.reconnected();
    await tester.pumpAndSettle();
    expect(find.text('Đã hoàn thành'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
}

class FakeBookingRealtime extends BookingRealtime {
  void Function()? _onChange;
  String? watchedRequest;

  @override
  void connect({
    required String url,
    required String token,
    required void Function() onChange,
  }) => _onChange = onChange;
  @override
  void watch(String? requestId) => watchedRequest = requestId;
  void reconnected() => _onChange?.call();
  @override
  void dispose() {}
}

class FakeBookingSessionStore implements BookingSessionStore {
  bool cleared = false;

  @override
  Future<String?> readToken() async => 'test-token';
  @override
  Future<String?> readLastRequestId() async => 'request-uuid';
  @override
  Future<void> writeToken(String token) async {}
  @override
  Future<void> writeLastRequestId(String id) async {}
  @override
  Future<void> clear() async => cleared = true;
}

class FakeBookingGateway implements BookingGateway {
  int loginCalls = 0;
  int quoteCalls = 0;
  int createCalls = 0;
  int cancelCalls = 0;
  String snapshotStatus = 'SEARCHING_DRIVER';

  @override
  Future<String> login({
    required String baseUrl,
    required String phone,
    required String password,
  }) async {
    loginCalls++;
    return 'test-token';
  }

  @override
  Future<List<VehicleOption>> loadVehicleTypes(BookingSession session) async {
    return const [
      VehicleOption(id: 'vehicle-uuid', key: 'MOTORBIKE', name: 'Xe máy'),
    ];
  }

  @override
  Future<QuoteSummary> createQuote(
    BookingSession session,
    BookingDraft draft,
  ) async {
    quoteCalls++;
    return QuoteSummary(
      id: 'quote-uuid',
      service: draft.service,
      grossFare: 18000,
      voucherDiscount: 0,
      customerPayable: 18000,
      currency: 'VND',
      distanceMeters: 2000,
      durationSeconds: 600,
      expiresAt: DateTime.now().add(const Duration(minutes: 5)),
    );
  }

  @override
  Future<ServiceRequestSummary> createServiceRequest({
    required BookingSession session,
    required QuoteSummary quote,
    required PaymentChoice payment,
    required PayerChoice payer,
    required String idempotencyKey,
    String? recipientUserId,
  }) async {
    createCalls++;
    return ServiceRequestSummary(
      id: 'request-uuid',
      service: quote.service,
      status: 'SEARCHING_DRIVER',
      paymentMethod: payment,
      customerPayable: quote.customerPayable,
    );
  }

  @override
  Future<ServiceRequestSummary> cancelServiceRequest({
    required BookingSession session,
    required ServiceRequestSummary serviceRequest,
    required String idempotencyKey,
    required String reasonCode,
  }) async {
    cancelCalls++;
    return ServiceRequestSummary(
      id: serviceRequest.id,
      service: serviceRequest.service,
      status: 'CANCELLED',
      paymentMethod: serviceRequest.paymentMethod,
      customerPayable: serviceRequest.customerPayable,
    );
  }

  @override
  Future<ServiceRequestSummary> loadServiceRequest(
    BookingSession session,
    String serviceRequestId,
  ) async {
    return ServiceRequestSummary(
      id: serviceRequestId,
      service: ServiceKind.delivery,
      status: snapshotStatus,
      paymentMethod: PaymentChoice.wallet,
      customerPayable: 18000,
    );
  }

  @override
  Future<WalletSummary> loadWallet(BookingSession session) async {
    return const WalletSummary(
      id: 'wallet-1',
      balance: 100000,
      reserved: 0,
      available: 100000,
      currency: 'VND',
    );
  }

  @override
  Future<WalletTopupSummary> createTopup({
    required BookingSession session,
    required double amount,
    required String idempotencyKey,
  }) async {
    return WalletTopupSummary(
      id: 'topup-1',
      amount: amount,
      status: 'PENDING',
      reference: 'TOPUP123',
      vietQrPayload: 'bank=MB&amount=$amount',
    );
  }
}
