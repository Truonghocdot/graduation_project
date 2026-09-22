import 'dart:convert';

import 'package:client/api/goong_location_api.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('autocomplete and place detail preserve Goong contracts', () async {
    final requestedPaths = <String>[];
    final api = GoongLocationApi(
      apiKey: 'test-key',
      client: MockClient((request) async {
        requestedPaths.add(request.url.path);
        expect(request.url.queryParameters['api_key'], 'test-key');
        if (request.url.path == '/place/autocomplete') {
          expect(request.url.queryParameters['input'], 'Nguyen Hue');
          return http.Response(
            jsonEncode({
              'predictions': [
                {
                  'place_id': 'place-1',
                  'description': 'Nguyen Hue, Quan 1',
                  'structured_formatting': {
                    'main_text': 'Nguyen Hue',
                    'secondary_text': 'Quan 1',
                  },
                },
              ],
            }),
            200,
          );
        }
        return http.Response(
          jsonEncode({
            'result': {
              'place_id': 'place-1',
              'formatted_address': 'Nguyen Hue, Quan 1',
              'location': {'lat': 10.773, 'lng': 106.704},
            },
          }),
          200,
        );
      }),
    );

    final suggestions = await api.autocomplete('Nguyen Hue');
    final place = await api.placeDetail(suggestions.single.placeId);

    expect(suggestions.single.mainText, 'Nguyen Hue');
    expect(place.address, 'Nguyen Hue, Quan 1');
    expect(place.coordinate.latitude, 10.773);
    expect(requestedPaths, ['/place/autocomplete', '/v2/place/detail']);
  });

  test('directions parses distance, duration and encoded route', () async {
    final api = GoongLocationApi(
      apiKey: 'test-key',
      client: MockClient((request) async {
        expect(request.url.path, '/direction');
        expect(request.url.queryParameters['vehicle'], 'bike');
        return http.Response(
          jsonEncode({
            'routes': [
              {
                'legs': [
                  {
                    'distance': {'value': 1275},
                    'duration': {'value': 420},
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
      origin: const GoongCoordinate(latitude: 10.7, longitude: 106.7),
      destination: const GoongCoordinate(latitude: 10.8, longitude: 106.8),
    );

    expect(route.distanceMeters, 1275);
    expect(route.durationSeconds, 420);
    expect(route.geometry, hasLength(3));
    expect(route.geometry.first.latitude, closeTo(38.5, 0.001));
  });

  test('missing API key fails before making a request', () async {
    final api = GoongLocationApi(apiKey: '');

    await expectLater(
      api.autocomplete('address'),
      throwsA(isA<GoongApiException>()),
    );
  });
}
