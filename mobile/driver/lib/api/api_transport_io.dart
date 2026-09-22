import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import 'api_transport.dart';

ApiTransport createPlatformTransport() => IoApiTransport();

class IoApiTransport implements ApiTransport, MultipartApiTransport {
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
        response.statusCode,
        decoded is Map<String, dynamic> ? decoded : {'data': decoded},
      );
    } finally {
      client.close();
    }
  }

  @override
  Future<ApiResponse> upload({
    required Uri uri,
    required String token,
    required String name,
    required Uint8List bytes,
    required Map<String, String> fields,
  }) async {
    final request = http.MultipartRequest('POST', uri)
      ..headers['Accept'] = 'application/json'
      ..headers['Authorization'] = 'Bearer $token'
      ..fields.addAll(fields)
      ..files.add(http.MultipartFile.fromBytes('file', bytes, filename: name));
    final client = http.Client();
    try {
      final response = await http.Response.fromStream(
        await client.send(request),
      );
      final decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body);
      return ApiResponse(
        response.statusCode,
        decoded is Map<String, dynamic> ? decoded : {'data': decoded},
      );
    } finally {
      client.close();
    }
  }
}
