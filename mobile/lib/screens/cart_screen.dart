import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/cart_item.dart';
import '../providers/cart_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../utils/validators.dart';
import '../widgets/common.dart';
import 'checkout_screen.dart';

// Cart lines with quantity controls and the checkout button.
class CartScreen extends StatelessWidget {
  const CartScreen({super.key});

  // Runs a cart change and shows the server message if it fails.
  Future<void> _run(BuildContext context, Future<void> Function() action) async {
    try {
      await action();
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }

  // One cart row.
  Widget _row(BuildContext context, CartProvider cart, CartItem item) {
    final p = item.product;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: SizedBox(
                  width: 72, height: 72, child: ProductImage(url: p.imageUrl)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(p.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(formatMoney(p.price)),
                  Row(
                    children: [
                      IconButton(
                          visualDensity: VisualDensity.compact,
                          onPressed: item.quantity > 1
                              ? () => _run(context,
                                  () => cart.setQuantity(item, item.quantity - 1))
                              : null,
                          icon: const Icon(Icons.remove_circle_outline)),
                      Text('${item.quantity}'),
                      IconButton(
                          visualDensity: VisualDensity.compact,
                          onPressed: () {
                            final limit = quantityLimit(p.stock);
                            if (item.quantity >= limit) {
                              showMessage(
                                  context,
                                  limit >= maxQuantity
                                      ? 'Maximum $maxQuantity per item'
                                      : 'Only $limit in stock');
                              return;
                            }
                            _run(context,
                                () => cart.setQuantity(item, item.quantity + 1));
                          },
                          icon: const Icon(Icons.add_circle_outline)),
                    ],
                  ),
                ],
              ),
            ),
            Column(
              children: [
                IconButton(
                    onPressed: () => _run(context, () => cart.remove(item)),
                    icon: const Icon(Icons.delete_outline, color: Colors.red)),
                Text(formatMoney(item.lineTotal),
                    style: const TextStyle(fontWeight: FontWeight.bold)),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    Widget body;
    if (cart.loading && cart.items.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (cart.error != null && cart.items.isEmpty) {
      body = MessageView(
          icon: Icons.wifi_off,
          message: cart.error!,
          actionLabel: 'Retry',
          onAction: cart.load);
    } else if (cart.items.isEmpty) {
      body = const MessageView(
          icon: Icons.shopping_cart_outlined, message: 'Your cart is empty');
    } else {
      body = RefreshIndicator(
        onRefresh: cart.load,
        child: ListView.separated(
          padding: const EdgeInsets.all(12),
          itemCount: cart.items.length,
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (_, i) => _row(context, cart, cart.items[i]),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Cart')),
      body: body,
      bottomNavigationBar: cart.items.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Text('Total'),
                          Text(formatMoney(cart.total),
                              style: Theme.of(context).textTheme.titleLarge),
                        ],
                      ),
                    ),
                    Expanded(
                      child: SizedBox(
                        height: 50,
                        child: FilledButton(
                          onPressed: () => Navigator.of(context, rootNavigator: true).push(
                              MaterialPageRoute(
                                  builder: (_) => const CheckoutScreen())),
                          child: const Text('Checkout'),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
