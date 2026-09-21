import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/models/user.dart';
import 'package:grocery_app/providers/auth_provider.dart';
import 'package:grocery_app/screens/profile_screen.dart';
import 'package:grocery_app/services/api_client.dart';
import 'package:grocery_app/utils.dart';
import 'package:provider/provider.dart';

// Checks for the profile photo feature.
void main() {
  Map<String, dynamic> base() => {'id': 1, 'name': 'Asha', 'email': 'a@b.c'};

  test('user parses avatar_url when present, null and missing', () {
    expect(User.fromJson({...base(), 'avatar_url': 'http://x/a.png'}).avatarUrl,
        'http://x/a.png');
    expect(User.fromJson({...base(), 'avatar_url': null}).avatarUrl, isNull);
    expect(User.fromJson(base()).avatarUrl, isNull);
  });

  test('avatar file check allows and blocks extensions', () {
    for (final n in ['a.jpg', 'a.JPEG', 'a.png', 'a.webp']) {
      expect(checkAvatarFile(n, 100), isNull);
    }
    for (final n in ['a.gif', 'a.pdf', 'noext']) {
      expect(checkAvatarFile(n, 100), isNotNull);
    }
  });

  test('avatar file check size limit at the boundary', () {
    const limit = 2 * 1024 * 1024;
    expect(checkAvatarFile('a.jpg', limit), isNull);
    expect(checkAvatarFile('a.jpg', limit + 1), isNotNull);
  });

  testWidgets('profile shows the initial letter without a photo',
      (tester) async {
    final auth = AuthProvider(ApiClient())
      ..user = User(id: 1, name: 'asha', email: 'a@b.c');
    await tester.pumpWidget(ChangeNotifierProvider<AuthProvider>.value(
        value: auth, child: const MaterialApp(home: ProfileScreen())));
    expect(find.text('A'), findsOneWidget);
  });
}
