<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexCandidateRequest;
use App\Http\Requests\Tenant\HR\StoreCandidateRequest;
use App\Http\Requests\Tenant\HR\StoreCandidateResumeRequest;
use App\Http\Requests\Tenant\HR\UpdateCandidateRequest;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Tenant\HR\CandidateResource;
use App\Models\Tenant\Candidate;
use App\Services\Tenant\HR\CandidateService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

#[Group('HR')]
class CandidateController extends Controller
{
    public function __construct(private readonly CandidateService $candidates) {}

    #[Response(status: 200, description: 'Paginated candidates.', type: 'array{success: true, message: string, data: CandidateResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexCandidateRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);

        $candidates = $this->candidates->list($request->validated());

        return $this->success(
            CandidateResource::collection($candidates->items()),
            'Candidates retrieved successfully.',
            $this->paginationMeta($candidates),
        );
    }

    #[Response(status: 201, description: 'Created candidate.', type: 'array{success: true, message: string, data: CandidateResource, meta: null, errors: null}')]
    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $this->authorize('create', Candidate::class);

        return $this->created(
            new CandidateResource($this->candidates->store($request->validated())),
            'Candidate created successfully.',
        );
    }

    #[Response(status: 200, description: 'A candidate.', type: 'array{success: true, message: string, data: CandidateResource, meta: null, errors: null}')]
    public function show(Candidate $candidate): JsonResponse
    {
        $this->authorize('view', $candidate);

        return $this->success(
            new CandidateResource($this->candidates->show($candidate)),
            'Candidate retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated candidate.', type: 'array{success: true, message: string, data: CandidateResource, meta: null, errors: null}')]
    public function update(UpdateCandidateRequest $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);

        return $this->updated(
            new CandidateResource($this->candidates->update($candidate, $request->validated())),
            'Candidate updated successfully.',
        );
    }

    #[Response(status: 201, description: 'Candidate resume.', type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}')]
    public function resume(StoreCandidateResumeRequest $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return $this->created(
            new MediaResource($this->candidates->addResume($candidate, $file)),
            'Resume uploaded successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted candidate.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Candidate $candidate): JsonResponse
    {
        $this->authorize('delete', $candidate);
        $this->candidates->destroy($candidate);

        return $this->deleted('Candidate deleted successfully.');
    }
}
