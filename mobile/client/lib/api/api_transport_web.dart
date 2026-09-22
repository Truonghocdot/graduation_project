import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_transport.dart';

ApiTransport createPlatformTransport() => WebApiTransport();

class WebApiTransport implements ApiTransport {
  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) async {
    final client = http.Client();
    try {
      final request = http.Request(method, uri);
      request.headers.addAll({
        'Accept': 'application/json',
        if (token.isNotEmpty) 'Authorization': 'Bearer $token',
        if (body != null) 'Content-Type': 'application/json',
        ...headers,
      });
      if (body != null) {
        request.body = jsonEncode(body);
      }

      final response = await http.Response.fromStream(
        await client.send(request),
      );
      final decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body);
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
