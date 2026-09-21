import 'package:flutter/foundation.dart' show ChangeNotifier;
import '../models/category.dart';
import '../models/product.dart';
import '../services/api_client.dart';

// Categories and the paginated, filterable product list.
class CatalogProvider extends ChangeNotifier {
  final ApiClient api;
  CatalogProvider(this.api);

  List<Category> categories = [];
  List<Product> products = [];
  int? categoryId;
  String search = '';
  int _page = 1;
  // Counts reloads so a slow, older response cannot replace a newer one.
  int _requestId = 0;
  bool hasMore = false;
  bool loading = false;
  bool loadingMore = false;
  String? error;

  // Loads the category chips. Failure is not fatal, the chips just stay empty.
  Future<void> loadCategories() async {
    try {
      final res = await api.get('/categories');
      categories = (res['data'] as List)
          .map((e) => Category.fromJson(e as Map<String, dynamic>))
          .toList();
      notifyListeners();
    } catch (_) {}
  }

  // Reloads the first page with the current filters.
  Future<void> refresh() async {
    final id = ++_requestId;
    loading = true;
    error = null;
    notifyListeners();
    try {
      final res = await _fetch(1);
      if (id != _requestId) return;
      products = res.items;
      hasMore = res.hasMore;
      _page = 1;
    } on ApiException catch (e) {
      if (id != _requestId) return;
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
    final id = _requestId;
    try {
      final next = await _fetch(_page + 1);
      // A reload started meanwhile, so this page belongs to an old list.
      if (id != _requestId) {
        loadingMore = false;
        return;
      }
      products = [...products, ...next.items];
      hasMore = next.hasMore;
      _page++;
    } on ApiException catch (_) {
      // Keep the list we have, the user can scroll again to retry.
    }
    loadingMore = false;
    notifyListeners();
  }

  // Picks a category (null means all) and reloads.
  Future<void> selectCategory(int? id) {
    categoryId = id;
    return refresh();
  }

  // Sets the search text and reloads.
  Future<void> setSearch(String text) async {
    final trimmed = text.trim();
    if (trimmed == search) return;
    search = trimmed;
    await refresh();
  }

  // Fetches one page and reports whether more pages follow it.
  Future<({List<Product> items, bool hasMore})> _fetch(int page) async {
    final query = <String, dynamic>{'page': page};
    if (categoryId != null) query['category_id'] = categoryId;
    if (search.isNotEmpty) query['search'] = search;
    final res = await api.get('/products', query: query);
    final meta = res['meta'] as Map<String, dynamic>?;
    final more = meta != null &&
        (meta['current_page'] as int) < (meta['last_page'] as int);
    final items = (res['data'] as List)
        .map((e) => Product.fromJson(e as Map<String, dynamic>))
        .toList();
    return (items: items, hasMore: more);
  }
}
