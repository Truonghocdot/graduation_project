import 'package:flutter/material.dart';
import 'package:maplibre_gl/maplibre_gl.dart';

import '../../api/goong_navigation_api.dart';

class DriverGoongMap extends StatefulWidget {
  const DriverGoongMap({
    super.key,
    required this.mapKey,
    this.current,
    this.pickup,
    this.dropoff,
    this.route,
    this.height = 260,
  });

  final String mapKey;
  final NavigationCoordinate? current;
  final NavigationCoordinate? pickup;
  final NavigationCoordinate? dropoff;
  final List<NavigationCoordinate>? route;
  final double height;

  @override
  State<DriverGoongMap> createState() => _DriverGoongMapState();
}

class _DriverGoongMapState extends State<DriverGoongMap> {
  MapLibreMapController? _controller;
  bool _styleReady = false;

  @override
  void didUpdateWidget(covariant DriverGoongMap oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_styleReady &&
        (oldWidget.current != widget.current ||
            oldWidget.pickup != widget.pickup ||
            oldWidget.dropoff != widget.dropoff ||
            oldWidget.route != widget.route)) {
      _drawMap();
    }
  }

  @override
  Widget build(BuildContext context) {
    final points = _visiblePoints;
    if (widget.mapKey.trim().isEmpty) {
      return _MapMessage(
        height: widget.height,
        message: 'Thêm GOONG_MAP_KEY để hiển thị bản đồ tài xế.',
      );
    }
    if (points.isEmpty) {
      return _MapMessage(
        height: widget.height,
        message: 'Đang chờ vị trí GPS của tài xế.',
      );
    }
    final center = NavigationCoordinate(
      latitude:
          points.fold<double>(0, (sum, point) => sum + point.latitude) /
          points.length,
      longitude:
          points.fold<double>(0, (sum, point) => sum + point.longitude) /
          points.length,
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: SizedBox(
        height: widget.height,
        child: MapLibreMap(
          styleString:
              'https://tiles.goong.io/assets/goong_map_highlight.json?api_key=${Uri.encodeComponent(widget.mapKey)}',
          initialCameraPosition: CameraPosition(
            target: LatLng(center.latitude, center.longitude),
            zoom: points.length == 1 ? 15 : 13,
          ),
          compassEnabled: false,
          logoEnabled: false,
          onMapCreated: (controller) => _controller = controller,
          onStyleLoadedCallback: () {
            _styleReady = true;
            _drawMap();
          },
        ),
      ),
    );
  }

  List<NavigationCoordinate> get _visiblePoints => [
    if (widget.current != null) widget.current!,
    if (widget.pickup != null) widget.pickup!,
    if (widget.dropoff != null) widget.dropoff!,
  ];

  Future<void> _drawMap() async {
    final controller = _controller;
    if (controller == null || !_styleReady || !mounted) return;
    await controller.clearLines();
    await controller.clearSymbols();
    final route = widget.route;
    if (route != null && route.length > 1) {
      await controller.addLine(
        LineOptions(
          geometry: route
              .map((point) => LatLng(point.latitude, point.longitude))
              .toList(growable: false),
          lineColor: '#215F9A',
          lineWidth: 6,
          lineOpacity: 0.9,
        ),
      );
    }
    await _addMarker(controller, widget.current, 'Bạn', '#215F9A');
    await _addMarker(controller, widget.pickup, 'Đón', '#146B52');
    await _addMarker(controller, widget.dropoff, 'Đến', '#B35C21');
    await _fitCamera(controller);
  }

  Future<void> _addMarker(
    MapLibreMapController controller,
    NavigationCoordinate? coordinate,
    String label,
    String color,
  ) async {
    if (coordinate == null) return;
    await controller.addSymbol(
      SymbolOptions(
        geometry: LatLng(coordinate.latitude, coordinate.longitude),
        textField: label,
        textColor: color,
        textHaloColor: '#FFFFFF',
        textHaloWidth: 2,
        textSize: 14,
      ),
    );
  }

  Future<void> _fitCamera(MapLibreMapController controller) async {
    final route = widget.route;
    final points = route != null && route.isNotEmpty
        ? [route.first, route.last]
        : _visiblePoints;
    if (points.isEmpty) return;
    if (points.length == 1) {
      await controller.animateCamera(
        CameraUpdate.newLatLngZoom(
          LatLng(points.first.latitude, points.first.longitude),
          15,
        ),
      );
      return;
    }
    final minLatitude = points
        .map((point) => point.latitude)
        .reduce((a, b) => a < b ? a : b);
    final maxLatitude = points
        .map((point) => point.latitude)
        .reduce((a, b) => a > b ? a : b);
    final minLongitude = points
        .map((point) => point.longitude)
        .reduce((a, b) => a < b ? a : b);
    final maxLongitude = points
        .map((point) => point.longitude)
        .reduce((a, b) => a > b ? a : b);
    if ((maxLatitude - minLatitude).abs() < 0.00001 &&
        (maxLongitude - minLongitude).abs() < 0.00001) {
      await controller.animateCamera(
        CameraUpdate.newLatLngZoom(
          LatLng(points.first.latitude, points.first.longitude),
          15,
        ),
      );
      return;
    }
    await controller.animateCamera(
      CameraUpdate.newLatLngBounds(
        LatLngBounds(
          southwest: LatLng(minLatitude, minLongitude),
          northeast: LatLng(maxLatitude, maxLongitude),
        ),
        left: 42,
        top: 42,
        right: 42,
        bottom: 42,
      ),
    );
  }
}

class _MapMessage extends StatelessWidget {
  const _MapMessage({required this.height, required this.message});

  final double height;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFE7EEF5),
        border: Border.all(color: const Color(0xFFC9D6E2)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          const Icon(Icons.map_outlined, color: Color(0xFF215F9A), size: 32),
          const SizedBox(width: 12),
          Expanded(child: Text(message)),
        ],
      ),
    );
  }
}
