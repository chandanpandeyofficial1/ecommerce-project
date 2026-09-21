import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/models/order.dart';
import 'package:grocery_app/models/order_item.dart';
import 'package:grocery_app/providers/return_request_provider.dart';
import 'package:grocery_app/screens/return_request_screen.dart';
import 'package:grocery_app/services/api_client.dart';
import 'package:grocery_app/utils/validators.dart' as v;
import 'package:provider/provider.dart';

void main() {
  Order order() => Order(
        id: 1,
        status: 'delivered',
        total: 30,
        address: '12 Main Road',
        phone: '9999999999',
        items: [
          OrderItem(id: 1, productName: 'Milk', price: 10, quantity: 2, lineTotal: 20),
          OrderItem(id: 2, productName: 'Bread', price: 10, quantity: 1, lineTotal: 10),
        ],
        history: [],
      );

  Widget buildScreen() {
    final api = ApiClient();
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ReturnRequestProvider(api)),
      ],
      child: MaterialApp(home: ReturnRequestScreen(order: order())),
    );
  }

  group('return description validator', () {
    test('empty is required', () => expect(v.returnDescription(''), isNotNull));
    test('whitespace is required',
        () => expect(v.returnDescription('   '), isNotNull));
    test('valid', () => expect(v.returnDescription('Box was crushed'), isNull));
    test('too long', () => expect(v.returnDescription('a' * 1001), isNotNull));
    test('max ok', () => expect(v.returnDescription('a' * 1000), isNull));
  });

  testWidgets('quantity stepper is capped at the ordered quantity',
      (tester) async {
    await tester.pumpWidget(buildScreen());

    // Milk was ordered x2: the plus button stops working at 2.
    final milkPlus = find.byIcon(Icons.add_circle_outline).first;
    await tester.tap(milkPlus);
    await tester.pump();
    await tester.tap(milkPlus);
    await tester.pump();
    expect(find.text('2'), findsOneWidget);

    // A third tap should not push the quantity past 2 (button disabled).
    await tester.tap(milkPlus, warnIfMissed: false);
    await tester.pump();
    expect(find.text('2'), findsOneWidget);
    expect(find.text('3'), findsNothing);
  });

  testWidgets('minus button cannot go below zero', (tester) async {
    await tester.pumpWidget(buildScreen());

    final breadMinus = find.byIcon(Icons.remove_circle_outline).last;
    await tester.tap(breadMinus, warnIfMissed: false);
    await tester.pump();
    expect(find.text('0'), findsWidgets);
  });

  testWidgets('submit without a description shows a validation error',
      (tester) async {
    await tester.pumpWidget(buildScreen());

    await tester.tap(find.text('Submit request'));
    await tester.pump();

    expect(find.text('Description is required'), findsOneWidget);
  });

  testWidgets('submit without selecting an item shows a message',
      (tester) async {
    await tester.pumpWidget(buildScreen());

    await tester.enterText(
        find.widgetWithText(TextFormField, 'Describe the problem'),
        'Box was crushed in transit');
    await tester.tap(find.text('Submit request'));
    await tester.pump();

    expect(find.text('Select at least one item to return.'), findsOneWidget);
  });
}
