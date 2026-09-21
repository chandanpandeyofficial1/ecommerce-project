import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/providers/auth_provider.dart';
import 'package:grocery_app/screens/login_screen.dart';
import 'package:grocery_app/services/api_client.dart';
import 'package:provider/provider.dart';

// Pumps the login screen with an auth provider that is never called.
Future<void> _open(WidgetTester t) async {
  await t.pumpWidget(ChangeNotifierProvider<AuthProvider>(
    create: (_) => AuthProvider(ApiClient()),
    child: const MaterialApp(home: LoginScreen()),
  ));
}

void main() {
  testWidgets('empty submit shows required errors', (t) async {
    await _open(t);
    await t.tap(find.text('Login'));
    await t.pump();
    expect(find.text('Email is required'), findsOneWidget);
    expect(find.text('Password is required'), findsOneWidget);
  });

  testWidgets('malformed email shows format error', (t) async {
    await _open(t);
    await t.enterText(find.byType(TextFormField).first, 'not-an-email');
    await t.tap(find.text('Login'));
    await t.pump();
    expect(find.text('Enter a valid email'), findsOneWidget);
  });
}
