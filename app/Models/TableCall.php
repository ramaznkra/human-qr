<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableCall extends Model
{
    use BelongsToRestaurant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const TYPE_WAITER = 'waiter';

    /** @deprecated use STATUS_PENDING */
    public const STATUS_ACTIVE = self::STATUS_PENDING;

    /** @deprecated use STATUS_COMPLETED */
    public const STATUS_RESOLVED = self::STATUS_COMPLETED;

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'type',
        'status',
        'forwarded_to_waiter',
        'waiter_id',
    ];

    protected function casts(): array
    {
        return ['forwarded_to_waiter' => 'boolean'];
    }

    public function isBill(): bool
    {
        return in_array($this->type, ['bill_cash', 'bill_card', 'bill'], true);
    }

    public function table(): BelongsTo
    {
        return $this->linkedTable();
    }

    public function linkedTable(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /** @deprecated use waiter() */
    public function assignedUser(): BelongsTo
    {
        return $this->waiter();
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'waiter' => 'Garson Çağrısı',
            'bill_cash' => 'Nakit Hesap',
            'bill_card' => 'Kart / Pos Hesap',
            'bill' => 'Hesap İsteme',
            default => $this->type,
        };
    }

    public function tableNumber(): ?string
    {
        $tableModel = $this->relationLoaded('linkedTable')
            ? $this->getRelation('linkedTable')
            : $this->linkedTable()->first();

        $number = $tableModel?->number;

        return $number !== null && $number !== ''
            ? (string) $number
            : ($this->table_id ? (string) $this->table_id : null);
    }

    public function customerMessage(): string
    {
        $this->loadMissing('waiter:id,name');

        if ($this->status === self::STATUS_IN_PROGRESS && $this->waiter) {
            return __('menu.call.waiter_on_the_way', ['name' => $this->waiter->name]);
        }

        return match ($this->type) {
            'waiter' => __('menu.call.waiter_sent'),
            'bill_cash' => __('menu.call.bill_cash_sent'),
            'bill_card' => __('menu.call.bill_card_sent'),
            default => __('menu.table_call.default'),
        };
    }

    public function getHeadlineAttribute(): string
    {
        $table = $this->tableNumber() ?? '?';

        return match ($this->type) {
            'waiter' => "MASA {$table}: GARSON ÇAĞIRIYOR",
            'bill_cash' => "MASA {$table}: HESAP İSTİYOR (NAKİT)",
            'bill_card' => "MASA {$table}: HESAP İSTİYOR (KART)",
            'bill' => "MASA {$table}: HESAP İSTİYOR",
            default => "MASA {$table}: ÇAĞRI",
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** @deprecated use scopePending */
    public function scopeActive($query)
    {
        return $this->scopePending($query);
    }

    /** Aktif veya garson ilgileniyor — henüz kapanmamış çağrılar. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }
}
