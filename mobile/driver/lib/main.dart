import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'api/driver_api.dart';
import 'api/driver_realtime.dart';
import 'api/session_store.dart';
import 'presentation/driver_app.dart';

export 'presentation/driver_app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  const sessionStore = SecureDriverSessionStore();
  final savedToken = await sessionStore.readToken();
  final onboarding = await sessionStore.readOnboarding();
  final deviceId = await sessionStore.installationId();
  const configured = String.fromEnvironment('API_BASE_URL');
  final defaultUrl = defaultTargetPlatform == TargetPlatform.android
      ? 'http://10.0.2.2:8000/api/v1'
      : 'http://127.0.0.1:8000/api/v1';

  runApp(
    DriverApp(
      gateway: DriverApi(deviceId: deviceId),
      sessionStore: sessionStore,
      realtime: DriverRealtime(),
      initialSession: DriverSession(
        baseUrl: configured.isEmpty ? defaultUrl : configured,
        token: savedToken ?? const String.fromEnvironment('API_TOKEN'),
        onboarding: onboarding,
      ),
    ),
  );
}
