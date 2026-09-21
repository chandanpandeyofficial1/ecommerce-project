@extends('layouts.layout')
@section('title', ($category->exists ? 'Edit' : 'Add').' category')
@section('inline_errors', true)
@section('crumb', 'Categories')
@section('content')
<form method="POST" class="card" style="max-width:520px"
      action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
    <div class="card-header">{{ $category->exists ? 'Edit' : 'Add' }} category</div>
    <div class="card-body">
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Must be unique, up to 100 characters.</div>
        </div>
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save</button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-link">Cancel</a>
    </div>
</form>
@endsection
