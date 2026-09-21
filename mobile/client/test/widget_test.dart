import 'package:client/api/booking_api.dart';
import 'package:client/booking_app.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
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

    expect(find.text('Đặt dịch vụ'), findsOneWidget);
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

    expect(find.text('Đặt dịch vụ'), findsOneWidget);
    expect(find.text('Delivery'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('quote-button')));
    await tester.tap(find.byKey(const Key('quote-button')));
    await tester.pumpAndSettle();

    expect(find.text('Báo giá'), findsOneWidget);
    expect(find.text('18000 VND'), findsNWidgets(2));
    expect(gateway.quoteCalls, 1);

    await tester.ensureVisible(find.byKey(const Key('create-button')));
    await tester.tap(find.byKey(const Key('create-button')));
    await tester.pumpAndSettle();

    expect(find.text('Mã yêu cầu'), findsOneWidget);
    expect(find.text('SEARCHING_DRIVER'), findsOneWidget);
    expect(gateway.createCalls, 1);

    await tester.ensureVisible(find.text('Hủy yêu cầu'));
    await tester.tap(find.text('Hủy yêu cầu'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Xác nhận hủy'));
    await tester.pumpAndSettle();

    expect(find.text('CANCELLED'), findsOneWidget);
    expect(gateway.cancelCalls, 1);
  });
}

class FakeBookingGateway implements BookingGateway {
  int loginCalls = 0;
  int quoteCalls = 0;
  int createCalls = 0;
  int cancelCalls = 0;

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
      status: 'SEARCHING_DRIVER',
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
