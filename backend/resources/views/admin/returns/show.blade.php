@extends('layouts.layout')
@php $order = $returnRequest->order; @endphp
@section('title', 'Return #'.$returnRequest->id)
@section('crumb', 'Return #'.$returnRequest->id)
@section('crumb_parent', 'Returns')
@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <h2 class="h5 mb-0">Return #{{ $returnRequest->id }}</h2> @include('admin.partials.return-status-badge', ['status' => $returnRequest->status])
    <span class="text-muted small ms-2">{{ $returnRequest->created_at->format('d M Y H:i') }}</span>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><i class="bi bi-person me-2" aria-hidden="true"></i>Customer</div><div class="card-body">
                <div class="fw-medium">{{ $order->user?->name }}</div>
                <div class="text-muted">{{ $order->user?->email }}</div>
                <div class="mt-2">Order: <a href="{{ route('admin.orders.show', $order) }}">#{{ $order->id }}</a></div>
                <div>Payment: {{ $order->payment_method === 'card' ? 'Card' : 'COD' }} @include('admin.partials.payment-badge', ['order' => $order])</div>
            </div></div></div>
            <div class="col-md-6"><div class="card h-100"><div class="card-header"><i class="bi bi-chat-left-text me-2" aria-hidden="true"></i>Reason</div><div class="card-body">
                <div>{{ ucfirst(str_replace('_', ' ', $returnRequest->reason)) }}</div>
                <div class="text-muted mt-2">{{ $returnRequest->description }}</div>
                @if ($returnRequest->photo)
                    <img src="{{ asset('storage/'.$returnRequest->photo) }}" alt="Photo submitted by the customer" class="mt-2" style="max-width:220px;border-radius:10px">
                @endif
            </div></div></div>
        </div>
        <div class="card"><div class="card-header">Items being returned</div><div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Product</th><th class="text-end">Price</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @php $lineTotal = 0; @endphp
                @foreach ($returnRequest->items as $line)
                    @php $amount = $line->orderItem->price * $line->quantity; $lineTotal += $amount; @endphp
                    <tr>
                        <td>{{ $line->orderItem->product_name }}</td>
                        <td class="text-end num">₹{{ number_format($line->orderItem->price, 2) }}</td>
                        <td class="text-end num">{{ $line->quantity }}</td>
                        <td class="text-end num">₹{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><th colspan="3" class="text-end">Return amount</th><th class="text-end num fs-5">₹{{ number_format($lineTotal, 2) }}</th></tr></tfoot>
            </table>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header">Actions</div><div class="card-body d-grid gap-2">
            @if ($returnRequest->status === 'requested')
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Approve
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Reject
                </button>
            @elseif ($returnRequest->status === 'approved')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#returnedModal">
                    <i class="bi bi-box-seam me-1" aria-hidden="true"></i>Mark returned
                </button>
            @elseif ($returnRequest->status === 'returned')
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#refundModal">
                    <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Refund
                </button>
            @elseif ($returnRequest->status === 'rejected')
                <div class="text-muted small">Rejected: {{ $returnRequest->rejection_reason }}</div>
            @elseif ($returnRequest->status === 'refunded')
                <div class="text-muted small">
                    Refunded ₹{{ number_format($returnRequest->refund_amount, 2) }} via {{ ucfirst($returnRequest->refund_method) }}.
                    @if ($returnRequest->refund_reference)
                        <div>Reference: {{ $returnRequest->refund_reference }}</div>
                    @endif
                </div>
            @endif
        </div></div>
    </div>
</div>
<a href="{{ route('admin.returns.index') }}" class="btn btn-link mt-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to returns</a>

{{-- Approve --}}
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Approve return</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">Approve this return? The refund amount will be ₹{{ number_format($lineTotal, 2) }}, for the selected items only.</div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <form method="POST" action="{{ route('admin.returns.approve', $returnRequest) }}">
                @csrf
                <button type="submit" class="btn btn-success">Approve</button>
            </form>
        </div>
    </div></div>
</div>

{{-- Reject --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <form method="POST" action="{{ route('admin.returns.reject', $returnRequest) }}">
        @csrf
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Reject return</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="rejection_reason">Reason for rejection</label>
                <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3" maxlength="1000" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </div></div>
    </form>
</div>

{{-- Mark returned --}}
<div class="modal fade" id="returnedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Mark returned</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">Confirm the item has been physically received back?</div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <form method="POST" action="{{ route('admin.returns.mark-returned', $returnRequest) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Mark returned</button>
            </form>
        </div>
    </div></div>
</div>

{{-- Refund --}}
<div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
    @if ($order->payment_method === 'card' && $order->razorpay_payment_id)
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Refund via Razorpay</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">Refund ₹{{ number_format($returnRequest->refund_amount, 2) }} to the original card through Razorpay?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('admin.returns.refund', $returnRequest) }}">
                    @csrf
                    <button type="submit" class="btn btn-success">Refund via Razorpay</button>
                </form>
            </div>
        </div></div>
    @else
        <form method="POST" action="{{ route('admin.returns.refund', $returnRequest) }}">
            @csrf
            <div class="modal-dialog"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Record manual refund</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <p class="text-muted">This order was paid {{ $order->payment_method === 'card' ? 'by card, but no payment id was recorded' : 'by cash on delivery' }}, so record the refund reference after paying the customer back manually.</p>
                    <label class="form-label" for="refund_reference">Refund reference</label>
                    <input id="refund_reference" name="refund_reference" class="form-control" maxlength="255" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record refund</button>
                </div>
            </div></div>
        </form>
    @endif
</div>
@endsection
