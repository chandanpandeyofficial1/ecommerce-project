// One line of a return request: the order item and how many of it.
class ReturnRequestItem {
  final int orderItemId;
  final String? productName;
  final int quantity;

  ReturnRequestItem(
      {required this.orderItemId, this.productName, required this.quantity});

  factory ReturnRequestItem.fromJson(Map<String, dynamic> json) {
    return ReturnRequestItem(
      orderItemId: json['order_item_id'] as int,
      productName: json['product_name'] as String?,
      quantity: json['quantity'] as int,
    );
  }
}

// A customer's request to return part of a delivered order.
class ReturnRequest {
  final int id;
  final int orderId;
  final String reason;
  final String description;
  final String? photoUrl;
  final String status;
  final String? rejectionReason;
  final double? refundAmount;
  final String? refundMethod;
  final String? createdAt;
  final List<ReturnRequestItem> items;

  ReturnRequest({
    required this.id,
    required this.orderId,
    required this.reason,
    required this.description,
    this.photoUrl,
    required this.status,
    this.rejectionReason,
    this.refundAmount,
    this.refundMethod,
    this.createdAt,
    required this.items,
  });

  factory ReturnRequest.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List? ?? [])
        .map((e) => ReturnRequestItem.fromJson(e as Map<String, dynamic>))
        .toList();
    return ReturnRequest(
      id: json['id'] as int,
      orderId: json['order_id'] as int,
      reason: json['reason'] as String,
      description: json['description'] as String,
      photoUrl: json['photo_url'] as String?,
      status: json['status'] as String,
      rejectionReason: json['rejection_reason'] as String?,
      refundAmount: json['refund_amount'] != null
          ? double.parse(json['refund_amount'].toString())
          : null,
      refundMethod: json['refund_method'] as String?,
      createdAt: json['created_at'] as String?,
      items: items,
    );
  }
}
