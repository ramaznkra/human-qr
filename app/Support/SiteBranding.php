<?php

namespace App\Support;

class SiteBranding
{
    public static function defaultVenueName(): string
    {
        return 'Human Cafe';
    }

    public static function defaultBrandMark(): string
    {
        return 'Human Cafe';
    }

    public static function logoPngPath(): ?string
    {
        $path = public_path('images/human-logo.png');

        return is_file($path) ? $path : null;
    }

    public static function logoSvgPath(): string
    {
        return public_path('icons/human-logo.svg');
    }

    public static function logoUrl(): string
    {
        $png = self::logoPngPath();
        if ($png !== null) {
            return asset('images/human-logo.png').'?v='.filemtime($png);
        }

        $svg = self::logoSvgPath();

        return asset('icons/human-logo.svg').(is_file($svg) ? '?v='.filemtime($svg) : '');
    }

    public static function faviconSvgUrl(): string
    {
        $svg = self::logoSvgPath();

        return asset('icons/human-logo.svg').(is_file($svg) ? '?v='.filemtime($svg) : '');
    }

    public static function faviconPngUrl(): string
    {
        return self::versionedAsset('icons/favicon-32.png');
    }

    public static function favicon16Url(): string
    {
        return self::versionedAsset('icons/favicon-16.png');
    }

    public static function appleTouchIconUrl(): string
    {
        return self::versionedAsset('icons/apple-touch-icon.png');
    }

    public static function pwaIcon192Url(): string
    {
        return self::versionedAsset('icons/icon-192.png');
    }

    public static function pwaIcon512Url(): string
    {
        return self::versionedAsset('icons/icon-512.png');
    }

    /**
     * PWA manifest ikonları: göreli yol, sorgu parametresi yok.
     * Windows/Edge masaüstü kısayolu ?v= içeren URL'leri reddedebilir.
     *
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    public static function manifestIcons(): array
    {
        return [
            [
                'src' => self::manifestIconSrc('icons/apple-touch-icon.png'),
                'sizes' => '180x180',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => self::manifestIconSrc('icons/icon-192.png'),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => self::manifestIconSrc('icons/icon-256.png'),
                'sizes' => '256x256',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => self::manifestIconSrc('icons/icon-512.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => self::manifestIconSrc('icons/icon-512-maskable.png'),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ];
    }

    private static function manifestIconSrc(string $relativePath): string
    {
        return '/'.ltrim($relativePath, '/');
    }

    private static function versionedAsset(string $relativePath): string
    {
        $full = public_path($relativePath);

        return asset($relativePath).(is_file($full) ? '?v='.filemtime($full) : '');
    }
}
