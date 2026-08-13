import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class AuthService {
  AuthService({http.Client? client, FlutterSecureStorage? storage})
    : _client = client ?? http.Client(),
      _storage = storage ?? const FlutterSecureStorage();

  static const String _tokenKey = 'tabangnow_access_token';

  static const String _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8001',
  );

  final http.Client _client;
  final FlutterSecureStorage _storage;

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/v1/auth/login'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'email': email.trim(),
        'password': password,
        'device_name': deviceName.trim(),
      }),
    );

    final data = _decodeResponse(response);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      final token = data['access_token'];

      if (token is! String || token.isEmpty) {
        throw const AuthException('The server did not return an access token.');
      }

      await _storage.write(key: _tokenKey, value: token);

      return data;
    }

    throw AuthException(
      _extractErrorMessage(data),
      statusCode: response.statusCode,
    );
  }

  Future<Map<String, dynamic>> me() async {
    final token = await getToken();

    if (token == null || token.isEmpty) {
      throw const AuthException(
        'No authenticated session was found.',
        statusCode: 401,
      );
    }

    final response = await _client.get(
      Uri.parse('$_baseUrl/api/v1/auth/me'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final data = _decodeResponse(response);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    if (response.statusCode == 401 || response.statusCode == 403) {
      await clearToken();
    }

    throw AuthException(
      _extractErrorMessage(data),
      statusCode: response.statusCode,
    );
  }

  Future<Map<String, dynamic>> dashboard() {
    return _authorizedGet('/api/v1/dashboard');
  }

  Future<Map<String, dynamic>> announcements() {
    return _authorizedGet('/api/v1/announcements');
  }

  Future<Map<String, dynamic>> emergencyHotlines() {
    return _authorizedGet('/api/v1/emergency-hotlines');
  }

  Future<Map<String, dynamic>> _authorizedGet(String path) async {
    final token = await getToken();

    if (token == null || token.isEmpty) {
      throw const AuthException(
        'No authenticated session was found.',
        statusCode: 401,
      );
    }

    final response = await _client.get(
      Uri.parse('$_baseUrl$path'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final data = _decodeResponse(response);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    if (response.statusCode == 401 || response.statusCode == 403) {
      await clearToken();
    }

    throw AuthException(
      _extractErrorMessage(data),
      statusCode: response.statusCode,
    );
  }

  Future<void> logout() async {
    final token = await getToken();

    if (token == null || token.isEmpty) {
      await clearToken();
      return;
    }

    try {
      final response = await _client.post(
        Uri.parse('$_baseUrl/api/v1/auth/logout'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode >= 400 && response.statusCode != 401) {
        final data = _decodeResponse(response);

        throw AuthException(
          _extractErrorMessage(data),
          statusCode: response.statusCode,
        );
      }
    } finally {
      await clearToken();
    }
  }

  Future<String?> getToken() {
    return _storage.read(key: _tokenKey);
  }

  Future<bool> hasToken() async {
    final token = await getToken();

    return token != null && token.isNotEmpty;
  }

  Future<void> clearToken() {
    return _storage.delete(key: _tokenKey);
  }

  Map<String, dynamic> _decodeResponse(http.Response response) {
    if (response.body.trim().isEmpty) {
      return <String, dynamic>{};
    }

    try {
      final decoded = jsonDecode(response.body);

      if (decoded is Map<String, dynamic>) {
        return decoded;
      }

      return <String, dynamic>{};
    } on FormatException {
      throw AuthException(
        'The server returned an invalid response.',
        statusCode: response.statusCode,
      );
    }
  }

  String _extractErrorMessage(Map<String, dynamic> data) {
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

        if (value is String && value.isNotEmpty) {
          return value;
        }
      }
    }

    return 'Unable to complete the request.';
  }
}

class AuthException implements Exception {
  const AuthException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
