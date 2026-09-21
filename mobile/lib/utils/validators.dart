import 'package:flutter/services.dart';

// Highest quantity allowed per line, same as the server.
const int maxQuantity = 100;

final RegExp _emailRe = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');
final RegExp _phoneRe = RegExp(r'^[0-9+\- ]+$');

// Allows only digits, plus, minus and space while typing a phone.
final List<TextInputFormatter> phoneFormatters = [
  FilteringTextInputFormatter.allow(RegExp(r'[0-9+\- ]')),
];

// Blocks spaces while typing an email.
final List<TextInputFormatter> noSpaceFormatters = [
  FilteringTextInputFormatter.deny(RegExp(r'\s')),
];

// Fails when the value is empty or only spaces.
String? requiredField(String? v, [String label = 'This field']) {
  if (v == null || v.trim().isEmpty) return '$label is required';
  return null;
}

// Checks the email is present, well formed and at most 255 chars.
String? email(String? v) {
  final r = requiredField(v, 'Email');
  if (r != null) return r;
  final t = v!.trim();
  if (t.length > 255) return 'Email must be at most 255 characters';
  if (!_emailRe.hasMatch(t)) return 'Enter a valid email';
  return null;
}

// Checks the name is 2 to 255 chars.
String? name(String? v) {
  final r = requiredField(v, 'Name');
  if (r != null) return r;
  final t = v!.trim();
  if (t.length < 2) return 'Name must be at least 2 characters';
  if (t.length > 255) return 'Name must be at most 255 characters';
  return null;
}

// Checks the password is at least 8 chars.
String? password(String? v) {
  if (v == null || v.isEmpty) return 'Password is required';
  if (v.length < 8) return 'Password must be at least 8 characters';
  return null;
}

// Returns a validator that checks the value matches [original()].
String? Function(String?) confirmPassword(String Function() original) {
  return (v) {
    if (v == null || v.isEmpty) return 'Confirm your password';
    if (v != original()) return 'Passwords do not match';
    return null;
  };
}

// Checks the phone format and 7 to 15 chars, empty is not allowed.
String? phone(String? v) {
  final r = requiredField(v, 'Phone');
  if (r != null) return r;
  return _phoneFormat(v!.trim());
}

// Same as [phone] but an empty value is fine.
String? phoneOptional(String? v) {
  if (v == null || v.trim().isEmpty) return null;
  return _phoneFormat(v.trim());
}

// Shared phone pattern and length check.
String? _phoneFormat(String t) {
  if (!_phoneRe.hasMatch(t) || t.length < 7 || t.length > 15) {
    return 'Enter a valid phone number (7 to 15 characters)';
  }
  return null;
}

// Checks the address is 5 to 500 chars.
String? address(String? v) {
  final r = requiredField(v, 'Address');
  if (r != null) return r;
  final t = v!.trim();
  if (t.length < 5) return 'Address must be at least 5 characters';
  if (t.length > 500) return 'Address must be at most 500 characters';
  return null;
}

// Largest quantity a customer can pick for this stock.
int quantityLimit(int stock) => stock < maxQuantity ? stock : maxQuantity;

// Checks the return request description is present and at most 1000 chars.
String? returnDescription(String? v) {
  final r = requiredField(v, 'Description');
  if (r != null) return r;
  if (v!.trim().length > 1000) {
    return 'Description must be at most 1000 characters';
  }
  return null;
}
