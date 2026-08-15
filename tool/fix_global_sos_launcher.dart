import 'dart:io';

void main() {
  const overlayPath = 'lib/widgets/global_sos_overlay.dart';
  const mainPath = 'lib/main.dart';
  const coinPath = 'lib/widgets/sos_flip_coin_button.dart';
  const homePath = 'lib/screens/home_screen.dart';

  final required = <String>[overlayPath, mainPath, coinPath, homePath];

  for (final path in required) {
    if (!File(path).existsSync()) {
      stderr.writeln('Missing required file: $path');
      exitCode = 1;
      return;
    }
  }

  final originals = <String, String>{
    for (final path in required) path: _normalize(File(path).readAsStringSync()),
  };

  try {
    final patched = <String, String>{
      overlayPath: _patchOverlay(originals[overlayPath]!),
      mainPath: _patchMain(originals[mainPath]!),
      coinPath: _patchCoin(originals[coinPath]!),
      homePath: _patchHome(originals[homePath]!),
    };

    _validate(patched);

    for (final entry in patched.entries) {
      if (entry.value == originals[entry.key]) {
        continue;
      }

      final backup = File('${entry.key}.before-sos-launcher-fix');
      if (!backup.existsSync()) {
        backup.writeAsStringSync(originals[entry.key]!);
      }
    }

    for (final entry in patched.entries) {
      if (entry.value != originals[entry.key]) {
        File(entry.key).writeAsStringSync(entry.value);
      }
    }

    stdout.writeln('Global SOS launcher wiring repaired successfully.');
    stdout.writeln('Fixed: global host mounting, callable SOS flow, removed legacy floating pill, and always-clickable flipping coin.');
    stdout.writeln('Preserved: confirmation dialog, emergency form, GPS/current-or-last-known location, and one-request SOS submission.');
  } catch (error) {
    stderr.writeln('SOS launcher repair aborted: $error');
    stderr.writeln('Nothing was written unless all patched files passed validation.');
    exitCode = 1;
  }
}

String _normalize(String value) => value.replaceAll('\r\n', '\n').replaceAll('\r', '\n');

String _patchOverlay(String text) {
  if (!text.contains('class GlobalSosOverlay extends StatefulWidget')) {
    throw StateError('GlobalSosOverlay class marker was not found.');
  }

  if (!text.contains('final Widget child;')) {
    throw StateError('GlobalSosOverlay child field was not found.');
  }

  const bridge = '''  static _GlobalSosOverlayState? _hostState;

  static Future<void> open(BuildContext context) async {
    final host = _hostState;

    if (host != null && host.mounted) {
      await host._beginSosFlow(context);
      return;
    }

    final ancestor =
        context.findAncestorStateOfType<_GlobalSosOverlayState>();

    if (ancestor != null && ancestor.mounted) {
      await ancestor._beginSosFlow(context);
      return;
    }

    if (context.mounted) {
      ScaffoldMessenger.maybeOf(context)
        ?..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text(
              'Emergency SOS is still initializing. Please try again.',
            ),
          ),
        );
    }
  }
''';

  final openPattern = RegExp(
    r'  static Future<void> open\(BuildContext context\) async \{[\s\S]*?\n  \}\n',
  );

  if (openPattern.hasMatch(text)) {
    text = text.replaceFirst(openPattern, bridge);

    if (!text.contains('static _GlobalSosOverlayState? _hostState;')) {
      text = text.replaceFirst(
        bridge,
        '  static _GlobalSosOverlayState? _hostState;\n\n${bridge.replaceFirst('  static _GlobalSosOverlayState? _hostState;\n\n', '')}',
      );
    }
  } else {
    text = text.replaceFirst(
      '  final Widget child;\n',
      '  final Widget child;\n\n$bridge',
    );
  }

  if (!text.contains('GlobalSosOverlay._hostState = this;')) {
    const stateMarker = '''class _GlobalSosOverlayState extends State<GlobalSosOverlay> {
  bool _flowOpen = false;
''';

    if (!text.contains(stateMarker)) {
      throw StateError('GlobalSosOverlay state marker was not found.');
    }

    const lifecycle = '''class _GlobalSosOverlayState extends State<GlobalSosOverlay> {
  bool _flowOpen = false;

  @override
  void initState() {
    super.initState();
    GlobalSosOverlay._hostState = this;
  }

  @override
  void dispose() {
    if (identical(GlobalSosOverlay._hostState, this)) {
      GlobalSosOverlay._hostState = null;
    }

    super.dispose();
  }
''';

    text = text.replaceFirst(stateMarker, lifecycle);
  }

  final buildToFlow = RegExp(
    r'  @override\n  Widget build\(BuildContext context\) \{[\s\S]*?\n  \}\n\n  Future<void> _beginSosFlow(?:\(\)|\([^)]*\)) async \{',
  );

  if (!buildToFlow.hasMatch(text)) {
    throw StateError('Unable to locate the GlobalSosOverlay build/SOS-flow boundary.');
  }

  text = text.replaceFirst(
    buildToFlow,
    '''  @override
  Widget build(BuildContext context) {
    return widget.child;
  }

  Future<void> _beginSosFlow(BuildContext launchContext) async {''',
  );

  text = text.replaceAll(
    'await HapticFeedback.mediumImpact();',
    'HapticFeedback.mediumImpact();',
  );

  final flowStart = text.indexOf(
    '  Future<void> _beginSosFlow(BuildContext launchContext) async {',
  );
  final flowEnd = text.indexOf('\n}\n\nclass _MobileSosForm', flowStart);

  if (flowStart < 0 || flowEnd < 0) {
    throw StateError('Unable to isolate _beginSosFlow for context repair.');
  }

  var flow = text.substring(flowStart, flowEnd);
  flow = flow.replaceAll('context: context,', 'context: launchContext,');
  flow = flow.replaceAll('Theme.of(context)', 'Theme.of(launchContext)');

  text = text.replaceRange(flowStart, flowEnd, flow);

  return text;
}

String _patchMain(String text) {
  if (!text.contains("import 'widgets/global_sos_overlay.dart';")) {
    const materialImport = "import 'package:flutter/material.dart';\n";

    if (!text.contains(materialImport)) {
      throw StateError('Flutter material import was not found in main.dart.');
    }

    text = text.replaceFirst(
      materialImport,
      "$materialImport\nimport 'widgets/global_sos_overlay.dart';\n",
    );
  }

  if (text.contains('GlobalSosOverlay(')) {
    return text;
  }

  final builderPattern = RegExp(
    r'builder:\s*\(context,\s*child\)\s*=>\s*TabangNowGlobalTheme\(\s*child:\s*child\s*\?\?\s*const SizedBox\.shrink\(\),?\s*\),',
  );

  if (!builderPattern.hasMatch(text)) {
    throw StateError(
      'Expected the TabangNowGlobalTheme MaterialApp builder in main.dart, but it was not found. No main.dart changes were written.',
    );
  }

  return text.replaceFirst(
    builderPattern,
    '''builder: (context, child) => TabangNowGlobalTheme(
        child: GlobalSosOverlay(
          child: child ?? const SizedBox.shrink(),
        ),
      ),''',
  );
}

String _patchCoin(String text) {
  if (!text.contains('class SosFlipCoinButton extends StatefulWidget')) {
    throw StateError('SosFlipCoinButton class marker was not found.');
  }

  final openPattern = RegExp(
    r'  Future<void> _openSos\(\) async \{[\s\S]*?\n  \}\n\n  @override',
  );

  if (!openPattern.hasMatch(text)) {
    throw StateError('Unable to locate SosFlipCoinButton._openSos().');
  }

  text = text.replaceFirst(
    openPattern,
    '''  Future<void> _openSos() async {
    await GlobalSosOverlay.open(context);
  }

  @override''',
  );

  text = text.replaceAll('      button: sos,', '      button: true,');
  text = text.replaceAll('      enabled: sos,', '      enabled: true,');
  text = text.replaceAll(
    "      label: sos ? 'Emergency SOS' : 'TabangNow system logo',",
    "      label: 'Emergency SOS',",
  );

  final hintPattern = RegExp(
    r"      hint: sos\n          \? 'Tap to open the emergency confirmation'\n          : 'The SOS face will flip into view automatically',",
  );
  if (hintPattern.hasMatch(text)) {
    text = text.replaceFirst(
      hintPattern,
      "      hint: 'Tap to open the emergency confirmation. The coin flips automatically.',",
    );
  }

  text = text.replaceAll(
    '          onTap: sos ? onTap : null,',
    '          onTap: onTap,',
  );

  return text;
}

String _patchHome(String text) {
  if (!text.contains("import '../widgets/sos_flip_coin_button.dart';")) {
    const anchor = "import '../widgets/global_theme_button.dart';\n";

    if (!text.contains(anchor)) {
      throw StateError('HomeScreen global theme-button import anchor was not found.');
    }

    text = text.replaceFirst(
      anchor,
      "$anchorimport '../widgets/sos_flip_coin_button.dart';\n",
    );
  }

  if (text.contains('SosFlipCoinButton(size: 42)')) {
    return text;
  }

  const actionAnchor = '''        actions: <Widget>[
          GlobalThemeButton(user: widget.user, authService: _authService),
''';

  if (!text.contains(actionAnchor)) {
    throw StateError('HomeScreen app-bar action anchor was not found.');
  }

  return text.replaceFirst(
    actionAnchor,
    '''        actions: <Widget>[
          const SosFlipCoinButton(size: 42),
          const SizedBox(width: 8),
          GlobalThemeButton(user: widget.user, authService: _authService),
''',
  );
}

void _validate(Map<String, String> patched) {
  final overlay = patched['lib/widgets/global_sos_overlay.dart']!;
  final main = patched['lib/main.dart']!;
  final coin = patched['lib/widgets/sos_flip_coin_button.dart']!;
  final home = patched['lib/screens/home_screen.dart']!;

  final checks = <String, bool>{
    'global SOS host registration':
        overlay.contains('GlobalSosOverlay._hostState = this;'),
    'global SOS callable bridge': overlay.contains(
      'await host._beginSosFlow(context);',
    ),
    'caller-context SOS flow': overlay.contains(
      'Future<void> _beginSosFlow(BuildContext launchContext) async',
    ),
    'legacy floating SOS removed':
        !overlay.contains('Positioned.fill(child: widget.child)'),
    'flow-only overlay build': overlay.contains('return widget.child;'),
    'main mounts GlobalSosOverlay': main.contains('GlobalSosOverlay('),
    'coin no longer rejects blue-face taps': !coin.contains('if (!_showSos)'),
    'coin always has tap handler': coin.contains('onTap: onTap,'),
    'authenticated app bar has SOS coin':
        home.contains('SosFlipCoinButton(size: 42)'),
    'no form-feed corruption in overlay': !overlay.contains(String.fromCharCode(12)),
    'no form-feed corruption in main': !main.contains(String.fromCharCode(12)),
    'no form-feed corruption in coin': !coin.contains(String.fromCharCode(12)),
    'no form-feed corruption in home': !home.contains(String.fromCharCode(12)),
  };

  final failures = checks.entries
      .where((entry) => !entry.value)
      .map((entry) => entry.key)
      .toList(growable: false);

  if (failures.isNotEmpty) {
    throw StateError('Validation failed: ${failures.join(', ')}');
  }
}
