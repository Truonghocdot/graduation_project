import 'api_transport.dart';

ApiTransport createPlatformTransport() => const UnsupportedApiTransport();

class UnsupportedApiTransport implements ApiTransport {
  const UnsupportedApiTransport();

  @override
  Future<ApiResponse> send({
    required String method,
    required Uri uri,
    required String token,
    Map<String, dynamic>? body,
    Map<String, String> headers = const {},
  }) {
    throw UnsupportedError('Không hỗ trợ kết nối API trên nền tảng này.');
  }
}
