import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../order/create_order_page.dart';

class ServiceDetailPage extends StatelessWidget {
  const ServiceDetailPage({
    super.key,
    required this.controller,
    required this.service,
  });

  final ClientAppController controller;
  final ServiceKind service;

  @override
  Widget build(BuildContext context) {
    final delivery = service == ServiceKind.delivery;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, _) => Scaffold(
        appBar: AppBar(title: Text(delivery ? 'Giao hàng' : 'Đặt xe')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Icon(
              delivery
                  ? Icons.local_shipping_outlined
                  : Icons.directions_car_outlined,
              size: 54,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 14),
            Text(
              delivery
                  ? 'Chọn phương tiện giao hàng'
                  : 'Chọn phương tiện di chuyển',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 20),
            for (final vehicle in controller.vehicles)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: ListTile(
                  selected: controller.session.vehicleTypeId == vehicle.id,
                  onTap: () => controller.selectVehicle(vehicle.id),
                  title: Text(vehicle.name),
                  subtitle: Text(vehicle.key),
                  leading: const Icon(Icons.two_wheeler_outlined),
                  trailing: controller.session.vehicleTypeId == vehicle.id
                      ? const Icon(Icons.check_circle, color: Color(0xFF146B52))
                      : const Icon(Icons.circle_outlined),
                ),
              ),
            const SizedBox(height: 12),
            SizedBox(
              height: 48,
              child: FilledButton.icon(
                key: const Key('continue-service-button'),
                onPressed: controller.session.vehicleTypeId.isEmpty
                    ? null
                    : () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => CreateOrderPage(
                            controller: controller,
                            service: service,
                          ),
                        ),
                      ),
                icon: const Icon(Icons.arrow_forward),
                label: const Text('Tiếp tục'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
