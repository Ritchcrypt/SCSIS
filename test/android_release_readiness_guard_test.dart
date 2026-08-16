import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'Android release shell keeps dedicated TabangNow splash configuration',
    () {
      final legacySplash = File(
        'android/app/src/main/res/drawable/launch_background.xml',
      ).readAsStringSync();

      final legacySplashV21 = File(
        'android/app/src/main/res/drawable-v21/launch_background.xml',
      ).readAsStringSync();

      final splashV31 = File(
        'android/app/src/main/res/values-v31/styles.xml',
      ).readAsStringSync();

      final splashNightV31 = File(
        'android/app/src/main/res/values-night-v31/styles.xml',
      ).readAsStringSync();

      final gradle = File('android/app/build.gradle.kts').readAsStringSync();

      final manifest = File(
        'android/app/src/main/AndroidManifest.xml',
      ).readAsStringSync();

      for (final splash in <String>[legacySplash, legacySplashV21]) {
        expect(splash, contains('@drawable/tabangnow_splash_logo'));
        expect(splash, isNot(contains('@mipmap/ic_launcher')));
      }

      for (final styles in <String>[splashV31, splashNightV31]) {
        expect(styles, contains('android:windowSplashScreenBackground'));
        expect(styles, contains('android:windowSplashScreenAnimatedIcon'));
        expect(styles, contains('@drawable/tabangnow_splash_logo'));
      }

      expect(gradle, contains('namespace = "ph.tabangnow.dao"'));
      expect(gradle, contains('applicationId = "ph.tabangnow.dao"'));
      expect(gradle, isNot(contains('signingConfigs.getByName("debug")')));

      expect(manifest, contains('android:label="TabangNow"'));
    },
  );
}
