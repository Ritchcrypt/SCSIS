import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('full self-profile UI contains website-parity security actions', () {
    final source = File(
      'lib/screens/current_account_profile_screen.dart',
    ).readAsStringSync();

    expect(source, contains('Sign Out Other Devices'));
    expect(source, contains('Reset Password'));
    expect(source, contains('Permanent Delete'));
    expect(source, contains('Delete My Account Permanently'));
    expect(source, contains('Adjust Profile Photo'));
    expect(source, contains("label: 'Status'"));
    expect(source, contains("label: 'Role'"));
  });

  test('profile service exposes all self-profile API actions', () {
    final source = File('lib/services/profile_service.dart').readAsStringSync();

    expect(source, contains('/api/v1/profile/update'));
    expect(source, contains('/api/v1/profile/photo'));
    expect(source, contains('/api/v1/profile/password'));
    expect(source, contains('/api/v1/profile/other-sessions'));
    expect(source, contains('/api/v1/profile/self-delete'));
  });

  test('Admin profile button no longer opens User Management details', () {
    final source = File('lib/screens/home_screen.dart').readAsStringSync();
    final start = source.indexOf('Future<void> _openAccountProfile()');
    final end = source.indexOf('Future<void> _openGlobalNotification', start);
    final method = source.substring(start, end);

    expect(method, contains('CurrentAccountProfileScreen'));
    expect(method, isNot(contains('UserManagementDetailScreen')));
    expect(method, isNot(contains('_appRole == AppRole.admin')));
  });

  test('current account footer uses self-profile photo service', () {
    final source = File(
      'lib/widgets/global_account_footer.dart',
    ).readAsStringSync();
    expect(source, contains('ProfileService'));
    expect(source, contains('profile_photo_version'));
  });
}
