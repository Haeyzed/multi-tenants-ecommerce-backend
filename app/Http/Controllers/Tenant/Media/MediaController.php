<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Media;

use App\Enums\Media\MediaCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Media\IndexMediaRequest;
use App\Http\Requests\Tenant\Media\StoreMediaRequest;
use App\Http\Requests\Tenant\Media\UpdateMediaRequest;
use App\Http\Resources\Media\MediaResource;
use App\Models\Tenant\User;
use App\Services\Media\MediaService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Authenticated tenant user's personal media library.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    #[Response(
        status: 200,
        description: 'Paginated media library items.',
        type: 'array{success: true, message: string, data: MediaResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexMediaRequest $request): JsonResponse
    {
        $media = $this->mediaService->listForOwner($this->actor(), $request->validated());

        return $this->success(
            MediaResource::collection($media->items()),
            'Media retrieved successfully.',
            $this->paginationMeta($media),
        );
    }

    #[Response(
        status: 200,
        description: 'Media options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexMediaRequest $request): JsonResponse
    {
        return $this->success(
            $this->mediaService->optionsForOwner($this->actor(), $request->validated()),
            'Media options retrieved successfully.',
        );
    }

    #[Response(
        status: 201,
        description: 'Uploaded media item or items.',
        type: 'array{success: true, message: string, data: MediaResource|MediaResource[], meta: null, errors: null}',
    )]
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $owner = $this->actor();
        $collection = $request->validated('collection') ?? MediaCollection::Library->value;
        $meta = $request->safe()->except(['file', 'files', 'collection']);

        if ($request->hasFile('files')) {
            /** @var list<UploadedFile> $files */
            $files = array_values($request->file('files'));

            $media = $this->mediaService->addMany($owner, $files, $collection, $meta);

            return $this->created(
                MediaResource::collection($media),
                'Media uploaded successfully.',
            );
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $media = $this->mediaService->add($owner, $file, $collection, $meta);

        return $this->created(
            new MediaResource($media),
            'Media uploaded successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'A single media item.',
        type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}',
    )]
    public function show(Media $media): JsonResponse
    {
        return $this->success(
            new MediaResource($this->mediaService->findOwned($this->actor(), $media)),
            'Media retrieved successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'Updated media item.',
        type: 'array{success: true, message: string, data: MediaResource, meta: null, errors: null}',
    )]
    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        $media = $this->mediaService->updateOwned($this->actor(), $media, $request->validated());

        return $this->updated(
            new MediaResource($media),
            'Media updated successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'Media deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(Media $media): JsonResponse
    {
        $this->mediaService->remove($this->actor(), $media);

        return $this->deleted('Media deleted successfully.');
    }

    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $user;
    }
}
