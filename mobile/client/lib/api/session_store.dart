import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'request_id.dart';

abstract interface class BookingSessionStore {
  Future<String?> readToken();
  Future<void> writeToken(String token);
  Future<String?> readLastRequestId();
  Future<void> writeLastRequestId(String id);
  Future<void> clear();
}

class SecureBookingSessionStore implements BookingSessionStore {
  const SecureBookingSessionStore();

  static const _storage = FlutterSecureStorage();

  Future<String> installationId() async {
    final saved = await _storage.read(key: 'customer_device_id');
    if (saved != null) return saved;
    final created = newRequestId();
    await _storage.write(key: 'customer_device_id', value: created);
    return created;
  }

  @override
  Future<String?> readToken() => _storage.read(key: 'customer_token');

  @override
  Future<void> writeToken(String token) =>
      _storage.write(key: 'customer_token', value: token);

  @override
  Future<String?> readLastRequestId() =>
      _storage.read(key: 'customer_last_request');

  @override
  Future<void> writeLastRequestId(String id) =>
      _storage.write(key: 'customer_last_request', value: id);

  @override
  Future<void> clear() async {
    await _storage.delete(key: 'customer_token');
    await _storage.delete(key: 'customer_last_request');
  }
}
