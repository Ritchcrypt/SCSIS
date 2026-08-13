import 'package:flutter_test/flutter_test.dart';
import 'package:tabangnow_flutter/main.dart';

void main() {
  testWidgets('TabangNow login screen loads', (WidgetTester tester) async {
    await tester.pumpWidget(const TabangNowApp());

    expect(find.text('TabangNow'), findsOneWidget);

    expect(find.text('Log in to your account'), findsOneWidget);
  });
}
