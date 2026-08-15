<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Product;

use App\Enums\Tenant\Catalog\ProductReviewStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates review moderation payloads.
 */
class ModerateProductReviewRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProductReviewStatus::class)],
        ];
    }
}
