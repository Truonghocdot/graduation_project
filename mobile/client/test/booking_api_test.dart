import 'package:client/api/api_transport.dart';
import 'package:client/api/booking_api.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'sends quote booking and cancellation contracts with idempotency keys',
    () async {
      final transport = RecordingTransport();
      final api = BookingApi(transport: transport);
      const session = BookingSession(
        baseUrl: 'http://localhost/api/v1/',
        token: 'token',
        vehicleTypeId: 'vehicle-uuid',
      );

      final quote = await api.createQuote(
        session,
        const BookingDraft(
          service: ServiceKind.delivery,
          pickup: LocationDraft(
            address: 'Pickup',
            latitude: 10.77,
            longitude: 106.68,
          ),
          dropoff: LocationDraft(
            address: 'Dropoff',
            latitude: 10.78,
            longitude: 106.69,
          ),
          goodsType: 'GENERAL',
          weightKg: 5,
          passengerCount: 1,
        ),
      );
      final request = await api.createServiceRequest(
        session: session,
        quote: quote,
        payment: PaymentChoice.wallet,
        payer: PayerChoice.orderer,
        idempotencyKey: 'create-key',
      );
      await api.cancelServiceRequest(
        session: session,
        serviceRequest: request,
        idempotencyKey: 'cancel-key',
        reasonCode: 'CUSTOMER_CHANGED_MIND',
      );

      expect(transport.calls[0].uri.path, '/api/v1/quotes');
      expect(transport.calls[0].body?['service_type'], 'DELIVERY');
      expect(transport.calls[1].uri.path, '/api/v1/delivery/orders');
      expect(transport.calls[1].headers['Idempotency-Key'], 'create-key');
      expect(
        transport.calls[2].uri.path,
        '/api/v1/service-requests/request-uuid/cancel',
      );
      expect(transport.calls[2].headers['Idempotency-Key'], 'cancel-key');
    },
  );
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
    calls.add(
      RecordedCall(
        method: method,
        uri: uri,
        token: token,
        body: body,
        headers: headers,
      ),
    );

    if (uri.path.endsWith('/quotes')) {
      return ApiResponse(
        statusCode: 201,
        body: {
          'data': {
            'id': 'quote-uuid',
            'service_type': 'DELIVERY',
            'pricing': {
              'gross_fare': 18000,
              'voucher_discount': 0,
              'customer_payable': 18000,
              'currency': 'VND',
            },
            'route': {'distance_meters': 2000, 'duration_seconds': 600},
            'expires_at': DateTime.now()
                .add(const Duration(minutes: 5))
                .toUtc()
                .toIso8601String(),
          },
        },
      );
    }

    final cancelled = uri.path.endsWith('/cancel');
    return ApiResponse(
      statusCode: cancelled ? 200 : 201,
      body: {
        'data': {
          'id': 'request-uuid',
          'service_type': 'DELIVERY',
          'status': cancelled ? 'CANCELLED' : 'SEARCHING_DRIVER',
          'payment': {'method': 'WALLET', 'customer_payable': 18000},
        },
      },
    );
  }
}

class RecordedCall {
  const RecordedCall({
    required this.method,
    required this.uri,
    required this.token,
    required this.body,
    required this.headers,
  });

  final String method;
  final Uri uri;
  final String token;
  final Map<String, dynamic>? body;
  final Map<String, String> headers;
}
