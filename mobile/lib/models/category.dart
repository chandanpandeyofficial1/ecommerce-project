// A product category shown as a filter chip.
class Category {
  final int id;
  final String name;

  Category({required this.id, required this.name});

  // Build a category from the API json.
  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(id: json['id'] as int, name: json['name'] as String);
  }
}
