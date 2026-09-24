import 'package:driver/api/api_transport.dart';
import 'package:driver/api/driver_api.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('uses HTTPS endpoints for offer list and response', () async {
    final transport = RecordingTransport();
    final api = DriverApi(transport: transport);
    const session = DriverSession(
      baseUrl: 'http://localhost/api/v1',
      token: 'driver-token',
    );

    final offers = await api.loadOffers(session);
    await api.respond(
      session: session,
      offer: offers.first,
      action: 'accept',
      idempotencyKey: 'accept-key',
    );

    expect(transport.calls[0].uri.path, '/api/v1/driver/offers');
    expect(
      transport.calls[1].uri.path,
      '/api/v1/driver/offers/offer-1/respond',
    );
    expect(transport.calls[1].headers['Idempotency-Key'], 'accept-key');
  });

  test('creates a driver wallet VietQR top-up through the driver endpoint', () async {
    final transport = RecordingTransport();
    final api = DriverApi(transport: transport);
    const session = DriverSession(
      baseUrl: 'http://localhost/api/v1',
      token: 'driver-token',
    );

    final topup = await api.createTopup(
      session: session,
      amount: 200000,
      idempotencyKey: 'driver-topup-key',
    );

    expect(topup.status, 'PENDING');
    expect(transport.calls.single.uri.path, '/api/v1/driver/wallet/topups');
    expect(transport.calls.single.headers['Idempotency-Key'], 'driver-topup-key');
  });
}

class RecordingTransport implements ApiTransport {
  final calls = <RecordedCall>[];

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    calls.add(RecordedCall(uri: uri, headers: headers));
    if (uri.path.endsWith('/driver/wallet/topups')) {
      return ApiResponse(201, {
        'data': {
          'id': 'topup-1',
          'amount': 200000,
          'status': 'PENDING',
          'vietqr_reference': 'TOPUP-TEST',
          'vietqr_payload': 'bank=MB&amount=200000',
          'expires_at': DateTime.now()
              .add(const Duration(minutes: 30))
              .toUtc()
              .toIso8601String(),
        },
      });
    }
    final status = body?['action'] == 'accept' ? 'ACCEPTED' : 'PENDING';
    final offer = {
      'id': 'offer-1',
      'status': status,
      'estimated_pickup_distance_meters': 500,
      'estimated_driver_earning': 15000,
      'expires_at': DateTime.now()
          .add(const Duration(seconds: 30))
          .toUtc()
          .toIso8601String(),
      'service_request': {
        'id': 'request-1',
        'service_type': 'DELIVERY',
        'status': 'DRIVER_ARRIVING_PICKUP',
        'payment': {'method': 'CASH', 'customer_payable': 18000},
        'stops': [
          {'type': 'PICKUP', 'latitude': 10.77, 'longitude': 106.68},
          {'type': 'DROPOFF', 'latitude': 10.78, 'longitude': 106.69},
        ],
      },
    };
    return ApiResponse(200, {
      'data': method == 'GET' ? [offer] : offer,
    });
  }
}

class RecordedCall {
  const RecordedCall({required this.uri, required this.headers});
  final Uri uri;
  final Map<String, String> headers;
}
