import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/catalog_provider.dart';
import '../widgets/common.dart';
import '../widgets/product_card.dart';
import 'product_detail_screen.dart';

// Search, category chips and the product grid.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();
  // Waits for a short pause in typing before searching.
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  // Loads the next page when the user is near the bottom.
  void _onScroll() {
    if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 300) {
      context.read<CatalogProvider>().loadMore();
    }
  }

  // Body below the chips: loading, error, empty or the grid.
  Widget _body(CatalogProvider c) {
    if (c.loading && c.products.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (c.error != null && c.products.isEmpty) {
      return MessageView(
          icon: Icons.wifi_off,
          message: c.error!,
          actionLabel: 'Retry',
          onAction: c.refresh);
    }
    if (c.products.isEmpty) {
      return RefreshIndicator(
        onRefresh: c.refresh,
        child: ListView(children: const [
          SizedBox(height: 120),
          MessageView(icon: Icons.search_off, message: 'No products found'),
        ]),
      );
    }
    return RefreshIndicator(
      onRefresh: c.refresh,
      child: GridView.builder(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(12),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 0.78),
        itemCount: c.products.length + (c.loadingMore ? 1 : 0),
        itemBuilder: (_, i) {
          if (i >= c.products.length) {
            return const Center(child: CircularProgressIndicator());
          }
          final p = c.products[i];
          return ProductCard(
            product: p,
            onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                    builder: (_) => ProductDetailScreen(product: p))),
          );
        },
      ),
    );
  }

  // Searches 400 ms after the user stops typing, so each key press does not
  // hit the server.
  void _onSearchChanged(String text) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (mounted) context.read<CatalogProvider>().setSearch(text);
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = context.watch<CatalogProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('Mini Grocery')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: _onSearchChanged,
              onSubmitted: c.setSearch,
              decoration: InputDecoration(
                hintText: 'Search products',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.clear),
                  onPressed: () {
                    _debounce?.cancel();
                    _search.clear();
                    c.setSearch('');
                  },
                ),
              ),
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                _chip('All', c.categoryId == null, () => c.selectCategory(null)),
                for (final cat in c.categories)
                  _chip(cat.name, c.categoryId == cat.id,
                      () => c.selectCategory(cat.id)),
              ],
            ),
          ),
          Expanded(child: _body(c)),
        ],
      ),
    );
  }

  // One category chip.
  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
          label: Text(label), selected: selected, onSelected: (_) => onTap()),
    );
  }
}
