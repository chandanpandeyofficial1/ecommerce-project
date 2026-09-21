import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/order.dart';
import '../providers/order_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../widgets/common.dart';
import 'home_shell.dart';

// Shows the payment state of a card order and keeps re-checking it.
class PaymentStatusScreen extends StatefulWidget {
  final int orderId;

  const PaymentStatusScreen({super.key, required this.orderId});

  @override
  State<PaymentStatusScreen> createState() => _PaymentStatusScreenState();
}

class _PaymentStatusScreenState extends State<PaymentStatusScreen>
    with WidgetsBindingObserver {
  Order? _order;
  String? _error;
  bool _checking = false;
  bool _paying = false;
  Timer? _timer;
  int _polls = 0;

  // Poll every 4 seconds, 30 times is about 2 minutes.
  static const _maxPolls = 30;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _check();
    _startPolling();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  // The user came back from the browser, so check right away.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _check();
      _startPolling();
    }
  }

  // Starts (or restarts) the light poll.
  void _startPolling() {
    _timer?.cancel();
    _polls = 0;
    _timer = Timer.periodic(const Duration(seconds: 4), (t) {
      _polls++;
      if (_polls > _maxPolls || _isFinal) {
        t.cancel();
        return;
      }
      _check();
    });
  }

  // Paid, failed or cancelled means nothing more will change.
  bool get _isFinal {
    final o = _order;
    if (o == null) return false;
    return o.isPaid || o.paymentStatus == 'failed' || o.status == 'cancelled';
  }

  // Fetches the real order state from the server.
  Future<void> _check() async {
    if (_checking) return;
    setState(() => _checking = true);
    try {
      final o = await context.read<OrderProvider>().refreshOne(widget.orderId);
      if (mounted) {
        setState(() {
          _order = o;
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
    if (mounted) setState(() => _checking = false);
  }

  // Asks for a fresh payment link and opens it.
  Future<void> _payNow() async {
    setState(() => _paying = true);
    try {
      final r = await context.read<OrderProvider>().pay(widget.orderId);
      if (r.checkoutUrl != null) await openCheckoutUrl(r.checkoutUrl!);
      _startPolling();
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _paying = false);
  }

  // Closes the full screen flow and shows the Home tab.
  void _backToShop() => backToShop(context);

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
              onAction: _check)
          : const Center(child: CircularProgressIndicator());
    } else if (o.isPaid) {
      body = _content(Icons.check_circle, Colors.green, 'Payment received',
          'Order #${o.id} - ${formatMoney(o.total)}', [
        BusyButton(busy: false, label: 'Back to shop', onPressed: _backToShop),
      ]);
    } else if (o.paymentStatus == 'failed' || o.status == 'cancelled') {
      body = _content(Icons.cancel, Colors.red, 'Payment not completed',
          'Order #${o.id} was not paid or was cancelled.', [
        BusyButton(busy: false, label: 'Back to shop', onPressed: _backToShop),
      ]);
    } else {
      body = _content(
          Icons.hourglass_top,
          Colors.orange,
          'Waiting for payment',
          'Order #${o.id} - ${formatMoney(o.total)}\nFinish paying in your browser, then return here.',
          [
            BusyButton(busy: _paying, label: 'Pay now', onPressed: _payNow),
            const SizedBox(height: 12),
            OutlinedButton(
              style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(50)),
              onPressed: _checking ? null : _check,
              child: const Text('Check payment status'),
            ),
          ]);
    }
    return Scaffold(
        appBar: AppBar(title: Text('Order #${widget.orderId}')), body: body);
  }

  // Shared layout: big icon, title, text and buttons.
  Widget _content(IconData icon, Color color, String title, String text,
      List<Widget> buttons) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 96, color: color),
            const SizedBox(height: 16),
            Text(title, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 8),
            Text(text, textAlign: TextAlign.center),
            const SizedBox(height: 32),
            ...buttons,
          ],
        ),
      ),
    );
  }
}
