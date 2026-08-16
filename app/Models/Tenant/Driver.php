<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use Database\Factories\Tenant\DriverFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password', 'status', 'availability'])]
#[Hidden(['password', 'remember_token'])]
class Driver extends Authenticatable
{
    /** @use HasFactory<DriverFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Free unique email/phone values on soft delete so they can be re-used.
     */
    protected static function booted(): void
    {
        static::deleting(function (Driver $driver): void {
            if ($driver->isForceDeleting()) {
                return;
            }

            $driver->email = 'deleted+'.$driver->id.'.'.time().'@deleted.local';

            if ($driver->phone !== null && $driver->phone !== '') {
                $driver->phone = 'deleted-'.$driver->id.'-'.time();
            }

            $driver->saveQuietly();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => DriverStatus::class,
            'availability' => DriverAvailability::class,
        ];
    }

    /**
     * Deliveries assigned to this driver.
     *
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Recorded GPS locations for this driver.
     *
     * @return HasMany<DriverLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(DriverLocation::class);
    }

    /**
     * Full display name.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Whether the driver is allowed to log in.
     */
    public function isLoginAllowed(): bool
    {
        return $this->status === DriverStatus::Active;
    }

    /**
     * @param  Builder<Driver>  $query
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     availability?: string|null
     * }  $params
     * @return Builder<Driver>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['availability'] ?? null, function (Builder $query, string $availability): void {
                $query->where('availability', $availability);
            });
    }

    /**
     * Apply a whitelist of sorts.
     *
     * @param  Builder<Driver>  $query
     * @return Builder<Driver>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['first_name', 'last_name', 'email', 'status', 'availability', 'created_at', 'updated_at', 'last_login_at', 'id'];
        $sort = $sort ?: '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
