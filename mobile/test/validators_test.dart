import 'package:flutter_test/flutter_test.dart';
import 'package:grocery_app/utils/validators.dart' as v;

void main() {
  group('email', () {
    test('accepts valid', () => expect(v.email(' a@b.co '), isNull));
    test('empty', () => expect(v.email(''), 'Email is required'));
    test('whitespace', () => expect(v.email('   '), 'Email is required'));
    test('bad format', () => expect(v.email('abc@'), 'Enter a valid email'));
    test('too long', () => expect(v.email('${'a' * 250}@b.com'), isNotNull));
  });

  group('name', () {
    test('valid', () => expect(v.name('Al'), isNull));
    test('empty', () => expect(v.name(null), 'Name is required'));
    test('whitespace', () => expect(v.name('  '), 'Name is required'));
    test('too short', () => expect(v.name(' a '), isNotNull));
    test('too long', () => expect(v.name('a' * 256), isNotNull));
    test('max ok', () => expect(v.name('a' * 255), isNull));
  });

  group('password', () {
    test('valid', () => expect(v.password('12345678'), isNull));
    test('empty', () => expect(v.password(''), 'Password is required'));
    test('too short', () => expect(v.password('1234567'), isNotNull));
  });

  group('confirmPassword', () {
    final check = v.confirmPassword(() => 'secret123');
    test('match', () => expect(check('secret123'), isNull));
    test('empty', () => expect(check(''), isNotNull));
    test('mismatch', () => expect(check('other'), 'Passwords do not match'));
  });

  group('phone', () {
    test('valid', () => expect(v.phone('+91 98765-4321'), isNull));
    test('empty', () => expect(v.phone(''), 'Phone is required'));
    test('whitespace', () => expect(v.phone('  '), 'Phone is required'));
    test('too short', () => expect(v.phone('123456'), isNotNull));
    test('too long', () => expect(v.phone('1' * 16), isNotNull));
    test('bad chars', () => expect(v.phone('12345abc78'), isNotNull));
  });

  group('phoneOptional', () {
    test('empty ok', () => expect(v.phoneOptional(''), isNull));
    test('whitespace ok', () => expect(v.phoneOptional('  '), isNull));
    test('valid', () => expect(v.phoneOptional('1234567'), isNull));
    test('bad', () => expect(v.phoneOptional('12'), isNotNull));
  });

  group('address', () {
    test('valid', () => expect(v.address('12 Main'), isNull));
    test('empty', () => expect(v.address(''), 'Address is required'));
    test('whitespace', () => expect(v.address('     '), 'Address is required'));
    test('too short', () => expect(v.address('abcd'), isNotNull));
    test('too long', () => expect(v.address('a' * 501), isNotNull));
    test('max ok', () => expect(v.address('a' * 500), isNull));
  });

  group('requiredField', () {
    test('valid', () => expect(v.requiredField('x'), isNull));
    test('whitespace', () => expect(v.requiredField(' ', 'Foo'), 'Foo is required'));
  });

  test('quantityLimit', () {
    expect(v.quantityLimit(5), 5);
    expect(v.quantityLimit(500), 100);
    expect(v.quantityLimit(0), 0);
  });
}
