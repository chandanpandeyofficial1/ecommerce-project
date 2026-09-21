@extends('layouts.layout')
@section('title', 'Returns')
@section('crumb', 'Returns')
@section('content')
@php $current = request('status'); @endphp
<div class="status-tabs mb-3">
    <a href="{{ route('admin.returns.index') }}" class="{{ $current ? '' : 'active' }}">All<span class="count">{{ $counts->sum() }}</span></a>
    @foreach ($statuses as $status)
        <a href="{{ route('admin.returns.index', ['status' => $status]) }}" class="{{ $current === $status ? 'active' : '' }}">{{ ucfirst(str_replace('_', ' ', $status)) }}<span class="count">{{ $counts[$status] ?? 0 }}</span></a>
    @endforeach
</div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Order</th><th>Customer</th><th>Reason</th><th>Requested</th><th>Status</th><th>Refund</th><th></th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td class="num"><a href="{{ route('admin.orders.show', $r->order_id) }}">#{{ $r->order_id }}</a></td>
                <td>{{ $r->order->user?->name }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $r->reason)) }}</td>
                <td class="text-muted">{{ $r->created_at->format('d M Y H:i') }}</td>
                <td>@include('admin.partials.return-status-badge', ['status' => $r->status])</td>
                <td class="text-muted">
                    @if ($r->refund_method)
                        {{ ucfirst($r->refund_method) }} · ₹{{ number_format($r->refund_amount, 2) }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-end"><a href="{{ route('admin.returns.show', $r) }}" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><i class="bi bi-arrow-return-left" aria-hidden="true"></i>No return requests found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
@endsection
