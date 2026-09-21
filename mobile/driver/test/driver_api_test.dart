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
