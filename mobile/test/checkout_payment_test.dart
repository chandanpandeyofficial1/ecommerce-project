import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/providers/auth_provider.dart';
import 'package:grocery_app/providers/cart_provider.dart';
import 'package:grocery_app/providers/catalog_provider.dart';
import 'package:grocery_app/providers/order_provider.dart';
import 'package:grocery_app/screens/checkout_screen.dart';
import 'package:grocery_app/services/api_client.dart';
import 'package:provider/provider.dart';

void main() {
  // No network is used: the screen only builds and reacts to taps.
  testWidgets('checkout shows both payment options and changes button label',
      (t) async {
    final api = ApiClient();
    await t.pumpWidget(MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider(api)),
        ChangeNotifierProvider(create: (_) => CartProvider(api)),
        ChangeNotifierProvider(create: (_) => CatalogProvider(api)),
        ChangeNotifierProvider(create: (_) => OrderProvider(api)),
      ],
      child: const MaterialApp(home: CheckoutScreen()),
    ));
    expect(find.text('Cash on delivery'), findsOneWidget);
    expect(find.text('Pay online (card)'), findsOneWidget);
    expect(find.text('Place order'), findsOneWidget);
    await t.tap(find.text('Pay online (card)'));
    await t.pump();
    expect(find.text('Pay and place order'), findsOneWidget);
  });
}
