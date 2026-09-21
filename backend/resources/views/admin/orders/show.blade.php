@extends('layouts.layout')
@section('title', 'Order #'.$order->id)
@section('crumb', 'Order #'.$order->id)
@section('crumb_parent', 'Orders')
@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <h2 class="h5 mb-0">Order #{{ $order->id }}</h2> @include('admin.partials.status-badge', ['status' => $order->status])
    <span class="text-muted small ms-2">{{ $order->created_at->format('d M Y H:i') }}</span>
    @if ($order->returnRequest)
        <a href="{{ route('admin.returns.show', $order->returnRequest) }}" class="ms-2"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>Return request</a>
    @endif
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><i class="bi bi-person me-2" aria-hidden="true"></i>Customer</div><div class="card-body">
                <div class="fw-medium">{{ $order->user?->name }}</div>
                <div class="text-muted">{{ $order->user?->email }}</div>
                <div class="mt-2">Phone: {{ $order->phone }}</div>
                <div>Payment: {{ $order->payment_method === 'card' ? 'Card' : 'COD' }} @include('admin.partials.payment-badge', ['order' => $order])</div>
                @if ($order->payment_method === 'card' && $order->payment_status === 'paid' && $order->status === 'cancelled')
                    <div class="text-warning small mt-2">Refund this payment in the Razorpay dashboard.</div>
                @endif
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Delivery address</div><div class="card-body">
                <div>Address: {{ $order->address }}</div>
            </div></div></div>
        </div>
        <div class="card"><div class="card-header">Items</div><div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Product</th><th class="text-end">Price</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                {{-- Name and price are the snapshot taken when the order was placed --}}
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="text-end num">₹{{ number_format($item->price, 2) }}</td>
                        <td class="text-end num">{{ $item->quantity }}</td>
                        <td class="text-end num">₹{{ number_format($item->price * $item->quantity, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><th colspan="3" class="text-end">Order total</th><th class="text-end num fs-5">₹{{ number_format($order->total, 2) }}</th></tr></tfoot>
            </table>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header">Change status</div><div class="card-body">
            @if (count($nextStatuses))
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <label class="form-label" for="status">Move to</label>
                    @if ($order->payment_method === 'card' && $order->payment_status !== 'paid')
                        <div class="text-muted small mb-2">Unpaid card order: it can only be cancelled.</div>
                    @endif
                    <select id="status" name="status" class="form-select mb-3">
                        @foreach ($nextStatuses as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary w-100">Update</button>
                </form>
            @else
                <select class="form-select mb-2" disabled aria-label="Status"><option>No further changes</option></select>
                <span class="text-muted small">This order is closed.</span>
            @endif
        </div></div>
        <div class="card"><div class="card-header">Status history</div><div class="card-body">
            <ul class="timeline">
                @foreach ($order->statusHistories as $history)
                    <li><span class="node"></span>
                        <div>@include('admin.partials.status-badge', ['status' => $history->status])</div>
                        <div class="text-muted small mt-1">{{ $history->created_at->format('d M Y H:i') }}</div>
                    </li>
                @endforeach
            </ul>
        </div></div>
    </div>
</div>
<a href="{{ route('admin.orders.index') }}" class="btn btn-link mt-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to orders</a>
@endsection
