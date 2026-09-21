<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    // List categories with their product counts.
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount(['products' => fn ($q) => $q->withTrashed()])->orderBy('id')->get(),
        ]);
    }

    // Empty form.
    public function create()
    {
        return view('admin.categories.form', ['category' => new Category()]);
    }

    // Save a new category.
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|min:2|max:100|unique:categories,name']);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    // Form filled with the category.
    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    // Save changes; the name may stay the same.
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    // Refuse to delete a category that still has products (soft deleted ones count too, the foreign key needs them).
    public function destroy(Category $category)
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->with('error', 'This category has products, so it cannot be deleted.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }
}
