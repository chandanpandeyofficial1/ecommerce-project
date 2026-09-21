import '../config/api_config.dart';

// A grocery product.
class Product {
  final int id;
  final String name;
  final String? category;
  final String? description;
  final double price;
  final int stock;
  final String? unit;
  final String? imageUrl;

  Product({
    required this.id,
    required this.name,
    this.category,
    this.description,
    required this.price,
    required this.stock,
    this.unit,
    this.imageUrl,
  });

  bool get inStock => stock > 0;

  // Build a product from the API json.
  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] as int,
      name: json['name'] as String,
      category: json['category'] as String?,
      description: json['description'] as String?,
      price: double.parse(json['price'].toString()),
      stock: json['stock'] as int,
      unit: json['unit'] as String?,
      imageUrl: fixImageUrl(json['image_url'] as String?),
    );
  }

  // The server may build image links with "localhost", which the emulator
  // cannot reach, so the part after /storage/ is put on our own base url.
  static String? fixImageUrl(String? url) {
    if (url == null || url.isEmpty) return null;
    final i = url.indexOf('/storage/');
    if (i < 0) return url;
    final root = ApiConfig.baseUrl.replaceFirst(RegExp(r'/api$'), '');
    return root + url.substring(i);
  }
}
