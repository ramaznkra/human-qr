@php
    $adminLogo = \App\Support\SiteBranding::logoUrl();
    $adminLogoFallback = \App\Support\SiteBranding::faviconSvgUrl();
    $staffIsAdmin = $staffIsAdmin ?? session('admin_role', 'admin') === 'admin';
    $staffIsCashier = $staffIsCashier ?? session('admin_role') === 'cashier';
@endphp

<aside class="admin-sidebar flex w-64 shrink-0 flex-col justify-between border-r border-zinc-950 bg-[#111111] p-4 text-zinc-100">
    <div class="min-h-0 flex-1 overflow-hidden">
        <div class="admin-sidebar__brand mb-6 border-b border-zinc-900 py-4 text-center">
            <div class="mb-3 flex justify-center">
                <img
                    src="{{ $adminLogo }}"
                    alt="{{ $settings['venue_name'] }}"
                    class="admin-sidebar__logo h-14 w-auto object-contain"
                    loading="eager"
                    decoding="async"
                    onerror="this.onerror=null;this.src='{{ $adminLogoFallback }}';"
                >
            </div>
            <span class="block text-xs font-bold uppercase tracking-widest text-[#C6A046]">{{ $settings['venue_name'] }}</span>
            <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-wider text-zinc-500">Yönetim Paneli</span>
            @if($staffIsCashier)
            <span class="mt-2 inline-block text-[10px] font-semibold uppercase tracking-wider text-[#C6A046]">Kasa Paneli</span>
            @endif
        </div>

        <nav class="admin-sidebar__nav space-y-1.5 overflow-y-auto pr-1" aria-label="Admin menü">
            @if($staffIsAdmin)
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span>📊 Panel</span>
            </a>
            @elseif($staffIsCashier)
            <a href="{{ route('admin.live-orders.index') }}" class="sidebar-link {{ request()->routeIs('admin.live-orders.*') ? 'active' : '' }}">
                <span>⚡ Kasa · Canlı</span>
            </a>
            @endif

            @unless($staffIsCashier)
            <div class="admin-sidebar__divider" role="separator"></div>
            <a href="{{ route('admin.live-orders.index') }}" class="sidebar-link sidebar-link--live {{ request()->routeIs('admin.live-orders.*') ? 'active' : '' }}">
                <span class="sidebar-link__main">
                    <span>⚡ Canlı Siparişler</span>
                </span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="sidebar-link {{ request()->routeIs('admin.orders.index', 'admin.orders.show') ? 'active' : '' }}">
                <span>📋 Siparişler</span>
            </a>
            <a href="{{ route('admin.orders.archive') }}" class="sidebar-link {{ request()->routeIs('admin.orders.archive') ? 'active' : '' }}">
                <span>🗄️ Geçmiş Adisyonlar</span>
            </a>

            <div class="admin-sidebar__divider" role="separator"></div>
            <a href="{{ route('admin.categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <span>🗂️ Kategoriler</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="sidebar-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <span>🍔 Ürünler</span>
            </a>
            <a href="{{ route('admin.tables.index') }}" class="sidebar-link {{ request()->routeIs('admin.tables.*') ? 'active' : '' }}">
                <span>🪑 Masalar</span>
            </a>
            @if($staffIsAdmin)
            <a href="{{ route('admin.waiters.index') }}" class="sidebar-link {{ request()->routeIs('admin.waiters.*') ? 'active' : '' }}">
                <span>👤 Personel</span>
            </a>
            <a href="{{ route('admin.settings.edit') }}" class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <span>⚙️ Ayarlar</span>
            </a>
            @endif

            <div class="admin-sidebar__divider" role="separator"></div>
            <a href="{{ route('admin.cafe-galleries.index') }}" class="sidebar-link {{ request()->routeIs('admin.cafe-galleries.*') ? 'active' : '' }}">
                <span>📸 Social Spotted</span>
            </a>
            <a href="{{ route('admin.slides.index') }}" class="sidebar-link {{ request()->routeIs('admin.slides.*') ? 'active' : '' }}">
                <span>📺 Ekran Slaytları</span>
            </a>

            <div class="admin-sidebar__divider" role="separator"></div>
            <a href="{{ route('menu.index') }}" target="_blank" rel="noopener" class="sidebar-link sidebar-link--external">
                <span>Menüyü Gör ↗</span>
            </a>
            @if($staffIsAdmin)
            <a href="{{ route('display.index') }}" target="_blank" rel="noopener" class="sidebar-link sidebar-link--external">
                <span>Ekranı Aç ↗</span>
            </a>
            @endif
            @endunless
        </nav>
    </div>

    <form action="{{ route('admin.logout') }}" method="POST" class="mt-4 shrink-0">
        @csrf
        <button type="submit" class="sidebar-link sidebar-link--logout w-full rounded-xl border border-zinc-800 bg-[#1A1A1A] py-3 text-xs font-medium text-zinc-400 transition-all hover:border-red-900/50 hover:bg-red-950/30 hover:text-red-400">
            🚪 Sistemden Çıkış
        </button>
    </form>
</aside>
