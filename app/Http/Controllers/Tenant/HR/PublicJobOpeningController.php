<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexPublicJobRequest;
use App\Http\Requests\Tenant\HR\PublicApplyJobRequest;
use App\Http\Resources\Tenant\HR\PublicJobOpeningResource;
use App\Services\Tenant\HR\JobApplicationService;
use App\Services\Tenant\HR\JobOpeningService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Public careers listings and applications. Tenant is resolved by domain.
 */
#[Group('Public Recruitment / Job Listings')]
class PublicJobOpeningController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  JobOpeningService  $openings
     * @param  JobApplicationService  $applications
     */
    public function __construct(
        private readonly JobOpeningService $openings,
        private readonly JobApplicationService $applications,
    ) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexPublicJobRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Published job listings.', type: 'array{success: true, message: string, data: PublicJobOpeningResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexPublicJobRequest $request): JsonResponse
    {
        $openings = $this->openings->listPublic($request->validated());

        return $this->success(
            PublicJobOpeningResource::collection($openings->items()),
            'Job listings retrieved successfully.',
            $this->paginationMeta($openings),
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  string  $slug
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Published job listing.', type: 'array{success: true, message: string, data: PublicJobOpeningResource, meta: null, errors: null}')]
    public function show(string $slug): JsonResponse
    {
        return $this->success(
            new PublicJobOpeningResource($this->openings->showPublicBySlug($slug)),
            'Job listing retrieved successfully.',
        );
    }

    /**
     * Apply.
     *
     * @param  PublicApplyJobRequest  $request
     * @param  string  $slug
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Public application accepted.', type: 'array{success: true, message: string, data: array{received: true}, meta: null, errors: null}')]
    public function apply(PublicApplyJobRequest $request, string $slug): JsonResponse
    {
        $opening = $this->openings->showPublicBySlug($slug);
        $data = $request->validated();
        unset($data['resume']);
        $data['job_opening_id'] = $opening->id;

        $resume = $request->file('resume');
        $this->applications->applyPublic(
            $data,
            $resume instanceof UploadedFile ? $resume : null,
        );

        return $this->created(
            ['received' => true],
            'Application submitted successfully.',
        );
    }
}
