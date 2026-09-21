import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/models/order.dart';
import 'package:grocery_app/models/product.dart';
import 'package:grocery_app/utils.dart';

// Small checks for json parsing and money formatting.
void main() {
  test('product parses price and missing image', () {
    final p = Product.fromJson({
      'id': 1,
      'name': 'Rice',
      'category': 'Grains',
      'description': null,
      'price': '120.50',
      'stock': 0,
      'unit': 'kg',
      'image_url': null,
    });
    expect(p.price, 120.5);
    expect(p.imageUrl, isNull);
    expect(p.inStock, isFalse);
  });

  test('order parses items and history', () {
    final o = Order.fromJson({
      'id': 5,
      'status': 'pending',
      'total': '240.00',
      'address': 'Street 1',
      'phone': '999',
      'created_at': '2026-01-01 10:00:00',
      'items': [
        {
          'product_id': 1,
          'product_name': 'Rice',
          'price': '120.00',
          'quantity': 2,
          'line_total': '240.00'
        }
      ],
      'status_history': [
        {'status': 'pending', 'at': '2026-01-01 10:00:00'}
      ],
    });
    expect(o.isPending, isTrue);
    expect(o.items.single.quantity, 2);
    expect(o.history.length, 1);
  });

  test('formatMoney uses rupee sign and grouping', () {
    expect(formatMoney(1250), contains('1,250.00'));
    expect(formatMoney(1250), startsWith('₹'));
  });

  test('order payment fields parse', () {
    final o = Order.fromJson({
      'id': 7,
      'status': 'pending',
      'total': '10',
      'payment_method': 'card',
      'payment_status': 'paid',
      'paid_at': '2026-01-01T10:00:00Z',
    });
    expect(o.isCard, isTrue);
    expect(o.paymentStatus, 'paid');
    expect(o.paidAt, isNotNull);
  });

  test('order payment fields default safely', () {
    final o = Order.fromJson({'id': 8, 'status': 'pending', 'total': '10'});
    expect(o.paymentMethod, 'cod');
    expect(o.paymentStatus, 'unpaid');
    expect(o.paidAt, isNull);
    expect(o.needsPayment, isFalse);
  });
}
