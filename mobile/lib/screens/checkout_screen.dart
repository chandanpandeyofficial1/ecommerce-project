import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/order.dart';
import '../models/product.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/catalog_provider.dart';
import '../providers/order_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../utils/validators.dart' as v;
import '../widgets/common.dart';
import 'home_shell.dart';
import 'payment_status_screen.dart';

// Checkout for the whole cart, or for one product when [product] is set
// (buy now).
class CheckoutScreen extends StatefulWidget {
  final Product? product;
  final int quantity;

  const CheckoutScreen({super.key, this.product, this.quantity = 1});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _address = TextEditingController();
  late final TextEditingController _phone;
  bool _busy = false;
  // 'cod' or 'card'.
  String _method = 'cod';
  Map<String, String> _errors = {};

  // Prefill the phone from the profile.
  @override
  void initState() {
    super.initState();
    _phone = TextEditingController(
        text: context.read<AuthProvider>().user?.phone ?? '');
  }

  @override
  void dispose() {
    _address.dispose();
    _phone.dispose();
    super.dispose();
  }

  // Drops the server error of a field once the user edits it.
  void _clear(String key) {
    if (_errors.containsKey(key)) {
      setState(() => _errors = {..._errors}..remove(key));
    }
  }

  // Validates, places the order and shows the confirmation.
  Future<void> _place() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = {};
    });
    final orders = context.read<OrderProvider>();
    final cart = context.read<CartProvider>();
    final catalog = context.read<CatalogProvider>();
    final nav = Navigator.of(context, rootNavigator: true);
    try {
      final result = await orders.place(
        productId: widget.product?.id,
        quantity: widget.quantity,
        address: _address.text.trim(),
        phone: _phone.text.trim(),
        paymentMethod: _method,
      );
      final order = result.order;
      // Stock and cart changed on the server, so refresh both.
      cart.load();
      catalog.refresh();
      final url = result.checkoutUrl;
      if (_method == 'card' && url != null) {
        // Open the card page, then wait for the server to confirm.
        final opened = await openCheckoutUrl(url);
        if (!opened && mounted) {
          showMessage(context, 'Could not open the payment page. Use Pay now.');
        }
        nav.pushReplacement(MaterialPageRoute(
            builder: (_) => PaymentStatusScreen(orderId: order.id)));
        return;
      }
      nav.pushReplacement(MaterialPageRoute(
          builder: (_) => OrderSuccessScreen(order: order)));
      return;
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = e.fieldErrors);
      // Stock and cart problems are not tied to a text field.
      showMessage(context, e.message);
    }
    if (mounted) setState(() => _busy = false);
  }

  // Radio tiles to pick cash on delivery or card payment.
  Widget _paymentSelector() {
    return Card(
      child: RadioGroup<String>(
        groupValue: _method,
        onChanged: (v) {
          if (!_busy && v != null) setState(() => _method = v);
        },
        child: const Column(
          children: [
            RadioListTile<String>(
              value: 'cod',
              title: Text('Cash on delivery'),
              secondary: Icon(Icons.payments_outlined),
            ),
            RadioListTile<String>(
              value: 'card',
              title: Text('Pay online (card)'),
              secondary: Icon(Icons.credit_card),
            ),
          ],
        ),
      ),
    );
  }

  // Order summary card.
  Widget _summary() {
    final cart = context.watch<CartProvider>();
    final p = widget.product;
    final lines = p != null
        ? [_line('${p.name} x ${widget.quantity}', p.price * widget.quantity)]
        : [for (final i in cart.items) _line('${i.product.name} x ${i.quantity}', i.lineTotal)];
    final total = p != null ? p.price * widget.quantity : cart.total;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            ...lines,
            const Divider(),
            _line('Total', total, bold: true),
          ],
        ),
      ),
    );
  }

  // One label and amount row.
  Widget _line(String label, double amount, {bool bold = false}) {
    final style = TextStyle(fontWeight: bold ? FontWeight.bold : null);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(formatMoney(amount), style: style),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _address,
              maxLines: 3,
              maxLength: 500,
              textInputAction: TextInputAction.next,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              onChanged: (_) => _clear('address'),
              decoration: InputDecoration(
                  labelText: 'Delivery address',
                  counterText: '',
                  errorText: _errors['address']),
              validator: v.address,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.done,
              maxLength: 15,
              inputFormatters: v.phoneFormatters,
              autovalidateMode: AutovalidateMode.onUserInteraction,
              onChanged: (_) => _clear('phone'),
              decoration: InputDecoration(
                  labelText: 'Phone',
                  counterText: '',
                  errorText: _errors['phone']),
              validator: v.phone,
            ),
            const SizedBox(height: 16),
            _paymentSelector(),
            const SizedBox(height: 16),
            _summary(),
            const SizedBox(height: 24),
            BusyButton(busy: _busy, label: _method == 'card' ? 'Pay and place order' : 'Place order', onPressed: _place),
          ],
        ),
      ),
    );
  }
}

// Confirmation shown after an order is placed.
class OrderSuccessScreen extends StatelessWidget {
  final Order order;

  const OrderSuccessScreen({super.key, required this.order});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.check_circle,
                  size: 96, color: Theme.of(context).colorScheme.primary),
              const SizedBox(height: 16),
              Text('Order placed',
                  style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 8),
              Text('Order #${order.id} - ${formatMoney(order.total)}'),
              const Text('Pay cash on delivery.'),
              const SizedBox(height: 32),
              BusyButton(
                  busy: false,
                  label: 'Back to shop',
                  onPressed: () =>
                      backToShop(context)),
            ],
          ),
        ),
      ),
    );
  }
}
