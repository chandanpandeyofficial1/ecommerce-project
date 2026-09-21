@extends('layouts.layout')
@section('title', 'Products')
@section('crumb', 'Products')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Everything you sell, with live stock levels.</p>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add product</a>
</div>

<div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-5"><input type="text" name="search" maxlength="100" value="{{ request('search') }}" class="form-control" placeholder="Search by name" aria-label="Search by name"></div>
        <div class="col-md-4">
            <select name="category_id" class="form-select" aria-label="Category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filter</button></div>
        <div class="col-auto"><a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Category</th><th class="text-end">Price</th><th>Stock</th><th>Unit</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse ($products as $product)
            <tr>
                <td class="num text-muted">{{ $product->id }}</td>
                <td style="width:70px">
                    {{-- Seeded products have no image --}}
                    @if ($product->image)
                        <img class="thumb" src="{{ asset('storage/'.$product->image) }}" alt="">
                    @else
                        <div class="thumb thumb-empty"><i class="bi bi-image" aria-hidden="true"></i></div>
                    @endif
                </td>
                <td class="fw-medium">{{ $product->name }}</td>
                <td class="text-muted">{{ $product->category?->name }}</td>
                <td class="text-end num">₹{{ number_format($product->price, 2) }}</td>
                <td>@include('admin.partials.stock-pill', ['stock' => $product->stock])</td>
                <td class="text-muted">{{ $product->unit }}</td>
                <td>
                    @if ($product->is_active)
                        <span class="status-badge status-delivered"><i class="bi bi-eye me-1" aria-hidden="true"></i>Active</span>
                    @else
                        <span class="status-badge status-cancelled"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Hidden</span>
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-icon btn-outline-secondary" aria-label="Edit {{ $product->name }}" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>

                    @if ($product->is_active)
                        <button type="button" class="btn btn-icon btn-outline-warning" data-bs-toggle="modal" data-bs-target="#hideModal{{ $product->id }}" aria-label="Hide {{ $product->name }}" title="Hide">
                            <i class="bi bi-eye-slash" aria-hidden="true"></i>
                        </button>

                        <div class="modal fade" id="hideModal{{ $product->id }}" tabindex="-1" aria-labelledby="hideModalLabel{{ $product->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="hideModalLabel{{ $product->id }}">Hide product</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Hide this product from customers? It will not appear in the app until you show it again.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('admin.products.toggle-active', $product) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-warning">Hide</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.products.toggle-active', $product) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-icon btn-outline-success" aria-label="Show {{ $product->name }}" title="Show"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline" onsubmit="return confirm('Delete this product?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-icon btn-outline-danger" aria-label="Delete {{ $product->name }}" title="Delete"><i class="bi bi-trash" aria-hidden="true"></i></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty"><i class="bi bi-basket" aria-hidden="true"></i>No products found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $products->links('pagination::bootstrap-5') }}</div>
@endsection
