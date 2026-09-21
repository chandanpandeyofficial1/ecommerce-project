import 'package:intl/intl.dart';

// Formats an amount as rupees with Indian digit grouping.
String formatMoney(double amount) {
  return NumberFormat.currency(
          locale: 'en_IN', symbol: '₹', decimalDigits: 2)
      .format(amount);
}

// Checks a picked photo before upload. Returns an error message, or null
// when the file is fine (jpg, jpeg, png or webp, at most 2 MB).
String? checkAvatarFile(String fileName, int sizeInBytes) {
  final dot = fileName.lastIndexOf('.');
  final ext = dot < 0 ? '' : fileName.substring(dot + 1).toLowerCase();
  if (!['jpg', 'jpeg', 'png', 'webp'].contains(ext)) {
    return 'Please choose a jpg, png or webp photo.';
  }
  if (sizeInBytes > 2 * 1024 * 1024) {
    return 'The photo must be 2 MB or smaller.';
  }
  return null;
}

// Turns "pending" into "Pending".
String capitalize(String s) {
  if (s.isEmpty) return s;
  return s[0].toUpperCase() + s.substring(1);
}

// Days a customer has to request a return after delivery. The server is the
// real authority; this is only used to show or hide the button.
const int returnWindowDays = 3;

// Labels for the return reasons, in the order shown in the dropdown.
const Map<String, String> returnReasons = {
  'wrong_item': 'Wrong item received',
  'damaged': 'Item arrived damaged',
  'missing_item': 'Item missing from order',
  'quality_problem': 'Quality problem',
  'other': 'Other',
};

// True when a "delivered" timestamp is still within the return window.
// The timestamp is the one from the order's own status history.
bool isWithinReturnWindow(String? deliveredAt) {
  if (deliveredAt == null || deliveredAt.isEmpty) return false;
  final utc = DateTime.tryParse('${deliveredAt.replaceFirst(' ', 'T')}Z');
  if (utc == null) return false;
  final days = DateTime.now().toUtc().difference(utc).inDays;
  return days <= returnWindowDays;
}

// The server sends UTC times like "2026-09-21 15:10:53"; show them in the
// phone's own time zone, for example "21 Sep 2026, 8:40 PM".
String formatDateTime(String? raw) {
  if (raw == null || raw.isEmpty) return '';
  final utc = DateTime.tryParse('${raw.replaceFirst(' ', 'T')}Z');
  if (utc == null) return raw;
  return DateFormat('d MMM y, h:mm a').format(utc.toLocal());
}
