import 'dart:io';

import 'package:client/api/api_transport_web.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'web transport returns JSON validation errors without throwing',
    () async {
      final server = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
      String? authorization;
      server.listen((request) async {
        authorization = request.headers.value(HttpHeaders.authorizationHeader);
        request.response
          ..statusCode = 401
          ..headers.contentType = ContentType.json
          ..write('{"message":"Unauthenticated."}');
        await request.response.close();
      });

      try {
        final response = await WebApiTransport().send(
          method: 'GET',
          uri: Uri.parse('http://127.0.0.1:${server.port}/api/v1/me'),
          token: 'expired-token',
        );
        expect(response.statusCode, 401);
        expect(response.body['message'], 'Unauthenticated.');
        expect(authorization, 'Bearer expired-token');
      } finally {
        await server.close(force: true);
      }
    },
  );
}
