@if($order->isWaiterOrder())
<span class="admin-waiter-badge" title="Garson tarafından girildi">🤵 Garson Siparişi</span>
@else
<span class="admin-source-badge admin-source-badge--qr">QR Menü</span>
@endif
