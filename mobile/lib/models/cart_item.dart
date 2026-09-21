import 'product.dart';

// One line of the cart.
class CartItem {
  final int id;
  final int quantity;
  final double lineTotal;
  final Product product;

  CartItem({
    required this.id,
    required this.quantity,
    required this.lineTotal,
    required this.product,
  });

  // Build a cart line from the API json.
  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      id: json['id'] as int,
      quantity: json['quantity'] as int,
      lineTotal: double.parse(json['line_total'].toString()),
      product: Product.fromJson(json['product'] as Map<String, dynamic>),
    );
  }
}
