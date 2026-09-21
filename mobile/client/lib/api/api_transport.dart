import 'api_transport_stub.dart'
    if (dart.library.io) 'api_transport_io.dart'
    if (dart.library.html) 'api_transport_web.dart';

class ApiResponse {
  const ApiResponse({required this.statusCode, required this.body});

  final int statusCode;
  final Map<String, dynamic> body;
}

abstract interface class ApiTransport {
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  });
}

ApiTransport createApiTransport() => createPlatformTransport();
