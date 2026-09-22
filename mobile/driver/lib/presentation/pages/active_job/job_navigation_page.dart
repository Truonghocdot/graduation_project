import 'package:flutter/material.dart';

import '../../../api/goong_navigation_api.dart';
import '../../../api/driver_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';
import '../../widgets/driver_goong_map.dart';
import 'chat_with_customer_page.dart';
import 'update_status_page.dart';

class JobNavigationPage extends StatefulWidget {
  const JobNavigationPage({super.key, required this.controller});

  final DriverAppController controller;

  @override
  State<JobNavigationPage> createState() => _JobNavigationPageState();
}

class _JobNavigationPageState extends State<JobNavigationPage> {
  DriverAppController get controller => widget.controller;

  NavigationRoute? route;
  String? routeError;
  String? loadedRouteKey;
  bool routeLoading = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshNavigation());
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) {
        final offer = controller.activeOffer;
        if (offer == null) {
          return const Scaffold(
            body: Center(child: Text('Không có chuyến đang chạy.')),
          );
        }
        final routeKey = '${offer.id}:${offer.serviceStatus}';
        if (routeKey != loadedRouteKey && !routeLoading) {
          WidgetsBinding.instance.addPostFrameCallback(
            (_) => _refreshNavigation(),
          );
        }
        final action = _nextAction(offer.serviceStatus);
        return Scaffold(
          appBar: AppBar(
            title: const Text('Điều hướng chuyến'),
            actions: [
              IconButton(
                tooltip: 'Làm mới tuyến đường',
                onPressed: routeLoading
                    ? null
                    : () => _refreshNavigation(force: true),
                icon: const Icon(Icons.refresh),
              ),
              IconButton(
                tooltip: 'Chat với khách',
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => ChatWithCustomerPage(
                      controller: controller,
                      offer: offer,
                    ),
                  ),
                ),
                icon: const Icon(Icons.chat_bubble_outline),
              ),
            ],
          ),
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DriverGoongMap(
                mapKey: const String.fromEnvironment('GOONG_MAP_KEY'),
                current: _currentCoordinate,
                pickup: NavigationCoordinate(
                  latitude: offer.pickupLatitude,
                  longitude: offer.pickupLongitude,
                ),
                dropoff: NavigationCoordinate(
                  latitude: offer.dropoffLatitude,
                  longitude: offer.dropoffLongitude,
                ),
                route: route?.geometry,
              ),
              if (routeLoading) const LinearProgressIndicator(minHeight: 2),
              if (route != null) ...[
                const SizedBox(height: 10),
                _NavigationSummary(
                  route: route!,
                  destinationLabel: _destinationLabel(offer.serviceStatus),
                ),
              ],
              if (routeError != null) ...[
                const SizedBox(height: 10),
                DriverErrorBanner(message: routeError!),
              ],
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              offer.serviceType,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          DriverStatusBadge(offer.serviceStatus),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Thanh toán ${offer.paymentMethod} · '
                        '${offer.customerPayable.toStringAsFixed(0)} VND',
                      ),
                      Text(
                        'Thu nhập dự kiến ${offer.estimatedEarning.toStringAsFixed(0)} VND',
                      ),
                    ],
                  ),
                ),
              ),
              if (controller.error case final error?) ...[
                const SizedBox(height: 10),
                DriverErrorBanner(message: error),
              ],
              const SizedBox(height: 14),
              if (action != null)
                SizedBox(
                  height: 48,
                  child: FilledButton.icon(
                    onPressed: controller.busy
                        ? null
                        : () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => UpdateStatusPage(
                                controller: controller,
                                offer: offer,
                                action: action,
                              ),
                            ),
                          ),
                    icon: Icon(action.icon),
                    label: Text(action.label),
                  ),
                ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: () => _sos(context, offer),
                icon: const Icon(Icons.sos_outlined),
                label: const Text('Báo SOS'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => _support(context, offer),
                icon: const Icon(Icons.support_agent_outlined),
                label: const Text('Yêu cầu hỗ trợ'),
              ),
            ],
          ),
        );
      },
    );
  }

  NavigationCoordinate? get _currentCoordinate {
    final position = controller.currentPosition;
    if (position == null) return null;
    return NavigationCoordinate(
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }

  Future<void> _refreshNavigation({bool force = false}) async {
    if (!mounted) return;
    final offer = controller.activeOffer;
    if (offer == null || routeLoading) return;
    final routeKey = '${offer.id}:${offer.serviceStatus}';
    if (!force && loadedRouteKey == routeKey) return;
    loadedRouteKey = routeKey;
    setState(() {
      routeLoading = true;
      routeError = null;
    });
    try {
      final position = await controller.refreshPosition();
      if (position == null) {
        throw StateError('Không lấy được vị trí hiện tại của tài xế.');
      }
      final api = controller.goong;
      if (api?.configured != true) {
        throw const GoongNavigationException(
          'Chưa cấu hình GOONG_API_KEY cho ứng dụng tài xế.',
        );
      }
      final target = _usesDropoff(offer.serviceStatus)
          ? NavigationCoordinate(
              latitude: offer.dropoffLatitude,
              longitude: offer.dropoffLongitude,
            )
          : NavigationCoordinate(
              latitude: offer.pickupLatitude,
              longitude: offer.pickupLongitude,
            );
      final nextRoute = await api!.directions(
        origin: NavigationCoordinate(
          latitude: position.latitude,
          longitude: position.longitude,
        ),
        destination: target,
        vehicle: offer.serviceType == 'DELIVERY' ? 'bike' : 'car',
      );
      if (mounted &&
          '${controller.activeOffer?.id}:${controller.activeOffer?.serviceStatus}' ==
              routeKey) {
        setState(() => route = nextRoute);
      }
    } catch (exception) {
      if (mounted) {
        setState(() {
          route = null;
          routeError = exception.toString();
        });
      }
    } finally {
      if (mounted) setState(() => routeLoading = false);
    }
  }

  bool _usesDropoff(String status) =>
      const {'PICKED_UP', 'IN_DELIVERY', 'IN_TRIP'}.contains(status);

  String _destinationLabel(String status) =>
      _usesDropoff(status) ? 'điểm đến' : 'điểm đón';

  JobAction? _nextAction(String status) => switch (status) {
    'DRIVER_ARRIVING_PICKUP' => const JobAction(
      value: 'arrive_pickup',
      label: 'Đã đến điểm lấy',
      icon: Icons.location_on_outlined,
    ),
    'AT_PICKUP' => const JobAction(
      value: 'pickup',
      label: 'Đã nhận hàng',
      icon: Icons.inventory_2_outlined,
      evidenceType: 'PICKUP',
    ),
    'PICKED_UP' => const JobAction(
      value: 'start_delivery',
      label: 'Bắt đầu giao',
      icon: Icons.local_shipping_outlined,
    ),
    'IN_DELIVERY' => const JobAction(
      value: 'deliver',
      label: 'Hoàn tất giao hàng',
      icon: Icons.check_circle_outline,
      evidenceType: 'DELIVERY',
      terminal: true,
    ),
    'DRIVER_ARRIVING' => const JobAction(
      value: 'arrive',
      label: 'Đã đến điểm đón',
      icon: Icons.location_on_outlined,
    ),
    'DRIVER_ARRIVED' => const JobAction(
      value: 'start',
      label: 'Bắt đầu chuyến',
      icon: Icons.play_arrow,
    ),
    'IN_TRIP' => const JobAction(
      value: 'complete',
      label: 'Kết thúc chuyến',
      icon: Icons.flag_outlined,
      terminal: true,
    ),
    _ => null,
  };

  Future<void> _sos(BuildContext context, DriverOfferSummary offer) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Báo động SOS?'),
        content: const Text(
          'Nếu nguy hiểm tức thời, hãy liên hệ dịch vụ khẩn cấp địa phương.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(context, true),
            icon: const Icon(Icons.sos_outlined),
            label: const Text('Báo ngay'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await controller.support?.reportIncident(
        session: controller.session,
        serviceRequestId: offer.serviceRequestId,
        incidentType: 'SOS',
      );
    }
  }

  Future<void> _support(BuildContext context, DriverOfferSummary offer) async {
    final subject = TextEditingController();
    final body = TextEditingController();
    final send = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tạo yêu cầu hỗ trợ'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: subject,
              decoration: const InputDecoration(labelText: 'Tiêu đề'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: body,
              maxLines: 3,
              decoration: const InputDecoration(labelText: 'Mô tả'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Gửi'),
          ),
        ],
      ),
    );
    if (send == true &&
        subject.text.trim().isNotEmpty &&
        body.text.trim().isNotEmpty) {
      await controller.support?.createSupportTicket(
        session: controller.session,
        serviceRequestId: offer.serviceRequestId,
        subject: subject.text.trim(),
        description: body.text.trim(),
      );
    }
    subject.dispose();
    body.dispose();
  }
}

class _NavigationSummary extends StatelessWidget {
  const _NavigationSummary({
    required this.route,
    required this.destinationLabel,
  });

  final NavigationRoute route;
  final String destinationLabel;

  @override
  Widget build(BuildContext context) {
    final kilometers = route.distanceMeters / 1000;
    final minutes = (route.durationSeconds / 60).ceil();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFE7EEF5),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        children: [
          const Icon(Icons.navigation_outlined, color: Color(0xFF215F9A)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              '${kilometers.toStringAsFixed(1)} km đến $destinationLabel',
            ),
          ),
          Text('$minutes phút'),
        ],
      ),
    );
  }
}
