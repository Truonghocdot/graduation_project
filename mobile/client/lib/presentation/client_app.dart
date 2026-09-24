import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import '../api/booking_api.dart';
import '../api/booking_realtime.dart';
import '../api/client_location.dart';
import '../api/goong_location_api.dart';
import '../api/session_store.dart';
import 'client_app_controller.dart';
import 'pages/auth/login_page.dart';
import 'pages/main_navigation_page.dart';
import 'theme/app_theme.dart';

class BookingApp extends StatefulWidget {
  const BookingApp({
    super.key,
    required this.gateway,
    required this.initialSession,
    this.locationSource,
    this.goong,
    this.sessionStore,
    this.realtime,
  });

  final BookingGateway gateway;
  final BookingSession initialSession;
  final ClientLocationSource? locationSource;
  final GoongLocationApi? goong;
  final BookingSessionStore? sessionStore;
  final BookingRealtime? realtime;

  @override
  State<BookingApp> createState() => _BookingAppState();
}

class _BookingAppState extends State<BookingApp> {
  late final ClientAppController controller = ClientAppController(
    gateway: widget.gateway,
    initialSession: widget.initialSession,
    locationSource: widget.locationSource,
    goong: widget.goong,
    sessionStore: widget.sessionStore,
    realtime: widget.realtime,
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
      title: 'Giao hàng & Đặt xe',
      locale: const Locale('vi', 'VN'),
      supportedLocales: const [Locale('vi', 'VN')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: ClientTheme.light(),
      darkTheme: ClientTheme.dark(),
      themeMode: ThemeMode.system,
      home: AnimatedBuilder(
        animation: controller,
        builder: (context, _) {
          if (controller.initializing) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          return controller.authenticated
              ? MainNavigationPage(controller: controller)
              : LoginPage(controller: controller);
        },
      ),
    );
  }
}
