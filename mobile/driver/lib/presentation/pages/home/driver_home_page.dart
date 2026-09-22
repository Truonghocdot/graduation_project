import 'package:flutter/material.dart';

import '../../../api/device_location.dart';
import '../../../api/goong_navigation_api.dart';
import '../../driver_app_controller.dart';
import '../../widgets/driver_feedback.dart';
import '../../widgets/driver_goong_map.dart';
import '../active_job/job_navigation_page.dart';
import 'incoming_order_dialog.dart';

class DriverHomePage extends StatefulWidget {
  const DriverHomePage({super.key, required this.controller});
  final DriverAppController controller;

  @override
  State<DriverHomePage> createState() => _DriverHomePageState();
}

class _DriverHomePageState extends State<DriverHomePage> {
  final shownOffers = <String>{};

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final active = controller.activeOffer;
    final pending = controller.offers
        .where((offer) => offer.status == 'PENDING')
        .toList();
    final online = controller.profile?.availabilityStatus == 'ONLINE';
    final unseen = pending
        .where((offer) => !shownOffers.contains(offer.id))
        .firstOrNull;
    if (unseen != null) {
      shownOffers.add(unseen.id);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        showDialog<void>(
          context: context,
          barrierDismissible: false,
          builder: (_) =>
              IncomingOrderDialog(controller: controller, offer: unseen),
        );
      });
    }
    return RefreshIndicator(
      onRefresh: controller.loadOffers,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          online ? 'Đang nhận chuyến' : 'Đang ngoại tuyến',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text(
                          online
                              ? 'Vị trí được cập nhật để tìm đơn gần bạn.'
                              : 'Bật online khi bạn sẵn sàng.',
                        ),
                      ],
                    ),
                  ),
                  Switch.adaptive(
                    value: online,
                    onChanged: controller.busy || active != null
                        ? null
                        : controller.setOnline,
                  ),
                ],
              ),
            ),
          ),
          if (controller.error case final error?) ...[
            const SizedBox(height: 10),
            DriverErrorBanner(message: error),
          ],
          if (controller.locationError case final locationError?
              when locationError != controller.error) ...[
            const SizedBox(height: 10),
            DriverErrorBanner(message: locationError),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: controller.prepareLocation,
                icon: const Icon(Icons.my_location_outlined),
                label: const Text('Thử lại quyền vị trí'),
              ),
            ),
          ],
          const SizedBox(height: 14),
          DriverGoongMap(
            height: 210,
            mapKey: const String.fromEnvironment('GOONG_MAP_KEY'),
            current: _coordinate(controller.currentPosition),
            pickup: active == null
                ? null
                : NavigationCoordinate(
                    latitude: active.pickupLatitude,
                    longitude: active.pickupLongitude,
                  ),
            dropoff: active == null
                ? null
                : NavigationCoordinate(
                    latitude: active.dropoffLatitude,
                    longitude: active.dropoffLongitude,
                  ),
          ),
          if (active != null) ...[
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => JobNavigationPage(controller: controller),
                ),
              ),
              icon: const Icon(Icons.navigation_outlined),
              label: const Text('Tiếp tục chuyến đang chạy'),
            ),
          ],
          const SizedBox(height: 22),
          Text('Đề nghị mới', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 10),
          if (pending.isEmpty)
            const DriverEmptyState(
              icon: Icons.inbox_outlined,
              title: 'Chưa có đề nghị',
              message: 'Giữ trạng thái online để nhận đơn gần vị trí của bạn.',
            ),
          for (final offer in pending)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                child: ListTile(
                  leading: Icon(
                    offer.serviceType == 'DELIVERY'
                        ? Icons.inventory_2_outlined
                        : Icons.directions_car_outlined,
                  ),
                  title: Text(offer.serviceType),
                  subtitle: Text(
                    '${(offer.pickupDistanceMeters / 1000).toStringAsFixed(1)} km · '
                    '${offer.estimatedEarning.toStringAsFixed(0)} VND',
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => showDialog<void>(
                    context: context,
                    barrierDismissible: false,
                    builder: (_) => IncomingOrderDialog(
                      controller: controller,
                      offer: offer,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  NavigationCoordinate? _coordinate(DriverPosition? position) {
    if (position == null) return null;
    return NavigationCoordinate(
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }
}
