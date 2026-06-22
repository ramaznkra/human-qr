@php
    $roleLabel = match (session('admin_role', 'admin')) {
        'cashier' => 'Kasa',
        'waiter' => 'Garson',
        default => 'Admin',
    };
@endphp
<header class="staff-topbar text-brand-cream">
    <div class="text-sm font-semibold">{{ $settings['venue_name'] }} · {{ $roleLabel }}</div>
    <div class="staff-topbar__meta">
        <time datetime="{{ now()->toDateString() }}">{{ now()->format('d.m.Y') }}</time>
        <span>{{ session('admin_name', 'Personel') }}</span>
    </div>
</header>
