import 'package:driver/api/api_transport.dart';
import 'package:driver/api/device_location.dart';
import 'package:driver/api/driver_api.dart';
import 'package:driver/api/driver_realtime.dart';
import 'package:driver/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('unapproved driver can create a draft without seeing offers', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final transport = DriverWidgetTransport();
    await tester.pumpWidget(
      DriverApp(
        gateway: DriverApi(transport: transport),
        locationSource: FakeLocation(),
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
      'password123',
    );
    await tester.tap(find.byKey(const Key('driver-login-button')));
    await tester.pumpAndSettle();

    expect(find.text('Trạng thái: CHƯA TẠO'), findsOneWidget);
    expect(find.text('Nhận chuyến'), findsNothing);
    await tester.tap(find.text('Lưu hồ sơ'));
    await tester.pumpAndSettle();
    expect(find.text('Trạng thái: DRAFT'), findsOneWidget);
    expect(transport.draftSaved, isTrue);
    expect(tester.takeException(), isNull);
  });

  testWidgets('approved driver starts online with device coordinates', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final transport = DriverWidgetTransport(approved: true);
    await tester.pumpWidget(
      DriverApp(
        gateway: DriverApi(transport: transport),
        locationSource: FakeLocation(),
        initialSession: const DriverSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'approved-token',
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();

    expect(transport.onlineLocation?['latitude'], 10.77);
    expect(transport.onlineLocation?['longitude'], 106.70);
    expect(find.text('Đang nhận chuyến'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Thu nhập'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('reconnect refreshes accepted offers and rejoins the room', (
    tester,
  ) async {
    final transport = DriverWidgetTransport(approved: true);
    final realtime = FakeDriverRealtime();
    await tester.pumpWidget(
      DriverApp(
        gateway: DriverApi(transport: transport),
        locationSource: FakeLocation(),
        realtime: realtime,
        initialSession: const DriverSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'approved-token',
        ),
      ),
    );
    await tester.pumpAndSettle();
    transport.offerVisible = true;
    realtime.reconnected();
    await tester.pumpAndSettle();

    expect(realtime.watchedRequest, 'request-1');
    expect(find.text('Tiếp tục chuyến đang chạy'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('denied location leaves driver offline', (tester) async {
    final transport = DriverWidgetTransport(approved: true);
    await tester.pumpWidget(
      DriverApp(
        gateway: DriverApi(transport: transport),
        locationSource: DeniedLocation(),
        initialSession: const DriverSession(
          baseUrl: 'http://localhost/api/v1',
          token: 'approved-token',
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    expect(transport.onlineLocation, isNull);
    expect(find.text('Đang ngoại tuyến'), findsOneWidget);
    expect(find.textContaining('Cần cấp quyền vị trí'), findsOneWidget);
  });
}

class DeniedLocation implements DriverLocationSource {
  @override
  Future<DriverPosition> current() async =>
      throw StateError('Cần cấp quyền vị trí để nhận chuyến.');
}

class FakeDriverRealtime extends DriverRealtime {
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

class FakeLocation implements DriverLocationSource {
  @override
  Future<DriverPosition> current() async =>
      const DriverPosition(10.77, 106.70, 5);
}

class DriverWidgetTransport implements ApiTransport {
  DriverWidgetTransport({this.approved = false});

  final bool approved;
  bool draftSaved = false;
  bool offerVisible = false;
  Map<String, dynamic>? onlineLocation;

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    final path = uri.path;
    if (path.endsWith('/auth/login') && body?['app_type'] == 'DRIVER_APP') {
      return const ApiResponse(422, {'message': 'Not approved.'});
    }
    if (path.endsWith('/auth/login')) {
      return const ApiResponse(200, {'token': 'onboarding-token'});
    }
    if (path.endsWith('/me')) {
      return ApiResponse(200, {
        'data': <String, dynamic>{'id': 'driver'},
      });
    }
    if (path.endsWith('/driver/application') && method == 'POST') {
      draftSaved = true;
    }
    if (path.endsWith('/driver/application') && !approved && !draftSaved) {
      return const ApiResponse(422, {'message': 'No profile.'});
    }
    if (path.endsWith('/catalog/vehicle-types')) {
      return const ApiResponse(200, {
        'data': [
          {'id': 'motorbike-1', 'name': 'Xe máy'},
        ],
      });
    }
    if (path.endsWith('/driver/offers')) {
      if (offerVisible) {
        return ApiResponse(200, {
          'data': [
            <String, dynamic>{
              'id': 'offer-1',
              'status': 'ACCEPTED',
              'estimated_pickup_distance_meters': 500,
              'estimated_driver_earning': 15000,
              'expires_at': DateTime.now()
                  .add(const Duration(minutes: 1))
                  .toIso8601String(),
              'service_request': <String, dynamic>{
                'id': 'request-1',
                'service_type': 'DRIVE',
                'status': 'DRIVER_ARRIVING',
                'payment': <String, dynamic>{
                  'method': 'CASH',
                  'customer_payable': 18000,
                },
                'stops': [
                  <String, dynamic>{
                    'type': 'PICKUP',
                    'latitude': 10.77,
                    'longitude': 106.7,
                  },
                  <String, dynamic>{
                    'type': 'DROPOFF',
                    'latitude': 10.78,
                    'longitude': 106.71,
                  },
                ],
              },
            },
          ],
        });
      }
      return const ApiResponse(200, {'data': []});
    }
    if (path.endsWith('/driver/history')) {
      return const ApiResponse(200, {'data': []});
    }
    if (path.endsWith('/wallet')) {
      return const ApiResponse(200, {
        'data': {
          'balance': 100000,
          'reserved_withdrawal_amount': 0,
          'available_balance': 100000,
        },
      });
    }
    if (path.endsWith('/driver/bank-accounts')) {
      return const ApiResponse(200, {'data': []});
    }
    if (path.endsWith('/driver/availability/online')) {
      onlineLocation = body;
    }
    return ApiResponse(200, {
      'data': <String, dynamic>{
        'review_status': approved ? 'APPROVED' : 'DRAFT',
        'availability_status': onlineLocation != null ? 'ONLINE' : 'OFFLINE',
        'capabilities': approved
            ? [
                {'is_active': true, 'service_type': 'DRIVE'},
              ]
            : [],
        'documents': [],
        'vehicles': [],
      },
    });
  }
}
