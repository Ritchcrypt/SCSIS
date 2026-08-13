import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Announcements full parity screen is wired', () {
    final home = File('lib/screens/home_screen.dart').readAsStringSync();

    final screen = File(
      'lib/screens/announcements_screen.dart',
    ).readAsStringSync();

    final form = File(
      'lib/screens/announcement_create_screen.dart',
    ).readAsStringSync();

    for (final marker in <String>[
      "import 'announcements_screen.dart';",
      'AnnouncementsScreen(',
      'GlobalThemeButton(',
      'GlobalNotificationBell(',
    ]) {
      expect(home, contains(marker));
    }

    for (final marker in <String>[
      'Community Announcements',
      'Advisories and emergency notifications',
      'Post Announcement',
      'Weather Feed',
      'Inactive',
      'Calamity Mode',
      'Deactivate',
      'Activate',
      'Delete',
      'Load More',
    ]) {
      expect(screen, contains(marker));
    }

    for (final marker in <String>[
      'Title *',
      'Content *',
      'Category',
      'Priority',
      'Audience',
      'Activate Calamity Mode',
      'Show in Weather & Disaster Feed',
      'Post Announcement',
    ]) {
      expect(form, contains(marker));
    }
  });

  test('Emergency Hotlines full parity screen is wired', () {
    final home = File('lib/screens/home_screen.dart').readAsStringSync();

    final screen = File(
      'lib/screens/emergency_hotlines_screen.dart',
    ).readAsStringSync();

    for (final marker in <String>[
      "import 'emergency_hotlines_screen.dart';",
      'EmergencyHotlinesScreen(',
    ]) {
      expect(home, contains(marker));
    }

    for (final marker in <String>[
      'Emergency Hotlines',
      'Emergency contact numbers for immediate reference.',
      'Use these hotline numbers only for emergencies',
      'Add Hotline',
      'Agency / Office Name',
      'Hotline Number',
      'Card Color',
      'Remove hotline',
    ]) {
      expect(screen, contains(marker));
    }
  });

  test('Services expose management endpoints', () {
    final announcements = File(
      'lib/services/announcement_service.dart',
    ).readAsStringSync();

    final hotlines = File(
      'lib/services/emergency_hotline_service.dart',
    ).readAsStringSync();

    expect(announcements, contains('/api/v1/announcements'));
    expect(announcements, contains('/toggle'));
    expect(announcements, contains('activate_calamity_mode'));
    expect(announcements, contains('show_in_weather_feed'));

    expect(hotlines, contains('/api/v1/emergency-hotlines'));
    expect(hotlines, contains('agency_name'));
    expect(hotlines, contains('hotline_number'));
  });

  test('Development bypass is not replaced with login UI', () {
    final gate = File('lib/screens/dev_session_gate.dart');

    if (!gate.existsSync()) {
      return;
    }

    final text = gate.readAsStringSync();

    expect(text, isNot(contains('return const LoginScreen()')));

    expect(text, isNot(contains('Open Login')));
  });
}
