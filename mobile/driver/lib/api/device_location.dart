import 'package:geolocator/geolocator.dart';

class DriverPosition {
  const DriverPosition(this.latitude, this.longitude, this.accuracy);
  final double latitude;
  final double longitude;
  final double accuracy;
}

abstract interface class DriverLocationSource {
  Future<DriverPosition> current();
}

class DeviceLocationSource implements DriverLocationSource {
  @override
  Future<DriverPosition> current() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw StateError('Hãy bật dịch vụ định vị trên thiết bị.');
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw StateError('Cần cấp quyền vị trí để nhận và thực hiện chuyến.');
    }
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 12),
      ),
    );
    return DriverPosition(
      position.latitude,
      position.longitude,
      position.accuracy,
    );
  }
}
