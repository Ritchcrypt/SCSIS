import 'dart:typed_data';

import 'package:http/http.dart' as http;

class PublicBrandingLogoService {
  PublicBrandingLogoService({http.Client? client})
    : _client = client ?? http.Client();

  static const String _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  final http.Client _client;

  Future<Uint8List?> fetchLogoBytes() async {
    final uri = Uri.parse('$_baseUrl/system-branding/logo').replace(
      queryParameters: <String, String>{
        'mobile_v': DateTime.now().millisecondsSinceEpoch.toString(),
      },
    );

    final response = await _client.get(
      uri,
      headers: const <String, String>{
        'Accept': 'image/png,image/jpeg,image/webp,image/*;q=0.8,*/*;q=0.1',
        'Cache-Control': 'no-cache',
      },
    );

    if (response.statusCode == 404) {
      return null;
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw PublicBrandingLogoException(
        'Unable to load the TabangNow system logo.',
        statusCode: response.statusCode,
      );
    }

    final bytes = response.bodyBytes;

    if (bytes.isEmpty) {
      return null;
    }

    if (bytes.length >= 3 &&
        bytes[0] == 0xEF &&
        bytes[1] == 0xBB &&
        bytes[2] == 0xBF) {
      return Uint8List.fromList(bytes.sublist(3));
    }

    return Uint8List.fromList(bytes);
  }
}

class PublicBrandingLogoException implements Exception {
  const PublicBrandingLogoException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
