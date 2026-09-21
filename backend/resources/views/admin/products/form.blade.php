@extends('layouts.layout')
@section('title', ($product->exists ? 'Edit' : 'Add').' product')
@section('inline_errors', true)
@section('crumb', ($product->exists ? 'Edit' : 'Add').' product')
@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
    @csrf
    @if ($product->exists)
        @method('PUT')
    @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-header">Product details</div><div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                        <option value="">Choose...</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Optional, shown to customers.</div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="price">Price</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">&#8377;</span>
                            <input id="price" type="number" step="0.01" name="price" value="{{ old('price', $product->price) }}" class="form-control @error('price') is-invalid @enderror">
                            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="stock">Stock</label>
                        <input id="stock" type="number" name="stock" value="{{ old('stock', $product->stock) }}" class="form-control @error('stock') is-invalid @enderror">
                        @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Units on hand.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="unit">Unit</label>
                        <input id="unit" type="text" name="unit" value="{{ old('unit', $product->unit) }}" placeholder="1 kg" class="form-control @error('unit') is-invalid @enderror">
                        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-header">Product image</div><div class="card-body">
                <div class="text-center mb-3">
                    <img id="imagePreview" class="preview {{ $product->image ? '' : 'd-none' }}" src="{{ $product->image ? asset('storage/'.$product->image) : '' }}" alt="Product image preview">
                </div>
                <label class="dropzone position-relative @error('image') is-invalid @enderror" id="dropzone">
                    <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                    <div class="mt-1">Drop an image here or click to browse</div>
                    <div class="small">jpg, png, webp, up to 2 MB</div>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Product image">
                </label>
                @error('image')<div class="field-error small mt-2"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $message }}</div>@enderror
                @if ($product->exists)<div class="form-text">Leave empty to keep the current image.</div>@endif
            </div></div>
        </div>
    </div>
    <div class="mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-link">Cancel</a>
    </div>
</form>
@endsection
