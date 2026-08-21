<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\HR\PublicJobOfferResource;
use App\Services\Tenant\HR\JobOfferService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tokenized candidate offer responses. No candidate accounts.
 */
#[Group('Public Recruitment / Offers')]
class PublicJobOfferController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  JobOfferService  $offers
     */
    public function __construct(private readonly JobOfferService $offers) {}

    /**
     * Retrieve a single resource.
     *
     * @param  string  $token
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Offer details for the candidate.', type: 'array{success: true, message: string, data: PublicJobOfferResource, meta: null, errors: null}')]
    public function show(string $token): JsonResponse
    {
        return $this->success(
            new PublicJobOfferResource($this->offers->showPublicByToken($token)),
            'Offer retrieved successfully.',
        );
    }

    /**
     * Accept.
     *
     * @param  string  $token
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Offer accepted.', type: 'array{success: true, message: string, data: array{received: true, status: string}, meta: null, errors: null}')]
    public function accept(string $token): JsonResponse
    {
        $offer = $this->offers->acceptPublicByToken($token);

        return $this->success(
            ['received' => true, 'status' => $offer->status->value],
            'Offer accepted successfully.',
        );
    }

    /**
     * Reject.
     *
     * @param  string  $token
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Offer declined.', type: 'array{success: true, message: string, data: array{received: true, status: string}, meta: null, errors: null}')]
    public function reject(string $token): JsonResponse
    {
        $offer = $this->offers->rejectPublicByToken($token);

        return $this->success(
            ['received' => true, 'status' => $offer->status->value],
            'Offer declined successfully.',
        );
    }
}
