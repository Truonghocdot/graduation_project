import 'package:geolocator/geolocator.dart';

class ClientPosition {
  const ClientPosition(this.latitude, this.longitude, this.accuracy);

  final double latitude;
  final double longitude;
  final double accuracy;
}

abstract interface class ClientLocationSource {
  Future<ClientPosition> current();
}

class DeviceClientLocationSource implements ClientLocationSource {
  Future<void> prepare() async {
    await _ensurePermission();
  }

  @override
  Future<ClientPosition> current() async {
    await _ensurePermission();
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 12),
      ),
    );
    return ClientPosition(
      position.latitude,
      position.longitude,
      position.accuracy,
    );
  }

  Future<void> _ensurePermission() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw StateError('Hãy bật dịch vụ định vị trên thiết bị.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw StateError(
        'Cần cấp quyền vị trí để chọn điểm đón và theo dõi chuyến.',
      );
    }
  }
}
