<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTask extends Model
{
    use BelongsToRestaurant;

    public const TYPE_DELIVER_ITEM = 'deliver_item';

    public const TYPE_CUSTOMER_CALL = 'customer_call';

    public const TYPE_BILL_REQUEST = 'bill_request';

    public const TYPE_COLLECT_EMPTY = 'collect_empty';

    public const TYPE_TABLE_CLEANUP = 'table_cleanup';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'order_item_id',
        'table_id',
        'assigned_user_id',
        'type',
        'status',
        'accepted_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_ASSIGNED,
            self::STATUS_ACCEPTED,
        ]);
    }

    public function scopeVisibleToWaiter(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->whereNull('assigned_user_id')
                ->orWhere('assigned_user_id', $userId);
        });
    }
}
