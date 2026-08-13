import 'dart:io';

void main() {
  const homePath =
      'lib/screens/home_screen.dart';
  const registryPath =
      'lib/core/module_registry.dart';

  final homeFile = File(homePath);
  final registryFile = File(registryPath);

  if (!homeFile.existsSync()) {
    throw StateError(
      'HomeScreen not found: $homePath',
    );
  }

  if (!registryFile.existsSync()) {
    throw StateError(
      'ModuleRegistry not found: $registryPath',
    );
  }

  var home = homeFile.readAsStringSync();
  var registry =
      registryFile.readAsStringSync();

  for (final marker in <String>[
    'GlobalThemeButton(',
    'GlobalNotificationBell(',
    'ModuleRegistry.forRole(_appRole)',
    'scaffoldState.isDrawerOpen',
    'TanodAlertsScreen(',
  ]) {
    if (!home.contains(marker)) {
      throw StateError(
        'Established HomeScreen marker missing: $marker',
      );
    }
  }

  if (home.contains("tooltip: 'Refresh'")) {
    throw StateError(
      'The retired global Refresh header control is present.',
    );
  }

  if (!File(
    'lib/core/tabangnow_theme.dart',
  ).existsSync()) {
    throw StateError(
      'Global theme-surface parity is missing. Install the approved global theme fix first.',
    );
  }

  // -------------------------------------------------------------------------
  // ModuleRegistry: only flip the existing Tanod Roster definition to live.
  // Access rules remain in AppCapabilities/ModuleRegistry.canAccess.
  // -------------------------------------------------------------------------
  final rosterIdIndex = registry.indexOf(
    'id: AppModuleId.tanodRoster,',
  );

  if (rosterIdIndex < 0) {
    throw StateError(
      'Tanod Roster definition not found in ModuleRegistry.',
    );
  }

  final nextDefinition = registry.indexOf(
    'AppModuleDefinition(',
    rosterIdIndex + 1,
  );

  final rosterEnd = nextDefinition >= 0
      ? nextDefinition
      : registry.length;

  var rosterBlock = registry.substring(
    rosterIdIndex,
    rosterEnd,
  );

  if (!rosterBlock.contains(
    'mobileImplemented: true',
  )) {
    if (!rosterBlock.contains(
      'mobileImplemented: false',
    )) {
      throw StateError(
        'Tanod Roster mobileImplemented state was not found.',
      );
    }

    rosterBlock = rosterBlock.replaceFirst(
      'mobileImplemented: false',
      'mobileImplemented: true',
    );

    registry = registry.replaceRange(
      rosterIdIndex,
      rosterEnd,
      rosterBlock,
    );
  }

  // -------------------------------------------------------------------------
  // HomeScreen import.
  // -------------------------------------------------------------------------
  const rosterImport =
      "import 'tanod_roster_screen.dart';";

  if (!home.contains(rosterImport)) {
    const alertsImport =
        "import 'tanod_alerts_screen.dart';";

    if (home.contains(alertsImport)) {
      home = home.replaceFirst(
        alertsImport,
        '$alertsImport\n$rosterImport',
      );
    } else {
      const materialImport =
          "import 'package:flutter/material.dart';";

      home = home.replaceFirst(
        materialImport,
        '$materialImport\n\n$rosterImport',
      );
    }
  }

  // -------------------------------------------------------------------------
  // _HomeModule enum.
  // -------------------------------------------------------------------------
  final enumMatch = RegExp(
    r'enum\s+_HomeModule\s*\{([^}]*)\}',
    multiLine: true,
  ).firstMatch(home);

  if (enumMatch == null) {
    throw StateError(
      '_HomeModule enum was not found.',
    );
  }

  final enumEntries = enumMatch
      .group(1)!
      .split(',')
      .map((entry) => entry.trim())
      .where((entry) => entry.isNotEmpty)
      .toList();

  if (!enumEntries.contains('tanodRoster')) {
    final insertAfter =
        enumEntries.indexOf('tanodAlerts');

    if (insertAfter >= 0) {
      enumEntries.insert(
        insertAfter + 1,
        'tanodRoster',
      );
    } else {
      enumEntries.add('tanodRoster');
    }

    final replacement = StringBuffer()
      ..writeln('enum _HomeModule {');

    for (final entry in enumEntries) {
      replacement.writeln('  $entry,');
    }

    replacement.write('}');

    home = home.replaceRange(
      enumMatch.start,
      enumMatch.end,
      replacement.toString(),
    );
  }

  // -------------------------------------------------------------------------
  // AppModuleId -> _HomeModule mapping.
  // -------------------------------------------------------------------------
  if (!home.contains(
    'AppModuleId.tanodRoster =>\n          _HomeModule.tanodRoster,',
  )) {
    const anchor =
        '        AppModuleId.announcements =>\n'
        '          _HomeModule.announcements,';

    if (!home.contains(anchor)) {
      throw StateError(
        '_homeModuleFor announcements anchor not found.',
      );
    }

    home = home.replaceFirst(
      anchor,
      '        AppModuleId.tanodRoster =>\n'
      '          _HomeModule.tanodRoster,\n'
      '$anchor',
    );
  }

  // -------------------------------------------------------------------------
  // _HomeModule -> AppModuleId mapping.
  // -------------------------------------------------------------------------
  if (!home.contains(
    '_HomeModule.tanodRoster =>\n          AppModuleId.tanodRoster,',
  )) {
    const anchor =
        '        _HomeModule.announcements =>\n'
        '          AppModuleId.announcements,';

    if (!home.contains(anchor)) {
      throw StateError(
        '_moduleIdForHomeModule announcements anchor not found.',
      );
    }

    home = home.replaceFirst(
      anchor,
      '        _HomeModule.tanodRoster =>\n'
      '          AppModuleId.tanodRoster,\n'
      '$anchor',
    );
  }

  // -------------------------------------------------------------------------
  // Selected module renderer.
  // -------------------------------------------------------------------------
  if (!home.contains(
    'return TanodRosterScreen(',
  )) {
    const anchor =
        '      case _HomeModule.announcements:';

    final anchorIndex = home.indexOf(anchor);

    if (anchorIndex < 0) {
      throw StateError(
        '_buildSelectedModule announcements case not found.',
      );
    }

    const rosterCase =
        '      case _HomeModule.tanodRoster:\n'
        '        return TanodRosterScreen(\n'
        '          authService: _authService,\n'
        '          user: widget.user,\n'
        '        );\n';

    home = home.replaceRange(
      anchorIndex,
      anchorIndex,
      rosterCase,
    );
  }

  // -------------------------------------------------------------------------
  // Final safety verification.
  // -------------------------------------------------------------------------
  for (final marker in <String>[
    'GlobalThemeButton(',
    'GlobalNotificationBell(',
    'ModuleRegistry.forRole(_appRole)',
    'ModuleRegistry.canAccess(',
    'scaffoldState.isDrawerOpen',
    'TanodAlertsScreen(',
    'TanodRosterScreen(',
    'AppModuleId.tanodRoster',
    '_HomeModule.tanodRoster',
  ]) {
    if (!home.contains(marker)) {
      throw StateError(
        'Final HomeScreen verification failed: $marker',
      );
    }
  }

  if (home.contains("tooltip: 'Refresh'")) {
    throw StateError(
      'Refresh header control was reintroduced.',
    );
  }

  if (!registry.contains(
    'id: AppModuleId.tanodRoster,',
  )) {
    throw StateError(
      'Tanod Roster disappeared from ModuleRegistry.',
    );
  }

  final verifiedRosterIndex =
      registry.indexOf(
    'id: AppModuleId.tanodRoster,',
  );

  final verifiedNextDefinition =
      registry.indexOf(
    'AppModuleDefinition(',
    verifiedRosterIndex + 1,
  );

  final verifiedRosterEnd =
      verifiedNextDefinition >= 0
      ? verifiedNextDefinition
      : registry.length;

  final verifiedBlock =
      registry.substring(
    verifiedRosterIndex,
    verifiedRosterEnd,
  );

  if (!verifiedBlock.contains(
    'mobileImplemented: true',
  )) {
    throw StateError(
      'Tanod Roster was not enabled as a live mobile module.',
    );
  }

  homeFile.writeAsStringSync(
    home,
    flush: true,
  );

  registryFile.writeAsStringSync(
    registry,
    flush: true,
  );

  stdout.writeln(
    '[PASS] Tanod Roster wired into HomeScreen + ModuleRegistry.',
  );
  stdout.writeln(
    '[PASS] Existing global shell/theme/notification/access wiring preserved.',
  );
}
