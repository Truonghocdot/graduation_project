import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'request_id.dart';

abstract interface class DriverSessionStore {
  Future<String?> readToken();
  Future<bool> readOnboarding();
  Future<void> save(String token, {required bool onboarding});
  Future<void> clear();
}

class SecureDriverSessionStore implements DriverSessionStore {
  const SecureDriverSessionStore();

  static const _storage = FlutterSecureStorage();

  Future<String> installationId() async {
    final saved = await _storage.read(key: 'driver_device_id');
    if (saved != null) return saved;
    final created = newRequestId();
    await _storage.write(key: 'driver_device_id', value: created);
    return created;
  }

  @override
  Future<String?> readToken() => _storage.read(key: 'driver_token');

  @override
  Future<bool> readOnboarding() async =>
      await _storage.read(key: 'driver_onboarding') == 'true';

  @override
  Future<void> save(String token, {required bool onboarding}) async {
    await _storage.write(key: 'driver_token', value: token);
    await _storage.write(key: 'driver_onboarding', value: '$onboarding');
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: 'driver_token');
    await _storage.delete(key: 'driver_onboarding');
  }
}
