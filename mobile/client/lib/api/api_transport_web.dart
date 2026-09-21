// ignore_for_file: avoid_web_libraries_in_flutter, deprecated_member_use

import 'dart:convert';
import 'dart:html' as html;

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
    final requestHeaders = <String, String>{
      'Accept': 'application/json',
      if (token.isNotEmpty) 'Authorization': 'Bearer $token',
      if (body != null) 'Content-Type': 'application/json',
      ...headers,
    };
    final request = await html.HttpRequest.request(
      uri.toString(),
      method: method,
      requestHeaders: requestHeaders,
      sendData: body == null ? null : jsonEncode(body),
    );
    final content = request.responseText ?? '';
    final decoded = content.isEmpty ? <String, dynamic>{} : jsonDecode(content);

    return ApiResponse(
      statusCode: request.status ?? 0,
      body: decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'data': decoded},
    );
  }
}
