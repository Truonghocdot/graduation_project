import 'dart:convert';
import 'dart:io';

import 'api_transport.dart';

ApiTransport createPlatformTransport() => IoApiTransport();

class IoApiTransport implements ApiTransport {
  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    final client = HttpClient();

    try {
      final request = await client.openUrl(method, uri);
      request.headers
        ..set(HttpHeaders.acceptHeader, 'application/json')
        ..set(HttpHeaders.authorizationHeader, 'Bearer $token');

      for (final entry in headers.entries) {
        request.headers.set(entry.key, entry.value);
      }

      if (body != null) {
        request.headers.contentType = ContentType.json;
        request.add(utf8.encode(jsonEncode(body)));
      }

      final response = await request.close();
      final content = await response.transform(utf8.decoder).join();
      final decoded = content.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(content);

      return ApiResponse(
        statusCode: response.statusCode,
        body: decoded is Map<String, dynamic>
            ? decoded
            : <String, dynamic>{'data': decoded},
      );
    } finally {
      client.close();
    }
  }
}
