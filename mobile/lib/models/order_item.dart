// A product line inside an order, with the price at purchase time.
class OrderItem {
  final int id;
  final String productName;
  final double price;
  final int quantity;
  final double lineTotal;

  OrderItem({
    required this.id,
    required this.productName,
    required this.price,
    required this.quantity,
    required this.lineTotal,
  });

  // Build an order line from the API json.
  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      id: json['id'] as int? ?? 0,
      productName: json['product_name'] as String,
      price: double.parse(json['price'].toString()),
      quantity: json['quantity'] as int,
      lineTotal: double.parse(json['line_total'].toString()),
    );
  }
}
