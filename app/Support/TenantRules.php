<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class TenantRules
{
    /**
     * Eloquent global scope ile uyumlu exists kuralı.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function existsModel(string $modelClass, string $column = 'id'): Exists
    {
        return self::scopedRule(Rule::exists((new $modelClass)->getTable(), $column));
    }

    /**
     * Tenant içinde benzersizlik (ör. masa numarası).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function uniqueModel(string $modelClass, string $column, ?int $ignoreId = null): Unique
    {
        $rule = self::scopedRule(Rule::unique((new $modelClass)->getTable(), $column));

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    /**
     * @param  Exists|Unique  $rule
     * @return Exists|Unique
     */
    private static function scopedRule(Exists|Unique $rule): Exists|Unique
    {
        $restaurantId = CurrentRestaurant::resolveId();

        return $rule->where(
            fn ($query) => $restaurantId !== null
                ? $query->where('restaurant_id', $restaurantId)
                : $query->whereRaw('1 = 0'),
        );
    }

    /**
     * @deprecated existsModel() kullanın.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function existsInCurrentRestaurant(string $table, string $column = 'id'): Exists
    {
        $restaurantId = CurrentRestaurant::resolveId();

        return Rule::exists($table, $column)->where(
            fn ($query) => $restaurantId !== null
                ? $query->where("{$table}.restaurant_id", $restaurantId)
                : $query->whereRaw('1 = 0'),
        );
    }
}
