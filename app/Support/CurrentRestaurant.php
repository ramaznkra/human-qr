<?php

namespace App\Support;

use App\Models\Restaurant;

/**
 * İstek bazlı aktif restoran bağlamı (SaaS tenant context).
 */
class CurrentRestaurant
{
    private static ?Restaurant $restaurant = null;

    public static function set(?Restaurant $restaurant): void
    {
        self::$restaurant = $restaurant;
    }

    public static function get(): ?Restaurant
    {
        return self::$restaurant;
    }

    public static function id(): ?int
    {
        return self::$restaurant?->id;
    }

    /**
     * Global scope ve tenant doğrulamaları için restoran kimliği.
     *
     * Öncelik: istek bağlamı → personel oturumu → kiosk → auth()->user()->restaurant_id
     */
    public static function resolveId(): ?int
    {
        if ($id = self::id()) {
            return $id;
        }

        if ($id = session('admin_restaurant_id')) {
            return (int) $id;
        }

        if ($id = session('kiosk_restaurant_id')) {
            return (int) $id;
        }

        $user = auth()->user();
        if ($user !== null && ! empty($user->restaurant_id)) {
            return (int) $user->restaurant_id;
        }

        return null;
    }

    public static function has(): bool
    {
        return self::$restaurant !== null;
    }

    public static function clear(): void
    {
        self::$restaurant = null;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Restaurant $restaurant, callable $callback): mixed
    {
        $previous = self::$restaurant;
        self::$restaurant = $restaurant;

        try {
            return $callback();
        } finally {
            self::$restaurant = $previous;
        }
    }
}
