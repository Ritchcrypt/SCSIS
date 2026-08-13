import 'dart:io';
import 'dart:math' as math;

const String _themeImport = "import '../core/tabangnow_theme.dart';";

const String _themeExpression = 'TabangNowTheme.of(context)';

const Map<String, String> _neutralTokens = <String, String>{
  '0xFFF8FAFC': 'surfaceMuted',
  '0xFFF8FBFF': 'surfaceMuted',
  '0xFFF1F5F9': 'surfaceSoft',
  '0xFFE2E8F0': 'border',
  '0xFFCBD5E1': 'borderStrong',
  '0xFF0F172A': 'textMain',
  '0xFF334155': 'textSoft',
  '0xFF475569': 'textSoft',
  '0xFF64748B': 'textMuted',
  '0xFF94A3B8': 'textFaint',
};

const List<String> _targetPaths = <String>[
  'lib/screens/home_screen.dart',
  'lib/screens/incidents_screen.dart',
  'lib/screens/report_incident_screen.dart',
  'lib/screens/incident_detail_screen.dart',
  'lib/screens/tanod_alerts_screen.dart',
  'lib/screens/system_branding_screen.dart',
];

void main() {
  final changed = <String>[];
  final skipped = <String>[];

  for (final path in _targetPaths) {
    final file = File(path);

    if (!file.existsSync()) {
      skipped.add(path);
      continue;
    }

    final original = file.readAsStringSync();
    final transformed = _transformFile(path, original);

    if (transformed != original) {
      file.writeAsStringSync(transformed, flush: true);
      changed.add(path);
    }
  }

  stdout.writeln('[PASS] Theme-surface migration processed.');

  if (changed.isEmpty) {
    stdout.writeln('[INFO] No additional source changes were needed.');
  } else {
    for (final path in changed) {
      stdout.writeln('[CHANGED] $path');
    }
  }

  for (final path in skipped) {
    stdout.writeln('[SKIP] Optional screen not present: $path');
  }
}

String _transformFile(String path, String source) {
  var result = source;

  result = _ensureThemeImport(result);

  // Old HomeScreen builds used a fixed light background. Preserve the
  // existing getter if the newer global shell has already migrated it.
  result = result.replaceAll(
    RegExp(
      r'static const Color _contentBackground\s*=\s*'
      r'(?:const\s+)?Color\(0xFFF8FAFC\);',
    ),
    'Color get _contentBackground => '
    'TabangNowTheme.of(context).pageBackground;',
  );

  // Three incident screens share a top-level panel helper. Give it an
  // explicit BuildContext so the helper can consume the global palette.
  if (path.endsWith('incidents_screen.dart') ||
      path.endsWith('report_incident_screen.dart') ||
      path.endsWith('incident_detail_screen.dart')) {
    result = result.replaceAll(
      'BoxDecoration _panelDecoration() {',
      'BoxDecoration _panelDecoration(BuildContext context) {',
    );

    // Replace calls only after the definition has been changed.
    result = result.replaceAll(
      RegExp(r'_panelDecoration\(\)'),
      '_panelDecoration(context)',
    );
  }

  // Tanod Alerts has one neutral fallback color helper outside build().
  if (path.endsWith('tanod_alerts_screen.dart')) {
    result = result.replaceAll(
      '_AlertTypeColors _typeColors(String type) {',
      '_AlertTypeColors _typeColors('
          'BuildContext context, String type) {',
    );

    result = result.replaceAll(
      'final colors = _typeColors(type);',
      'final colors = _typeColors(context, type);',
    );
  }

  // Scaffold backgrounds are page-level, not card-level.
  result = result.replaceAll(
    RegExp(
      r'backgroundColor:\s*(?:const\s+)?'
      r'Color\(0xFFF8FAFC\)',
    ),
    'backgroundColor: '
    'TabangNowTheme.of(context).pageBackground',
  );

  // Replace the website neutral palette only. Semantic colors such as
  // red/yellow/green status badges remain unchanged.
  for (final entry in _neutralTokens.entries) {
    result = result.replaceAll(
      RegExp(
        r'(?:const\s+)?Color\('
        '${RegExp.escape(entry.key)}'
        r'\)',
      ),
      '$_themeExpression.${entry.value}',
    );
  }

  // Standard Material surfaces that were explicitly pinned to white.
  result = result.replaceAll(
    RegExp(r'backgroundColor:\s*Colors\.white'),
    'backgroundColor: '
    'TabangNowTheme.of(context).surface',
  );

  result = result.replaceAll(
    RegExp(r'surfaceTintColor:\s*Colors\.white'),
    'surfaceTintColor: '
    'TabangNowTheme.of(context).surface',
  );

  result = result.replaceAll(
    RegExp(r'fillColor:\s*Colors\.white'),
    'fillColor: '
    'TabangNowTheme.of(context).surface',
  );

  result = result.replaceAll(
    RegExp(r'\bbackground:\s*Colors\.white'),
    'background: '
    'TabangNowTheme.of(context).surface',
  );

  // White used as a BoxDecoration's direct background is a generic surface
  // in the authenticated content screens. This does NOT touch white text,
  // icons, map-marker borders, progress indicators, or drawer foregrounds.
  result = result.replaceAllMapped(
    RegExp(
      r'(decoration:\s*BoxDecoration\(\s*)'
      r'color:\s*Colors\.white,',
      multiLine: true,
    ),
    (match) =>
        '${match.group(1)}color: '
        'TabangNowTheme.of(context).surface,',
  );

  // Incident panel helper's white background can sit outside the preceding
  // decoration pattern because it is a top-level function.
  result = result.replaceAllMapped(
    RegExp(
      r'(BoxDecoration _panelDecoration'
      r'\(BuildContext context\) \{\s*'
      r'return BoxDecoration\(\s*)'
      r'color:\s*Colors\.white,',
      multiLine: true,
    ),
    (match) =>
        '${match.group(1)}color: '
        'TabangNowTheme.of(context).surface,',
  );

  // Tanod alert unread card uses a conditional white fallback.
  if (path.endsWith('tanod_alerts_screen.dart')) {
    result = result.replaceAllMapped(
      RegExp(
        r'(:\s*)Colors\.white'
        r'(?=,\s*borderRadius:)',
        multiLine: true,
      ),
      (match) =>
          '${match.group(1)}'
          'TabangNowTheme.of(context).surface',
    );
  }

  // New dynamic token expressions cannot remain beneath const constructors
  // or const literal collections. Remove only const ancestors enclosing a
  // dynamic TabangNowTheme.of(context) reference.
  result = _removeConstAncestors(result);

  return result;
}

String _ensureThemeImport(String source) {
  if (source.contains(_themeImport)) {
    return source;
  }

  const materialImport = "import 'package:flutter/material.dart';";

  if (!source.contains(materialImport)) {
    throw StateError('Could not locate Flutter material import.');
  }

  return source.replaceFirst(
    materialImport,
    '$materialImport\n\n$_themeImport',
  );
}

String _removeConstAncestors(String source) {
  final dynamicPositions = <int>[];
  var searchFrom = 0;

  while (true) {
    final index = source.indexOf(_themeExpression, searchFrom);

    if (index < 0) {
      break;
    }

    dynamicPositions.add(index);
    searchFrom = index + _themeExpression.length;
  }

  if (dynamicPositions.isEmpty) {
    return source;
  }

  final pairs = _delimiterPairs(source);
  final removeIndexes = <int>{};

  for (final position in dynamicPositions) {
    for (final entry in pairs.entries) {
      final open = entry.key;
      final close = entry.value;

      if (open >= position || close <= position) {
        continue;
      }

      final constIndex = _constBeforeDelimiter(source, open);

      if (constIndex != null) {
        removeIndexes.add(constIndex);
      }
    }
  }

  final ordered = removeIndexes.toList()..sort((a, b) => b.compareTo(a));

  var result = source;

  for (final index in ordered) {
    final tail = result.substring(index);
    final match = RegExp(r'^const\s+').firstMatch(tail);

    if (match == null) {
      continue;
    }

    result = result.replaceRange(index, index + match.end, '');
  }

  return result;
}

Map<int, int> _delimiterPairs(String source) {
  final pairs = <int, int>{};
  final stack = <_OpenDelimiter>[];

  var state = _LexState.code;
  var quote = '';
  var tripleQuote = false;

  var i = 0;

  while (i < source.length) {
    final char = source[i];
    final next = i + 1 < source.length ? source[i + 1] : '';

    if (state == _LexState.lineComment) {
      if (char == '\n') {
        state = _LexState.code;
      }

      i++;
      continue;
    }

    if (state == _LexState.blockComment) {
      if (char == '*' && next == '/') {
        state = _LexState.code;
        i += 2;
        continue;
      }

      i++;
      continue;
    }

    if (state == _LexState.string) {
      if (tripleQuote) {
        final marker = '$quote$quote$quote';

        if (source.startsWith(marker, i)) {
          state = _LexState.code;
          quote = '';
          tripleQuote = false;
          i += 3;
          continue;
        }

        i++;
        continue;
      }

      if (char.codeUnitAt(0) == 92) {
        i += 2;
        continue;
      }

      if (char == quote) {
        state = _LexState.code;
        quote = '';
      }

      i++;
      continue;
    }

    if (char == '/' && next == '/') {
      state = _LexState.lineComment;
      i += 2;
      continue;
    }

    if (char == '/' && next == '*') {
      state = _LexState.blockComment;
      i += 2;
      continue;
    }

    if (char == "'" || char == '"') {
      quote = char;
      final marker = '$char$char$char';
      tripleQuote = source.startsWith(marker, i);
      state = _LexState.string;
      i += tripleQuote ? 3 : 1;
      continue;
    }

    if (char == '(' || char == '[' || char == '{') {
      stack.add(_OpenDelimiter(char, i));

      i++;
      continue;
    }

    if (char == ')' || char == ']' || char == '}') {
      final expected = switch (char) {
        ')' => '(',
        ']' => '[',
        '}' => '{',
        _ => '',
      };

      if (stack.isNotEmpty && stack.last.character == expected) {
        final opening = stack.removeLast();
        pairs[opening.index] = i;
      }

      i++;
      continue;
    }

    i++;
  }

  return pairs;
}

int? _constBeforeDelimiter(String source, int openIndex) {
  final delimiter = source[openIndex];
  final start = math.max(0, openIndex - 220);
  final prefix = source.substring(start, openIndex);

  RegExp pattern;

  if (delimiter == '(') {
    pattern = RegExp(
      r'\bconst\s+'
      r'[A-Za-z_]'
      r'[A-Za-z0-9_.$<>,? \t]*$',
    );
  } else {
    pattern = RegExp(
      r'\bconst'
      r'(?:\s*<[^>\n]+>)?'
      r'\s*$',
    );
  }

  final matches = pattern.allMatches(prefix).toList();

  if (matches.isEmpty) {
    return null;
  }

  return start + matches.last.start;
}

enum _LexState { code, lineComment, blockComment, string }

class _OpenDelimiter {
  const _OpenDelimiter(this.character, this.index);

  final String character;
  final int index;
}
