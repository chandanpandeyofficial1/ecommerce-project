import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/order_provider.dart';
import '../utils.dart';
import '../widgets/common.dart';
import 'order_detail_screen.dart';

// List of the customer orders with infinite scroll.
class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 200) {
        context.read<OrderProvider>().loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final o = context.watch<OrderProvider>();
    Widget body;
    if (o.loading && o.orders.isEmpty) {
      body = const Center(child: CircularProgressIndicator());
    } else if (o.error != null && o.orders.isEmpty) {
      body = MessageView(
          icon: Icons.wifi_off,
          message: o.error!,
          actionLabel: 'Retry',
          onAction: o.refresh);
    } else if (o.orders.isEmpty) {
      body = const MessageView(
          icon: Icons.receipt_long_outlined, message: 'No orders yet');
    } else {
      body = RefreshIndicator(
        onRefresh: o.refresh,
        child: ListView.separated(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(12),
          itemCount: o.orders.length + (o.loadingMore ? 1 : 0),
          separatorBuilder: (_, _) => const SizedBox(height: 10),
          itemBuilder: (_, i) {
            if (i >= o.orders.length) {
              return const Center(child: CircularProgressIndicator());
            }
            final order = o.orders[i];
            // A row (not a ListTile) so the card grows with the chips and total.
            return Card(
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                        builder: (_) => OrderDetailScreen(orderId: order.id))),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Order #${order.id}',
                                style: const TextStyle(
                                    fontWeight: FontWeight.w600, fontSize: 16)),
                            const SizedBox(height: 4),
                            Text(formatDateTime(order.createdAt)),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          StatusChip(status: order.status),
                          const SizedBox(height: 6),
                          PaymentChip(
                              paymentMethod: order.paymentMethod,
                              paymentStatus: order.paymentStatus),
                          const SizedBox(height: 6),
                          Text(formatMoney(order.total),
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      );
    }
    return Scaffold(appBar: AppBar(title: const Text('My orders')), body: body);
  }
}
