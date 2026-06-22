<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Support\CurrentRestaurant;
use App\Support\SiteBranding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use BelongsToRestaurant;

    protected $fillable = ['restaurant_id', 'key', 'value'];

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'venue_name' => SiteBranding::defaultVenueName(),
            'brand_mark' => SiteBranding::defaultBrandMark(),
            'venue_slogan' => 'Human Social People',
            'venue_tagline' => 'Human Social People',
            'currency' => '₺',
            'order_enabled' => '1',
            'display_interval' => '10',
            'show_motto_banner' => '1',
            'show_wifi_banner' => '1',
        ];
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $restaurantId = CurrentRestaurant::resolveId() ?? 0;
        $cacheKey = "setting.{$restaurantId}.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();
            $value = $setting?->value;

            if ($value !== null && $value !== '') {
                return $value;
            }

            return $default ?? static::defaults()[$key] ?? null;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        $restaurantId = CurrentRestaurant::resolveId();
        if ($restaurantId === null) {
            throw new \RuntimeException('Setting yazmak için restoran bağlamı gerekli.');
        }

        static::updateOrCreate(
            ['restaurant_id' => $restaurantId, 'key' => $key],
            ['value' => $value],
        );
        static::clearCache();
    }

    public static function allCached(): array
    {
        $restaurantId = CurrentRestaurant::resolveId() ?? 0;

        return Cache::remember("settings.all.{$restaurantId}", 3600, function () {
            $stored = static::query()->pluck('value', 'key')->toArray();
            $stored = array_filter($stored, static fn ($value) => $value !== null && $value !== '');

            return array_merge(static::defaults(), $stored);
        });
    }

    public static function clearCache(): void
    {
        $restaurantId = CurrentRestaurant::resolveId() ?? 0;
        Cache::forget("settings.all.{$restaurantId}");
        foreach (static::query()->pluck('key') as $key) {
            Cache::forget("setting.{$restaurantId}.{$key}");
        }
    }
}
