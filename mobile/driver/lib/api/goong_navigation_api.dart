import 'dart:convert';

import 'package:http/http.dart' as http;

class NavigationCoordinate {
  const NavigationCoordinate({required this.latitude, required this.longitude});

  final double latitude;
  final double longitude;
}

class NavigationRoute {
  const NavigationRoute({
    required this.distanceMeters,
    required this.durationSeconds,
    required this.geometry,
  });

  final double distanceMeters;
  final int durationSeconds;
  final List<NavigationCoordinate> geometry;
}

class GoongNavigationException implements Exception {
  const GoongNavigationException(this.message);

  final String message;

  @override
  String toString() => message;
}

class GoongNavigationApi {
  GoongNavigationApi({required this.apiKey, http.Client? client})
    : _client = client ?? http.Client();

  final String apiKey;
  final http.Client _client;

  bool get configured => apiKey.trim().isNotEmpty;

  Future<NavigationRoute> directions({
    required NavigationCoordinate origin,
    required NavigationCoordinate destination,
    required String vehicle,
  }) async {
    if (!configured) {
      throw const GoongNavigationException(
        'Chưa cấu hình GOONG_API_KEY cho ứng dụng tài xế.',
      );
    }
    final uri = Uri.https('rsapi.goong.io', '/direction', {
      'origin': '${origin.latitude},${origin.longitude}',
      'destination': '${destination.latitude},${destination.longitude}',
      'vehicle': vehicle,
      'api_key': apiKey,
    });
    final response = await _client
        .get(uri)
        .timeout(const Duration(seconds: 12));
    final decoded = response.body.isEmpty
        ? const <String, dynamic>{}
        : jsonDecode(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw GoongNavigationException(
        'Goong trả về lỗi ${response.statusCode}.',
      );
    }
    if (decoded is! Map<String, dynamic>) {
      throw const GoongNavigationException('Phản hồi Goong không hợp lệ.');
    }
    final routes = decoded['routes'];
    final route = routes is List && routes.isNotEmpty ? routes.first : null;
    if (route is! Map<String, dynamic>) {
      throw const GoongNavigationException(
        'Không tìm thấy tuyến đường phù hợp.',
      );
    }

    final legs = route['legs'];
    final firstLeg = legs is List && legs.isNotEmpty ? legs.first : null;
    final leg = firstLeg is Map<String, dynamic> ? firstLeg : null;
    final distanceData = leg == null ? null : leg['distance'];
    final durationData = leg == null ? null : leg['duration'];
    final distance = distanceData is Map<String, dynamic>
        ? distanceData['value']
        : null;
    final duration = durationData is Map<String, dynamic>
        ? durationData['value']
        : null;
    final overview = route['overview_polyline'];
    final encoded = overview is Map<String, dynamic>
        ? overview['points']?.toString()
        : null;

    return NavigationRoute(
      distanceMeters: (distance as num?)?.toDouble() ?? 0,
      durationSeconds: (duration as num?)?.toInt() ?? 0,
      geometry: encoded == null || encoded.isEmpty
          ? [origin, destination]
          : decodeNavigationPolyline(encoded),
    );
  }

  void dispose() => _client.close();
}

List<NavigationCoordinate> decodeNavigationPolyline(String encoded) {
  var index = 0;
  var latitude = 0;
  var longitude = 0;
  final points = <NavigationCoordinate>[];

  while (index < encoded.length) {
    final latitudeResult = _decodeValue(encoded, index);
    index = latitudeResult.$2;
    latitude += latitudeResult.$1;
    final longitudeResult = _decodeValue(encoded, index);
    index = longitudeResult.$2;
    longitude += longitudeResult.$1;
    points.add(
      NavigationCoordinate(
        latitude: latitude / 100000,
        longitude: longitude / 100000,
      ),
    );
  }
  return points;
}

(int, int) _decodeValue(String value, int start) {
  var result = 0;
  var shift = 0;
  var index = start;
  int byte;
  do {
    byte = value.codeUnitAt(index++) - 63;
    result |= (byte & 0x1f) << shift;
    shift += 5;
  } while (byte >= 0x20 && index < value.length);
  return ((result & 1) == 1 ? ~(result >> 1) : result >> 1, index);
}
