<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\InventoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stock level for a product or variant at a warehouse.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int|null $warehouse_location_id
 * @property string $inventoryable_type
 * @property int $inventoryable_id
 * @property int $quantity
 * @property int $reserved_quantity
 * @property int|null $reorder_level
 * @property int|null $reorder_quantity
 */
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'warehouse_location_id',
        'inventoryable_type',
        'inventoryable_id',
        'quantity',
        'reserved_quantity',
        'reorder_level',
        'reorder_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'warehouse_location_id' => 'integer',
            'inventoryable_id' => 'integer',
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'reorder_level' => 'integer',
            'reorder_quantity' => 'integer',
        ];
    }

    /**
     * Quantity available for sale (on hand minus reserved).
     */
    public function availableQuantity(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<WarehouseLocation, $this>
     */
    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{
     *     warehouse_id?: int|null,
     *     warehouse_location_id?: int|null
     * }  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when(array_key_exists('warehouse_id', $params) && $params['warehouse_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('warehouse_id', (int) $params['warehouse_id']);
            })
            ->when(array_key_exists('warehouse_location_id', $params) && $params['warehouse_location_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('warehouse_location_id', (int) $params['warehouse_location_id']);
            });
    }

    /**
     * Apply a whitelist of sorts.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['quantity', 'reserved_quantity', 'created_at', 'updated_at', 'id'];
        $sort = $sort ?: 'id';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'id';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction);
    }
}
