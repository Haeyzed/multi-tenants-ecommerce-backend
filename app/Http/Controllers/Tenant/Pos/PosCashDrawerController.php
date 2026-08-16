<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Pos\PosCashMovementRequest;
use App\Http\Resources\Tenant\Pos\PosCashMovementResource;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\User;
use App\Services\Tenant\Pos\PosSessionService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * POS cash drawer in/out.
 */
class PosCashDrawerController extends Controller
{
    public function __construct(private readonly PosSessionService $sessions) {}

    #[Response(
        status: 201,
        description: 'Cash in recorded.',
        type: 'array{success: true, message: string, data: PosCashMovementResource, meta: null, errors: null}',
    )]
    public function cashIn(PosCashMovementRequest $request, PosSession $pos_session): JsonResponse
    {
        $this->authorize('cashIn', $pos_session);

        /** @var User $user */
        $user = $request->user();

        $movement = $this->sessions->cashIn(
            $pos_session,
            $user,
            (string) $request->validated('amount'),
            $request->validated('reason'),
        );

        return $this->created(
            new PosCashMovementResource($movement),
            'Cash in recorded successfully.',
        );
    }

    #[Response(
        status: 201,
        description: 'Cash out recorded.',
        type: 'array{success: true, message: string, data: PosCashMovementResource, meta: null, errors: null}',
    )]
    public function cashOut(PosCashMovementRequest $request, PosSession $pos_session): JsonResponse
    {
        $this->authorize('cashOut', $pos_session);

        /** @var User $user */
        $user = $request->user();

        $movement = $this->sessions->cashOut(
            $pos_session,
            $user,
            (string) $request->validated('amount'),
            $request->validated('reason'),
        );

        return $this->created(
            new PosCashMovementResource($movement),
            'Cash out recorded successfully.',
        );
    }
}
