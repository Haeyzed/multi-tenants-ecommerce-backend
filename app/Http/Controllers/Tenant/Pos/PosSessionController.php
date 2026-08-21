<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Pos\ClosePosSessionRequest;
use App\Http\Requests\Tenant\Pos\OpenPosSessionRequest;
use App\Http\Resources\Tenant\Pos\PosSessionResource;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;
use App\Services\Tenant\Pos\PosSessionService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POS cashier sessions.
 */
class PosSessionController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PosSessionService  $sessions
     */
    public function __construct(private readonly PosSessionService $sessions) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated sessions.',
        type: 'array{success: true, message: string, data: PosSessionResource[], meta: array, errors: null}',
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PosSession::class);

        $sessions = $this->sessions->list($request->only(['status', 'pos_terminal_id', 'user_id', 'sort', 'per_page']));

        return $this->success(
            PosSessionResource::collection($sessions->items()),
            'POS sessions retrieved successfully.',
            $this->paginationMeta($sessions),
        );
    }

    /**
     * Open.
     *
     * @param  OpenPosSessionRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Opened session.',
        type: 'array{success: true, message: string, data: PosSessionResource, meta: null, errors: null}',
    )]
    public function open(OpenPosSessionRequest $request): JsonResponse
    {
        $terminal = PosTerminal::query()->findOrFail((int) $request->validated('pos_terminal_id'));
        $this->authorize('openSession', $terminal);

        /** @var User $user */
        $user = $request->user();

        $session = $this->sessions->open(
            $terminal,
            $user,
            (string) $request->validated('opening_cash'),
            $request->validated('notes'),
        );

        return $this->created(
            new PosSessionResource($session),
            'POS session opened successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  PosSession  $pos_session
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A session.',
        type: 'array{success: true, message: string, data: PosSessionResource, meta: null, errors: null}',
    )]
    public function show(PosSession $pos_session): JsonResponse
    {
        $this->authorize('view', $pos_session);

        return $this->success(
            new PosSessionResource($this->sessions->show($pos_session)),
            'POS session retrieved successfully.',
        );
    }

    /**
     * Close.
     *
     * @param  ClosePosSessionRequest  $request
     * @param  PosSession  $pos_session
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Closed session.',
        type: 'array{success: true, message: string, data: PosSessionResource, meta: null, errors: null}',
    )]
    public function close(ClosePosSessionRequest $request, PosSession $pos_session): JsonResponse
    {
        $this->authorize('close', $pos_session);

        $session = $this->sessions->close(
            $pos_session,
            (string) $request->validated('actual_cash'),
            $request->validated('notes'),
        );

        return $this->success(
            new PosSessionResource($session),
            'POS session closed successfully.',
        );
    }
}
