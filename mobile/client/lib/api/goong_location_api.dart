import 'dart:convert';

import 'package:http/http.dart' as http;

class GoongCoordinate {
  const GoongCoordinate({required this.latitude, required this.longitude});

  final double latitude;
  final double longitude;
}

class GoongPlaceSuggestion {
  const GoongPlaceSuggestion({
    required this.placeId,
    required this.description,
    this.mainText,
    this.secondaryText,
  });

  final String placeId;
  final String description;
  final String? mainText;
  final String? secondaryText;
}

class GoongPlace {
  const GoongPlace({
    required this.placeId,
    required this.address,
    required this.coordinate,
  });

  final String? placeId;
  final String address;
  final GoongCoordinate coordinate;
}

class GoongRoute {
  const GoongRoute({
    required this.distanceMeters,
    required this.durationSeconds,
    required this.geometry,
  });

  final double distanceMeters;
  final int durationSeconds;
  final List<GoongCoordinate> geometry;
}

class GoongApiException implements Exception {
  const GoongApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class GoongLocationApi {
  GoongLocationApi({required this.apiKey, http.Client? client})
    : _client = client ?? http.Client();

  final String apiKey;
  final http.Client _client;

  bool get configured => apiKey.trim().isNotEmpty;

  Future<List<GoongPlaceSuggestion>> autocomplete(
    String input, {
    GoongCoordinate? location,
  }) async {
    _assertConfigured();
    final query = <String, String>{
      'input': input.trim(),
      'limit': '6',
      'api_key': apiKey,
      if (location != null)
        'location': '${location.latitude},${location.longitude}',
    };
    final data = await _get('/place/autocomplete', query);
    final predictions = data['predictions'];
    if (predictions is! List) return const [];
    return predictions
        .whereType<Map<String, dynamic>>()
        .map((item) {
          final formatting = item['structured_formatting'];
          final structured = formatting is Map<String, dynamic>
              ? formatting
              : const <String, dynamic>{};
          return GoongPlaceSuggestion(
            placeId: item['place_id']?.toString() ?? '',
            description: item['description']?.toString() ?? '',
            mainText: structured['main_text']?.toString(),
            secondaryText: structured['secondary_text']?.toString(),
          );
        })
        .where((item) => item.placeId.isNotEmpty && item.description.isNotEmpty)
        .toList(growable: false);
  }

  Future<GoongPlace> placeDetail(String placeId) async {
    _assertConfigured();
    final data = await _get('/v2/place/detail', {
      'place_id': placeId,
      'api_key': apiKey,
    });
    final result = data['result'];
    if (result is! Map<String, dynamic>) {
      throw const GoongApiException('Goong không trả về chi tiết địa điểm.');
    }
    final coordinate = _coordinateFromResult(result);
    if (coordinate == null) {
      throw const GoongApiException('Địa điểm chưa có tọa độ hợp lệ.');
    }
    return GoongPlace(
      placeId: result['place_id']?.toString() ?? placeId,
      address: result['formatted_address']?.toString() ?? '',
      coordinate: coordinate,
    );
  }

  Future<GoongPlace> geocode(String address) async {
    _assertConfigured();
    final data = await _get('/geocode', {
      'address': address.trim(),
      'api_key': apiKey,
    });
    final results = data['results'];
    final result = results is List && results.isNotEmpty ? results.first : null;
    if (result is! Map<String, dynamic>) {
      throw const GoongApiException('Không tìm thấy tọa độ cho địa chỉ này.');
    }
    final coordinate = _coordinateFromGeometry(result['geometry']);
    if (coordinate == null) {
      throw const GoongApiException('Địa chỉ chưa có tọa độ hợp lệ.');
    }
    return GoongPlace(
      placeId: result['place_id']?.toString(),
      address: result['formatted_address']?.toString() ?? address.trim(),
      coordinate: coordinate,
    );
  }

  Future<GoongRoute> directions({
    required GoongCoordinate origin,
    required GoongCoordinate destination,
    String vehicle = 'bike',
  }) async {
    _assertConfigured();
    final data = await _get('/direction', {
      'origin': '${origin.latitude},${origin.longitude}',
      'destination': '${destination.latitude},${destination.longitude}',
      'vehicle': vehicle,
      'api_key': apiKey,
    });
    final routes = data['routes'];
    final route = routes is List && routes.isNotEmpty ? routes.first : null;
    if (route is! Map<String, dynamic>) {
      throw const GoongApiException('Không tìm thấy tuyến đường phù hợp.');
    }
    final legs = route['legs'];
    final firstLeg = legs is List && legs.isNotEmpty ? legs.first : null;
    final firstLegMap = firstLeg is Map<String, dynamic> ? firstLeg : null;
    final distanceMap = firstLegMap == null ? null : firstLegMap['distance'];
    final durationMap = firstLegMap == null ? null : firstLegMap['duration'];
    dynamic distance;
    dynamic duration;
    if (distanceMap is Map<String, dynamic>) {
      distance = distanceMap['value'];
    }
    if (durationMap is Map<String, dynamic>) {
      duration = durationMap['value'];
    }
    final overview = route['overview_polyline'];
    final points = overview is Map<String, dynamic>
        ? overview['points']?.toString()
        : null;
    return GoongRoute(
      distanceMeters: (distance as num?)?.toDouble() ?? 0,
      durationSeconds: (duration as num?)?.toInt() ?? 0,
      geometry: points == null || points.isEmpty
          ? [origin, destination]
          : decodePolyline(points),
    );
  }

  void dispose() => _client.close();

  Future<Map<String, dynamic>> _get(
    String path,
    Map<String, String> query,
  ) async {
    final uri = Uri.https('rsapi.goong.io', path, query);
    final response = await _client
        .get(uri)
        .timeout(const Duration(seconds: 12));
    final decoded = response.body.isEmpty
        ? const <String, dynamic>{}
        : jsonDecode(response.body);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw GoongApiException('Goong trả về lỗi ${response.statusCode}.');
    }
    if (decoded is! Map<String, dynamic>) {
      throw const GoongApiException('Phản hồi Goong không hợp lệ.');
    }
    return decoded;
  }

  void _assertConfigured() {
    if (!configured) {
      throw const GoongApiException(
        'Chưa cấu hình GOONG_API_KEY. Hãy chạy app với --dart-define.',
      );
    }
  }
}

List<GoongCoordinate> decodePolyline(String encoded) {
  var index = 0;
  var latitude = 0;
  var longitude = 0;
  final points = <GoongCoordinate>[];

  while (index < encoded.length) {
    final latitudeResult = _decodePolylineValue(encoded, index);
    index = latitudeResult.$2;
    latitude += latitudeResult.$1;
    final longitudeResult = _decodePolylineValue(encoded, index);
    index = longitudeResult.$2;
    longitude += longitudeResult.$1;
    points.add(
      GoongCoordinate(
        latitude: latitude / 100000,
        longitude: longitude / 100000,
      ),
    );
  }
  return points;
}

(int, int) _decodePolylineValue(String value, int start) {
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

GoongCoordinate? _coordinateFromGeometry(dynamic geometry) {
  if (geometry is! Map<String, dynamic>) return null;
  final location = geometry['location'];
  if (location is! Map<String, dynamic>) return null;
  final latitude = (location['lat'] as num?)?.toDouble();
  final longitude = (location['lng'] as num?)?.toDouble();
  if (latitude == null || longitude == null) return null;
  return GoongCoordinate(latitude: latitude, longitude: longitude);
}

GoongCoordinate? _coordinateFromResult(Map<String, dynamic> result) {
  final directLocation = result['location'];
  if (directLocation is Map<String, dynamic>) {
    final latitude = (directLocation['lat'] as num?)?.toDouble();
    final longitude = (directLocation['lng'] as num?)?.toDouble();
    if (latitude != null && longitude != null) {
      return GoongCoordinate(latitude: latitude, longitude: longitude);
    }
  }
  return _coordinateFromGeometry(result['geometry']);
}
