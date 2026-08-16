<?php

declare(strict_types=1);

namespace App\Services\Tenant\Cms;

use App\Enums\Cms\CmsContentStatus;
use App\Models\Tenant\Cms\BlogPost;
use App\Services\Tenant\Commerce\CommerceSettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Tenant blog post CRUD and public listing.
 */
class BlogPostService
{
    public function __construct(private readonly CommerceSettingService $commerceSettings) {}

    /**
     * @param  array{search?: string|null, status?: string|null, blog_category_id?: int|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return BlogPost::query()
            ->with(['category', 'author'])
            ->filter($params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{search?: string|null, blog_category_id?: int|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function listPublic(array $params = []): LengthAwarePaginator
    {
        $this->assertBlogEnabled();

        return BlogPost::query()
            ->with(['category'])
            ->published()
            ->when($params['search'] ?? null, function ($query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhere('excerpt', 'like', $like);
                });
            })
            ->when($params['blog_category_id'] ?? null, function ($query, int $categoryId): void {
                $query->where('blog_category_id', $categoryId);
            })
            ->latest('published_at')
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{
     *     title: string,
     *     slug?: string|null,
     *     excerpt?: string|null,
     *     content?: string|null,
     *     status?: CmsContentStatus|string|null,
     *     published_at?: string|null,
     *     author_id?: int|null,
     *     blog_category_id?: int|null,
     *     seo?: array<string, mixed>|null
     * }  $data
     */
    public function store(array $data): BlogPost
    {
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $post = BlogPost::query()->create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'status' => $data['status'] ?? CmsContentStatus::Draft,
            'published_at' => $data['published_at'] ?? null,
            'author_id' => $data['author_id'] ?? null,
            'blog_category_id' => $data['blog_category_id'] ?? null,
        ]);

        $this->syncSeo($post, $seo);

        return $post->load(['category', 'author', 'seo']);
    }

    public function show(BlogPost $post): BlogPost
    {
        return $post->load(['category', 'author', 'seo']);
    }

    public function showPublicBySlug(string $slug): BlogPost
    {
        $this->assertBlogEnabled();

        $post = BlogPost::query()
            ->with(['category', 'seo'])
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($post === null) {
            throw (new ModelNotFoundException)->setModel(BlogPost::class, [$slug]);
        }

        return $post;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BlogPost $post, array $data): BlogPost
    {
        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $post->fill($data);
        $post->save();
        $this->syncSeo($post, is_array($seo) ? $seo : null);

        return $post->fresh(['category', 'author', 'seo']) ?? $post;
    }

    public function destroy(BlogPost $post): void
    {
        $post->seo()?->delete();
        $post->delete();
    }

    protected function assertBlogEnabled(): void
    {
        $enabled = filter_var(
            $this->commerceSettings->get('cms.blog_enabled', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $enabled) {
            throw ValidationException::withMessages([
                'cms' => ['Blog is disabled for this store.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $seo
     */
    protected function syncSeo(BlogPost $post, ?array $seo): void
    {
        if ($seo === null) {
            return;
        }

        $post->seo()->updateOrCreate([], [
            'meta_title' => $seo['meta_title'] ?? null,
            'meta_description' => $seo['meta_description'] ?? null,
            'meta_keywords' => $seo['meta_keywords'] ?? null,
            'canonical_url' => $seo['canonical_url'] ?? null,
            'og_title' => $seo['og_title'] ?? null,
            'og_description' => $seo['og_description'] ?? null,
        ]);
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
