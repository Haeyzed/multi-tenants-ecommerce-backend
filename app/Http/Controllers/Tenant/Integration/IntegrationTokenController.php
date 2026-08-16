<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Integration\StoreIntegrationTokenRequest;
use App\Http\Resources\Tenant\Integration\IntegrationTokenResource;
use App\Models\Tenant\User;
use App\Services\Tenant\Integration\IntegrationTokenService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant admin endpoints for programmatic integration API tokens.
 *
 * Gated by plan feature `api-access` — does not affect SPA login tokens.
 */
class IntegrationTokenController extends Controller
{
    public function __construct(private readonly IntegrationTokenService $tokens) {}

    #[Response(status: 200, description: 'Paginated integration tokens.', type: 'array{success: true, message: string, data: IntegrationTokenResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        $tokens = $this->tokens->list($user, $request->only(['per_page']));

        return $this->success(
            IntegrationTokenResource::collection($tokens->items()),
            'Integration tokens retrieved successfully.',
            $this->paginationMeta($tokens),
        );
    }

    #[Response(status: 201, description: 'Created integration token. Plain text shown once.', type: 'array{success: true, message: string, data: array{token: IntegrationTokenResource, plain_text_token: string}, meta: null, errors: null}')]
    public function store(StoreIntegrationTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        $created = $this->tokens->create($user, $request->validated());

        return $this->created(
            [
                'token' => (new IntegrationTokenResource($created['token']))->resolve(),
                'plain_text_token' => $created['plain_text_token'],
            ],
            'Integration token created successfully. Store the plain text token now; it cannot be retrieved again.',
        );
    }

    #[Response(status: 200, description: 'Integration token revoked.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(int $token): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        $this->tokens->destroy($user, $token);

        return $this->success(null, 'Integration token revoked successfully.');
    }
}
