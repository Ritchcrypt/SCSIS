import 'dart:convert';

import 'package:http/http.dart' as http;

import 'auth_service.dart';

class EmergencyHotlineService {
  EmergencyHotlineService({required this.authService, http.Client? client})
    : _client = client ?? http.Client();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  final AuthService authService;
  final http.Client _client;

  Future<Map<String, dynamic>> index() async {
    return _request(
      () async => _client.get(
        Uri.parse('$baseUrl/api/v1/emergency-hotlines'),
        headers: await _headers(),
      ),
    );
  }

  Future<Map<String, dynamic>> create({
    required String agencyName,
    required String hotlineNumber,
    required String color,
  }) async {
    return _request(
      () async => _client.post(
        Uri.parse('$baseUrl/api/v1/emergency-hotlines'),
        headers: await _jsonHeaders(),
        body: jsonEncode(<String, dynamic>{
          'agency_name': agencyName.trim(),
          'hotline_number': hotlineNumber.trim(),
          'color': color,
        }),
      ),
    );
  }

  Future<Map<String, dynamic>> delete(int hotlineId) async {
    return _request(
      () async => _client.delete(
        Uri.parse('$baseUrl/api/v1/emergency-hotlines/$hotlineId'),
        headers: await _headers(),
      ),
    );
  }

  Future<Map<String, String>> _jsonHeaders() async {
    return <String, String>{
      ...await _headers(),
      'Content-Type': 'application/json',
    };
  }

  Future<Map<String, String>> _headers() async {
    final token = await authService.getToken();

    if (token == null || token.isEmpty) {
      throw const AuthException(
        'No authenticated session was found.',
        statusCode: 401,
      );
    }

    return <String, String>{
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    };
  }

  Future<Map<String, dynamic>> _request(
    Future<http.Response> Function() request,
  ) async {
    final response = await request();
    final data = _decode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    if (response.statusCode == 401) {
      await authService.clearToken();
    }

    throw AuthException(_message(data), statusCode: response.statusCode);
  }

  Map<String, dynamic> _decode(String body) {
    if (body.trim().isEmpty) {
      return <String, dynamic>{};
    }

    final decoded = jsonDecode(body);

    if (decoded is Map<String, dynamic>) {
      return decoded;
    }

    if (decoded is Map) {
      return Map<String, dynamic>.from(decoded);
    }

    return <String, dynamic>{};
  }

  String _message(Map<String, dynamic> data) {
    final errors = data['errors'];

    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }

        final text = value?.toString().trim() ?? '';

        if (text.isNotEmpty) {
          return text;
        }
      }
    }

    final message = data['message']?.toString().trim();

    return message != null && message.isNotEmpty
        ? message
        : 'The emergency hotline request could not be completed.';
  }
}
