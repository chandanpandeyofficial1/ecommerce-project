@extends('layouts.layout')
@section('title', 'Orders')
@section('crumb', 'Orders')
@section('content')
@php $current = request('status'); @endphp
<div class="status-tabs mb-3">
    <a href="{{ route('admin.orders.index') }}" class="{{ $current ? '' : 'active' }}">All<span class="count">{{ $counts->sum() }}</span></a>
    @foreach ($statuses as $status)
        <a href="{{ route('admin.orders.index', ['status' => $status]) }}" class="{{ $current === $status ? 'active' : '' }}">{{ ucfirst($status) }}<span class="count">{{ $counts[$status] ?? 0 }}</span></a>
    @endforeach
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>ID</th><th>Customer</th><th class="text-end">Total</th><th>Payment</th><th>Status</th><th>Placed</th><th></th></tr></thead>
        <tbody>
        @forelse ($orders as $order)
            <tr>
                <td class="num">#{{ $order->id }}</td>
                <td>{{ $order->user?->name }}</td>
                <td class="text-end num">₹{{ number_format($order->total, 2) }}</td>
                <td>@include('admin.partials.payment-badge', ['order' => $order])</td>
                <td>@include('admin.partials.status-badge', ['status' => $order->status])</td>
                <td class="text-muted">{{ $order->created_at->format('d M Y H:i') }}</td>
                <td class="text-end">
                    @if ($order->returnRequest)
                        <a href="{{ route('admin.returns.show', $order->returnRequest) }}" class="btn btn-sm btn-outline-warning me-1" title="Return request"><i class="bi bi-arrow-return-left" aria-hidden="true"></i></a>
                    @endif
                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><i class="bi bi-inbox" aria-hidden="true"></i>No orders found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $orders->links('pagination::bootstrap-5') }}</div>
@endsection
