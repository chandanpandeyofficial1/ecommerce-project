@extends('layouts.layout')
@section('title', 'Dashboard')
@section('crumb', 'Dashboard')
@section('content')
@php
    $stats = [
        ['Total orders', $totalOrders, 'bi-receipt', 'tone-indigo'],
        ['Pending orders', $pendingOrders, 'bi-hourglass-split', 'tone-amber'],
        ['Revenue (delivered)', '₹'.number_format($revenue, 2), 'bi-cash-stack', 'tone-green'],
        ['Low stock products', $lowStockCount, 'bi-exclamation-triangle', 'tone-red'],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach ($stats as [$label, $value, $icon, $tone])
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon {{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Orders in the last 7 days</div>
            <div class="card-body"><div style="position:relative;height:280px"><canvas id="ordersChart" aria-label="Orders in the last 7 days" role="img"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">Low stock <span class="text-muted small fw-normal">{{ \App\Models\Product::LOW_STOCK_LIMIT }} or less</span></div>
            @forelse ($lowStock as $product)
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom" style="border-color:var(--card-border)!important">
                    <span class="text-truncate me-2">{{ $product->name }}</span>
                    <span class="d-flex align-items-center gap-2">@include('admin.partials.stock-pill', ['stock' => $product->stock])
                        <a href="{{ route('admin.products.edit', $product) }}" class="small">Edit</a></span>
                </div>
            @empty
                <div class="empty"><i class="bi bi-emoji-smile" aria-hidden="true"></i>Nothing is running low. Nice.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">Latest orders <a href="{{ route('admin.orders.index') }}" class="small fw-normal">View all</a></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>ID</th><th>Customer</th><th class="text-end">Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            @forelse ($latestOrders as $order)
                <tr>
                    <td class="num">#{{ $order->id }}</td>
                    <td>{{ $order->user?->name }}</td>
                    <td class="text-end num">₹{{ number_format($order->total, 2) }}</td>
                    <td>@include('admin.partials.status-badge', ['status' => $order->status])</td>
                    <td class="text-muted">{{ $order->created_at->format('d M Y H:i') }}</td>
                    <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-inbox" aria-hidden="true"></i>No orders yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    // Draw the chart again when the theme changes so colours stay readable
    (function () {
        var chart;
        var labels = @json($chart['labels']);
        var values = @json($chart['values']);
        window.redrawChart = function () {
            if (!window.Chart) return;
            var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            var grid = dark ? 'rgba(148,163,184,.15)' : 'rgba(15,23,42,.08)';
            var tick = dark ? '#94a3b8' : '#64748b';
            if (chart) chart.destroy();
            chart = new Chart(document.getElementById('ordersChart'), {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'Orders', data: values, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.18)', fill: true, tension: .35, pointRadius: 4 }] },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: grid }, ticks: { color: tick } },
                        y: { beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } }
                    }
                }
            });
        };
        window.redrawChart();
    })();
</script>
@endpush
