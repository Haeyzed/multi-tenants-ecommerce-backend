<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Accounting\StoreJournalEntryRequest;
use App\Http\Resources\Tenant\Accounting\JournalEntryResource;
use App\Models\Tenant\JournalEntry;
use App\Services\Tenant\Accounting\AccountService;
use App\Services\Tenant\Accounting\JournalEntryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Journal entry admin endpoints.
 */
class JournalEntryController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly JournalEntryService $journalEntryService,
    ) {}

    #[Response(
        status: 200,
        description: 'Paginated journal entries.',
        type: 'array{success: true, message: string, data: JournalEntryResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = $this->accountService->listJournalEntries($request->only(['status', 'entry_type', 'per_page']));

        return $this->success(
            JournalEntryResource::collection($entries->items()),
            'Journal entries retrieved successfully.',
            $this->paginationMeta($entries),
        );
    }

    #[Response(
        status: 200,
        description: 'A journal entry.',
        type: 'array{success: true, message: string, data: JournalEntryResource, meta: null, errors: null}',
    )]
    public function show(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('view', $journalEntry);

        return $this->success(
            new JournalEntryResource($this->accountService->showJournalEntry($journalEntry)),
            'Journal entry retrieved successfully.',
        );
    }

    #[Response(
        status: 201,
        description: 'Created (and optionally posted) journal entry.',
        type: 'array{success: true, message: string, data: JournalEntryResource, meta: null, errors: null}',
    )]
    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $this->authorize('create', JournalEntry::class);

        $data = $request->validated();
        $shouldPost = (bool) ($data['post'] ?? true);

        if ($shouldPost) {
            $this->authorize('post', JournalEntry::class);
        }

        $entry = $this->journalEntryService->createDraft(
            reference: $data['reference'],
            description: $data['description'] ?? null,
            entryDate: $data['entry_date'],
            lines: $data['lines'],
            entryType: $data['entry_type'] ?? null,
        );

        if ($shouldPost) {
            $entry = $this->journalEntryService->post($entry);
        }

        return $this->created(
            new JournalEntryResource($entry->load('lines.account')),
            $shouldPost ? 'Journal entry posted successfully.' : 'Journal entry draft created successfully.',
        );
    }
}
