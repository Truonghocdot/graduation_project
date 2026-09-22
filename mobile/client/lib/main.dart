import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'api/booking_api.dart';
import 'api/booking_realtime.dart';
import 'api/session_store.dart';
import 'presentation/client_app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  const sessionStore = SecureBookingSessionStore();
  final savedToken = await sessionStore.readToken();
  final deviceId = await sessionStore.installationId();
  const configuredBaseUrl = String.fromEnvironment('API_BASE_URL');
  final defaultBaseUrl = kIsWeb
      ? 'http://127.0.0.1:8000/api/v1'
      : defaultTargetPlatform == TargetPlatform.android
      ? 'http://10.0.2.2:8000/api/v1'
      : 'http://127.0.0.1:8000/api/v1';

  runApp(
    BookingApp(
      gateway: BookingApi(deviceId: deviceId),
      sessionStore: sessionStore,
      realtime: BookingRealtime(),
      initialSession: BookingSession(
        baseUrl: configuredBaseUrl.isEmpty ? defaultBaseUrl : configuredBaseUrl,
        token: savedToken ?? const String.fromEnvironment('API_TOKEN'),
        vehicleTypeId: const String.fromEnvironment('VEHICLE_TYPE_ID'),
      ),
    ),
  );
}
