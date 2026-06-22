<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use BelongsToRestaurant */
    use BelongsToRestaurant, HasFactory;

    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_MIXED = 'mixed';

    public const METHOD_OTHER = 'other';

    public const MODE_MANUAL_CASH = 'manual_cash';

    public const MODE_MANUAL_CARD = 'manual_card';

    public const MODE_POS_CARD = 'pos_card';

    public const STATUS_CREATED = 'created';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'table_id',
        'user_id',
        'method',
        'mode',
        'provider',
        'status',
        'amount',
        'currency',
        'reference',
        'idempotency_key',
        'provider_transaction_id',
        'terminal_id',
        'request_payload',
        'response_payload',
        'failure_code',
        'failure_message',
        'initiated_at',
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATED,
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_UNKNOWN,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
            self::STATUS_PARTIALLY_REFUNDED,
        ], true);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_CREATED,
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_UNKNOWN,
        ]);
    }
}
