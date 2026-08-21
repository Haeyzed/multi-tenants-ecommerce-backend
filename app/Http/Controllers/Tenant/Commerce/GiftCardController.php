<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Commerce\CancelGiftCardRequest;
use App\Http\Requests\Tenant\Commerce\StoreGiftCardRequest;
use App\Http\Requests\Tenant\Commerce\UpdateGiftCardRequest;
use App\Http\Resources\Tenant\Commerce\GiftCardResource;
use App\Models\Tenant\GiftCard;
use App\Services\Tenant\Commerce\GiftCardService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff gift card administration.
 */
class GiftCardController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  GiftCardService  $giftCardService
     */
    public function __construct(private readonly GiftCardService $giftCardService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated gift cards.', type: 'array{success: true, message: string, data: GiftCardResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GiftCard::class);

        $giftCards = $this->giftCardService->list($request->only(['search', 'status', 'customer_id', 'per_page']));

        return $this->success(
            GiftCardResource::collection($giftCards->items()),
            'Gift cards retrieved successfully.',
            $this->paginationMeta($giftCards),
        );
    }

    /**
     * Issue a gift card. The plain code is returned exactly once and never stored.
     *
     * @param  StoreGiftCardRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created gift card with its one-time plain code.', type: 'array{success: true, message: string, data: array{gift_card: GiftCardResource, code: string}, meta: null, errors: null}')]
    public function store(StoreGiftCardRequest $request): JsonResponse
    {
        $this->authorize('create', GiftCard::class);

        [$giftCard, $plainCode] = $this->giftCardService->create($request->validated());

        return $this->created(
            [
                'gift_card' => (new GiftCardResource($giftCard))->resolve(),
                'code' => $plainCode,
            ],
            'Gift card created successfully. Store the code now; it cannot be retrieved again.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  GiftCard  $giftCard
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A gift card with its ledger.', type: 'array{success: true, message: string, data: GiftCardResource, meta: null, errors: null}')]
    public function show(GiftCard $giftCard): JsonResponse
    {
        $this->authorize('view', $giftCard);

        return $this->success(
            new GiftCardResource($this->giftCardService->show($giftCard)),
            'Gift card retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateGiftCardRequest  $request
     * @param  GiftCard  $giftCard
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated gift card.', type: 'array{success: true, message: string, data: GiftCardResource, meta: null, errors: null}')]
    public function update(UpdateGiftCardRequest $request, GiftCard $giftCard): JsonResponse
    {
        $this->authorize('update', $giftCard);

        $giftCard->fill($request->validated());
        $giftCard->save();

        return $this->updated(
            new GiftCardResource($this->giftCardService->show($giftCard->fresh() ?? $giftCard)),
            'Gift card updated successfully.',
        );
    }

    /**
     * Activate.
     *
     * @param  GiftCard  $giftCard
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Activated gift card.', type: 'array{success: true, message: string, data: GiftCardResource, meta: null, errors: null}')]
    public function activate(GiftCard $giftCard): JsonResponse
    {
        $this->authorize('update', $giftCard);

        return $this->updated(
            new GiftCardResource($this->giftCardService->activate($giftCard)),
            'Gift card activated successfully.',
        );
    }

    /**
     * Cancel.
     *
     * @param  CancelGiftCardRequest  $request
     * @param  GiftCard  $giftCard
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Cancelled gift card.', type: 'array{success: true, message: string, data: GiftCardResource, meta: null, errors: null}')]
    public function cancel(CancelGiftCardRequest $request, GiftCard $giftCard): JsonResponse
    {
        $this->authorize('cancel', $giftCard);

        return $this->updated(
            new GiftCardResource($this->giftCardService->cancel($giftCard, $request->validated()['reason'] ?? null)),
            'Gift card cancelled successfully.',
        );
    }
}
