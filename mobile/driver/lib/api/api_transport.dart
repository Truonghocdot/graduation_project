import 'api_transport_stub.dart' if (dart.library.io) 'api_transport_io.dart';

class ApiResponse {
  const ApiResponse(this.statusCode, this.body);

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
