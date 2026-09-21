import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/order.dart';
import '../models/return_request.dart';
import '../providers/catalog_provider.dart';
import '../providers/order_provider.dart';
import '../providers/return_request_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../widgets/common.dart';
import 'payment_status_screen.dart';
import 'return_request_screen.dart';

// One order with items, address and status timeline.
class OrderDetailScreen extends StatefulWidget {
  final int orderId;

  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Order? _order;
  String? _error;
  bool _cancelling = false;
  bool _paying = false;
  ReturnRequest? _returnRequest;
  bool _returnLoading = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  // Fetches the order with its items and history.
  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final o = await context.read<OrderProvider>().fetchOne(widget.orderId);
      if (mounted) setState(() => _order = o);
      if (o.isDelivered) await _loadReturnRequest();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  // Checks whether a return request already exists for this order.
  Future<void> _loadReturnRequest() async {
    setState(() => _returnLoading = true);
    try {
      final r = await context
          .read<ReturnRequestProvider>()
          .fetchFor(widget.orderId);
      if (mounted) setState(() => _returnRequest = r);
    } on ApiException catch (_) {
      // Leave the button available; the return screen itself will report errors.
    }
    if (mounted) setState(() => _returnLoading = false);
  }

  // Opens the return request form, and reloads the status on success.
  Future<void> _openReturnForm() async {
    final order = _order;
    if (order == null) return;
    final done = await Navigator.of(context).push<bool>(
        MaterialPageRoute(builder: (_) => ReturnRequestScreen(order: order)));
    if (done == true) _loadReturnRequest();
  }

  // Starts a new card payment, opens the page and shows the waiting screen.
  Future<void> _payNow() async {
    setState(() => _paying = true);
    final nav = Navigator.of(context, rootNavigator: true);
    try {
      final r = await context.read<OrderProvider>().pay(widget.orderId);
      if (r.checkoutUrl != null) await openCheckoutUrl(r.checkoutUrl!);
      await nav.push(MaterialPageRoute(
          builder: (_) => PaymentStatusScreen(orderId: widget.orderId)));
      if (mounted) _load();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _paying = false);
  }

  // Asks for confirmation, then cancels the order.
  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel order?'),
        content: const Text('The items will go back to stock.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Keep order')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Cancel order')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    setState(() => _cancelling = true);
    try {
      final updated = await context.read<OrderProvider>().cancel(widget.orderId);
      // Stock is back on the server, so reload the product list.
      if (mounted) context.read<CatalogProvider>().refresh();
      if (mounted) setState(() => _order = updated);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _cancelling = false);
  }

  @override
  Widget build(BuildContext context) {
    final o = _order;
    Widget body;
    if (o == null) {
      body = _error != null
          ? MessageView(
              icon: Icons.wifi_off,
              message: _error!,
              actionLabel: 'Retry',
              onAction: _load)
          : const Center(child: CircularProgressIndicator());
    } else {
      body = ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(children: [
            Expanded(
                child: Text(formatDateTime(o.createdAt),
                    style: Theme.of(context).textTheme.bodyMedium)),
            StatusChip(status: o.status),
            const SizedBox(width: 6),
            PaymentChip(
                paymentMethod: o.paymentMethod,
                paymentStatus: o.paymentStatus),
          ]),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  for (final i in o.items)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(children: [
                        Expanded(
                            child: Text(
                                '${i.productName} x ${i.quantity}')),
                        Text(formatMoney(i.lineTotal)),
                      ]),
                    ),
                  const Divider(),
                  Row(children: [
                    const Expanded(
                        child: Text('Total',
                            style: TextStyle(fontWeight: FontWeight.bold))),
                    Text(formatMoney(o.total),
                        style: const TextStyle(fontWeight: FontWeight.bold)),
                  ]),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              leading: const Icon(Icons.location_on_outlined),
              title: Text(o.address),
              subtitle: Text('Phone: ${o.phone}\n${o.isCard ? 'Card payment' : 'Cash on delivery'}'),
            ),
          ),
          const SizedBox(height: 16),
          Text('Status history',
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          for (final h in o.history)
            ListTile(
              dense: true,
              leading: Icon(Icons.circle, size: 14, color: StatusChip.colorFor(h.status)),
              title: Text(capitalize(h.status)),
              subtitle: Text(formatDateTime(h.at)),
            ),
          if (o.needsPayment) ...[
            const SizedBox(height: 16),
            BusyButton(busy: _paying, label: 'Pay now', onPressed: _payNow),
          ],
          if (o.isPending) ...[
            const SizedBox(height: 16),
            OutlinedButton(
              style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red,
                  minimumSize: const Size.fromHeight(50)),
              onPressed: _cancelling ? null : _cancel,
              child: _cancelling
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Cancel order'),
            ),
          ],
          if (o.isDelivered && !_returnLoading) ...[
            const SizedBox(height: 16),
            if (_returnRequest != null)
              _ReturnStatusCard(request: _returnRequest!)
            else if (isWithinReturnWindow(o.deliveredAt))
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(50)),
                onPressed: _openReturnForm,
                icon: const Icon(Icons.assignment_return_outlined),
                label: const Text('Report a problem / Return'),
              ),
          ],
        ],
      );
    }
    return Scaffold(
        appBar: AppBar(title: Text('Order #${widget.orderId}')), body: body);
  }
}

// Colour for each return request status, matching the order status chips.
Color _returnStatusColor(String status) {
  switch (status) {
    case 'requested':
      return Colors.orange;
    case 'approved':
      return Colors.blue;
    case 'rejected':
      return Colors.red;
    case 'returned':
      return Colors.indigo;
    case 'refunded':
      return Colors.green;
    default:
      return Colors.grey;
  }
}

// Card shown instead of the return button once a request exists.
class _ReturnStatusCard extends StatelessWidget {
  final ReturnRequest request;

  const _ReturnStatusCard({required this.request});

  @override
  Widget build(BuildContext context) {
    final color = _returnStatusColor(request.status);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              const Expanded(
                  child: Text('Return request',
                      style: TextStyle(fontWeight: FontWeight.bold))),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  capitalize(request.status),
                  style: TextStyle(
                      color: color,
                      fontWeight: FontWeight.w600,
                      fontSize: 12),
                ),
              ),
            ]),
            const SizedBox(height: 8),
            Text(returnReasons[request.reason] ?? request.reason),
            const SizedBox(height: 4),
            Text(request.description,
                style: Theme.of(context).textTheme.bodyMedium),
            if (request.status == 'rejected' &&
                request.rejectionReason != null) ...[
              const SizedBox(height: 8),
              Text('Reason: ${request.rejectionReason}',
                  style: const TextStyle(color: Colors.red)),
            ],
            if (request.status == 'refunded' &&
                request.refundAmount != null) ...[
              const SizedBox(height: 8),
              Text(
                  'Refunded ${formatMoney(request.refundAmount!)}'
                  '${request.refundMethod != null ? ' via ${request.refundMethod}' : ''}'),
            ],
          ],
        ),
      ),
    );
  }
}
