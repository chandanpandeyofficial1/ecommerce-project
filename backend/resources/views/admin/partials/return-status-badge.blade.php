@php
    // Reuse the order status colours: requested=amber, approved=blue, rejected=red, returned=purple, refunded=green.
    $map = ['requested' => 'pending', 'approved' => 'confirmed', 'rejected' => 'cancelled', 'returned' => 'shipped', 'refunded' => 'delivered'];
    $icons = ['requested' => 'bi-hourglass-split', 'approved' => 'bi-check2-circle', 'rejected' => 'bi-x-circle', 'returned' => 'bi-box-seam', 'refunded' => 'bi-cash-coin'];
@endphp
<span class="status-badge status-{{ $map[$status] ?? 'pending' }}"><i class="bi {{ $icons[$status] ?? 'bi-circle' }}" aria-hidden="true"></i>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
