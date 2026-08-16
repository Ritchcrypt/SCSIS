import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('AuthException provides a safe public network message', () {
    final source = File('lib/services/auth_service.dart').readAsStringSync();

    expect(source, contains('String get userMessage'));
    expect(source, contains("'127.0.0.1'"));
    expect(source, contains("'connection refused'"));
    expect(source, contains("'adb reverse'"));
    expect(
      source,
      contains("'Unable to connect to TabangNow right now. Please try again.'"),
    );
  });

  test('public auth screens do not render raw AuthException.message', () {
    for (final path in <String>[
      'lib/screens/auth_gate.dart',
      'lib/screens/login_screen.dart',
      'lib/screens/register_screen.dart',
    ]) {
      final source = File(path).readAsStringSync();

      expect(
        source,
        isNot(contains('exception.message')),
        reason: '$path must use AuthException.userMessage.',
      );

      expect(
        source,
        isNot(contains('Unable to reach the TabangNow server at')),
        reason: '$path must not expose the development endpoint.',
      );

      expect(
        source,
        isNot(contains('adb reverse tcp:8000 tcp:8000')),
        reason: '$path must not expose developer instructions.',
      );
    }
  });

  test('one-command Android development launcher is present', () {
    final source = File('tool/run_android_dev.ps1').readAsStringSync();

    expect(source, contains('php artisan serve --host=127.0.0.1'));
    expect(source, contains('adb reverse'));
    expect(source, contains('API_BASE_URL=http://127.0.0.1'));
    expect(source, contains('flutter run'));
  });
}
