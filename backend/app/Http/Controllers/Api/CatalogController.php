<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    // All categories, sorted by name.
    public function categories()
    {
        return CategoryResource::collection(Category::orderBy('name')->get());
    }

    // Paginated products with optional category and name filters.
    public function products(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
            'page' => 'integer|min:1',
        ]);

        // Escape LIKE wildcards so they match literally.
        $escape = fn ($s) => addcslashes($s, '%_\\');

        $products = Product::with('category')
            ->where('is_active', true)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$escape($request->input('search')).'%'))
            ->orderBy('name')
            ->paginate(10);

        return ProductResource::collection($products);
    }

    // One product by id. Hidden products 404, same as a nonexistent one.
    public function show(int $id)
    {
        return new ProductResource(Product::with('category')->where('is_active', true)->findOrFail($id));
    }
}
