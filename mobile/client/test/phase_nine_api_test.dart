import 'package:client/api/api_transport.dart';
import 'package:client/api/booking_api.dart';
import 'package:client/api/request_id.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const session = BookingSession(
    baseUrl: 'http://localhost/api/v1',
    token: 'customer-token',
    vehicleTypeId: 'motorbike',
  );

  test('register verify reset and logout follow the worker contract', () async {
    final transport = CustomerTransport();
    final api = BookingApi(transport: transport);
    await api.register(
      baseUrl: session.baseUrl,
      name: 'Customer',
      phone: '0901234567',
      password: 'password123',
    );
    final token = await api.verifyPhone(
      baseUrl: session.baseUrl,
      phone: '0901234567',
      code: '123456',
    );
    await api.forgotPassword(baseUrl: session.baseUrl, phone: '0901234567');
    final resetToken = await api.verifyReset(
      baseUrl: session.baseUrl,
      phone: '0901234567',
      code: '654321',
    );
    await api.resetPassword(
      baseUrl: session.baseUrl,
      phone: '0901234567',
      resetToken: resetToken,
      password: 'anotherPassword123',
    );
    await api.logout(session);

    expect(token, 'customer-token');
    expect(transport.calls[0].body?['password_confirmation'], 'password123');
    expect(transport.calls[1].body?['app_type'], 'CUSTOMER_APP');
    expect(transport.calls[1].body?['code'], '123456');
    expect(transport.calls[4].body?['token'], resetToken);
    expect(transport.calls.last.uri.path, '/api/v1/auth/logout');
    expect(transport.calls.last.token, 'customer-token');
  });

  test(
    'lists ticket details and sends replies without disclosing another user',
    () async {
      final transport = CustomerTransport();
      final api = BookingApi(transport: transport);
      final tickets = await api.loadTickets(session);
      final ticket = await api.loadTicket(session, tickets.first.id);
      await api.replyToTicket(
        session: session,
        id: ticket.id,
        body: 'More details',
      );

      expect(tickets.single.status, 'OPEN');
      expect(ticket.messages.single.body, 'A message');
      expect(
        transport.calls.last.uri.path,
        '/api/v1/support/tickets/ticket-1/messages',
      );
      expect(transport.calls.last.body?['body'], 'More details');
    },
  );

  test(
    'preserves unauthorized status for clearing an expired session',
    () async {
      final api = BookingApi(transport: CustomerTransport(expired: true));
      await expectLater(
        api.validateSession(session),
        throwsA(
          isA<BookingApiException>().having(
            (error) => error.statusCode,
            'statusCode',
            401,
          ),
        ),
      );
    },
  );

  test(
    'loads the customer profile and exact unread notification count',
    () async {
      final transport = CustomerTransport();
      final api = BookingApi(transport: transport);

      final profile = await api.validateSession(session);
      final unreadCount = await api.loadUnreadNotificationCount(session);

      expect(profile.name, 'Customer');
      expect(profile.phone, '0901234567');
      expect(profile.email, 'customer@example.test');
      expect(unreadCount, 3);
      expect(transport.calls[0].uri.path, '/api/v1/me');
      expect(transport.calls[1].uri.path, '/api/v1/notifications/unread');
    },
  );

  test('parses the customer order history read model', () async {
    final api = BookingApi(transport: CustomerTransport());
    final history = await api.loadServiceRequests(session);

    expect(history.single.id, 'request-history-1');
    expect(history.single.pickupAddress, 'Pickup address');
    expect(history.single.dropoffAddress, 'Dropoff address');
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
    'reuses the chat id after a failed send, then rotates it on success',
    () async {
      final transport = FlakyCustomerChatTransport();
      final api = BookingApi(transport: transport);
      await expectLater(
        api.sendChat(
          session: session,
          serviceRequestId: 'trip-1',
          body: 'Where are you?',
        ),
        throwsA(isA<BookingApiException>()),
      );
      await api.sendChat(
        session: session,
        serviceRequestId: 'trip-1',
        body: 'Where are you?',
      );
      await api.sendChat(
        session: session,
        serviceRequestId: 'trip-1',
        body: 'Where are you?',
      );
      expect(transport.ids[0], transport.ids[1]);
      expect(transport.ids[1], isNot(transport.ids[2]));
    },
  );
}

class FlakyCustomerChatTransport implements ApiTransport {
  final ids = <String>[];

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    ids.add(body!['client_message_id'] as String);
    return ids.length == 1
        ? const ApiResponse(statusCode: 503, body: {'message': 'Offline'})
        : const ApiResponse(
            statusCode: 201,
            body: {'data': <String, dynamic>{}},
          );
  }
}

class CustomerTransport implements ApiTransport {
  CustomerTransport({this.expired = false});
  final bool expired;
  final calls = <CustomerCall>[];

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    calls.add(CustomerCall(uri, token, body));
    if (expired) {
      return const ApiResponse(
        statusCode: 401,
        body: {'message': 'Unauthenticated.'},
      );
    }
    if (uri.path.endsWith('/auth/phone/verify')) {
      return const ApiResponse(
        statusCode: 200,
        body: {'token': 'customer-token'},
      );
    }
    if (uri.path.endsWith('/auth/password/verify')) {
      return const ApiResponse(
        statusCode: 200,
        body: {'reset_token': 'reset-token'},
      );
    }
    if (uri.path.endsWith('/auth/logout') ||
        uri.path.endsWith('/auth/password/reset')) {
      return const ApiResponse(statusCode: 204, body: {});
    }
    if (uri.path.endsWith('/me')) {
      return const ApiResponse(
        statusCode: 200,
        body: {
          'data': {
            'id': 'customer-1',
            'name': 'Customer',
            'phone': '0901234567',
            'email': 'customer@example.test',
          },
        },
      );
    }
    if (uri.path.endsWith('/notifications/unread')) {
      return const ApiResponse(
        statusCode: 200,
        body: {
          'data': {'unread_count': 3},
        },
      );
    }
    if (uri.path.endsWith('/support/tickets')) {
      return const ApiResponse(
        statusCode: 200,
        body: {
          'data': [
            {'id': 'ticket-1', 'subject': 'Help', 'status': 'OPEN'},
          ],
        },
      );
    }
    if (uri.path.endsWith('/support/tickets/ticket-1')) {
      return const ApiResponse(
        statusCode: 200,
        body: {
          'data': {
            'id': 'ticket-1',
            'subject': 'Help',
            'status': 'OPEN',
            'messages': [
              {
                'sender': {'name': 'Customer'},
                'body': 'A message',
              },
            ],
          },
        },
      );
    }
    if (uri.path.endsWith('/service-requests')) {
      return const ApiResponse(
        statusCode: 200,
        body: {
          'data': [
            {
              'id': 'request-history-1',
              'service_type': 'DELIVERY',
              'status': 'COMPLETED',
              'booking_type': 'NOW',
              'payment': {'method': 'CASH', 'customer_payable': 18000},
              'stops': [
                {
                  'type': 'PICKUP',
                  'address': 'Pickup address',
                  'latitude': 10.77,
                  'longitude': 106.68,
                },
                {
                  'type': 'DROPOFF',
                  'address': 'Dropoff address',
                  'latitude': 10.78,
                  'longitude': 106.69,
                },
              ],
              'created_at': '2026-09-22T00:00:00Z',
            },
          ],
        },
      );
    }
    return const ApiResponse(
      statusCode: 200,
      body: {'data': <String, dynamic>{}},
    );
  }
}

class CustomerCall {
  const CustomerCall(this.uri, this.token, this.body);
  final Uri uri;
  final String token;
  final Map<String, dynamic>? body;
}
