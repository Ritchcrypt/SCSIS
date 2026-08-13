import 'dart:convert';

import 'package:http/http.dart' as http;

import 'auth_service.dart';

class BarangayMapService {
  BarangayMapService({required this.authService, http.Client? client})
    : _client = client ?? http.Client();

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8001',
  );

  final AuthService authService;
  final http.Client _client;

  Future<Map<String, dynamic>> index() async {
    final token = await authService.getToken();

    if (token == null || token.isEmpty) {
      throw const AuthException(
        'No authenticated development session was found.',
        statusCode: 401,
      );
    }

    final response = await _client.get(
      Uri.parse('$baseUrl/api/v1/barangay-map'),
      headers: <String, String>{
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );

    final data = _decode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    if (response.statusCode == 401) {
      await authService.clearToken();
    }

    throw AuthException(
      data['message']?.toString().trim() ?? 'Unable to load the Barangay Map.',
      statusCode: response.statusCode,
    );
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
}
