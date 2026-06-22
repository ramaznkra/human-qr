@php
    $venueName = $settings['venue_name'];
    $brandMark = strtoupper(mb_substr($venueName, 0, 1));
    $logoUrl = \App\Support\SiteBranding::logoUrl();
    $wifiPassword = ($settings['show_wifi_banner'] ?? '1') === '1' ? trim($settings['wifi_password'] ?? '') : '';
@endphp
<header class="menu-top-bar sticky top-0 z-50 border-b border-zinc-900/60 bg-[#0F0F0F]/90 shadow-xl shadow-black/20 backdrop-blur-md">
    <div class="menu-top-bar__inner flex items-center justify-between gap-4 px-5 py-4">
        <div class="flex min-w-0 items-center gap-4">
            <div class="menu-top-bar__mark shrink-0">
                @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="menu-top-bar__mark-img">
                @else
                <span class="menu-top-bar__mark-letter">{{ $brandMark }}</span>
                @endif
            </div>
            <div class="min-w-0">
                <p class="truncate text-xs font-black uppercase tracking-[0.25em] text-zinc-100">{{ strtoupper($venueName) }}</p>
                @if($table)
                <p class="menu-top-bar__meta truncate text-[9px] font-bold uppercase tracking-widest text-[#C6A046]">
                    {{ __('menu.lounge_table', ['number' => $table->number]) }}
                </p>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @include('menu.partials.lang-switcher', ['table' => $table, 'locale' => $locale])
            @if($wifiPassword !== '')
            <button
                type="button"
                class="menu-top-bar__wifi"
                id="menuTopWifiCopy"
                data-wifi-password="{{ $wifiPassword }}"
                data-copy-done="{{ __('menu.wifi_copied') }}"
            >
                <span class="menu-top-bar__wifi-dot" aria-hidden="true"></span>
                <span class="menu-top-bar__wifi-label">{{ __('menu.wifi_copy_short') }}</span>
            </button>
            @endif
        </div>
    </div>
</header>

@once
@push('scripts')
<script>
(function () {
    var btn = document.getElementById('menuTopWifiCopy');
    if (!btn) return;
    var password = btn.getAttribute('data-wifi-password') || '';
    var doneLabel = btn.getAttribute('data-copy-done') || 'OK';
    var label = btn.querySelector('.menu-top-bar__wifi-label');
    var dot = btn.querySelector('.menu-top-bar__wifi-dot');
    var defaultLabel = label ? label.textContent : 'Wi-Fi';

    btn.addEventListener('click', async function () {
        if (!password) return;
        try {
            await navigator.clipboard.writeText(password);
        } catch {
            var ta = document.createElement('textarea');
            ta.value = password;
            ta.setAttribute('readonly', '');
            ta.style.position = 'absolute';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        if (label) label.textContent = doneLabel;
        if (dot) dot.classList.add('menu-top-bar__wifi-dot--done');
        btn.classList.add('menu-top-bar__wifi--done');
        setTimeout(function () {
            btn.classList.remove('menu-top-bar__wifi--done');
            if (dot) dot.classList.remove('menu-top-bar__wifi-dot--done');
            if (label) label.textContent = defaultLabel;
        }, 2000);
    });
})();
</script>
@endpush
@endonce
