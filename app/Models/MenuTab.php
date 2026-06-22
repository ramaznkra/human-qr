<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Concerns\HasMenuTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MenuTab extends Model
{
    /** @use BelongsToRestaurant */
    use BelongsToRestaurant, HasMenuTranslations, HasTranslations;

    public const LAYOUT_GROUPED = 'grouped';

    public const LAYOUT_FLAT = 'flat';

    public array $translatable = ['name'];

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'slug',
        'layout',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function menuSections(): HasMany
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function directProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'menu_tab_id')->orderBy('sort_order');
    }

    public function isFlat(): bool
    {
        return ($this->layout ?? self::LAYOUT_GROUPED) === self::LAYOUT_FLAT;
    }

    public function isGrouped(): bool
    {
        return ! $this->isFlat();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
