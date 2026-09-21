import 'product.dart';

// The logged in customer.
class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  // Profile photo link, null when the user has no photo.
  final String? avatarUrl;

  User(
      {required this.id,
      required this.name,
      required this.email,
      this.phone,
      this.avatarUrl});

  // Build a user from the API json.
  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      avatarUrl: Product.fixImageUrl(json['avatar_url'] as String?),
    );
  }
}
