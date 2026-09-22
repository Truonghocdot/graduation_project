import 'package:flutter/material.dart';

import '../../../api/booking_api.dart';
import '../../client_app_controller.dart';
import '../../widgets/app_feedback.dart';
import 'order_checkout_page.dart';

class CreateOrderPage extends StatefulWidget {
  const CreateOrderPage({
    super.key,
    required this.controller,
    required this.service,
  });

  final ClientAppController controller;
  final ServiceKind service;

  @override
  State<CreateOrderPage> createState() => _CreateOrderPageState();
}

class _CreateOrderPageState extends State<CreateOrderPage> {
  final formKey = GlobalKey<FormState>();
  final pickupAddress = TextEditingController(text: '1 Nguyễn Huệ, Quận 1');
  final pickupLatitude = TextEditingController(text: '10.773');
  final pickupLongitude = TextEditingController(text: '106.704');
  final dropoffAddress = TextEditingController(text: '1 Võ Văn Tần, Quận 3');
  final dropoffLatitude = TextEditingController(text: '10.780');
  final dropoffLongitude = TextEditingController(text: '106.690');
  final goodsType = TextEditingController(text: 'GENERAL');
  final weight = TextEditingController(text: '5');
  final passengers = TextEditingController(text: '1');
  final voucher = TextEditingController();
  DateTime? scheduledAt;

  @override
  void dispose() {
    for (final controller in [
      pickupAddress,
      pickupLatitude,
      pickupLongitude,
      dropoffAddress,
      dropoffLatitude,
      dropoffLongitude,
      goodsType,
      weight,
      passengers,
      voucher,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final delivery = widget.service == ServiceKind.delivery;
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, _) => Scaffold(
        appBar: AppBar(
          title: Text(delivery ? 'Tạo đơn giao hàng' : 'Tạo chuyến xe'),
        ),
        body: Form(
          key: formKey,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _LocationSection(
                icon: Icons.radio_button_checked,
                title: 'Điểm đón / lấy hàng',
                address: pickupAddress,
                latitude: pickupLatitude,
                longitude: pickupLongitude,
              ),
              const SizedBox(height: 16),
              _LocationSection(
                icon: Icons.location_on_outlined,
                title: 'Điểm đến / giao hàng',
                address: dropoffAddress,
                latitude: dropoffLatitude,
                longitude: dropoffLongitude,
              ),
              const SizedBox(height: 20),
              if (delivery) ...[
                TextField(
                  controller: goodsType,
                  decoration: const InputDecoration(labelText: 'Loại hàng hóa'),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: weight,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Khối lượng (kg)',
                  ),
                ),
              ] else
                TextField(
                  controller: passengers,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Số hành khách'),
                ),
              const SizedBox(height: 10),
              TextField(
                controller: voucher,
                decoration: const InputDecoration(
                  labelText: 'Mã giảm giá',
                  prefixIcon: Icon(Icons.local_offer_outlined),
                ),
              ),
              const SizedBox(height: 10),
              ListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 4),
                leading: const Icon(Icons.schedule_outlined),
                title: Text(
                  scheduledAt == null
                      ? 'Đặt ngay'
                      : 'Đặt lúc ${scheduledAt!.day}/${scheduledAt!.month} '
                            '${scheduledAt!.hour.toString().padLeft(2, '0')}:'
                            '${scheduledAt!.minute.toString().padLeft(2, '0')}',
                ),
                trailing: IconButton(
                  tooltip: 'Chọn thời gian',
                  onPressed: _pickSchedule,
                  icon: const Icon(Icons.edit_calendar_outlined),
                ),
              ),
              if (widget.controller.error case final error?) ...[
                const SizedBox(height: 12),
                ErrorBanner(message: error),
              ],
              const SizedBox(height: 16),
              SizedBox(
                height: 48,
                child: FilledButton.icon(
                  key: const Key('quote-button'),
                  onPressed: widget.controller.busy ? null : _quote,
                  icon: const Icon(Icons.calculate_outlined),
                  label: const Text('Nhận báo giá'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _quote() async {
    final pickupLat = double.tryParse(pickupLatitude.text);
    final pickupLng = double.tryParse(pickupLongitude.text);
    final dropoffLat = double.tryParse(dropoffLatitude.text);
    final dropoffLng = double.tryParse(dropoffLongitude.text);
    if (pickupAddress.text.trim().isEmpty ||
        dropoffAddress.text.trim().isEmpty ||
        pickupLat == null ||
        pickupLng == null ||
        dropoffLat == null ||
        dropoffLng == null) {
      return;
    }
    await widget.controller.requestQuote(
      BookingDraft(
        service: widget.service,
        pickup: LocationDraft(
          address: pickupAddress.text.trim(),
          latitude: pickupLat,
          longitude: pickupLng,
        ),
        dropoff: LocationDraft(
          address: dropoffAddress.text.trim(),
          latitude: dropoffLat,
          longitude: dropoffLng,
        ),
        goodsType: goodsType.text.trim(),
        weightKg: double.tryParse(weight.text) ?? 0,
        passengerCount: int.tryParse(passengers.text) ?? 1,
        voucherCode: voucher.text.trim().isEmpty ? null : voucher.text.trim(),
        scheduledAt: scheduledAt,
      ),
    );
    if (widget.controller.quote != null && mounted) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => OrderCheckoutPage(controller: widget.controller),
        ),
      );
    }
  }

  Future<void> _pickSchedule() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      firstDate: now,
      lastDate: now.add(const Duration(days: 30)),
      initialDate: now.add(const Duration(days: 1)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(now.add(const Duration(hours: 1))),
    );
    if (time == null) return;
    setState(
      () => scheduledAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }
}

class _LocationSection extends StatelessWidget {
  const _LocationSection({
    required this.icon,
    required this.title,
    required this.address,
    required this.latitude,
    required this.longitude,
  });

  final IconData icon;
  final String title;
  final TextEditingController address;
  final TextEditingController latitude;
  final TextEditingController longitude;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Icon(icon, size: 18),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        TextField(
          controller: address,
          decoration: const InputDecoration(labelText: 'Địa chỉ'),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: latitude,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'Vĩ độ'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: longitude,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'Kinh độ'),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
