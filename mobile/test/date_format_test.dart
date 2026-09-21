import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/utils.dart';
import 'package:intl/intl.dart';

void main() {
  group('formatDateTime', () {
    test('returns an empty string for null or empty input', () {
      expect(formatDateTime(null), '');
      expect(formatDateTime(''), '');
    });

    test('returns the raw text when it is not a date', () {
      expect(formatDateTime('not a date'), 'not a date');
    });

    test('reads the server time as UTC and shows it in local time', () {
      final expected = DateFormat('d MMM y, h:mm a')
          .format(DateTime.utc(2026, 9, 21, 15, 10, 53).toLocal());
      expect(formatDateTime('2026-09-21 15:10:53'), expected);
    });
  });
}
