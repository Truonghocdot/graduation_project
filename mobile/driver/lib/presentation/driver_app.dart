import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import '../api/device_location.dart';
import '../api/driver_api.dart';
import '../api/driver_realtime.dart';
import '../api/goong_navigation_api.dart';
import '../api/session_store.dart';
import 'driver_app_controller.dart';
import 'pages/auth/driver_kyc_page.dart';
import 'pages/auth/driver_login_page.dart';
import 'pages/main_driver_navigation_page.dart';

class DriverApp extends StatefulWidget {
  const DriverApp({
    super.key,
    required this.gateway,
    required this.initialSession,
    this.sessionStore,
    this.realtime,
    this.locationSource,
    this.goong,
  });

  final DriverGateway gateway;
  final DriverSession initialSession;
  final DriverSessionStore? sessionStore;
  final DriverRealtime? realtime;
  final DriverLocationSource? locationSource;
  final GoongNavigationApi? goong;

  @override
  State<DriverApp> createState() => _DriverAppState();
}

class _DriverAppState extends State<DriverApp> {
  late final DriverAppController controller = DriverAppController(
    gateway: widget.gateway,
    initialSession: widget.initialSession,
    sessionStore: widget.sessionStore,
    realtime: widget.realtime,
    locationSource: widget.locationSource ?? DeviceLocationSource(),
    goong: widget.goong,
  );

  @override
  void initState() {
    super.initState();
    controller.initialize();
    controller.prepareLocation();
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Ứng dụng tài xế',
      locale: const Locale('vi', 'VN'),
      supportedLocales: const [Locale('vi', 'VN')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: ThemeData(
        colorScheme: const ColorScheme.light(
          primary: Color(0xFF215F9A),
          onPrimary: Colors.white,
          secondary: Color(0xFFB35C21),
          surface: Color(0xFFF6F7F8),
          onSurface: Color(0xFF202428),
          error: Color(0xFFB42318),
        ),
        scaffoldBackgroundColor: const Color(0xFFF6F7F8),
        useMaterial3: true,
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(8)),
            side: BorderSide(color: Color(0xFFD8DDE2)),
          ),
        ),
        inputDecorationTheme: const InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(6)),
          ),
        ),
        navigationBarTheme: const NavigationBarThemeData(height: 68),
      ),
      home: AnimatedBuilder(
        animation: controller,
        builder: (context, _) {
          if (controller.initializing) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          if (!controller.authenticated) {
            return DriverLoginPage(controller: controller);
          }
          if (controller.onboarding) {
            return DriverKycPage(controller: controller);
          }
          return MainDriverNavigationPage(controller: controller);
        },
      ),
    );
  }
}
