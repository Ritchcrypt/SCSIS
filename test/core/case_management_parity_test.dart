import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('development boot has no active LoginScreen fallback', () {
    final gate = File('lib/screens/dev_session_gate.dart').readAsStringSync();

    final auth = File('lib/services/auth_service.dart').readAsStringSync();

    expect(gate, contains('_authService.devSession()'));
    expect(gate, contains('_restoreOrCreateDevelopmentSession'));
    expect(gate, isNot(contains('LoginScreen')));
    expect(gate, isNot(contains('Open Login')));
    expect(auth, contains('/api/v1/auth/dev-session'));
  });

  test('Case Management is live in global navigation', () {
    final registry = File('lib/core/module_registry.dart').readAsStringSync();

    final home = File('lib/screens/home_screen.dart').readAsStringSync();

    final start = registry.indexOf('id: AppModuleId.caseManagement,');

    expect(start, greaterThanOrEqualTo(0));

    final next = registry.indexOf('AppModuleDefinition(', start + 1);

    final block = registry.substring(start, next >= 0 ? next : registry.length);

    expect(block, contains('mobileImplemented: true'));

    expect(home, contains("import 'case_management_screen.dart';"));
    expect(home, contains('_HomeModule.caseManagement'));
    expect(home, contains('CaseManagementScreen('));
  });

  test('Case Management mirrors website fields and actions', () {
    final form = File('lib/screens/case_form_screen.dart').readAsStringSync();

    final screen = File(
      'lib/screens/case_management_screen.dart',
    ).readAsStringSync();

    for (final expected in <String>[
      'Case Number',
      'Case Type *',
      'Subject Name *',
      'Contact',
      'Address',
      'Related Incident',
      'Incident Title',
      'Status',
      'Hearing Date',
      'Handled By',
      'Resolution',
      'Notes',
      'AUTO-GENERATED',
      'Save Case',
      'Update Case',
    ]) {
      expect(
        form,
        contains(expected),
        reason: 'missing case form field: $expected',
      );
    }

    for (final expected in <String>[
      'Case Management',
      'Barangay blotter and case files',
      'New Case',
      'Search cases...',
      'CASE NO.',
      'Type',
      'Incident',
      'Hearing',
      'Handled By',
      'Edit case',
      'Delete case',
      'Load More',
    ]) {
      expect(
        screen,
        contains(expected),
        reason: 'missing case list element: $expected',
      );
    }
  });

  test('Tanod Task response service matches actual API route', () {
    final service = File(
      'lib/services/tanod_task_service.dart',
    ).readAsStringSync();

    expect(service, contains('/api/v1/tanod-tasks/responses/'));

    expect(service, isNot(contains('/api/v1/tanod-task-responses/')));
  });
}
