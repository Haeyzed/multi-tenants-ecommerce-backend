<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Pos\PosSessionStatus;
use Database\Factories\Tenant\PosSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cashier shift on a POS terminal.
 *
 * @property int $id
 * @property int $pos_terminal_id
 * @property int $user_id
 * @property PosSessionStatus $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string $opening_cash
 * @property string|null $closing_cash
 * @property string|null $expected_cash
 * @property string|null $actual_cash
 * @property string|null $cash_difference
 * @property string|null $notes
 */
class PosSession extends Model
{
    /** @use HasFactory<PosSessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pos_terminal_id',
        'user_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'actual_cash',
        'cash_difference',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pos_terminal_id' => 'integer',
            'user_id' => 'integer',
            'status' => PosSessionStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    /**
     * Cashier who owns the session.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PosCashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PosSessionStatus::Open;
    }

    /**
     * @param  Builder<PosSession>  $query
     * @param  array{
     *     status?: string|null,
     *     pos_terminal_id?: int|null,
     *     user_id?: int|null
     * }  $params
     * @return Builder<PosSession>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['pos_terminal_id'] ?? null, function (Builder $query, int $terminalId): void {
                $query->where('pos_terminal_id', $terminalId);
            })
            ->when($params['user_id'] ?? null, function (Builder $query, int $userId): void {
                $query->where('user_id', $userId);
            });
    }

    /**
     * @param  Builder<PosSession>  $query
     * @return Builder<PosSession>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['opened_at', 'closed_at', 'status', 'created_at', 'id'];
        $sort = $sort ?: '-opened_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'opened_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
