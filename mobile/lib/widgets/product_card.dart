import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/product.dart';
import '../providers/cart_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import 'common.dart';

// Grid card for one product with a quick add button.
class ProductCard extends StatelessWidget {
  final Product product;
  final VoidCallback onTap;

  const ProductCard({super.key, required this.product, required this.onTap});

  // Adds one piece to the cart and reports the result.
  Future<void> _add(BuildContext context) async {
    try {
      await context.read<CartProvider>().add(product.id, 1);
      if (context.mounted) showMessage(context, '${product.name} added to cart');
    } on ApiException catch (e) {
      if (context.mounted) showMessage(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: ProductImage(url: product.imageUrl)),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(product.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(product.unit ?? '',
                      style: theme.textTheme.bodySmall),
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      Expanded(
                        child: Text(formatMoney(product.price),
                            style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: theme.colorScheme.primary)),
                      ),
                      product.inStock
                          ? SizedBox(
                              height: 32,
                              width: 32,
                              child: IconButton.filled(
                                padding: EdgeInsets.zero,
                                iconSize: 18,
                                onPressed: () => _add(context),
                                icon: const Icon(Icons.add),
                              ),
                            )
                          : const Text('Out of stock',
                              style: TextStyle(
                                  color: Colors.red,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 4),
          ],
        ),
      ),
    );
  }
}
