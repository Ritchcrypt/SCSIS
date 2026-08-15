import 'dart:convert';

import 'package:http/http.dart' as http;

class RegistrationException implements Exception {
  const RegistrationException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class RegistrationService {
  RegistrationService({http.Client? client}) : _client = client ?? http.Client();

  static const String _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8001',
  );

  final http.Client _client;

  Future<Map<String, dynamic>> registerResident({
    required String name,
    required String email,
    required String contactNumber,
    required String address,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/v1/auth/register'),
      headers: const <String, String>{
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode(<String, dynamic>{
        'name': name.trim(),
        'email': email.trim(),
        'contact_number': contactNumber.trim(),
        'address': address.trim(),
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    final data = _decode(response);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    throw RegistrationException(
      _errorMessage(data),
      statusCode: response.statusCode,
    );
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.body.trim().isEmpty) {
      return <String, dynamic>{};
    }

    try {
      final decoded = jsonDecode(response.body);
      return decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{};
    } on FormatException {
      throw RegistrationException(
        'The server returned an invalid response.',
        statusCode: response.statusCode,
      );
    }
  }

  String _errorMessage(Map<String, dynamic> data) {
    final message = data['message'];
    if (message is String && message.trim().isNotEmpty) {
      return message.trim();
    }

    final errors = data['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
        if (value is String && value.trim().isNotEmpty) {
          return value.trim();
        }
      }
    }

    return 'Unable to create the resident account.';
  }
}
