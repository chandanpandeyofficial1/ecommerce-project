import 'order_item.dart';

// One step in the status history of an order.
class StatusEntry {
  final String status;
  final String? at;

  StatusEntry({required this.status, this.at});
}

// A customer order. Items and history are empty when the list endpoint
// does not send them.
class Order {
  final int id;
  final String status;
  final double total;
  final String address;
  final String phone;
  final String? createdAt;
  final String paymentMethod;
  final String paymentStatus;
  final String? paidAt;
  final List<OrderItem> items;
  final List<StatusEntry> history;

  Order({
    required this.id,
    required this.status,
    required this.total,
    required this.address,
    required this.phone,
    this.createdAt,
    this.paymentMethod = 'cod',
    this.paymentStatus = 'unpaid',
    this.paidAt,
    required this.items,
    required this.history,
  });

  bool get isPending => status == 'pending';

  bool get isDelivered => status == 'delivered';

  // When this order was marked delivered, or null if it never was.
  String? get deliveredAt {
    for (final h in history.reversed) {
      if (h.status == 'delivered') return h.at;
    }
    return null;
  }

  // True for orders paid online by card.
  bool get isCard => paymentMethod == 'card';

  bool get isPaid => paymentStatus == 'paid';

  // A card order that still needs a payment and can still be paid.
  bool get needsPayment =>
      isCard && paymentStatus == 'unpaid' && status == 'pending';

  // Build an order from the API json.
  factory Order.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List? ?? [])
        .map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
        .toList();
    final history = (json['status_history'] as List? ?? [])
        .map((e) => StatusEntry(
            status: e['status'] as String, at: e['at'] as String?))
        .toList();
    return Order(
      id: json['id'] as int,
      status: json['status'] as String,
      total: double.parse(json['total'].toString()),
      address: (json['address'] ?? '') as String,
      phone: (json['phone'] ?? '') as String,
      createdAt: json['created_at'] as String?,
      paymentMethod: (json['payment_method'] ?? 'cod').toString(),
      paymentStatus: (json['payment_status'] ?? 'unpaid').toString(),
      paidAt: json['paid_at']?.toString(),
      items: items,
      history: history,
    );
  }
}
