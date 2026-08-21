<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Pos\IndexPosTerminalRequest;
use App\Http\Requests\Tenant\Pos\StorePosTerminalRequest;
use App\Http\Requests\Tenant\Pos\UpdatePosTerminalRequest;
use App\Http\Resources\Tenant\Pos\PosTerminalResource;
use App\Models\Tenant\PosTerminal;
use App\Services\Tenant\Pos\PosTerminalService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * POS terminal management.
 */
class PosTerminalController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PosTerminalService  $terminals
     */
    public function __construct(private readonly PosTerminalService $terminals) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexPosTerminalRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated POS terminals.',
        type: 'array{success: true, message: string, data: PosTerminalResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexPosTerminalRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PosTerminal::class);

        $terminals = $this->terminals->list($request->validated());

        return $this->success(
            PosTerminalResource::collection($terminals->items()),
            'POS terminals retrieved successfully.',
            $this->paginationMeta($terminals),
        );
    }

    /**
     * Return options for select inputs.
     *
     * @param  IndexPosTerminalRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Terminal options.', type: ApiResponseSchema::OPTIONS)]
    public function options(IndexPosTerminalRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PosTerminal::class);

        return $this->success(
            $this->terminals->options($request->validated()),
            'POS terminal options retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StorePosTerminalRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created terminal.',
        type: 'array{success: true, message: string, data: PosTerminalResource, meta: null, errors: null}',
    )]
    public function store(StorePosTerminalRequest $request): JsonResponse
    {
        $this->authorize('create', PosTerminal::class);

        return $this->created(
            new PosTerminalResource($this->terminals->store($request->validated())),
            'POS terminal created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  PosTerminal  $pos_terminal
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A terminal.',
        type: 'array{success: true, message: string, data: PosTerminalResource, meta: null, errors: null}',
    )]
    public function show(PosTerminal $pos_terminal): JsonResponse
    {
        $this->authorize('view', $pos_terminal);

        return $this->success(
            new PosTerminalResource($this->terminals->show($pos_terminal)),
            'POS terminal retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdatePosTerminalRequest  $request
     * @param  PosTerminal  $pos_terminal
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated terminal.',
        type: 'array{success: true, message: string, data: PosTerminalResource, meta: null, errors: null}',
    )]
    public function update(UpdatePosTerminalRequest $request, PosTerminal $pos_terminal): JsonResponse
    {
        $this->authorize('update', $pos_terminal);

        return $this->updated(
            new PosTerminalResource($this->terminals->update($pos_terminal, $request->validated())),
            'POS terminal updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  PosTerminal  $pos_terminal
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Terminal deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(PosTerminal $pos_terminal): JsonResponse
    {
        $this->authorize('delete', $pos_terminal);
        $this->terminals->destroy($pos_terminal);

        return $this->deleted('POS terminal deleted successfully.');
    }
}
