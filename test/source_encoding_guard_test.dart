import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Flutter source contains no known mojibake markers', () {
    const markers = <String>[
      'ΓÇ',
      '≡ƒ',
      '∩╕',
      'Ã',
      'Â',
      'â€',
      'ðŸ',
      'ï¸',
      '\uFFFD',
    ];

    final offenders = <String>[];
    final lib = Directory('lib');

    expect(lib.existsSync(), isTrue, reason: 'lib/ directory was not found.');

    for (final entity in lib.listSync(recursive: true, followLinks: false)) {
      if (entity is! File || !entity.path.endsWith('.dart')) {
        continue;
      }

      final lines = entity.readAsLinesSync();

      for (var index = 0; index < lines.length; index++) {
        final line = lines[index];

        for (final marker in markers) {
          if (line.contains(marker)) {
            offenders.add('${entity.path}:${index + 1} contains "$marker"');
          }
        }
      }
    }

    expect(
      offenders,
      isEmpty,
      reason: 'Suspicious encoding corruption found:\n${offenders.join('\n')}',
    );
  });
}
