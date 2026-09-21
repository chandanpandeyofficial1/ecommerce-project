{{-- Cash orders are paid on delivery; card orders show their online payment result --}}
@if ($order->payment_method !== 'card')
    <span class="pill">Pay on delivery</span>
@elseif ($order->payment_status === 'paid')
    <span class="pill pill-ok">Paid</span>
@elseif ($order->payment_status === 'failed')
    <span class="pill pill-bad">Failed</span>
@else
    <span class="pill pill-warn">Unpaid</span>
@endif
