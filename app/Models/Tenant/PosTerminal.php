<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Pos\PosTerminalStatus;
use Database\Factories\Tenant\PosTerminalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Point-of-sale terminal (register) assigned to an optional warehouse.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property PosTerminalStatus $status
 * @property int|null $warehouse_id
 * @property string|null $location_label
 */
class PosTerminal extends Model
{
    /** @use HasFactory<PosTerminalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'status',
        'warehouse_id',
        'location_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'status' => PosTerminalStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PosSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(PosSession::class);
    }

    /**
     * Current open cashier session, if any.
     *
     * @return HasOne<PosSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(PosSession::class)->where('status', 'open')->latestOfMany();
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param  Builder<PosTerminal>  $query
     * @param  array{search?: string|null, status?: string|null}  $params
     * @return Builder<PosTerminal>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('location_label', 'like', $like);
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            });
    }

    /**
     * @param  Builder<PosTerminal>  $query
     * @return Builder<PosTerminal>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['name', 'code', 'status', 'created_at', 'updated_at', 'id'];
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
