<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToRestaurant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use BelongsToRestaurant — App\Models\Scopes\RestaurantScope tenant izolasyonu */
    use BelongsToRestaurant;

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CARD = 'card';

    public const SOURCE_QR = 'qr';

    public const SOURCE_WAITER = 'waiter';

    public const SOURCE_KASA = 'kasa';

    /** Durumlar: mutfak/bar hazır, kasa teslim + ödeme bekliyor */
    public static function liveStatuses(): array
    {
        return [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_PENDING,
            self::STATUS_PREPARING,
            self::STATUS_READY,
            self::STATUS_DELIVERED,
        ];
    }

    /** Arşiv: tamamlandı, iptal */
    public static function archivedStatuses(): array
    {
        return [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'public_token',
        'order_number',
        'status',
        'notes',
        'total',
        'payment_method',
        'source',
        'kasa_served_through_item_id',
        'dismissed_from_live_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => MoneyCast::class,
            'dismissed_from_live_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->public_token)) {
                $order->public_token = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryTasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class);
    }

    /** Canlı panel: ödeme alınmamış teslimler dahil */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function successfulPayments(): HasMany
    {
        return $this->payments()->successful();
    }

    public function paidAmount(): string
    {
        return Money::sum($this->successfulPayments()->pluck('amount')->all());
    }

    public function remainingAmount(): string
    {
        return Money::max(Money::sub($this->total ?? '0.00', $this->paidAmount()), '0.00');
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->paidAmount(), Money::normalize($this->total ?? '0.00'), Money::SCALE) >= 0;
    }

    public function hasActivePayment(): bool
    {
        return $this->payments()->active()->exists();
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', [
                self::STATUS_PENDING_APPROVAL,
                self::STATUS_PENDING,
                self::STATUS_PREPARING,
                self::STATUS_READY,
            ])->orWhere(function (Builder $q2) {
                $q2->where('status', self::STATUS_DELIVERED)
                    ->whereNull('payment_method');
            });
        });
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_DELIVERED && $this->payment_method !== null;
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereIn('status', self::archivedStatuses());
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => 'Onay Bekliyor',
            self::STATUS_PENDING => 'Beklemede',
            self::STATUS_PREPARING => 'Hazırlanıyor',
            self::STATUS_READY => 'Masada',
            self::STATUS_DELIVERED => $this->payment_method ? 'Kapandı' : 'Afiyet Olsun',
            self::STATUS_CANCELLED => 'İptal',
            default => $this->status,
        };
    }

    public function getPaymentMethodLabelAttribute(): ?string
    {
        return match ($this->payment_method) {
            self::PAYMENT_CASH => 'Nakit',
            self::PAYMENT_CARD => 'Kart',
            default => null,
        };
    }

    /** Müşteri menüsü / sipariş takibi metinleri */
    public function getCustomerStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => __('menu.order_label.pending_approval'),
            self::STATUS_PENDING => __('menu.order_label.pending'),
            self::STATUS_PREPARING => __('menu.order_label.preparing'),
            self::STATUS_READY => __('menu.order_label.ready'),
            self::STATUS_DELIVERED => __('menu.order_label.delivered'),
            self::STATUS_CANCELLED => __('menu.order_label.cancelled'),
            default => $this->status,
        };
    }

    public function customerStatusStep(): int
    {
        return match ($this->status) {
            self::STATUS_PREPARING => 2,
            self::STATUS_READY, self::STATUS_DELIVERED => 3,
            self::STATUS_CANCELLED => 0,
            default => 1,
        };
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_KASA => 'Kasa',
            self::SOURCE_WAITER => 'Garson',
            default => 'QR Menü',
        };
    }

    public function isKasaPanelOrder(): bool
    {
        return $this->source === self::SOURCE_KASA;
    }

    public function isWaiterOrder(): bool
    {
        return $this->source === self::SOURCE_WAITER;
    }

    /** Müşteri ekranı için kısa açıklama */
    public function getCustomerStatusMessageAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => __('menu.order_msg.pending_approval'),
            self::STATUS_PENDING => __('menu.order_msg.pending'),
            self::STATUS_PREPARING => __('menu.order_msg.preparing'),
            self::STATUS_READY => __('menu.order_msg.ready'),
            self::STATUS_DELIVERED => $this->payment_method
                ? __('menu.order_msg.delivered_paid', ['method' => $this->payment_method_label])
                : __('menu.order_msg.delivered'),
            self::STATUS_CANCELLED => __('menu.order_msg.cancelled'),
            default => '',
        };
    }

    public function canTransitionTo(string $nextStatus): bool
    {
        if ($this->status === $nextStatus) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => in_array($nextStatus, [self::STATUS_PREPARING, self::STATUS_CANCELLED], true),
            self::STATUS_PENDING => in_array($nextStatus, [self::STATUS_PREPARING, self::STATUS_CANCELLED], true),
            self::STATUS_PREPARING => in_array($nextStatus, [self::STATUS_READY, self::STATUS_DELIVERED, self::STATUS_CANCELLED], true),
            self::STATUS_READY => in_array($nextStatus, [self::STATUS_DELIVERED, self::STATUS_CANCELLED], true),
            self::STATUS_DELIVERED => false,
            self::STATUS_CANCELLED => false,
            default => false,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_APPROVAL => 'warning',
            self::STATUS_PENDING => 'warning',
            self::STATUS_PREPARING => 'info',
            self::STATUS_READY => 'success',
            self::STATUS_DELIVERED => 'secondary',
            self::STATUS_CANCELLED => 'danger',
            default => 'secondary',
        };
    }
}
