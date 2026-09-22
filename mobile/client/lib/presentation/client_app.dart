import 'package:flutter/material.dart';

import '../api/booking_api.dart';
import '../api/booking_realtime.dart';
import '../api/client_location.dart';
import '../api/goong_location_api.dart';
import '../api/session_store.dart';
import 'client_app_controller.dart';
import 'pages/auth/login_page.dart';
import 'pages/main_navigation_page.dart';

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
    const colors = ColorScheme.light(
      primary: Color(0xFF146B52),
      onPrimary: Colors.white,
      secondary: Color(0xFFB35C21),
      onSecondary: Colors.white,
      surface: Color(0xFFF7F8F5),
      onSurface: Color(0xFF1B2420),
      error: Color(0xFFB42318),
      onError: Colors.white,
    );

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Delivery & Drive',
      theme: ThemeData(
        colorScheme: colors,
        scaffoldBackgroundColor: colors.surface,
        useMaterial3: true,
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(8)),
            side: BorderSide(color: Color(0xFFD9DEDA)),
          ),
        ),
        inputDecorationTheme: const InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.all(Radius.circular(6)),
          ),
        ),
        navigationBarTheme: const NavigationBarThemeData(
          height: 68,
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        ),
      ),
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
