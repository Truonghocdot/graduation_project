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
import 'theme/app_theme.dart';

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
      theme: DriverTheme.light(),
      darkTheme: DriverTheme.dark(),
      themeMode: ThemeMode.system,
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
