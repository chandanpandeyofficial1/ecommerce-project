@extends('layouts.layout')
@section('title', 'Categories')
@section('crumb', 'Categories')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Group your products into categories.</p>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add category</a>
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>ID</th><th>Name</th><th>Products</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($categories as $category)
            <tr>
                <td class="num text-muted">{{ $category->id }}</td>
                <td class="fw-medium">{{ $category->name }}</td>
                <td><span class="pill tone-indigo num">{{ $category->products_count }}</span></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-icon btn-outline-secondary" aria-label="Edit {{ $category->name }}" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Delete this category?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-icon btn-outline-danger" aria-label="Delete {{ $category->name }}" title="Delete"><i class="bi bi-trash" aria-hidden="true"></i></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><div class="empty"><i class="bi bi-tags" aria-hidden="true"></i>No categories yet.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
