import 'package:driver/api/driver_api.dart';
import 'package:driver/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('logs in lists and accepts an offer', (tester) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final gateway = FakeDriverGateway();
    await tester.pumpWidget(
      DriverApp(
        gateway: gateway,
        initialSession: const DriverSession(
          baseUrl: 'http://localhost/api/v1',
          token: '',
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
    await tester.tap(find.byKey(const Key('driver-login-button')));
    await tester.pumpAndSettle();

    expect(find.text('Giao hàng'), findsWidgets);
    expect(find.text('Đề nghị mới'), findsWidgets);

    await tester.tap(find.byKey(const Key('accept-offer-1')));
    await tester.pumpAndSettle();

    expect(find.text('Tiếp tục chuyến đang chạy'), findsOneWidget);
    expect(gateway.respondCalls, 1);
    expect(tester.takeException(), isNull);
  });
}

class FakeDriverGateway implements DriverGateway {
  int respondCalls = 0;

  @override
  Future<String> login({
    required String baseUrl,
    required String phone,
    required String password,
  }) async {
    return 'driver-token';
  }

  @override
  Future<List<DriverOfferSummary>> loadOffers(DriverSession session) async {
    return [
      DriverOfferSummary(
        id: 'offer-1',
        status: 'PENDING',
        serviceType: 'DELIVERY',
        serviceRequestId: 'request-1',
        serviceStatus: 'DRIVER_ARRIVING_PICKUP',
        paymentMethod: 'CASH',
        customerPayable: 18000,
        pickupDistanceMeters: 500,
        estimatedEarning: 15000,
        expiresAt: DateTime.now().add(const Duration(seconds: 30)),
        pickupLatitude: 10.77,
        pickupLongitude: 106.68,
        dropoffLatitude: 10.78,
        dropoffLongitude: 106.69,
      ),
    ];
  }

  @override
  Future<DriverOfferSummary> respond({
    required DriverSession session,
    required DriverOfferSummary offer,
    required String action,
    required String idempotencyKey,
  }) async {
    respondCalls++;
    return DriverOfferSummary(
      id: offer.id,
      status: action == 'accept' ? 'ACCEPTED' : 'DECLINED',
      serviceType: offer.serviceType,
      serviceRequestId: offer.serviceRequestId,
      serviceStatus: offer.serviceStatus,
      paymentMethod: offer.paymentMethod,
      customerPayable: offer.customerPayable,
      pickupDistanceMeters: offer.pickupDistanceMeters,
      estimatedEarning: offer.estimatedEarning,
      expiresAt: offer.expiresAt,
      pickupLatitude: offer.pickupLatitude,
      pickupLongitude: offer.pickupLongitude,
      dropoffLatitude: offer.dropoffLatitude,
      dropoffLongitude: offer.dropoffLongitude,
    );
  }

  @override
  Future<String> transition({
    required DriverSession session,
    required DriverOfferSummary offer,
    required String action,
    required double latitude,
    required double longitude,
    required String idempotencyKey,
    double? cashCollected,
    double? codCollected,
    String? evidenceId,
    String? outOfGeofenceReason,
  }) async {
    return offer.serviceStatus;
  }

  @override
  Future<DriverWalletSummary> loadWallet(DriverSession session) async {
    return const DriverWalletSummary(
      balance: 100000,
      reserved: 0,
      available: 100000,
    );
  }

  @override
  Future<List<DriverBankAccountSummary>> loadBankAccounts(
    DriverSession session,
  ) async {
    return const [];
  }

  @override
  Future<void> requestWithdrawal({
    required DriverSession session,
    required String bankAccountId,
    required double amount,
    required String idempotencyKey,
  }) async {}
}
