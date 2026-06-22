<?php

namespace App\Support;

use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class MenuLocale
{
    public const LOCALES = ['tr', 'en', 'ru'];

    public const DEFAULT = 'tr';

    public const LABELS = [
        'tr' => 'TR',
        'en' => 'EN',
        'ru' => 'RU',
    ];

    /**
     * QR menü sayfası: yalnızca ?lang= ile seçim; aksi halde her açılışta TR (cookie yok sayılır).
     */
    public static function resolveForMenuPage(Request $request): string
    {
        if ($request->filled('lang')) {
            $candidate = $request->input('lang') ?? $request->query('lang');

            if (in_array($candidate, self::LOCALES, true)) {
                return $candidate;
            }
        }

        return self::DEFAULT;
    }

    /**
     * Sipariş durumu, API ve menü içi istekler: URL → cookie → session.
     */
    public static function resolve(Request $request): string
    {
        $candidate = $request->input('lang')
            ?? $request->query('lang')
            ?? $request->cookie('menu_lang')
            ?? session('menu_lang', self::DEFAULT);

        return in_array($candidate, self::LOCALES, true) ? $candidate : self::DEFAULT;
    }

    public static function apply(Request $request, string $locale): void
    {
        app()->setLocale($locale);
        session(['menu_lang' => $locale]);
        Cookie::queue('menu_lang', $locale, 60 * 24 * 365);
    }

    public static function menuUrl(?Table $table = null, ?string $locale = null, array $extraQuery = []): string
    {
        $locale = $locale ?? app()->getLocale();
        $query = array_merge(['lang' => $locale], $extraQuery);

        if ($table?->uuid) {
            $base = route('menu.table', ['uuid' => $table->uuid]);
        } elseif ($table?->qr_token) {
            $base = route('menu.legacy', ['token' => $table->qr_token]);
        } else {
            $base = route('menu.index');
        }

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($query);
    }
}
