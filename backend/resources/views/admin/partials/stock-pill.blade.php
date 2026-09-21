@php
    $cls = $stock <= 0 ? 'pill-bad' : ($stock <= \App\Models\Product::LOW_STOCK_LIMIT ? 'pill-warn' : 'pill-ok');
@endphp
<span class="pill {{ $cls }} num">{{ $stock }}</span>
