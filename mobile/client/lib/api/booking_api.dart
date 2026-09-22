import 'api_transport.dart';

enum ServiceKind {
  delivery('DELIVERY'),
  drive('DRIVE');

  const ServiceKind(this.apiValue);
  final String apiValue;
}

enum PaymentChoice {
  wallet('WALLET'),
  cash('CASH');

  const PaymentChoice(this.apiValue);
  final String apiValue;
}

enum PayerChoice {
  orderer('ORDERER'),
  recipient('RECIPIENT');

  const PayerChoice(this.apiValue);
  final String apiValue;
}

class BookingSession {
  const BookingSession({
    required this.baseUrl,
    required this.token,
    required this.vehicleTypeId,
  });

  final String baseUrl;
  final String token;
  final String vehicleTypeId;

  BookingSession copyWith({
    String? baseUrl,
    String? token,
    String? vehicleTypeId,
  }) {
    return BookingSession(
      baseUrl: baseUrl ?? this.baseUrl,
      token: token ?? this.token,
      vehicleTypeId: vehicleTypeId ?? this.vehicleTypeId,
    );
  }
}

class VehicleOption {
  const VehicleOption({
    required this.id,
    required this.key,
    required this.name,
  });

  final String id;
  final String key;
  final String name;

  factory VehicleOption.fromJson(Map<String, dynamic> json) {
    return VehicleOption(
      id: json['id'] as String,
      key: json['key'] as String,
      name: json['name'] as String,
    );
  }
}

class LocationDraft {
  const LocationDraft({
    required this.address,
    required this.latitude,
    required this.longitude,
  });

  final String address;
  final double latitude;
  final double longitude;

  Map<String, dynamic> toJson() => {
    'address': address,
    'latitude': latitude,
    'longitude': longitude,
  };
}

class BookingDraft {
  const BookingDraft({
    required this.service,
    required this.pickup,
    required this.dropoff,
    required this.goodsType,
    required this.weightKg,
    required this.passengerCount,
    this.voucherCode,
    this.scheduledAt,
  });

  final ServiceKind service;
  final LocationDraft pickup;
  final LocationDraft dropoff;
  final String goodsType;
  final double weightKg;
  final int passengerCount;
  final String? voucherCode;
  final DateTime? scheduledAt;
}

class QuoteSummary {
  const QuoteSummary({
    required this.id,
    required this.service,
    required this.grossFare,
    required this.voucherDiscount,
    required this.customerPayable,
    required this.currency,
    required this.distanceMeters,
    required this.durationSeconds,
    required this.expiresAt,
  });

  final String id;
  final ServiceKind service;
  final double grossFare;
  final double voucherDiscount;
  final double customerPayable;
  final String currency;
  final double distanceMeters;
  final int durationSeconds;
  final DateTime expiresAt;

  factory QuoteSummary.fromJson(Map<String, dynamic> json) {
    final pricing = json['pricing'] as Map<String, dynamic>;
    final route = json['route'] as Map<String, dynamic>;

    return QuoteSummary(
      id: json['id'] as String,
      service: json['service_type'] == ServiceKind.delivery.apiValue
          ? ServiceKind.delivery
          : ServiceKind.drive,
      grossFare: (pricing['gross_fare'] as num).toDouble(),
      voucherDiscount: (pricing['voucher_discount'] as num).toDouble(),
      customerPayable: (pricing['customer_payable'] as num).toDouble(),
      currency: pricing['currency'] as String,
      distanceMeters: (route['distance_meters'] as num).toDouble(),
      durationSeconds: (route['duration_seconds'] as num).toInt(),
      expiresAt: DateTime.parse(json['expires_at'] as String),
    );
  }
}

class ServiceRequestSummary {
  const ServiceRequestSummary({
    required this.id,
    required this.service,
    required this.status,
    required this.paymentMethod,
    required this.customerPayable,
    this.driverNetEarning,
  });

  final String id;
  final ServiceKind service;
  final String status;
  final PaymentChoice paymentMethod;
  final double customerPayable;
  final double? driverNetEarning;

  factory ServiceRequestSummary.fromJson(Map<String, dynamic> json) {
    final payment = json['payment'] as Map<String, dynamic>;
    final settlement = payment['settlement'];

    return ServiceRequestSummary(
      id: json['id'] as String,
      service: json['service_type'] == ServiceKind.delivery.apiValue
          ? ServiceKind.delivery
          : ServiceKind.drive,
      status: json['status'] as String,
      paymentMethod: payment['method'] == PaymentChoice.wallet.apiValue
          ? PaymentChoice.wallet
          : PaymentChoice.cash,
      customerPayable: (payment['customer_payable'] as num).toDouble(),
      driverNetEarning: settlement is Map<String, dynamic>
          ? (settlement['driver_net_earning'] as num?)?.toDouble()
          : null,
    );
  }
}

class WalletSummary {
  const WalletSummary({
    required this.id,
    required this.balance,
    required this.reserved,
    required this.available,
    required this.currency,
  });

  final String id;
  final double balance;
  final double reserved;
  final double available;
  final String currency;

  factory WalletSummary.fromJson(Map<String, dynamic> json) {
    return WalletSummary(
      id: json['id'] as String,
      balance: (json['balance'] as num).toDouble(),
      reserved: (json['reserved_withdrawal_amount'] as num).toDouble(),
      available: (json['available_balance'] as num).toDouble(),
      currency: json['currency'] as String,
    );
  }
}

class WalletTopupSummary {
  const WalletTopupSummary({
    required this.id,
    required this.amount,
    required this.status,
    required this.reference,
    required this.vietQrPayload,
  });

  final String id;
  final double amount;
  final String status;
  final String reference;
  final String vietQrPayload;

  factory WalletTopupSummary.fromJson(Map<String, dynamic> json) {
    return WalletTopupSummary(
      id: json['id'] as String,
      amount: (json['amount'] as num).toDouble(),
      status: json['status'] as String,
      reference: json['vietqr_reference'] as String,
      vietQrPayload: json['vietqr_payload'] as String,
    );
  }
}

class SupportChatMessage {
  const SupportChatMessage({required this.senderName, required this.body});

  final String senderName;
  final String body;

  factory SupportChatMessage.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'] as Map<String, dynamic>?;
    return SupportChatMessage(
      senderName: sender?['name']?.toString() ?? 'User',
      body: json['body']?.toString() ?? '',
    );
  }
}

class AppNotificationSummary {
  const AppNotificationSummary({
    required this.id,
    required this.type,
    required this.isRead,
  });

  final String id;
  final String type;
  final bool isRead;

  factory AppNotificationSummary.fromJson(Map<String, dynamic> json) {
    return AppNotificationSummary(
      id: json['id'] as String,
      type: json['type'] as String,
      isRead: json['read_at'] != null,
    );
  }
}

abstract interface class BookingSupportGateway {
  Future<List<SupportChatMessage>> loadChat(
    BookingSession session,
    String serviceRequestId,
  );

  Future<void> sendChat({
    required BookingSession session,
    required String serviceRequestId,
    required String body,
  });

  Future<void> createSupportTicket({
    required BookingSession session,
    required String serviceRequestId,
    required String subject,
    required String description,
  });

  Future<void> reportIncident({
    required BookingSession session,
    required String serviceRequestId,
    required String incidentType,
    String? description,
  });

  Future<void> submitRating({
    required BookingSession session,
    required String serviceRequestId,
    required int score,
    String? comment,
  });

  Future<List<AppNotificationSummary>> loadNotifications(
    BookingSession session,
  );

  Future<void> markNotificationRead(
    BookingSession session,
    String notificationId,
  );
}

abstract interface class BookingGateway {
  Future<String> login({
    required String baseUrl,
    required String phone,
    required String password,
  });

  Future<List<VehicleOption>> loadVehicleTypes(BookingSession session);

  Future<QuoteSummary> createQuote(BookingSession session, BookingDraft draft);

  Future<ServiceRequestSummary> createServiceRequest({
    required BookingSession session,
    required QuoteSummary quote,
    required PaymentChoice payment,
    required PayerChoice payer,
    required String idempotencyKey,
    String? recipientUserId,
  });

  Future<ServiceRequestSummary> cancelServiceRequest({
    required BookingSession session,
    required ServiceRequestSummary serviceRequest,
    required String idempotencyKey,
    required String reasonCode,
  });

  Future<ServiceRequestSummary> loadServiceRequest(
    BookingSession session,
    String serviceRequestId,
  );

  Future<WalletSummary> loadWallet(BookingSession session);

  Future<WalletTopupSummary> createTopup({
    required BookingSession session,
    required double amount,
    required String idempotencyKey,
  });
}

class BookingApi implements BookingGateway, BookingSupportGateway {
  BookingApi({ApiTransport? transport})
    : _transport = transport ?? createApiTransport();

  final ApiTransport _transport;

  @override
  Future<String> login({
    required String baseUrl,
    required String phone,
    required String password,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse('${baseUrl.replaceFirst(RegExp(r'/$'), '')}/auth/login'),
      token: '',
      body: {
        'phone': phone,
        'password': password,
        'device_id': 'customer-app-session',
        'app_type': 'CUSTOMER_APP',
        'platform': 'ANDROID',
      },
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw BookingApiException.fromResponse(response);
    }

    final token = response.body['token'];
    if (token is! String || token.isEmpty) {
      throw const BookingApiException('Phiên đăng nhập không hợp lệ.');
    }

    return token;
  }

  @override
  Future<List<VehicleOption>> loadVehicleTypes(BookingSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/catalog/vehicle-types'),
      token: session.token,
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw BookingApiException.fromResponse(response);
    }

    final data = response.body['data'];
    if (data is! List) {
      throw const BookingApiException('Danh sách phương tiện không hợp lệ.');
    }

    return data
        .whereType<Map<String, dynamic>>()
        .map(VehicleOption.fromJson)
        .toList(growable: false);
  }

  @override
  Future<QuoteSummary> createQuote(
    BookingSession session,
    BookingDraft draft,
  ) async {
    final response = await _transport.send(
      method: 'POST',
      uri: _uri(session, '/quotes'),
      token: session.token,
      body: {
        'service_type': draft.service.apiValue,
        'vehicle_type_id': session.vehicleTypeId,
        'booking_type': draft.scheduledAt == null ? 'NOW' : 'SCHEDULED',
        'scheduled_at': ?draft.scheduledAt?.toUtc().toIso8601String(),
        'pickup': draft.pickup.toJson(),
        'dropoff': draft.dropoff.toJson(),
        'service_payload': draft.service == ServiceKind.delivery
            ? {'goods_type': draft.goodsType, 'weight_kg': draft.weightKg}
            : {'passenger_count': draft.passengerCount},
        'voucher_code': ?draft.voucherCode,
      },
    );

    return QuoteSummary.fromJson(_data(response));
  }

  @override
  Future<ServiceRequestSummary> createServiceRequest({
    required BookingSession session,
    required QuoteSummary quote,
    required PaymentChoice payment,
    required PayerChoice payer,
    required String idempotencyKey,
    String? recipientUserId,
  }) async {
    final path = quote.service == ServiceKind.delivery
        ? '/delivery/orders'
        : '/rides/bookings';
    final response = await _transport.send(
      method: 'POST',
      uri: _uri(session, path),
      token: session.token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: {
        'quote_id': quote.id,
        'payment_method': payment.apiValue,
        if (quote.service == ServiceKind.delivery) 'payer_type': payer.apiValue,
        'recipient_user_id': ?recipientUserId,
      },
    );

    return ServiceRequestSummary.fromJson(_data(response));
  }

  @override
  Future<ServiceRequestSummary> cancelServiceRequest({
    required BookingSession session,
    required ServiceRequestSummary serviceRequest,
    required String idempotencyKey,
    required String reasonCode,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: _uri(session, '/service-requests/${serviceRequest.id}/cancel'),
      token: session.token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: {'reason_code': reasonCode},
    );

    return ServiceRequestSummary.fromJson(_data(response));
  }

  @override
  Future<ServiceRequestSummary> loadServiceRequest(
    BookingSession session,
    String serviceRequestId,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/service-requests/$serviceRequestId'),
      token: session.token,
    );

    return ServiceRequestSummary.fromJson(_data(response));
  }

  @override
  Future<WalletSummary> loadWallet(BookingSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/wallet'),
      token: session.token,
    );

    return WalletSummary.fromJson(_data(response));
  }

  @override
  Future<WalletTopupSummary> createTopup({
    required BookingSession session,
    required double amount,
    required String idempotencyKey,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: _uri(session, '/wallet/topups'),
      token: session.token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: {'amount': amount},
    );

    return WalletTopupSummary.fromJson(_data(response));
  }

  @override
  Future<List<SupportChatMessage>> loadChat(
    BookingSession session,
    String serviceRequestId,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/service-requests/$serviceRequestId/chat'),
      token: session.token,
    );

    return _listData(response)
        .map(SupportChatMessage.fromJson)
        .toList(growable: false);
  }

  @override
  Future<void> sendChat({
    required BookingSession session,
    required String serviceRequestId,
    required String body,
  }) async {
    _data(
      await _transport.send(
        method: 'POST',
        uri: _uri(session, '/service-requests/$serviceRequestId/chat'),
        token: session.token,
        body: {'client_message_id': _uuid(), 'body': body},
      ),
    );
  }

  @override
  Future<void> createSupportTicket({
    required BookingSession session,
    required String serviceRequestId,
    required String subject,
    required String description,
  }) async {
    _data(
      await _transport.send(
        method: 'POST',
        uri: _uri(session, '/support/tickets'),
        token: session.token,
        headers: {'Idempotency-Key': _uuid()},
        body: {
          'service_request_id': serviceRequestId,
          'category': 'OTHER',
          'subject': subject,
          'description': description,
        },
      ),
    );
  }

  @override
  Future<void> reportIncident({
    required BookingSession session,
    required String serviceRequestId,
    required String incidentType,
    String? description,
  }) async {
    _data(
      await _transport.send(
        method: 'POST',
        uri: _uri(session, '/service-requests/$serviceRequestId/incidents'),
        token: session.token,
        headers: {'Idempotency-Key': _uuid()},
        body: {
          'incident_type': incidentType,
          if (description?.trim().isNotEmpty ?? false)
            'description': description!.trim(),
        },
      ),
    );
  }

  @override
  Future<void> submitRating({
    required BookingSession session,
    required String serviceRequestId,
    required int score,
    String? comment,
  }) async {
    _data(
      await _transport.send(
        method: 'POST',
        uri: _uri(session, '/service-requests/$serviceRequestId/ratings'),
        token: session.token,
        body: {
          'score': score,
          if (comment?.trim().isNotEmpty ?? false) 'comment': comment!.trim(),
        },
      ),
    );
  }

  @override
  Future<List<AppNotificationSummary>> loadNotifications(
    BookingSession session,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/notifications'),
      token: session.token,
    );

    return _listData(response)
        .map(AppNotificationSummary.fromJson)
        .toList(growable: false);
  }

  @override
  Future<void> markNotificationRead(
    BookingSession session,
    String notificationId,
  ) async {
    _data(
      await _transport.send(
        method: 'PUT',
        uri: _uri(session, '/notifications/$notificationId/read'),
        token: session.token,
      ),
    );
  }

  Uri _uri(BookingSession session, String path) {
    return Uri.parse('${session.baseUrl.replaceFirst(RegExp(r'/$'), '')}$path');
  }

  Map<String, dynamic> _data(ApiResponse response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw BookingApiException.fromResponse(response);
    }

    final data = response.body['data'];
    if (data is! Map<String, dynamic>) {
      throw const BookingApiException('Phản hồi từ máy chủ không hợp lệ.');
    }

    return data;
  }

  List<Map<String, dynamic>> _listData(ApiResponse response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw BookingApiException.fromResponse(response);
    }

    final data = response.body['data'];
    if (data is! List) {
      throw const BookingApiException('Invalid server response.');
    }

    return data.whereType<Map<String, dynamic>>().toList(growable: false);
  }

  String _uuid() {
    final value = DateTime.now().microsecondsSinceEpoch.toRadixString(16);
    final padded = value.padLeft(32, '0');
    return '${padded.substring(0, 8)}-${padded.substring(8, 12)}-4${padded.substring(13, 16)}-a${padded.substring(17, 20)}-${padded.substring(20, 32)}';
  }
}

class BookingApiException implements Exception {
  const BookingApiException(this.message);

  final String message;

  factory BookingApiException.fromResponse(ApiResponse response) {
    final errors = response.body['errors'];
    if (errors is Map<String, dynamic>) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return BookingApiException(value.first.toString());
        }
      }
    }

    return BookingApiException(
      response.body['message']?.toString() ??
          'Không thể hoàn tất yêu cầu (${response.statusCode}).',
    );
  }

  @override
  String toString() => message;
}
