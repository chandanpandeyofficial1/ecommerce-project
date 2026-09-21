import 'package:flutter/foundation.dart';
import '../models/return_request.dart';
import '../services/api_client.dart';

// Loads and creates return requests for one order.
class ReturnRequestProvider extends ChangeNotifier {
  final ApiClient api;
  ReturnRequestProvider(this.api);

  // Returns the existing return request for the order, or null if none.
  Future<ReturnRequest?> fetchFor(int orderId) async {
    final res = await api.get('/orders/$orderId/returns');
    final list = (res['data'] as List? ?? [])
        .map((e) => ReturnRequest.fromJson(e as Map<String, dynamic>))
        .toList();
    return list.isEmpty ? null : list.first;
  }

  // Submits a new return request, with an optional photo.
  Future<ReturnRequest> create({
    required int orderId,
    required String reason,
    required String description,
    required List<MapEntry<int, int>> items, // order_item_id -> quantity
    String? photoPath,
    String? photoName,
  }) async {
    final fields = <String, dynamic>{
      'reason': reason,
      'description': description,
    };
    for (var i = 0; i < items.length; i++) {
      fields['items[$i][order_item_id]'] = items[i].key;
      fields['items[$i][quantity]'] = items[i].value;
    }
    final res = await api.postForm(
      '/orders/$orderId/returns',
      fields,
      fileField: photoPath == null ? null : 'photo',
      filePath: photoPath,
      fileName: photoName,
    );
    return ReturnRequest.fromJson(res['data'] as Map<String, dynamic>);
  }
}
