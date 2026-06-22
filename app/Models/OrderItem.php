<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_SERVED = 'served';

    public const STATUS_CANCELLED = 'cancelled';

    /** unit_price: sipariş oluşturulurken ürün+varyasyon fiyatının anlık kopyası (admin fiyat değişse bile geçmiş ciro korunur). */
    protected $fillable = [
        'order_id', 'product_id', 'quantity', 'unit_price', 'product_name', 'notes', 'preparation_status', 'options',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'options' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliveryTasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class);
    }

    public function getSubtotalAttribute(): string
    {
        return Money::mul($this->unit_price ?? '0', $this->quantity);
    }

    /**
     * @return list<string>
     */
    public function optionLabelLines(): array
    {
        $options = $this->options;
        if (! is_array($options) || $options === []) {
            return [];
        }

        return collect($options)
            ->map(fn ($row) => is_array($row) ? ($row['name'] ?? null) : null)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values()
            ->all();
    }
}
