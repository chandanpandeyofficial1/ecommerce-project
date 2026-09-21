@php
    $icons = ['pending' => 'bi-hourglass-split', 'confirmed' => 'bi-check2-circle', 'shipped' => 'bi-truck', 'delivered' => 'bi-box-seam', 'cancelled' => 'bi-x-circle'];
@endphp
<span class="status-badge status-{{ $status }}"><i class="bi {{ $icons[$status] ?? 'bi-circle' }}" aria-hidden="true"></i>{{ ucfirst($status) }}</span>
