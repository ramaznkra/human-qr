<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ProductOptionGroup extends Model
{
    use BelongsToRestaurant, HasTranslations;

    public const TYPE_SINGLE = 'single';

    public const TYPE_MULTIPLE = 'multiple';

    public array $translatable = ['name'];

    protected $fillable = [
        'restaurant_id',
        'product_id',
        'name',
        'type',
        'required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return (string) ($this->getTranslation('name', $locale, false)
            ?: $this->getTranslation('name', 'tr', false)
            ?: '');
    }
}
