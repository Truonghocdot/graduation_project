import 'dart:convert';

import 'package:driver/api/goong_navigation_api.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('directions sends driver coordinates and parses route', () async {
    final api = GoongNavigationApi(
      apiKey: 'driver-key',
      client: MockClient((request) async {
        expect(request.url.path, '/direction');
        expect(request.url.queryParameters['origin'], '10.77,106.7');
        expect(request.url.queryParameters['destination'], '10.78,106.71');
        expect(request.url.queryParameters['vehicle'], 'bike');
        expect(request.url.queryParameters['api_key'], 'driver-key');
        return http.Response(
          jsonEncode({
            'routes': [
              {
                'legs': [
                  {
                    'distance': {'value': 2400},
                    'duration': {'value': 600},
                  },
                ],
                'overview_polyline': {'points': '_p~iF~ps|U_ulLnnqC_mqNvxq`@'},
              },
            ],
          }),
          200,
        );
      }),
    );

    final route = await api.directions(
      origin: const NavigationCoordinate(latitude: 10.77, longitude: 106.7),
      destination: const NavigationCoordinate(
        latitude: 10.78,
        longitude: 106.71,
      ),
      vehicle: 'bike',
    );

    expect(route.distanceMeters, 2400);
    expect(route.durationSeconds, 600);
    expect(route.geometry, hasLength(3));
  });

  test('missing key stops before requesting Directions', () async {
    final api = GoongNavigationApi(apiKey: '');

    await expectLater(
      api.directions(
        origin: const NavigationCoordinate(latitude: 10, longitude: 106),
        destination: const NavigationCoordinate(latitude: 11, longitude: 107),
        vehicle: 'car',
      ),
      throwsA(isA<GoongNavigationException>()),
    );
  });
}
