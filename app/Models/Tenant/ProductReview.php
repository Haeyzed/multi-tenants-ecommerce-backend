<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Catalog\ProductReviewStatus;
use Database\Factories\Tenant\ProductReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Customer review of a catalog product.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $rating
 * @property string|null $title
 * @property string $content
 * @property ProductReviewStatus $status
 * @property bool $verified_purchase
 * @property Carbon|null $approved_at
 */
class ProductReview extends Model
{
    /** @use HasFactory<ProductReviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'product_id',
        'product_variant_id',
        'rating',
        'title',
        'content',
        'status',
        'verified_purchase',
        'approved_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'verified_purchase' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'rating' => 'integer',
            'status' => ProductReviewStatus::class,
            'verified_purchase' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array{
     *     search?: string|null,
     *     product_id?: int|null,
     *     customer_id?: int|null,
     *     status?: ProductReviewStatus|string|null,
     *     rating?: int|null
     * }  $params
     * @return Builder<$this>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('content', 'like', $like);
                });
            })
            ->when(array_key_exists('product_id', $params) && $params['product_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('product_id', (int) $params['product_id']);
            })
            ->when(array_key_exists('customer_id', $params) && $params['customer_id'] !== null, function (Builder $query) use ($params): void {
                $query->where('customer_id', (int) $params['customer_id']);
            })
            ->when($params['status'] ?? null, function (Builder $query, ProductReviewStatus|string $status): void {
                $query->where('status', $status instanceof ProductReviewStatus ? $status->value : $status);
            })
            ->when(array_key_exists('rating', $params) && $params['rating'] !== null, function (Builder $query) use ($params): void {
                $query->where('rating', (int) $params['rating']);
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
        $allowed = ['rating', 'approved_at', 'created_at', 'updated_at', 'id'];
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
