<?php

declare(strict_types=1);

namespace App\Services\Tenant\Cms;

use App\Enums\Cms\CmsContentStatus;
use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Content\Page;
use App\Services\Media\MediaService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Tenant CMS page CRUD and public show.
 */
class PageService
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     * @param  MediaService  $mediaService
     */
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Page>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Page::query()
            ->filter($params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * title: string, slug?: string|null, content?: string|null, status?: CmsContentStatus|string|null, published_at?: string|null, seo?: array<string, mixed>|null }  $data
     *
     * @param  array{
     *     title: string,
     *     slug?: string|null,
     *     content?: string|null,
     *     status?: CmsContentStatus|string|null,
     *     published_at?: string|null,
     *     seo?: array<string, mixed>|null
     * }  $data
     * @return Page
     */
    public function store(array $data): Page
    {
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $page = Page::query()->create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'content' => $data['content'] ?? null,
            'status' => $data['status'] ?? CmsContentStatus::Draft,
            'published_at' => $data['published_at'] ?? null,
        ]);

        $this->syncSeo($page, $seo);

        return $page->load('seo');
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Page  $page
     * @return Page
     */
    public function show(Page $page): Page
    {
        return $page->load('seo');
    }

    /**
     * Retrieve a published resource by slug.
     *
     * @param  string  $slug
     * @return Page
     */
    public function showPublicBySlug(string $slug): Page
    {
        $enabled = filter_var(
            $this->commerceSettings->get('cms.pages_enabled', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $enabled) {
            throw ValidationException::withMessages([
                'cms' => ['Pages are disabled for this store.'],
            ]);
        }

        $page = Page::query()
            ->with('seo')
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($page === null) {
            throw (new ModelNotFoundException)->setModel(Page::class, [$slug]);
        }

        return $page;
    }

    /**
     * Update a resource.
     *
     * @param  Page  $page
     * @param  array<string, mixed>  $data
     * @return Page
     */
    public function update(Page $page, array $data): Page
    {
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $page->fill($data);
        $page->save();
        $this->syncSeo($page, is_array($seo) ? $seo : null);

        return $page->fresh('seo') ?? $page;
    }

    /**
     * Delete a resource.
     *
     * @param  Page  $page
     * @return void
     */
    public function destroy(Page $page): void
    {
        $page->seo()?->delete();
        $page->delete();
    }

    /**
     * Replace the featured image for a page.
     *
     * @param  Page  $page
     * @param  UploadedFile  $image
     * @return Page
     */
    public function storeFeaturedImage(Page $page, UploadedFile $image): Page
    {
        $this->mediaService->replace($page, $image, MediaCollection::FeaturedImage);

        return $page->fresh(['seo', 'media']) ?? $page->load(['seo', 'media']);
    }

    /**
     * Remove the featured image for a page.
     *
     * @param  Page  $page
     * @return Page
     */
    public function destroyFeaturedImage(Page $page): Page
    {
        $this->mediaService->removeCollection($page, MediaCollection::FeaturedImage);

        return $page->fresh(['seo', 'media']) ?? $page->load(['seo', 'media']);
    }

    /**
     * Sync seo.
     *
     * @param  Page  $page
     * @param  array<string, mixed>|null  $seo
     * @return void
     */
    protected function syncSeo(Page $page, ?array $seo): void
    {
        if ($seo === null) {
            return;
        }

        $page->seo()->updateOrCreate([], [
            'meta_title' => $seo['meta_title'] ?? null,
            'meta_description' => $seo['meta_description'] ?? null,
            'meta_keywords' => $seo['meta_keywords'] ?? null,
            'canonical_url' => $seo['canonical_url'] ?? null,
            'og_title' => $seo['og_title'] ?? null,
            'og_description' => $seo['og_description'] ?? null,
        ]);
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
