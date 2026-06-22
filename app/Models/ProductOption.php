<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ProductOption extends Model
{
    use HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = [
        'product_option_group_id',
        'name',
        'price_modifier',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_modifier' => MoneyCast::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return (string) ($this->getTranslation('name', $locale, false)
            ?: $this->getTranslation('name', 'tr', false)
            ?: '');
    }
}
