<?php

declare(strict_types=1);

namespace App\Services\Tenant\Product;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Events\ProductReviewApproved;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductReview;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product review create, moderation, and aggregate recalculation.
 */
class ProductReviewService
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Customer creates a pending review. Never accepts verified_purchase from client.
     *
     * @param  array{
     *     rating: int,
     *     title?: string|null,
     *     content: string,
     *     product_variant_id?: int|null,
     *     images?: list<UploadedFile>|null
     * }  $data
     */
    public function customerStore(Customer $customer, Product $product, array $data): ProductReview
    {
        unset($data['verified_purchase'], $data['status'], $data['approved_at']);

        if (ProductReview::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'You have already reviewed this product.',
            ]);
        }

        $verifiedPurchase = $this->customerHasVerifiedPurchase($customer, $product);
        $images = $data['images'] ?? null;
        unset($data['images']);

        /** @var ProductReview $review */
        $review = DB::transaction(function () use ($customer, $product, $data, $verifiedPurchase, $images): ProductReview {
            /** @var ProductReview $review */
            $review = ProductReview::query()->create([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'content' => $data['content'],
                'status' => ProductReviewStatus::Pending,
                'verified_purchase' => $verifiedPurchase,
                'approved_at' => null,
            ]);

            if (is_array($images) && $images !== []) {
                $this->mediaService->addMany($review, $images, MediaCollection::Images);
            }

            return $review;
        });

        return $review->load(['customer', 'product', 'variant', 'media']);
    }

    /**
     * Whether the customer has a paid, completed order containing the product.
     */
    public function customerHasVerifiedPurchase(Customer $customer, Product $product): bool
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->exists();
    }

    /**
     * Admin paginated review list.
     *
     * @param  array{
     *     search?: string|null,
     *     product_id?: int|null,
     *     customer_id?: int|null,
     *     status?: string|null,
     *     rating?: int|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function adminList(array $params = []): LengthAwarePaginator
    {
        return ProductReview::query()
            ->with(['customer', 'product', 'variant'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Moderate a review and recalculate product aggregates when needed.
     *
     * @throws ValidationException
     */
    public function moderate(ProductReview $review, ProductReviewStatus|string $status): ProductReview
    {
        $status = $status instanceof ProductReviewStatus
            ? $status
            : ProductReviewStatus::from($status);

        return DB::transaction(function () use ($review, $status): ProductReview {
            $review->status = $status;
            $review->approved_at = $status === ProductReviewStatus::Approved ? now() : null;
            $review->save();

            $this->recalculateAggregates($review->product ?? Product::query()->findOrFail($review->product_id));

            $fresh = $review->fresh(['customer', 'product', 'variant']) ?? $review;

            if ($status === ProductReviewStatus::Approved) {
                event(new ProductReviewApproved($fresh));
            }

            return $fresh;
        });
    }

    /**
     * Delete a review and recalculate aggregates.
     */
    public function destroy(ProductReview $review): void
    {
        DB::transaction(function () use ($review): void {
            $product = $review->product ?? Product::query()->findOrFail($review->product_id);
            $review->delete();
            $this->recalculateAggregates($product);
        });
    }

    /**
     * Recalculate average rating and approved review count on the product.
     */
    public function recalculateAggregates(Product $product): void
    {
        $approved = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved);

        $count = (clone $approved)->count();
        $avg = $count > 0 ? (clone $approved)->avg('rating') : null;

        $product->forceFill([
            'reviews_count' => $count,
            'average_rating' => $avg !== null ? round((float) $avg, 2) : null,
        ])->save();
    }

    /**
     * Approved reviews for a product (storefront).
     *
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function approvedForProduct(Product $product, array $params = []): LengthAwarePaginator
    {
        return ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved)
            ->with(['customer'])
            ->applySort($params['sort'] ?? '-approved_at')
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
