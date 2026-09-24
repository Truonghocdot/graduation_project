import 'package:flutter/material.dart';

abstract final class ClientTheme {
  static const primary = Color(0xFF146B52);
  static const accent = Color(0xFFB35C21);

  static ThemeData light() => _theme(
    ColorScheme.fromSeed(
      seedColor: primary,
      brightness: Brightness.light,
    ).copyWith(
      primary: primary,
      onPrimary: Colors.white,
      secondary: accent,
      onSecondary: Colors.white,
      surface: const Color(0xFFF7F8F5),
      onSurface: const Color(0xFF1B2420),
      error: const Color(0xFFB42318),
      onError: Colors.white,
    ),
  );

  static ThemeData dark() => _theme(
    ColorScheme.fromSeed(seedColor: primary, brightness: Brightness.dark)
        .copyWith(
          primary: const Color(0xFF8CDAB9),
          onPrimary: const Color(0xFF00382A),
          secondary: const Color(0xFFFFB77C),
          onSecondary: const Color(0xFF4A2000),
          surface: const Color(0xFF101B17),
          onSurface: const Color(0xFFE0E9E3),
          error: const Color(0xFFFFB4AB),
          onError: const Color(0xFF690005),
        ),
  );

  static ThemeData _theme(ColorScheme scheme) => ThemeData(
    colorScheme: scheme,
    scaffoldBackgroundColor: scheme.surface,
    useMaterial3: true,
    textTheme: ThemeData(brightness: scheme.brightness).textTheme.copyWith(
      bodyLarge: const TextStyle(fontSize: 16),
      bodyMedium: const TextStyle(fontSize: 16),
      bodySmall: const TextStyle(fontSize: 14),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      margin: EdgeInsets.zero,
      color: scheme.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: const BorderRadius.all(Radius.circular(8)),
        side: BorderSide(color: scheme.outlineVariant),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: scheme.surfaceContainerLowest,
      border: const OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(6)),
      ),
    ),
    navigationBarTheme: const NavigationBarThemeData(
      height: 68,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
    ),
    filledButtonTheme: const FilledButtonThemeData(
      style: ButtonStyle(
        minimumSize: WidgetStatePropertyAll(Size.fromHeight(48)),
      ),
    ),
    outlinedButtonTheme: const OutlinedButtonThemeData(
      style: ButtonStyle(
        minimumSize: WidgetStatePropertyAll(Size.fromHeight(48)),
      ),
    ),
  );
}
