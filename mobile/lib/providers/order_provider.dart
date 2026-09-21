import 'package:flutter/foundation.dart';
import '../models/order.dart';
import '../services/api_client.dart';

// Result of placing or paying an order: the order plus the card page url.
class PlaceResult {
  final Order order;
  final String? checkoutUrl;

  PlaceResult(this.order, this.checkoutUrl);
}

// Order history and order placing.
class OrderProvider extends ChangeNotifier {
  final ApiClient api;
  OrderProvider(this.api);

  List<Order> orders = [];
  int _page = 1;
  bool hasMore = false;
  bool loading = false;
  bool loadingMore = false;
  String? error;

  // Reloads the first page of orders.
  Future<void> refresh() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      orders = await _fetch(1);
      _page = 1;
    } on ApiException catch (e) {
      error = e.message;
    }
    loading = false;
    notifyListeners();
  }

  // Appends the next page when there is one.
  Future<void> loadMore() async {
    if (loading || loadingMore || !hasMore) return;
    loadingMore = true;
    notifyListeners();
    try {
      final next = await _fetch(_page + 1);
      orders = [...orders, ...next];
      _page++;
    } on ApiException catch (_) {
      // Keep what we have, scrolling again retries.
    }
    loadingMore = false;
    notifyListeners();
  }

  // Loads one order with items and status history.
  Future<Order> fetchOne(int id) async {
    final res = await api.get('/orders/$id');
    return Order.fromJson(res['data'] as Map<String, dynamic>);
  }

  // Places an order from the cart, or a direct buy when productId is set.
  Future<PlaceResult> place({
    int? productId,
    int quantity = 1,
    required String address,
    required String phone,
    String paymentMethod = 'cod',
  }) async {
    final body = <String, dynamic>{
      'address': address,
      'phone': phone,
      'payment_method': paymentMethod,
    };
    if (productId == null) {
      body['use_cart'] = true;
    } else {
      body['product_id'] = productId;
      body['quantity'] = quantity;
    }
    final res = await api.post('/orders', body);
    final order = Order.fromJson(res['data'] as Map<String, dynamic>);
    orders = [order, ...orders];
    notifyListeners();
    return PlaceResult(order, res['checkout_url'] as String?);
  }

  // Starts a card payment for an existing order.
  Future<PlaceResult> pay(int id) async {
    final res = await api.post('/orders/$id/pay');
    final order = Order.fromJson(res['data'] as Map<String, dynamic>);
    orders = [for (final o in orders) o.id == id ? order : o];
    notifyListeners();
    return PlaceResult(order, res['checkout_url'] as String?);
  }

  // Fetches one order and updates its copy in the list.
  Future<Order> refreshOne(int id) async {
    final order = await fetchOne(id);
    orders = [for (final o in orders) o.id == id ? order : o];
    notifyListeners();
    return order;
  }

  // Cancels a pending order and updates the list copy.
  Future<Order> cancel(int id) async {
    final res = await api.post('/orders/$id/cancel');
    final updated = Order.fromJson(res['data'] as Map<String, dynamic>);
    orders = [for (final o in orders) o.id == id ? updated : o];
    notifyListeners();
    return updated;
  }

  // Empties the local copy, used on logout.
  void reset() {
    orders = [];
    error = null;
    notifyListeners();
  }

  // Fetches one page and updates hasMore from the pagination meta.
  Future<List<Order>> _fetch(int page) async {
    final res = await api.get('/orders', query: {'page': page});
    final meta = res['meta'] as Map<String, dynamic>?;
    hasMore = meta != null &&
        (meta['current_page'] as int) < (meta['last_page'] as int);
    return (res['data'] as List)
        .map((e) => Order.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
