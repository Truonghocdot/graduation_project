import 'dart:typed_data';

import 'package:driver/api/api_transport.dart';
import 'package:driver/api/driver_api.dart';
import 'package:driver/api/request_id.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const session = DriverSession(
    baseUrl: 'http://localhost/api/v1',
    token: 'onboarding-token',
    onboarding: true,
  );

  test('falls back to onboarding session only for unapproved driver', () async {
    final transport = DriverOperationsTransport();
    final api = DriverApi(transport: transport);
    final result = await api.authenticate(
      baseUrl: session.baseUrl,
      phone: '0901234567',
      password: 'password123',
    );
    expect(result.onboarding, isTrue);
    expect(result.token, 'onboarding-token');
    expect(transport.calls.map((call) => call.body?['app_type']), [
      'DRIVER_APP',
      'CUSTOMER_APP',
    ]);
  });

  test(
    'creates draft, vehicle, private document and submits application',
    () async {
      final transport = DriverOperationsTransport();
      final api = DriverApi(transport: transport);

      expect(await api.loadApplication(session), isNull);
      final profile = await api.saveApplication(session, 500000);
      final vehicleTypes = await api.loadVehicleTypes(session);
      await api.createVehicle(
        session: session,
        vehicleTypeId: vehicleTypes.first['id'] as String,
        plateNumber: '59A12345',
      );
      await api.uploadDocument(
        session: session,
        documentType: 'IDENTITY',
        documentNumber: '123456789',
        name: 'card.jpg',
        bytes: Uint8List.fromList([1, 2]),
      );
      await api.submitApplication(
        session: session,
        vehicleId: 'vehicle-1',
        serviceTypes: ['DELIVERY'],
      );

      expect(profile.reviewStatus, 'DRAFT');
      expect(transport.uploaded?.fields['document_type'], 'IDENTITY');
      expect(transport.uploaded?.name, 'card.jpg');
      expect(transport.calls.last.body?['service_types'], ['DELIVERY']);
    },
  );

  test(
    'updates availability, assigned location, evidence and bank account',
    () async {
      final transport = DriverOperationsTransport();
      final api = DriverApi(transport: transport);
      final online = await api.setAvailability(
        session: session,
        online: true,
        latitude: 10.776,
        longitude: 106.7,
        accuracy: 5,
        serviceTypes: ['DELIVERY'],
      );
      await api.updateLocation(
        session: session,
        latitude: 10.777,
        longitude: 106.701,
        accuracy: 4,
      );
      final evidence = await api.uploadEvidence(
        session: session,
        serviceRequestId: 'request-1',
        evidenceType: 'PICKUP',
        name: 'pickup.jpg',
        bytes: Uint8List.fromList([1, 2, 3]),
        latitude: 10.777,
        longitude: 106.701,
      );
      await api.createBankAccount(
        session: session,
        bankCode: 'VCB',
        accountNumber: '123456789',
        accountName: 'Driver',
      );

      expect(online.availabilityStatus, 'ONLINE');
      expect(evidence, 'evidence-1');
      expect(transport.calls.first.body?['captured_at'], isA<String>());
      expect(transport.calls[1].uri.path, '/api/v1/driver/location');
      expect(transport.uploaded?.fields['evidence_type'], 'PICKUP');
      expect(transport.calls.last.body?['bank_code'], 'VCB');
    },
  );

  test(
    'does not fall back to customer session after a network failure',
    () async {
      final transport = DriverOperationsTransport(networkError: true);
      final api = DriverApi(transport: transport);
      await expectLater(
        api.authenticate(
          baseUrl: session.baseUrl,
          phone: '0901234567',
          password: 'password123',
        ),
        throwsA(
          isA<DriverApiException>().having(
            (e) => e.statusCode,
            'statusCode',
            503,
          ),
        ),
      );
      expect(transport.calls.length, 1);
    },
  );

  test('parses the closed driver job history read model', () async {
    final api = DriverApi(transport: DriverOperationsTransport());
    final history = await api.loadJobHistory(session);

    expect(history.single.id, 'job-1');
    expect(history.single.pickupAddress, 'Pickup address');
    expect(history.single.driverNetEarning, 15000);
  });

  test('generates unique RFC 4122 version 4 ids', () {
    final ids = List.generate(50, (_) => newRequestId());
    expect(ids.toSet().length, ids.length);
    expect(
      ids.first,
      matches(
        RegExp(
          r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
        ),
      ),
    );
  });

  test(
    'preserves unauthorized status for clearing a revoked session',
    () async {
      final api = DriverApi(
        transport: DriverOperationsTransport(revoked: true),
      );
      await expectLater(
        api.validateSession(session),
        throwsA(
          isA<DriverApiException>().having(
            (e) => e.statusCode,
            'statusCode',
            401,
          ),
        ),
      );
    },
  );

  test('reuses the incident idempotency key after network failure', () async {
    final transport = FlakyDriverIncidentTransport();
    final api = DriverApi(transport: transport);
    await expectLater(
      api.reportIncident(
        session: session,
        serviceRequestId: 'trip-1',
        incidentType: 'SOS',
      ),
      throwsA(isA<DriverApiException>()),
    );
    await api.reportIncident(
      session: session,
      serviceRequestId: 'trip-1',
      incidentType: 'SOS',
    );
    expect(transport.keys[0], transport.keys[1]);
  });
}

class FlakyDriverIncidentTransport implements ApiTransport {
  final keys = <String>[];

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    keys.add(headers['Idempotency-Key']!);
    return keys.length == 1
        ? const ApiResponse(503, {'message': 'Offline'})
        : const ApiResponse(201, {'data': <String, dynamic>{}});
  }
}

class DriverOperationsTransport implements ApiTransport, MultipartApiTransport {
  DriverOperationsTransport({this.networkError = false, this.revoked = false});
  final bool networkError;
  final bool revoked;
  final calls = <DriverCall>[];
  UploadCall? uploaded;

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    calls.add(DriverCall(uri, body));
    if (revoked) {
      return const ApiResponse(401, {'message': 'Unauthenticated.'});
    }
    if (uri.path.endsWith('/auth/login') && body?['app_type'] == 'DRIVER_APP') {
      return ApiResponse(networkError ? 503 : 422, {
        'message': 'Not approved.',
      });
    }
    if (uri.path.endsWith('/auth/login')) {
      return const ApiResponse(200, {'token': 'onboarding-token'});
    }
    if (uri.path.endsWith('/driver/application') && method == 'GET') {
      return const ApiResponse(422, {'message': 'Application not found.'});
    }
    if (uri.path.endsWith('/catalog/vehicle-types')) {
      return const ApiResponse(200, {
        'data': [
          {'id': 'motorbike-1', 'name': 'Motorbike'},
        ],
      });
    }
    if (uri.path.endsWith('/driver/history')) {
      return const ApiResponse(200, {
        'data': [
          {
            'id': 'job-1',
            'service_type': 'DRIVE',
            'status': 'COMPLETED',
            'payment': {
              'method': 'CASH',
              'customer_payable': 18000,
              'settlement': {'driver_net_earning': 15000},
            },
            'stops': [
              {'type': 'PICKUP', 'address': 'Pickup address'},
              {'type': 'DROPOFF', 'address': 'Dropoff address'},
            ],
            'created_at': '2026-09-22T00:00:00Z',
          },
        ],
      });
    }
    if (uri.path.endsWith('/driver/availability/online')) {
      return const ApiResponse(200, {
        'data': {'review_status': 'APPROVED', 'availability_status': 'ONLINE'},
      });
    }
    return const ApiResponse(200, {
      'data': {'review_status': 'DRAFT', 'availability_status': 'OFFLINE'},
    });
  }

  @override
  Future<ApiResponse> upload({
    required Uri uri,
    required String token,
    required String name,
    required Uint8List bytes,
    required Map<String, String> fields,
  }) async {
    uploaded = UploadCall(uri, name, fields);
    return const ApiResponse(201, {
      'data': {'id': 'evidence-1'},
    });
  }
}

class DriverCall {
  const DriverCall(this.uri, this.body);
  final Uri uri;
  final Map<String, dynamic>? body;
}

class UploadCall {
  const UploadCall(this.uri, this.name, this.fields);
  final Uri uri;
  final String name;
  final Map<String, String> fields;
}
