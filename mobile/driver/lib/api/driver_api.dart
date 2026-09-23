import 'dart:convert';

import 'package:flutter/foundation.dart';

import 'api_transport.dart';
import 'request_id.dart';

class DriverSession {
  const DriverSession({
    required this.baseUrl,
    required this.token,
    this.onboarding = false,
  });

  final String baseUrl;
  final String token;
  final bool onboarding;

  DriverSession copyWith({String? token, bool? onboarding}) {
    return DriverSession(
      baseUrl: baseUrl,
      token: token ?? this.token,
      onboarding: onboarding ?? this.onboarding,
    );
  }
}

class DriverLoginResult {
  const DriverLoginResult(this.token, {required this.onboarding});
  final String token;
  final bool onboarding;
}

class DriverProfileSummary {
  const DriverProfileSummary({
    required this.reviewStatus,
    required this.availabilityStatus,
    required this.vehicles,
    required this.documents,
    required this.capabilities,
    this.reviewReason,
  });
  final String reviewStatus;
  final String availabilityStatus;
  final String? reviewReason;
  final List<Map<String, dynamic>> vehicles;
  final List<Map<String, dynamic>> documents;
  final List<String> capabilities;

  factory DriverProfileSummary.fromJson(Map<String, dynamic> json) =>
      DriverProfileSummary(
        reviewStatus: json['review_status'] as String,
        availabilityStatus: json['availability_status'] as String,
        reviewReason: json['review_reason_code'] as String?,
        vehicles: (json['vehicles'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .toList(growable: false),
        documents: (json['documents'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .toList(growable: false),
        capabilities: (json['capabilities'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .where((item) => item['is_active'] == true)
            .map((item) => item['service_type'].toString())
            .toList(growable: false),
      );
}

abstract interface class DriverOperationsGateway {
  Future<DriverLoginResult> authenticate({
    required String baseUrl,
    required String phone,
    required String password,
  });
  Future<void> register({
    required String baseUrl,
    required String name,
    required String phone,
    required String password,
  });
  Future<String> verifyPhone({
    required String baseUrl,
    required String phone,
    required String code,
  });
  Future<void> resendPhone({required String baseUrl, required String phone});
  Future<void> forgotPassword({required String baseUrl, required String phone});
  Future<String> verifyReset({
    required String baseUrl,
    required String phone,
    required String code,
  });
  Future<void> resetPassword({
    required String baseUrl,
    required String phone,
    required String resetToken,
    required String password,
  });
  Future<void> validateSession(DriverSession session);
  Future<void> logout(DriverSession session);
  Future<DriverProfileSummary?> loadApplication(DriverSession session);
  Future<DriverProfileSummary> saveApplication(DriverSession session);
  Future<List<Map<String, dynamic>>> loadVehicleTypes(DriverSession session);
  Future<void> createVehicle({
    required DriverSession session,
    required String vehicleTypeId,
    required String plateNumber,
  });
  Future<void> updateVehicle({
    required DriverSession session,
    required String vehicleId,
    required String vehicleTypeId,
    required String plateNumber,
  });
  Future<void> uploadDocument({
    required DriverSession session,
    required String documentType,
    required String name,
    required Uint8List bytes,
    String? documentNumber,
    String? vehicleId,
  });
  Future<void> submitApplication({
    required DriverSession session,
    required String vehicleId,
    required List<String> serviceTypes,
  });
  Future<DriverProfileSummary> setAvailability({
    required DriverSession session,
    required bool online,
    required double latitude,
    required double longitude,
    required double accuracy,
    required List<String> serviceTypes,
  });
  Future<void> updateLocation({
    required DriverSession session,
    required double latitude,
    required double longitude,
    required double accuracy,
  });
  Future<String> uploadEvidence({
    required DriverSession session,
    required String serviceRequestId,
    required String evidenceType,
    required String name,
    required Uint8List bytes,
    required double latitude,
    required double longitude,
  });
  Future<void> createBankAccount({
    required DriverSession session,
    required String bankCode,
    required String accountNumber,
    required String accountName,
  });
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

class DriverJobSummary {
  const DriverJobSummary({
    required this.id,
    required this.serviceType,
    required this.status,
    required this.customerPayable,
    required this.paymentMethod,
    this.driverNetEarning,
    this.pickupAddress,
    this.dropoffAddress,
    this.createdAt,
  });

  final String id;
  final String serviceType;
  final String status;
  final double customerPayable;
  final String paymentMethod;
  final double? driverNetEarning;
  final String? pickupAddress;
  final String? dropoffAddress;
  final DateTime? createdAt;

  factory DriverJobSummary.fromJson(Map<String, dynamic> json) {
    final payment = json['payment'] as Map<String, dynamic>;
    final settlement = payment['settlement'];
    final stops = (json['stops'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .toList(growable: false);
    final pickup = stops.where((stop) => stop['type'] == 'PICKUP').firstOrNull;
    final dropoff = stops
        .where((stop) => stop['type'] == 'DROPOFF')
        .firstOrNull;
    return DriverJobSummary(
      id: json['id'] as String,
      serviceType: json['service_type'] as String,
      status: json['status'] as String,
      customerPayable: (payment['customer_payable'] as num).toDouble(),
      paymentMethod: payment['method'] as String,
      driverNetEarning: settlement is Map<String, dynamic>
          ? (settlement['driver_net_earning'] as num?)?.toDouble()
          : null,
      pickupAddress: pickup?['address']?.toString(),
      dropoffAddress: dropoff?['address']?.toString(),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'].toString()),
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

class DriverChatMessage {
  const DriverChatMessage({required this.senderName, required this.body});

  final String senderName;
  final String body;

  factory DriverChatMessage.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'] as Map<String, dynamic>?;
    return DriverChatMessage(
      senderName: sender?['name']?.toString() ?? 'Người dùng',
      body: json['body']?.toString() ?? '',
    );
  }
}

class DriverNotificationSummary {
  const DriverNotificationSummary({
    required this.id,
    required this.type,
    required this.isRead,
  });

  final String id;
  final String type;
  final bool isRead;

  factory DriverNotificationSummary.fromJson(Map<String, dynamic> json) {
    return DriverNotificationSummary(
      id: json['id'] as String,
      type: json['type'] as String,
      isRead: json['read_at'] != null,
    );
  }
}

class DriverTicketSummary {
  const DriverTicketSummary({
    required this.id,
    required this.subject,
    required this.status,
    required this.messages,
  });
  final String id;
  final String subject;
  final String status;
  final List<DriverChatMessage> messages;

  factory DriverTicketSummary.fromJson(Map<String, dynamic> json) =>
      DriverTicketSummary(
        id: json['id'] as String,
        subject: json['subject'] as String,
        status: json['status'] as String,
        messages: (json['messages'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(DriverChatMessage.fromJson)
            .toList(growable: false),
      );
}

abstract interface class DriverSupportGateway {
  Future<List<DriverTicketSummary>> loadTickets(DriverSession session);
  Future<DriverTicketSummary> loadTicket(DriverSession session, String id);
  Future<void> replyToTicket({
    required DriverSession session,
    required String id,
    required String body,
  });
  Future<List<DriverChatMessage>> loadChat(
    DriverSession session,
    String serviceRequestId,
  );

  Future<void> sendChat({
    required DriverSession session,
    required String serviceRequestId,
    required String body,
  });

  Future<void> createSupportTicket({
    required DriverSession session,
    required String serviceRequestId,
    required String subject,
    required String description,
  });

  Future<void> reportIncident({
    required DriverSession session,
    required String serviceRequestId,
    required String incidentType,
    String? description,
  });

  Future<void> submitRating({
    required DriverSession session,
    required String serviceRequestId,
    required int score,
    String? comment,
  });

  Future<List<DriverNotificationSummary>> loadNotifications(
    DriverSession session,
  );

  Future<void> markNotificationRead(
    DriverSession session,
    String notificationId,
  );
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
    String? evidenceId,
    String? outOfGeofenceReason,
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

abstract interface class DriverHistoryGateway {
  Future<List<DriverJobSummary>> loadJobHistory(DriverSession session);
}

class DriverApi
    implements
        DriverGateway,
        DriverSupportGateway,
        DriverOperationsGateway,
        DriverHistoryGateway {
  DriverApi({ApiTransport? transport, this.deviceId = 'driver-app-session'})
    : _transport = transport ?? createApiTransport();

  final ApiTransport _transport;
  final String deviceId;
  final _pendingOperations = <String, String>{};

  Future<void> _sendRetryable({
    required DriverSession session,
    required String path,
    required Map<String, dynamic> body,
    bool chat = false,
  }) async {
    final key = '${session.token}:$path:${jsonEncode(body)}';
    final id = _pendingOperations.putIfAbsent(key, newRequestId);
    final response = await _transport.send(
      method: 'POST',
      uri: _uri(session, path),
      token: session.token,
      headers: chat ? const {} : {'Idempotency-Key': id},
      body: chat ? {'client_message_id': id, ...body} : body,
    );
    _assertSuccess(response);
    _pendingOperations.remove(key);
  }

  String get _platform => kIsWeb
      ? 'WEB'
      : defaultTargetPlatform == TargetPlatform.iOS
      ? 'IOS'
      : 'ANDROID';

  Future<ApiResponse> _postAuth(
    String baseUrl,
    String path,
    Map<String, dynamic> body,
  ) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse('${_base(baseUrl)}$path'),
      token: '',
      body: body,
    );
    _assertSuccess(response);
    return response;
  }

  Future<String> _loginAs(
    String baseUrl,
    String phone,
    String password,
    String appType,
  ) async {
    final response = await _postAuth(baseUrl, '/auth/login', {
      'phone': phone,
      'password': password,
      'device_id': deviceId,
      'app_type': appType,
      'platform': _platform,
    });
    final token = response.body['token'];
    if (token is! String || token.isEmpty) {
      throw const DriverApiException('Phiên đăng nhập không hợp lệ.');
    }
    return token;
  }

  @override
  Future<DriverLoginResult> authenticate({
    required String baseUrl,
    required String phone,
    required String password,
  }) async {
    try {
      return DriverLoginResult(
        await _loginAs(baseUrl, phone, password, 'DRIVER_APP'),
        onboarding: false,
      );
    } on DriverApiException catch (error) {
      if (error.statusCode != 422) rethrow;
      return DriverLoginResult(
        await _loginAs(baseUrl, phone, password, 'CUSTOMER_APP'),
        onboarding: true,
      );
    }
  }

  @override
  Future<void> register({
    required String baseUrl,
    required String name,
    required String phone,
    required String password,
  }) async {
    await _postAuth(baseUrl, '/auth/register', {
      'name': name,
      'phone': phone,
      'password': password,
      'password_confirmation': password,
    });
  }

  @override
  Future<String> verifyPhone({
    required String baseUrl,
    required String phone,
    required String code,
  }) async {
    final response = await _postAuth(baseUrl, '/auth/phone/verify', {
      'phone': phone,
      'code': code,
      'device_id': deviceId,
      'app_type': 'CUSTOMER_APP',
      'platform': _platform,
    });
    final token = response.body['token'];
    if (token is! String || token.isEmpty) {
      throw const DriverApiException('Phiên đăng nhập không hợp lệ.');
    }
    return token;
  }

  @override
  Future<void> resendPhone({
    required String baseUrl,
    required String phone,
  }) async {
    await _postAuth(baseUrl, '/auth/phone/resend', {'phone': phone});
  }

  @override
  Future<void> forgotPassword({
    required String baseUrl,
    required String phone,
  }) async {
    await _postAuth(baseUrl, '/auth/password/forgot', {'phone': phone});
  }

  @override
  Future<String> verifyReset({
    required String baseUrl,
    required String phone,
    required String code,
  }) async {
    final response = await _postAuth(baseUrl, '/auth/password/verify', {
      'phone': phone,
      'code': code,
    });
    final token = response.body['reset_token'];
    if (token is! String) {
      throw const DriverApiException('Mã đặt lại mật khẩu không hợp lệ.');
    }
    return token;
  }

  @override
  Future<void> resetPassword({
    required String baseUrl,
    required String phone,
    required String resetToken,
    required String password,
  }) async {
    await _postAuth(baseUrl, '/auth/password/reset', {
      'phone': phone,
      'token': resetToken,
      'password': password,
      'password_confirmation': password,
    });
  }

  @override
  Future<void> validateSession(DriverSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: Uri.parse('${_base(session.baseUrl)}/me'),
      token: session.token,
    );
    _assertSuccess(response);
  }

  @override
  Future<List<DriverTicketSummary>> loadTickets(DriverSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/support/tickets'),
      token: session.token,
    );
    _assertSuccess(response);
    return _listData(response)
        .map(DriverTicketSummary.fromJson)
        .toList(growable: false);
  }

  @override
  Future<DriverTicketSummary> loadTicket(
    DriverSession session,
    String id,
  ) async => DriverTicketSummary.fromJson(
    await _resource(session, 'GET', '/support/tickets/$id'),
  );

  @override
  Future<void> replyToTicket({
    required DriverSession session,
    required String id,
    required String body,
  }) async {
    await _resource(
      session,
      'POST',
      '/support/tickets/$id/messages',
      body: {'body': body},
    );
  }

  @override
  Future<void> logout(DriverSession session) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse('${_base(session.baseUrl)}/auth/logout'),
      token: session.token,
    );
    _assertSuccess(response);
  }

  @override
  Future<String> login({
    required String baseUrl,
    required String phone,
    required String password,
  }) async {
    return _loginAs(baseUrl, phone, password, 'DRIVER_APP');
  }

  Uri _uri(DriverSession session, String path) =>
      Uri.parse('${_base(session.baseUrl)}$path');

  Future<Map<String, dynamic>> _resource(
    DriverSession session,
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final response = await _transport.send(
      method: method,
      uri: _uri(session, path),
      token: session.token,
      body: body,
    );
    _assertSuccess(response);
    final data = response.body['data'];
    if (data is! Map<String, dynamic>) {
      throw const DriverApiException('Phản hồi máy chủ không hợp lệ.');
    }
    return data;
  }

  @override
  Future<DriverProfileSummary?> loadApplication(DriverSession session) async {
    try {
      return DriverProfileSummary.fromJson(
        await _resource(session, 'GET', '/driver/application'),
      );
    } on DriverApiException catch (error) {
      if (error.statusCode == 422) return null;
      rethrow;
    }
  }

  @override
  Future<DriverProfileSummary> saveApplication(DriverSession session) async =>
      DriverProfileSummary.fromJson(
        await _resource(session, 'POST', '/driver/application', body: const {}),
      );

  @override
  Future<List<Map<String, dynamic>>> loadVehicleTypes(
    DriverSession session,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/catalog/vehicle-types'),
      token: session.token,
    );
    _assertSuccess(response);
    return _listData(response);
  }

  @override
  Future<void> createVehicle({
    required DriverSession session,
    required String vehicleTypeId,
    required String plateNumber,
  }) async {
    await _resource(
      session,
      'POST',
      '/driver/vehicles',
      body: {'vehicle_type_id': vehicleTypeId, 'plate_number': plateNumber},
    );
  }

  @override
  Future<void> updateVehicle({
    required DriverSession session,
    required String vehicleId,
    required String vehicleTypeId,
    required String plateNumber,
  }) async {
    await _resource(
      session,
      'PATCH',
      '/driver/vehicles/$vehicleId',
      body: {'vehicle_type_id': vehicleTypeId, 'plate_number': plateNumber},
    );
  }

  Future<Map<String, dynamic>> _upload(
    DriverSession session,
    String path,
    String name,
    Uint8List bytes,
    Map<String, String> fields,
  ) async {
    final transport = _transport;
    if (transport is! MultipartApiTransport) {
      throw const DriverApiException('Thiết bị không hỗ trợ tải tệp.');
    }
    final response = await (transport as MultipartApiTransport).upload(
      uri: _uri(session, path),
      token: session.token,
      name: name,
      bytes: bytes,
      fields: fields,
    );
    _assertSuccess(response);
    final data = response.body['data'];
    if (data is! Map<String, dynamic>) {
      throw const DriverApiException('Phản hồi tải tệp không hợp lệ.');
    }
    return data;
  }

  @override
  Future<void> uploadDocument({
    required DriverSession session,
    required String documentType,
    required String name,
    required Uint8List bytes,
    String? documentNumber,
    String? vehicleId,
  }) async {
    await _upload(session, '/driver/documents', name, bytes, {
      'document_type': documentType,
      if (documentNumber != null && documentNumber.isNotEmpty)
        'document_number': documentNumber,
      if (vehicleId != null && vehicleId.isNotEmpty) 'vehicle_id': vehicleId,
    });
  }

  @override
  Future<void> submitApplication({
    required DriverSession session,
    required String vehicleId,
    required List<String> serviceTypes,
  }) async {
    await _resource(
      session,
      'POST',
      '/driver/application/submit',
      body: {'vehicle_id': vehicleId, 'service_types': serviceTypes},
    );
  }

  @override
  Future<DriverProfileSummary> setAvailability({
    required DriverSession session,
    required bool online,
    required double latitude,
    required double longitude,
    required double accuracy,
    required List<String> serviceTypes,
  }) async => DriverProfileSummary.fromJson(
    await _resource(
      session,
      'PUT',
      online ? '/driver/availability/online' : '/driver/availability/offline',
      body: online
          ? {
              'service_types': serviceTypes,
              'latitude': latitude,
              'longitude': longitude,
              'accuracy': accuracy,
              'captured_at': DateTime.now().toUtc().toIso8601String(),
            }
          : null,
    ),
  );

  @override
  Future<void> updateLocation({
    required DriverSession session,
    required double latitude,
    required double longitude,
    required double accuracy,
  }) async {
    await _resource(
      session,
      'PUT',
      '/driver/location',
      body: {
        'latitude': latitude,
        'longitude': longitude,
        'accuracy': accuracy,
        'captured_at': DateTime.now().toUtc().toIso8601String(),
      },
    );
  }

  @override
  Future<String> uploadEvidence({
    required DriverSession session,
    required String serviceRequestId,
    required String evidenceType,
    required String name,
    required Uint8List bytes,
    required double latitude,
    required double longitude,
  }) async {
    final data = await _upload(
      session,
      '/driver/service-requests/$serviceRequestId/evidence',
      name,
      bytes,
      {
        'evidence_type': evidenceType,
        'latitude': '$latitude',
        'longitude': '$longitude',
      },
    );
    return data['id'] as String;
  }

  @override
  Future<void> createBankAccount({
    required DriverSession session,
    required String bankCode,
    required String accountNumber,
    required String accountName,
  }) async {
    await _resource(
      session,
      'POST',
      '/driver/bank-accounts',
      body: {
        'bank_code': bankCode,
        'account_number': accountNumber,
        'account_name': accountName,
      },
    );
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
  Future<List<DriverJobSummary>> loadJobHistory(DriverSession session) async {
    final response = await _transport.send(
      method: 'GET',
      uri: _uri(session, '/driver/history'),
      token: session.token,
    );
    _assertSuccess(response);
    return _listData(response)
        .map(DriverJobSummary.fromJson)
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
    String? evidenceId,
    String? outOfGeofenceReason,
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
        'evidence_id': ?evidenceId,
        'out_of_geofence_reason': ?outOfGeofenceReason,
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

  @override
  Future<List<DriverChatMessage>> loadChat(
    DriverSession session,
    String serviceRequestId,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: Uri.parse(
        '${_base(session.baseUrl)}/service-requests/$serviceRequestId/chat',
      ),
      token: session.token,
    );
    _assertSuccess(response);
    return _listData(response)
        .map(DriverChatMessage.fromJson)
        .toList(growable: false);
  }

  @override
  Future<void> sendChat({
    required DriverSession session,
    required String serviceRequestId,
    required String body,
  }) async {
    await _sendRetryable(
      session: session,
      path: '/service-requests/$serviceRequestId/chat',
      body: {'body': body},
      chat: true,
    );
  }

  @override
  Future<void> createSupportTicket({
    required DriverSession session,
    required String serviceRequestId,
    required String subject,
    required String description,
  }) async {
    await _sendRetryable(
      session: session,
      path: '/support/tickets',
      body: {
        'service_request_id': serviceRequestId,
        'category': 'OTHER',
        'subject': subject,
        'description': description,
      },
    );
  }

  @override
  Future<void> reportIncident({
    required DriverSession session,
    required String serviceRequestId,
    required String incidentType,
    String? description,
  }) async {
    await _sendRetryable(
      session: session,
      path: '/service-requests/$serviceRequestId/incidents',
      body: {
        'incident_type': incidentType,
        if (description?.trim().isNotEmpty ?? false)
          'description': description!.trim(),
      },
    );
  }

  @override
  Future<void> submitRating({
    required DriverSession session,
    required String serviceRequestId,
    required int score,
    String? comment,
  }) async {
    final response = await _transport.send(
      method: 'POST',
      uri: Uri.parse(
        '${_base(session.baseUrl)}/service-requests/$serviceRequestId/ratings',
      ),
      token: session.token,
      body: {
        'score': score,
        if (comment?.trim().isNotEmpty ?? false) 'comment': comment!.trim(),
      },
    );
    _assertSuccess(response);
  }

  @override
  Future<List<DriverNotificationSummary>> loadNotifications(
    DriverSession session,
  ) async {
    final response = await _transport.send(
      method: 'GET',
      uri: Uri.parse('${_base(session.baseUrl)}/notifications'),
      token: session.token,
    );
    _assertSuccess(response);
    return _listData(response)
        .map(DriverNotificationSummary.fromJson)
        .toList(growable: false);
  }

  @override
  Future<void> markNotificationRead(
    DriverSession session,
    String notificationId,
  ) async {
    final response = await _transport.send(
      method: 'PUT',
      uri: Uri.parse(
        '${_base(session.baseUrl)}/notifications/$notificationId/read',
      ),
      token: session.token,
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
          throw DriverApiException(
            value.first.toString(),
            statusCode: response.statusCode,
          );
        }
      }
    }
    throw DriverApiException(
      response.body['message']?.toString() ?? 'Không thể hoàn tất yêu cầu.',
      statusCode: response.statusCode,
    );
  }

  List<Map<String, dynamic>> _listData(ApiResponse response) {
    final data = response.body['data'];
    if (data is! List) {
      throw const DriverApiException('Phản hồi từ máy chủ không hợp lệ.');
    }
    return data.whereType<Map<String, dynamic>>().toList(growable: false);
  }
}

class DriverApiException implements Exception {
  const DriverApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
