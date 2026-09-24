import 'package:flutter/material.dart';

abstract final class DriverTheme {
  static const primary = Color(0xFF215F9A);
  static const accent = Color(0xFFB35C21);

  static ThemeData light() => _theme(
    ColorScheme.fromSeed(seedColor: primary, brightness: Brightness.light)
        .copyWith(
          primary: primary,
          onPrimary: Colors.white,
          secondary: accent,
          onSecondary: Colors.white,
          surface: const Color(0xFFF6F7F8),
          onSurface: const Color(0xFF202428),
          error: const Color(0xFFB42318),
        ),
  );

  static ThemeData dark() => _theme(
    ColorScheme.fromSeed(seedColor: primary, brightness: Brightness.dark)
        .copyWith(
          primary: const Color(0xFF9CCBFF),
          onPrimary: const Color(0xFF003258),
          secondary: const Color(0xFFFFB77C),
          onSecondary: const Color(0xFF4A2000),
          surface: const Color(0xFF101820),
          onSurface: const Color(0xFFE1EAF2),
          error: const Color(0xFFFFB4AB),
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
    navigationBarTheme: const NavigationBarThemeData(height: 68),
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
