<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Concerns\HasMenuTranslations;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    /** @use BelongsToRestaurant — App\Models\Scopes\RestaurantScope tenant izolasyonu */
    use BelongsToRestaurant, HasMenuTranslations, HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'restaurant_id', 'category_id', 'menu_section_id', 'menu_tab_id', 'type', 'name', 'description',
        'station', 'price', 'image', 'badge', 'sort_order', 'is_available', 'in_stock',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'is_available' => 'boolean',
            'in_stock' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function menuSection(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class);
    }

    public function menuTab(): BelongsTo
    {
        return $this->belongsTo(MenuTab::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cartOptionsPayload(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        return $this->optionGroups
            ->map(fn (ProductOptionGroup $group) => [
                'id' => $group->id,
                'name' => $group->localizedName($locale),
                'type' => $group->type,
                'required' => $group->required,
                'options' => $group->options
                    ->filter(fn (ProductOption $option) => $option->is_active)
                    ->map(fn (ProductOption $option) => [
                        'id' => $option->id,
                        'name' => $option->localizedName($locale),
                        'price' => Money::normalize($option->price_modifier),
                        'default' => $option->is_default,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http')
            ? $this->image
            : asset('storage/'.$this->image);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->orderBy('sort_order');
    }

    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    public function variationPreviewText(?string $locale = null): ?string
    {
        $locale = $locale ?? 'tr';
        $this->loadMissing(['optionGroups.options']);

        if ($this->optionGroups->isEmpty()) {
            return null;
        }

        return $this->optionGroups
            ->map(function (ProductOptionGroup $group) use ($locale) {
                $options = $group->options
                    ->filter(fn (ProductOption $option) => $option->is_active)
                    ->map(fn (ProductOption $option) => $option->localizedName($locale))
                    ->filter()
                    ->join(', ');

                return $options !== '' ? $group->localizedName($locale).': '.$options : null;
            })
            ->filter()
            ->implode(' · ');
    }

    /** İşlem istasyonu — öncelik kategori tipi, yedek ürün tipi. */
    public function stationType(): string
    {
        $this->loadMissing('category:id,type');

        $station = self::normalizeStation($this->station);
        if ($station !== null) {
            return $station;
        }

        $fromCategory = self::normalizeStation($this->category?->type);
        if ($fromCategory !== null) {
            return $fromCategory;
        }

        return self::normalizeStation($this->type) ?? Category::TYPE_KITCHEN;
    }

    /**
     * @return list<string>
     */
    public static function stationOptions(): array
    {
        return ['kitchen', 'bar', 'hookah', 'service'];
    }

    /**
     * @return array<string, string>
     */
    public static function stationLabels(): array
    {
        return [
            'kitchen' => 'Mutfak',
            'bar' => 'Bar',
            'hookah' => 'Nargile',
            'service' => 'Servis',
        ];
    }

    public static function normalizeStation(?string $station): ?string
    {
        return match ($station) {
            'kitchen', 'bar', 'hookah', 'service' => $station,
            'nargile' => 'hookah',
            'retail' => 'service',
            default => null,
        };
    }
}
