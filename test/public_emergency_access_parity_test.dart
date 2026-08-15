import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('public auth screens expose SOS without authentication', () {
    final authGate = File('lib/screens/auth_gate.dart').readAsStringSync();
    final register = File(
      'lib/screens/register_screen.dart',
    ).readAsStringSync();
    final card = File(
      'lib/widgets/public_emergency_access_card.dart',
    ).readAsStringSync();

    expect(authGate, contains('PublicEmergencyAccessCard(compact: true)'));
    expect(register, contains('PublicEmergencyAccessCard(compact: true)'));

    expect(card, contains('no account or login required'));
    expect(card, contains('Open Emergency SOS'));
    expect(card, contains('GlobalSosOverlay.open(context)'));
  });

  test('SOS light semantic cards retain readable text in dark mode', () {
    final overlay = File(
      'lib/widgets/global_sos_overlay.dart',
    ).readAsStringSync();

    for (final marker in <String>[
      'Color(0xFF7C2D12)',
      'Color(0xFF065F46)',
      'Color(0xFF047857)',
      'Color(0xFF991B1B)',
      'Color(0xFFB91C1C)',
      'foregroundColor: theme.colorScheme.onSurfaceVariant',
    ]) {
      expect(
        overlay,
        contains(marker),
        reason: 'Missing SOS readability marker: $marker',
      );
    }
  });

  test('public branding fallback stays on the unified API port', () {
    final service = File(
      'lib/services/public_branding_logo_service.dart',
    ).readAsStringSync();

    expect(service, isNot(contains('127.0.0.1:8001')));
    expect(service, contains('127.0.0.1:8000'));
  });
}
