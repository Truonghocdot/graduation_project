import 'api_transport.dart';

class DriverSession {
  const DriverSession({required this.baseUrl, required this.token});

  final String baseUrl;
  final String token;

  DriverSession copyWith({String? token}) {
    return DriverSession(baseUrl: baseUrl, token: token ?? this.token);
  }
}

class DriverOfferSummary {
  const DriverOfferSummary({
    required this.id,
    required this.status,
    required this.serviceType,
    required this.serviceRequestId,
    required this.serviceStatus,
    required this.paymentMethod,
    required this.customerPayable,
    required this.pickupDistanceMeters,
    required this.estimatedEarning,
    required this.expiresAt,
    required this.pickupLatitude,
    required this.pickupLongitude,
    required this.dropoffLatitude,
    required this.dropoffLongitude,
  });

  final String id;
  final String status;
  final String serviceType;
  final String serviceRequestId;
  final String serviceStatus;
  final String paymentMethod;
  final double customerPayable;
  final double pickupDistanceMeters;
  final double estimatedEarning;
  final DateTime expiresAt;
  final double pickupLatitude;
  final double pickupLongitude;
  final double dropoffLatitude;
  final double dropoffLongitude;

  factory DriverOfferSummary.fromJson(Map<String, dynamic> json) {
    final serviceRequest = json['service_request'] as Map<String, dynamic>;
    final stops = (serviceRequest['stops'] as List)
        .whereType<Map<String, dynamic>>()
        .toList(growable: false);
    final pickup = stops.firstWhere((stop) => stop['type'] == 'PICKUP');
    final dropoff = stops.firstWhere((stop) => stop['type'] == 'DROPOFF');
    return DriverOfferSummary(
      id: json['id'] as String,
      status: json['status'] as String,
      serviceType: serviceRequest['service_type'] as String,
      serviceRequestId: serviceRequest['id'] as String,
      serviceStatus: serviceRequest['status'] as String,
      paymentMethod:
          (serviceRequest['payment'] as Map<String, dynamic>)['method']
              as String,
      customerPayable:
          ((serviceRequest['payment']
                      as Map<String, dynamic>)['customer_payable']
                  as num)
              .toDouble(),
      pickupDistanceMeters: (json['estimated_pickup_distance_meters'] as num)
          .toDouble(),
      estimatedEarning: (json['estimated_driver_earning'] as num).toDouble(),
      expiresAt: DateTime.parse(json['expires_at'] as String),
      pickupLatitude: (pickup['latitude'] as num).toDouble(),
      pickupLongitude: (pickup['longitude'] as num).toDouble(),
      dropoffLatitude: (dropoff['latitude'] as num).toDouble(),
      dropoffLongitude: (dropoff['longitude'] as num).toDouble(),
    );
  }

  DriverOfferSummary withServiceStatus(String value) {
    return DriverOfferSummary(
      id: id,
      status: status,
      serviceType: serviceType,
      serviceRequestId: serviceRequestId,
      serviceStatus: value,
      paymentMethod: paymentMethod,
      customerPayable: customerPayable,
      pickupDistanceMeters: pickupDistanceMeters,
      estimatedEarning: estimatedEarning,
      expiresAt: expiresAt,
      pickupLatitude: pickupLatitude,
      pickupLongitude: pickupLongitude,
      dropoffLatitude: dropoffLatitude,
      dropoffLongitude: dropoffLongitude,
    );
  }
}

class DriverWalletSummary {
  const DriverWalletSummary({
    required this.balance,
    required this.reserved,
    required this.available,
  });

  final double balance;
  final double reserved;
  final double available;

  factory DriverWalletSummary.fromJson(Map<String, dynamic> json) {
    return DriverWalletSummary(
      balance: (json['balance'] as num).toDouble(),
      reserved: (json['reserved_withdrawal_amount'] as num).toDouble(),
      available: (json['available_balance'] as num).toDouble(),
    );
  }
}

class DriverBankAccountSummary {
  const DriverBankAccountSummary({
    required this.id,
    required this.bankCode,
    required this.accountName,
    required this.verified,
  });

  final String id;
  final String bankCode;
  final String accountName;
  final bool verified;

  factory DriverBankAccountSummary.fromJson(Map<String, dynamic> json) {
    return DriverBankAccountSummary(
      id: json['id'] as String,
      bankCode: json['bank_code'] as String,
      accountName: json['account_name'] as String,
      verified: json['is_verified'] as bool,
    );
  }
}

abstract interface class DriverGateway {
  Future<String> login({
    required String baseUrl,
    required String phone,
    required String password,
  });

  Future<List<DriverOfferSummary>> loadOffers(DriverSession session);

  Future<DriverOfferSummary> respond({
    required DriverSession session,
    required DriverOfferSummary offer,
    required String action,
    required String idempotencyKey,
  });

  Future<String> transition({
    required DriverSession session,
    required DriverOfferSummary offer,
    required String action,
    required double latitude,
    required double longitude,
    required String idempotencyKey,
    double? cashCollected,
    double? codCollected,
  });

  Future<DriverWalletSummary> loadWallet(DriverSession session);

  Future<List<DriverBankAccountSummary>> loadBankAccounts(
    DriverSession session,
  );

  Future<void> requestWithdrawal({
    required DriverSession session,
    required String bankAccountId,
    required double amount,
    required String idempotencyKey,
  });
}

class DriverApi implements DriverGateway {
  DriverApi({ApiTransport? transport})
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
      uri: Uri.parse('${_base(baseUrl)}/auth/login'),
      token: '',
      body: {
        'phone': phone,
        'password': password,
        'device_id': 'driver-app-session',
        'app_type': 'DRIVER_APP',
        'platform': 'ANDROID',
      },
    );
    _assertSuccess(response);
    final token = response.body['token'];
    if (token is! String || token.isEmpty) {
      throw const DriverApiException('Phiên đăng nhập không hợp lệ.');
    }
    return token;
  }

  @override
  Future<List<DriverOfferSummary>> loadOffers(DriverSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: Uri.parse('${_base(session.baseUrl)}/driver/offers'),
      token: session.token,
    );
    _assertSuccess(response);
    final data = response.body['data'];
    if (data is! List) {
      throw const DriverApiException('Danh sách đề nghị không hợp lệ.');
    }
    return data
        .whereType<Map<String, dynamic>>()
        .map(DriverOfferSummary.fromJson)
        .toList(growable: false);
  }

  @override
  Future<DriverOfferSummary> respond({
    required DriverSession session,
    required DriverOfferSummary offer,
    required String action,
    required String idempotencyKey,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse(
        '${_base(session.baseUrl)}/driver/offers/${offer.id}/respond',
      ),
      token: session.token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: {'action': action},
    );
    _assertSuccess(response);
    return DriverOfferSummary.fromJson(
      response.body['data'] as Map<String, dynamic>,
    );
  }

  @override
  Future<String> transition({
    required DriverSession session,
    required DriverOfferSummary offer,
    required String action,
    required double latitude,
    required double longitude,
    required String idempotencyKey,
    double? cashCollected,
    double? codCollected,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse(
        '${_base(session.baseUrl)}/driver/service-requests/${offer.serviceRequestId}/transition',
      ),
      token: session.token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: {
        'action': action,
        'latitude': latitude,
        'longitude': longitude,
        'cash_collected': ?cashCollected,
        'cod_collected': ?codCollected,
      },
    );
    _assertSuccess(response);
    return (response.body['data'] as Map<String, dynamic>)['status'] as String;
  }

  @override
  Future<DriverWalletSummary> loadWallet(DriverSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: Uri.parse('${_base(session.baseUrl)}/wallet'),
      token: session.token,
    );
    _assertSuccess(response);
    return DriverWalletSummary.fromJson(
      response.body['data'] as Map<String, dynamic>,
    );
  }

  @override
  Future<List<DriverBankAccountSummary>> loadBankAccounts(
    DriverSession session,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: Uri.parse('${_base(session.baseUrl)}/driver/bank-accounts'),
      token: session.token,
    );
    _assertSuccess(response);
    final data = response.body['data'] as List;
    return data
        .whereType<Map<String, dynamic>>()
        .map(DriverBankAccountSummary.fromJson)
        .toList(growable: false);
  }

  @override
  Future<void> requestWithdrawal({
    required DriverSession session,
    required String bankAccountId,
    required double amount,
    required String idempotencyKey,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse('${_base(session.baseUrl)}/driver/withdrawals'),
      token: session.token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: {'bank_account_id': bankAccountId, 'amount': amount},
    );
    _assertSuccess(response);
  }

  String _base(String value) => value.replaceFirst(RegExp(r'/$'), '');

  void _assertSuccess(ApiResponse response) {
    if (response.statusCode >= 200 && response.statusCode < 300) return;
    final errors = response.body['errors'];
    if (errors is Map<String, dynamic>) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          throw DriverApiException(value.first.toString());
        }
      }
    }
    throw DriverApiException(
      response.body['message']?.toString() ?? 'Không thể hoàn tất yêu cầu.',
    );
  }
}

class DriverApiException implements Exception {
  const DriverApiException(this.message);
  final String message;

  @override
  String toString() => message;
}
