{{-- ESKİ DÜZEN (dikey Spotify + Instagram şeridi). Geri almak için index.blade.php içinde include'u buna çevirin. --}}
@if($spotifyUrl !== '' || $instagramUrl !== '')
<footer class="menu-social-footer px-5" aria-label="{{ __('menu.social_links_aria') }}">
    @if($spotifyUrl !== '')
    <div class="mt-12 flex items-center gap-4 rounded-2xl border border-white/5 bg-[#262220]/40 p-4 backdrop-blur-md md:mt-16">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-[#1DB954]/25 bg-[#1DB954]/15" aria-hidden="true">
            <svg class="h-7 w-7 text-[#1DB954]" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="Spotify">
                <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="font-bold text-gray-100">{{ $settings['spotify_title'] ?? __('menu.spotify_title_fallback') }}</h2>
            <p class="mt-0.5 text-xs text-[#D4C5B9]">{{ __('menu.spotify_desc') }}</p>
        </div>
        <a href="{{ $spotifyUrl }}" target="_blank" rel="noopener noreferrer" class="shrink-0 rounded-full bg-[#E67E22] px-4 py-2 text-xs font-semibold text-white shadow-md transition-all hover:scale-105">{{ __('menu.spotify_open') }}</a>
    </div>
    @endif
    @if($instagramUrl !== '')
    <a href="{{ $instagramUrl }}" target="_blank" rel="noopener noreferrer" class="menu-instagram-strip my-8 block border-y border-white/5 py-4 transition-colors hover:border-[#E67E22]/20 hover:bg-white/[0.02]">
        <p class="text-center text-sm font-light tracking-wide text-[#D4C5B9]">
            {{ __('menu.instagram_strip_text') }} <span class="text-[#E67E22]/90">{{ $instagramHandle }}</span> ✨ #HumanSocialPerson
        </p>
    </a>
    @endif
</footer>
@endif
