import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../models/order.dart';
import '../models/order_item.dart';
import '../providers/return_request_provider.dart';
import '../services/api_client.dart';
import '../utils.dart';
import '../utils/validators.dart' as v;
import '../widgets/common.dart';

// Form to request a return for a delivered order: pick items and
// quantities, a reason, a description and an optional photo.
class ReturnRequestScreen extends StatefulWidget {
  final Order order;

  const ReturnRequestScreen({super.key, required this.order});

  @override
  State<ReturnRequestScreen> createState() => _ReturnRequestScreenState();
}

class _ReturnRequestScreenState extends State<ReturnRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  final _descriptionController = TextEditingController();

  String _reason = returnReasons.keys.first;
  // Quantity picked per order item id; 0 means not selected.
  late Map<int, int> _quantities;
  String? _photoPath;
  String? _photoName;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _quantities = {for (final i in widget.order.items) i.id: 0};
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  // Picks a photo, checks it client side and keeps it for submission.
  Future<void> _pickPhoto() async {
    final picked = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 1024,
        maxHeight: 1024);
    if (picked == null) return;
    final size = await File(picked.path).length();
    final problem = checkAvatarFile(picked.name, size);
    if (problem != null) {
      if (mounted) showMessage(context, problem);
      return;
    }
    setState(() {
      _photoPath = picked.path;
      _photoName = picked.name;
    });
  }

  // At least one item must have a quantity above zero.
  bool get _hasSelection => _quantities.values.any((q) => q > 0);

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (!_hasSelection) {
      showMessage(context, 'Select at least one item to return.');
      return;
    }
    setState(() => _submitting = true);
    try {
      final items = _quantities.entries
          .where((e) => e.value > 0)
          .map((e) => MapEntry(e.key, e.value))
          .toList();
      await context.read<ReturnRequestProvider>().create(
            orderId: widget.order.id,
            reason: _reason,
            description: _descriptionController.text.trim(),
            items: items,
            photoPath: _photoPath,
            photoName: _photoName,
          );
      if (mounted) {
        showMessage(context, 'Return request submitted.');
        Navigator.pop(context, true);
      }
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.message);
    }
    if (mounted) setState(() => _submitting = false);
  }

  // One order item row with a quantity stepper capped at its ordered qty.
  Widget _itemRow(OrderItem item) {
    final qty = _quantities[item.id] ?? 0;
    return ListTile(
      title: Text(item.productName),
      subtitle: Text('Ordered: ${item.quantity}'),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.remove_circle_outline),
            onPressed: qty > 0
                ? () => setState(() => _quantities[item.id] = qty - 1)
                : null,
          ),
          Text('$qty', style: const TextStyle(fontWeight: FontWeight.bold)),
          IconButton(
            icon: const Icon(Icons.add_circle_outline),
            onPressed: qty < item.quantity
                ? () => setState(() => _quantities[item.id] = qty + 1)
                : null,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Request a return')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Items', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Card(
              child: Column(
                children: [for (final i in widget.order.items) _itemRow(i)],
              ),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _reason,
              decoration: const InputDecoration(labelText: 'Reason'),
              items: [
                for (final entry in returnReasons.entries)
                  DropdownMenuItem(value: entry.key, child: Text(entry.value)),
              ],
              onChanged: (value) =>
                  setState(() => _reason = value ?? _reason),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _descriptionController,
              decoration: const InputDecoration(
                labelText: 'Describe the problem',
                alignLabelWithHint: true,
              ),
              maxLines: 4,
              maxLength: 1000,
              validator: v.returnDescription,
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _pickPhoto,
              icon: const Icon(Icons.photo_camera_outlined),
              label: Text(_photoPath == null
                  ? 'Attach a photo (optional)'
                  : 'Photo attached: $_photoName'),
            ),
            const SizedBox(height: 24),
            BusyButton(
                busy: _submitting, label: 'Submit request', onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
