<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'restaurant_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_WAITER = 'waiter';

    /** @var list<string> */
    public const STAFF_ROLES = [self::ROLE_WAITER, self::ROLE_CASHIER];

    /** @var list<string> Personel yönetim ekranında oluşturulabilir / düzenlenebilir roller */
    public const MANAGEABLE_ROLES = [self::ROLE_ADMIN, self::ROLE_WAITER, self::ROLE_CASHIER];

    /** @use HasFactory<UserFactory> */
    use BelongsToRestaurant, HasFactory, Notifiable;

    public function isWaiter(): bool
    {
        return $this->role === self::ROLE_WAITER;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isFullAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function isStaffMember(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function staffRoleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_WAITER => 'Garson',
            self::ROLE_CASHIER => 'Kasa',
            default => $this->role,
        };
    }

    public function deliveryTasks(): HasMany
    {
        return $this->hasMany(DeliveryTask::class, 'assigned_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
