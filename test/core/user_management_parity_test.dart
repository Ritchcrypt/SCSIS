import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('User Management is live in ModuleRegistry and HomeScreen', () {
    final registry = File('lib/core/module_registry.dart').readAsStringSync();

    final start = registry.indexOf('id: AppModuleId.userManagement,');

    expect(start, greaterThanOrEqualTo(0));

    final next = registry.indexOf('AppModuleDefinition(', start + 1);

    final block = registry.substring(start, next >= 0 ? next : registry.length);

    expect(block, contains('mobileImplemented: true'));

    final home = File('lib/screens/home_screen.dart').readAsStringSync();

    for (final marker in <String>[
      "import 'user_management_screen.dart';",
      '_HomeModule.userManagement',
      'AppModuleId.userManagement => _HomeModule.userManagement,',
      '_HomeModule.userManagement => AppModuleId.userManagement,',
      'return UserManagementScreen(',
      'GlobalThemeButton(',
      'GlobalNotificationBell(',
      'ModuleRegistry.canAccess(',
      'scaffoldState.isDrawerOpen',
    ]) {
      expect(
        home,
        contains(marker),
        reason: 'Missing User Management/global marker: $marker',
      );
    }

    expect(home, isNot(contains("tooltip: 'Refresh'")));
  });

  test('User Management remains Admin-only in Flutter capability matrix', () {
    final capabilities = File(
      'lib/core/app_capabilities.dart',
    ).readAsStringSync();

    final adminStart = capabilities.indexOf('case AppRole.admin:');

    final officialStart = capabilities.indexOf('case AppRole.official:');

    final tanodStart = capabilities.indexOf('case AppRole.tanod:');

    final residentStart = capabilities.indexOf('case AppRole.resident:');

    expect(adminStart, greaterThanOrEqualTo(0));

    expect(officialStart, greaterThan(adminStart));

    expect(
      capabilities.substring(adminStart, officialStart),
      contains('AppCapability.manageUsers'),
    );

    expect(
      capabilities.substring(officialStart, tanodStart),
      isNot(contains('AppCapability.manageUsers')),
    );

    expect(
      capabilities.substring(tanodStart, residentStart),
      isNot(contains('AppCapability.manageUsers')),
    );
  });

  test(
    'User Management list replicates summary filters pagination and CSV export',
    () {
      final screen = File(
        'lib/screens/user_management_screen.dart',
      ).readAsStringSync();

      for (final marker in <String>[
        "'Users'",
        'Manage admin, official, tanod, and resident accounts.',
        'Total Users',
        'Online',
        'Offline',
        'Staff',
        'Residents',
        'Search name, email, contact, address...',
        'All Roles',
        'All Presence',
        'All Dates',
        'Rows per page',
        'Add User',
        "'Export'",
        'TabangNow Exports',
        'OpenFilex.open',
        'No users found',
        r'Page $page of',
      ]) {
        expect(
          screen,
          contains(marker),
          reason: 'Missing User Management list marker: $marker',
        );
      }
    },
  );

  test('User form preserves hardened website account workflow', () {
    final form = File(
      'lib/screens/user_management_form_screen.dart',
    ).readAsStringSync();

    for (final marker in <String>[
      'Add User',
      'Edit User',
      'Profile Picture',
      'Maximum size: 5 MB',
      'Full Name *',
      'Email *',
      'Contact Number',
      'Address',
      'Barangay',
      'Role *',
      'Status:',
      'Approved and allowed to access the system.',
      'Initial Password *',
      'At least 12 characters with uppercase, lowercase, number, and symbol.',
      'Create User',
      'Save Changes',
      'Tanod account synchronization',
      'Tanod Roster placeholder',
    ]) {
      expect(
        form,
        contains(marker),
        reason: 'Missing hardened user form marker: $marker',
      );
    }

    expect(form, isNot(contains('Temporary Password')));

    expect(form, isNot(contains('Reset Password to')));
  });

  test('User detail exposes secure dedicated account actions', () {
    final detail = File(
      'lib/screens/user_management_detail_screen.dart',
    ).readAsStringSync();

    for (final marker in <String>[
      'User Details',
      'Account Information',
      'Employee Profile',
      'Account Actions',
      'Edit User',
      'Activate Account',
      'Deactivate Account',
      'Send Password Reset Link',
      'Permanent Delete',
      'Existing sessions and mobile tokens will be revoked.',
      'final active administrator',
      'Deleted User placeholder',
    ]) {
      expect(
        detail,
        contains(marker),
        reason: 'Missing User Management detail marker: $marker',
      );
    }
  });

  test(
    'User service includes full account lifecycle endpoints and private files',
    () {
      final service = File(
        'lib/services/user_management_service.dart',
      ).readAsStringSync();

      for (final marker in <String>[
        '/api/v1/users',
        '/update',
        '/activate',
        '/deactivate',
        '/reset-password',
        '/profile-photo',
        '/export',
        'MultipartRequest',
        'DownloadedUserExport',
        'response.bodyBytes',
      ]) {
        expect(
          service,
          contains(marker),
          reason: 'Missing User Management service marker: $marker',
        );
      }
    },
  );

  test('Temporary development login bypass remains active', () {
    final gate = File('lib/screens/dev_session_gate.dart');

    if (!gate.existsSync()) {
      return;
    }

    final text = gate.readAsStringSync();

    expect(text, isNot(contains('return const LoginScreen()')));

    expect(text, isNot(contains('Open Login')));
  });
}
