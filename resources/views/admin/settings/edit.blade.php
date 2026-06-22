@extends('layouts.admin')
@section('title', 'Ayarlar')
@section('page_heading', 'Ayarlar')
@section('section_label', 'Yönetim')
@section('content')
<div class="max-w-4xl space-y-6">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="admin-card">
            <h3 class="mb-1">Mekan Bilgileri</h3>
            <p class="admin-text-muted mb-4">Menü başlığı, kasa ekranı ve tüm panellerde görünen mekan adı.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label">Mekan Adı</label>
                    <input type="text" name="venue_name" value="{{ $settings['venue_name'] }}" class="form-input" placeholder="Human Cafe">
                </div>
                <div>
                    <label class="form-label">Menü Logo Yazısı</label>
                    <input type="text" name="brand_mark" value="{{ $settings['brand_mark'] }}" class="form-input" placeholder="Human Cafe">
                </div>
                <div>
                    <label class="form-label">Para Birimi</label>
                    <input type="text" name="currency" value="{{ $settings['currency'] ?? '₺' }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Menü Alt Slogan</label>
                    <input type="text" name="venue_tagline" value="{{ $settings['venue_tagline'] }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Slogan (kısa)</label>
                    <input type="text" name="venue_slogan" value="{{ $settings['venue_slogan'] }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Telefon</label>
                    <input type="text" name="venue_phone" value="{{ $settings['venue_phone'] ?? '' }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Adres</label>
                    <input type="text" name="venue_address" value="{{ $settings['venue_address'] ?? '' }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Sipariş Özelliği</label>
                    <select name="order_enabled" class="form-input">
                        <option value="1" {{ ($settings['order_enabled'] ?? '1') == '1' ? 'selected' : '' }}>Açık</option>
                        <option value="0" {{ ($settings['order_enabled'] ?? '1') == '0' ? 'selected' : '' }}>Kapalı (sadece menü)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">TV Ekran Geçiş Süresi (sn)</label>
                    <input type="number" name="display_interval" value="{{ $settings['display_interval'] ?? 10 }}" min="3" max="60" class="form-input">
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h3 class="mb-1">QR Menü — Wi-Fi</h3>
            <p class="admin-text-muted mb-4">Şifre menünün <strong>üst barındaki Wi-Fi butonuna</strong> tıklanınca kopyalanır. Ayrı widget kartı gösterilmez.</p>
            <div class="space-y-3">
                <div>
                    <label class="form-label">Wi-Fi Şifresi</label>
                    <input type="text" name="wifi_password" value="{{ $settings['wifi_password'] ?? '' }}" class="form-input" placeholder="HumanSocial2026" autocomplete="off">
                </div>
                <label class="admin-form-check">
                    <input type="hidden" name="show_wifi_banner" value="0">
                    <input type="checkbox" name="show_wifi_banner" value="1" {{ ($settings['show_wifi_banner'] ?? '1') == '1' ? 'checked' : '' }} class="rounded border-zinc-700 bg-[#141414] text-[#C6A046] focus:ring-[#C6A046]/30">
                    Menü üst barında Wi-Fi butonunu göster
                </label>
            </div>
        </div>

        <div class="admin-card">
            <h3 class="mb-1">QR Menü — Sosyal Widgetlar</h3>
            <p class="admin-text-muted mb-4">Kategorilerin altında Spotify ve Instagram kartları. Boş URL = kart gizlenir.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="form-label">Günün Sosyal Mottosu</label>
                    <input type="text" name="daily_motto" value="{{ $settings['daily_motto'] ?? '' }}" class="form-input" placeholder="Bugün sosyalleşme günü ☕">
                    <label class="admin-form-check mt-2">
                        <input type="hidden" name="show_motto_banner" value="0">
                        <input type="checkbox" name="show_motto_banner" value="1" {{ ($settings['show_motto_banner'] ?? '1') == '1' ? 'checked' : '' }} class="rounded border-zinc-700 bg-[#141414] text-[#C6A046] focus:ring-[#C6A046]/30">
                        Menüde motto banner göster
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Spotify Playlist URL</label>
                    <input type="url" name="spotify_url" value="{{ $settings['spotify_url'] ?? '' }}" class="form-input" placeholder="https://open.spotify.com/playlist/...">
                </div>
                <div>
                    <label class="form-label">Spotify Kart Başlığı</label>
                    <input type="text" name="spotify_title" value="{{ $settings['spotify_title'] ?? 'HSP Vibes' }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Instagram Profil URL</label>
                    <input type="url" name="instagram_url" value="{{ $settings['instagram_url'] ?? '' }}" class="form-input" placeholder="https://www.instagram.com/...">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Instagram Etiketi (menüde görünen)</label>
                    <input type="text" name="instagram_handle" value="{{ $settings['instagram_handle'] ?? '@ramaznkra' }}" class="form-input" placeholder="@ramaznkra">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Tüm Ayarları Kaydet</button>
        </div>
    </form>

    @isset($restaurant)
    <div class="admin-card">
        <h3 class="mb-2">Kiosk / Mutfak Ekranı Güvenliği</h3>
        <p class="admin-text-muted mb-4">Mutfak ve TV ekranı token olmadan açılmaz.</p>
        <div class="space-y-4">
            @include('admin.partials.secret-url-field', [
                'label' => 'Mutfak Kiosk URL',
                'url' => url('/mutfak').'?kiosk='.$restaurant->kitchen_token,
            ])
            @include('admin.partials.secret-url-field', [
                'label' => 'Bar Kiosk URL',
                'url' => url('/mutfak').'?kiosk='.$restaurant->kitchen_token.'&station=bar',
            ])
            @include('admin.partials.secret-url-field', [
                'label' => 'TV Ekran URL',
                'url' => url('/ekran').'?kiosk='.$restaurant->kitchen_token,
            ])
        </div>
    </div>
    @endisset
</div>
@endsection
