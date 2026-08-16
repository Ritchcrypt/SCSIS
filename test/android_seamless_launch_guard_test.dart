import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'Android launch is visually seamless with TabangNow auth background',
    () {
      final launchColor = File(
        'android/app/src/main/res/values/tabangnow_launch_colors.xml',
      ).readAsStringSync();

      final legacySplash = File(
        'android/app/src/main/res/drawable/launch_background.xml',
      ).readAsStringSync();

      final legacySplashV21 = File(
        'android/app/src/main/res/drawable-v21/launch_background.xml',
      ).readAsStringSync();

      final dayStyles = File(
        'android/app/src/main/res/values/styles.xml',
      ).readAsStringSync();

      final nightStyles = File(
        'android/app/src/main/res/values-night/styles.xml',
      ).readAsStringSync();

      final v31Styles = File(
        'android/app/src/main/res/values-v31/styles.xml',
      ).readAsStringSync();

      final nightV31Styles = File(
        'android/app/src/main/res/values-night-v31/styles.xml',
      ).readAsStringSync();

      expect(launchColor, contains('#F4F7FB'));

      for (final splash in <String>[legacySplash, legacySplashV21]) {
        expect(splash, contains('@color/tabangnow_launch_background'));
        expect(splash, isNot(contains('@mipmap/ic_launcher')));
        expect(splash, isNot(contains('tabangnow_splash_logo')));
      }

      for (final styles in <String>[dayStyles, nightStyles]) {
        expect(
          styles,
          contains(
            '<item name="android:windowBackground">'
            '@color/tabangnow_launch_background'
            '</item>',
          ),
        );
      }

      for (final styles in <String>[v31Styles, nightV31Styles]) {
        expect(
          styles,
          contains(
            'android:windowSplashScreenBackground">'
            '@color/tabangnow_launch_background',
          ),
        );
        expect(
          styles,
          contains(
            'android:windowSplashScreenAnimatedIcon">'
            '@drawable/tabangnow_transparent_splash_icon',
          ),
        );
        expect(styles, isNot(contains('@mipmap/ic_launcher')));
      }
    },
  );
}
