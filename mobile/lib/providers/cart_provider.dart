import 'package:flutter/foundation.dart';
import '../models/cart_item.dart';
import '../services/api_client.dart';

// The server side cart. Mutating methods throw ApiException so the screen
// can show the server message.
class CartProvider extends ChangeNotifier {
  final ApiClient api;
  CartProvider(this.api);

  List<CartItem> items = [];
  double total = 0;
  bool loading = false;
  String? error;

  // Number of lines, shown on the cart badge.
  int get count => items.length;

  // Loads the cart from the server.
  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await api.get('/cart');
      final data = res['data'] as Map<String, dynamic>;
      items = (data['items'] as List)
          .map((e) => CartItem.fromJson(e as Map<String, dynamic>))
          .toList();
      total = double.parse(data['total'].toString());
    } on ApiException catch (e) {
      error = e.message;
    }
    loading = false;
    notifyListeners();
  }

  // Adds a product, then reloads so totals stay correct.
  Future<void> add(int productId, int quantity) async {
    await api.post('/cart', {'product_id': productId, 'quantity': quantity});
    await load();
  }

  // Sets the quantity of a cart line.
  Future<void> setQuantity(CartItem item, int quantity) async {
    await api.put('/cart/${item.id}', {'quantity': quantity});
    await load();
  }

  // Removes a cart line.
  Future<void> remove(CartItem item) async {
    await api.delete('/cart/${item.id}');
    await load();
  }

  // Empties the local copy, used on logout.
  void reset() {
    items = [];
    total = 0;
    error = null;
    notifyListeners();
  }
}
