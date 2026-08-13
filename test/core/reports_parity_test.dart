import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Reports is live in ModuleRegistry and HomeScreen', () {
    final registry = File('lib/core/module_registry.dart').readAsStringSync();

    final reportsStart = registry.indexOf('id: AppModuleId.reports,');

    expect(reportsStart, greaterThanOrEqualTo(0));

    final next = registry.indexOf('AppModuleDefinition(', reportsStart + 1);

    final block = registry.substring(
      reportsStart,
      next >= 0 ? next : registry.length,
    );

    expect(block, contains('mobileImplemented: true'));

    final home = File('lib/screens/home_screen.dart').readAsStringSync();

    for (final marker in <String>[
      "import 'reports_screen.dart';",
      '_HomeModule.reports',
      'AppModuleId.reports => _HomeModule.reports,',
      '_HomeModule.reports => AppModuleId.reports,',
      'return ReportsScreen(',
      'GlobalThemeButton(',
      'GlobalNotificationBell(',
      'ModuleRegistry.canAccess(',
      'scaffoldState.isDrawerOpen',
    ]) {
      expect(
        home,
        contains(marker),
        reason: 'Missing Reports/global marker: $marker',
      );
    }

    expect(home, isNot(contains("tooltip: 'Refresh'")));
  });

  test('Reports screen replicates website report UI', () {
    final screen = File('lib/screens/reports_screen.dart').readAsStringSync();

    for (final marker in <String>[
      "'Reports'",
      'Dao, Capiz —',
      'Today',
      'This Week',
      'This Month',
      'This Year',
      'Total Incidents',
      'Active / Pending',
      'Resolved',
      'Cases Filed',
      'Records Breakdown',
      'Tanod Response Summary',
      'Incident PDF',
      'Case PDF',
      'Complaint PDF',
      'Download PDF',
      'Generate PDF',
      'Show All',
      'Show Less',
    ]) {
      expect(
        screen,
        contains(marker),
        reason: 'Missing report UI marker: $marker',
      );
    }
  });

  test('ReportService exposes all authoritative PDF endpoints', () {
    final service = File('lib/services/report_service.dart').readAsStringSync();

    for (final marker in <String>[
      '/api/v1/reports',
      '/api/v1/reports/pdf',
      '/api/v1/reports/incidents/',
      '/api/v1/reports/cases/',
      '/api/v1/reports/complaints/',
      'DownloadedReportPdf',
      'content-disposition',
      'response.bodyBytes',
    ]) {
      expect(
        service,
        contains(marker),
        reason: 'Missing ReportService marker: $marker',
      );
    }
  });

  test('PDF workflow persists and opens the generated server PDF', () {
    final screen = File('lib/screens/reports_screen.dart').readAsStringSync();

    for (final marker in <String>[
      'getApplicationDocumentsDirectory',
      'TabangNow Reports',
      'writeAsBytes',
      'OpenFilex.open',
      'Print/Share menu',
    ]) {
      expect(
        screen,
        contains(marker),
        reason: 'Missing PDF workflow marker: $marker',
      );
    }
  });

  test('Reports remains Admin-only in Flutter capabilities', () {
    final capabilities = File(
      'lib/core/app_capabilities.dart',
    ).readAsStringSync();

    expect(capabilities, contains('AppCapability.viewReports'));

    final officialStart = capabilities.indexOf('case AppRole.official:');

    final tanodStart = capabilities.indexOf('case AppRole.tanod:');

    final adminStart = capabilities.indexOf('case AppRole.admin:');

    expect(adminStart, greaterThanOrEqualTo(0));

    expect(officialStart, greaterThan(adminStart));

    final adminBlock = capabilities.substring(adminStart, officialStart);

    expect(adminBlock, contains('AppCapability.viewReports'));

    final officialBlock = capabilities.substring(officialStart, tanodStart);

    expect(officialBlock, isNot(contains('AppCapability.viewReports')));
  });

  test('Temporary development login bypass remains intact', () {
    final gate = File('lib/screens/dev_session_gate.dart');

    if (!gate.existsSync()) {
      return;
    }

    final text = gate.readAsStringSync();

    expect(text, isNot(contains('return const LoginScreen()')));

    expect(text, isNot(contains('Open Login')));
  });
}
