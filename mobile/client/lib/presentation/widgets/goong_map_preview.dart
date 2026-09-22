import 'package:flutter/material.dart';
import 'package:maplibre_gl/maplibre_gl.dart';

import '../../api/goong_location_api.dart';

class GoongMapPreview extends StatefulWidget {
  const GoongMapPreview({
    super.key,
    required this.pickup,
    required this.dropoff,
    required this.mapKey,
    this.route,
  });

  final GoongCoordinate pickup;
  final GoongCoordinate dropoff;
  final String mapKey;
  final List<GoongCoordinate>? route;

  @override
  State<GoongMapPreview> createState() => _GoongMapPreviewState();
}

class _GoongMapPreviewState extends State<GoongMapPreview> {
  MapLibreMapController? _map;
  bool _styleReady = false;

  @override
  void didUpdateWidget(covariant GoongMapPreview oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_styleReady &&
        (oldWidget.pickup != widget.pickup ||
            oldWidget.dropoff != widget.dropoff ||
            oldWidget.route != widget.route)) {
      _drawAnnotations();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.mapKey.trim().isEmpty) {
      return _MapUnavailableCard(
        message: 'Thêm GOONG_MAP_KEY để hiển thị bản đồ Goong.',
      );
    }
    final center = GoongCoordinate(
      latitude: (widget.pickup.latitude + widget.dropoff.latitude) / 2,
      longitude: (widget.pickup.longitude + widget.dropoff.longitude) / 2,
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: SizedBox(
        height: 220,
        child: MapLibreMap(
          styleString:
              'https://tiles.goong.io/assets/goong_map_highlight.json?api_key=${Uri.encodeComponent(widget.mapKey)}',
          initialCameraPosition: CameraPosition(
            target: LatLng(center.latitude, center.longitude),
            zoom: 13,
          ),
          compassEnabled: false,
          logoEnabled: false,
          onMapCreated: (controller) => _map = controller,
          onStyleLoadedCallback: () {
            _styleReady = true;
            _drawAnnotations();
          },
        ),
      ),
    );
  }

  Future<void> _drawAnnotations() async {
    final map = _map;
    if (map == null || !_styleReady || !mounted) return;
    await map.clearLines();
    await map.clearSymbols();
    final geometry = widget.route ?? [widget.pickup, widget.dropoff];
    if (geometry.length > 1) {
      await map.addLine(
        LineOptions(
          geometry: geometry
              .map((point) => LatLng(point.latitude, point.longitude))
              .toList(growable: false),
          lineColor: '#146B52',
          lineWidth: 5,
          lineOpacity: 0.9,
        ),
      );
    }
    await map.addSymbol(
      SymbolOptions(
        geometry: LatLng(widget.pickup.latitude, widget.pickup.longitude),
        textField: 'A',
        textColor: '#146B52',
        textHaloColor: '#FFFFFF',
        textHaloWidth: 2,
        textSize: 16,
      ),
    );
    await map.addSymbol(
      SymbolOptions(
        geometry: LatLng(widget.dropoff.latitude, widget.dropoff.longitude),
        textField: 'B',
        textColor: '#B35C21',
        textHaloColor: '#FFFFFF',
        textHaloWidth: 2,
        textSize: 16,
      ),
    );
  }
}

class _MapUnavailableCard extends StatelessWidget {
  const _MapUnavailableCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 116,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFE7EFEA),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFC8D7D0)),
      ),
      child: Row(
        children: [
          const Icon(Icons.map_outlined, color: Color(0xFF146B52), size: 30),
          const SizedBox(width: 12),
          Expanded(
            child: Text(message, style: Theme.of(context).textTheme.bodyMedium),
          ),
        ],
      ),
    );
  }
}
