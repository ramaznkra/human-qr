<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Concerns\HasMenuTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    public const TYPE_KITCHEN = 'kitchen';

    public const TYPE_BAR = 'bar';

    public const TYPE_NARGILE = 'nargile';

    public const TYPE_RETAIL = 'retail';

    /** @return array<int, string> */
    public static function stationTypes(): array
    {
        return [
            self::TYPE_KITCHEN,
            self::TYPE_BAR,
            self::TYPE_NARGILE,
            self::TYPE_RETAIL,
        ];
    }

    /** @use BelongsToRestaurant — App\Models\Scopes\RestaurantScope tenant izolasyonu */
    use BelongsToRestaurant, HasMenuTranslations, HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'restaurant_id', 'name', 'description', 'slug', 'type', 'icon', 'image', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_BAR => 'Bar / İçecek',
            self::TYPE_NARGILE => 'Nargile İstasyonu',
            self::TYPE_RETAIL => 'Kasa / Stand',
            default => 'Mutfak / Yemek',
        };
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort_order');
    }

    public function menuTabs(): HasMany
    {
        return $this->hasMany(MenuTab::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Aktif sekmeler — aynı slug / görünen isim yalnızca bir kez (UI tekrarını önler).
     *
     * @return Collection<int, MenuTab>
     */
    public function distinctActiveMenuTabs(?string $locale = null): Collection
    {
        $locale = $locale ?? app()->getLocale();

        return $this->menuTabs
            ->filter(fn (MenuTab $tab) => $tab->is_active)
            ->sortBy(fn (MenuTab $tab) => [$tab->sort_order, $tab->id])
            ->unique(fn (MenuTab $tab) => $tab->slug)
            ->values();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->resolvePublicImagePath($this->image)
            ?? $this->resolvePublicImagePath($this->defaultPhotoPath());
    }

    /** QR menü kategori blokları — örnek SVG yerine fotoğraf tercih eder. */
    public function getHubImageUrlAttribute(): ?string
    {
        $photo = $this->defaultPhotoPath();
        if ($photo && file_exists(public_path($photo))) {
            return asset($photo);
        }

        $stored = $this->image;
        if (filled($stored) && ! str_contains((string) $stored, '/samples/')) {
            return $this->resolvePublicImagePath($stored);
        }

        return $this->image_url;
    }

    protected function defaultPhotoPath(): ?string
    {
        $photos = [
            'yiyecek' => 'images/categories/photos/yiyecek.jpg',
            'icecek' => 'images/categories/photos/icecek.jpg',
            'nargile' => 'images/categories/photos/nargile.jpg',
            'okey' => 'images/categories/photos/okey.jpg',
            'biblo' => 'images/categories/photos/biblo.jpg',
        ];

        return $photos[$this->slug ?? ''] ?? null;
    }

    protected function resolvePublicImagePath(?string $image): ?string
    {
        if (! filled($image)) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, 'images/')) {
            if (file_exists(public_path($image))) {
                return asset($image);
            }

            return null;
        }

        if (Storage::disk('public')->exists($image)) {
            return asset('storage/'.$image);
        }

        return null;
    }
}
