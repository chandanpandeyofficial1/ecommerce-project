import 'dart:async';
import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/providers/catalog_provider.dart';
import 'package:grocery_app/services/api_client.dart';

// A stand-in for the API that lets the test decide when each answer arrives.
class FakeApi extends ApiClient {
  final calls = <String>[];
  final answers = <String, Completer<Map<String, dynamic>>>{};

  @override
  Future<Map<String, dynamic>> get(String path,
      {Map<String, dynamic>? query}) {
    final term = (query?['search'] ?? '') as String;
    calls.add(term);
    return answers.putIfAbsent(term, () => Completer()).future;
  }

  // Sends the reply for one search term, with a single product named after it.
  void reply(String term) {
    answers.putIfAbsent(term, () => Completer()).complete({
      'data': [
        {
          'id': term.length + 1,
          'name': 'Result for "$term"',
          'category': 'Rice',
          'description': null,
          'price': '10.00',
          'stock': 5,
          'unit': '1 kg',
          'image_url': null,
        }
      ],
      'meta': {'current_page': 1, 'last_page': 1},
    });
  }
}

void main() {
  test('a slow old response does not replace a newer search', () async {
    final api = FakeApi();
    final catalog = CatalogProvider(api);

    final first = catalog.setSearch('r');
    final second = catalog.setSearch('ri');

    // The newer search answers first, then the older one arrives late.
    api.reply('ri');
    await second;
    api.reply('r');
    await first;

    expect(catalog.products.single.name, 'Result for "ri"');
    expect(catalog.loading, isFalse);
  });

  test('searching for the same text again does not reload', () async {
    final api = FakeApi();
    final catalog = CatalogProvider(api);

    final load = catalog.setSearch('rice');
    api.reply('rice');
    await load;
    await catalog.setSearch('  rice ');

    expect(api.calls, ['rice']);
  });
}
