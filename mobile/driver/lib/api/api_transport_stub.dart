import 'api_transport.dart';

ApiTransport createPlatformTransport() => const UnsupportedTransport();

class UnsupportedTransport implements ApiTransport {
  const UnsupportedTransport();

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) {
    throw UnsupportedError('API transport unavailable.');
  }
}
