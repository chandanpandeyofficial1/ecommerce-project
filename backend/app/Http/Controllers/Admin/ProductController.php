<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // Paginated list with name search and category filter.
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
        ]);

        // Escape LIKE wildcards so they are searched as plain characters.
        $search = addcslashes((string) $request->input('search'), '%_\\');

        $products = Product::with('category')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->input('category_id')))
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    // Empty form.
    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    // Save a new product; the image is required here.
    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));
        $data['image'] = $request->file('image')->store('products', 'public');
        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    // Form filled with the product.
    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    // Save changes. A new image replaces the old file, no image keeps it.
    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules(false));
        unset($data['image']);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    // Soft delete, so old orders still show the product. The image file is kept for the same reason.
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    // Hide or show a product to customers, without touching its stock or orders.
    public function toggleActive(Product $product)
    {
        $product->is_active = ! $product->is_active;
        $product->save();

        $message = $product->is_active
            ? "{$product->name} is now visible to customers."
            : "{$product->name} is now hidden from customers.";

        return back()->with('success', $message);
    }

    // Shared validation rules.
    private function rules(bool $imageRequired): array
    {
        return [
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0.01|max:99999999',
            'stock' => 'required|integer|min:0|max:1000000',
            'unit' => 'required|string|min:1|max:50',
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
