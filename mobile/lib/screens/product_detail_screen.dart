import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/product.dart';
import '../providers/cart_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../utils/validators.dart';
import '../widgets/common.dart';
import 'checkout_screen.dart';

// Full product view with quantity, add to cart and buy now.
class ProductDetailScreen extends StatefulWidget {
  final Product product;

  const ProductDetailScreen({super.key, required this.product});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  int _qty = 1;
  bool _busy = false;

  // Raises the quantity, or tells the user the limit is reached.
  void _increase(int limit) {
    if (_qty >= limit) {
      showMessage(context,
          limit >= maxQuantity ? 'Maximum $maxQuantity per order' : 'Only $limit in stock');
      return;
    }
    setState(() => _qty++);
  }

  // Adds the chosen quantity to the cart.
  Future<void> _addToCart() async {
    setState(() => _busy = true);
    try {
      await context.read<CartProvider>().add(widget.product.id, _qty);
      if (mounted) showMessage(context, 'Added to cart');
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _busy = false);
  }

  // Goes straight to checkout with this product only, the cart is untouched.
  void _buyNow() {
    Navigator.of(context, rootNavigator: true).push(
        MaterialPageRoute(
            builder: (_) =>
                CheckoutScreen(product: widget.product, quantity: _qty)));
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.product;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(p.name)),
      body: ListView(
        children: [
          ProductImage(url: p.imageUrl, height: 240),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(p.name, style: theme.textTheme.headlineSmall),
                const SizedBox(height: 4),
                if (p.category != null)
                  Text(p.category!, style: theme.textTheme.bodyMedium),
                const SizedBox(height: 8),
                Text(
                    '${formatMoney(p.price)}${p.unit != null ? ' / ${p.unit}' : ''}',
                    style: theme.textTheme.titleLarge?.copyWith(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text(p.inStock ? '${p.stock} in stock' : 'Out of stock',
                    style: TextStyle(
                        color: p.inStock ? Colors.green[700] : Colors.red)),
                const SizedBox(height: 16),
                Text(p.description ?? 'No description.'),
                const SizedBox(height: 20),
                if (p.inStock)
                  Row(
                    children: [
                      const Text('Quantity'),
                      const Spacer(),
                      IconButton.outlined(
                          onPressed: _qty > 1 ? () => setState(() => _qty--) : null,
                          icon: const Icon(Icons.remove)),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text('$_qty', style: theme.textTheme.titleMedium),
                      ),
                      IconButton.outlined(
                          onPressed: () => _increase(quantityLimit(p.stock)),
                          icon: const Icon(Icons.add)),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(50),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12))),
                  onPressed: p.inStock && !_busy ? _addToCart : null,
                  child: const Text('Add to cart'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: SizedBox(
                  height: 50,
                  child: FilledButton(
                      onPressed: p.inStock && p.stock > 0 ? _buyNow : null,
                      child: const Text('Buy now')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
