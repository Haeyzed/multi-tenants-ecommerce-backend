<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Generic Spatie Media Library operations reusable by any HasMedia model.
 */
class MediaService
{
    /**
     * Add a file to a media collection.
     *
     * For single-file collections registered with singleFile(), Spatie replaces
     * the previous item after the new upload succeeds.
     *
     * @param  array{name?: string|null, custom_properties?: array<string, mixed>}  $options
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function add(
        HasMedia $model,
        UploadedFile $file,
        MediaCollection|string $collection,
        array $options = [],
    ): Media {
        $collectionName = $this->collectionName($collection);

        $adder = $model->addMedia($file);

        if (! empty($options['name'])) {
            $adder->usingName((string) $options['name']);
        }

        if (! empty($options['custom_properties']) && is_array($options['custom_properties'])) {
            $adder->withCustomProperties($options['custom_properties']);
        }

        return $adder->toMediaCollection($collectionName);
    }

    /**
     * Add multiple files to a collection.
     *
     * @param  list<UploadedFile>  $files
     * @param  array{custom_properties?: array<string, mixed>}  $options
     * @return Collection<int, Media>
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function addMany(
        HasMedia $model,
        array $files,
        MediaCollection|string $collection,
        array $options = [],
    ): Collection {
        return collect($files)
            ->map(fn (UploadedFile $file): Media => $this->add($model, $file, $collection, $options))
            ->values();
    }

    /**
     * Replace media in a collection.
     *
     * Prefer Spatie singleFile() collections so the previous item is cleared
     * only after the new file is stored. For multi-file collections, clears
     * the collection after a successful upload of the replacement file.
     *
     * @param  array{name?: string|null, custom_properties?: array<string, mixed>}  $options
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function replace(
        HasMedia $model,
        UploadedFile $file,
        MediaCollection|string $collection,
        array $options = [],
    ): Media {
        $collectionName = $this->collectionName($collection);
        $enum = $collection instanceof MediaCollection
            ? $collection
            : MediaCollection::tryFrom($collectionName);

        $media = $this->add($model, $file, $collectionName, $options);

        if ($enum === null || ! $enum->isSingleFile()) {
            $model->getMedia($collectionName)
                ->reject(fn (Media $item): bool => $item->is($media))
                ->each(fn (Media $item) => $item->delete());
        }

        return $media;
    }

    /**
     * Remove a media item that belongs to the given model.
     *
     * @throws NotFoundHttpException
     */
    public function remove(HasMedia $model, Media $media): void
    {
        $this->assertBelongsTo($model, $media);

        $media->delete();
    }

    /**
     * Clear an entire media collection on the model.
     */
    public function removeCollection(HasMedia $model, MediaCollection|string $collection): void
    {
        $model->clearMediaCollection($this->collectionName($collection));
    }

    /**
     * Retrieve all media for a collection.
     *
     * @return Collection<int, Media>
     */
    public function get(HasMedia $model, MediaCollection|string $collection): Collection
    {
        return $model->getMedia($this->collectionName($collection));
    }

    /**
     * Retrieve the first media item in a collection.
     */
    public function getFirst(HasMedia $model, MediaCollection|string $collection): ?Media
    {
        return $model->getFirstMedia($this->collectionName($collection));
    }

    /**
     * Resolve a media URL, optionally for a conversion.
     */
    public function getUrl(
        HasMedia $model,
        MediaCollection|string $collection,
        MediaConversion|string|null $conversion = null,
    ): ?string {
        $media = $this->getFirst($model, $collection);

        if ($media === null) {
            return null;
        }

        return $this->urlFor($media, $conversion);
    }

    /**
     * Resolve a URL for a media item / conversion when available.
     */
    public function urlFor(Media $media, MediaConversion|string|null $conversion = null): ?string
    {
        if ($conversion === null) {
            return $media->getUrl();
        }

        $name = $conversion instanceof MediaConversion ? $conversion->value : $conversion;

        if (! $media->hasGeneratedConversion($name)) {
            return null;
        }

        return $media->getUrl($name);
    }

    /**
     * Reorder media within a collection using Spatie order_column values.
     *
     * @param  list<int|string>  $orderedMediaIds
     *
     * @throws NotFoundHttpException
     * @throws InvalidArgumentException
     */
    public function reorder(HasMedia $model, MediaCollection|string $collection, array $orderedMediaIds): void
    {
        $collectionName = $this->collectionName($collection);
        $media = $model->getMedia($collectionName)->keyBy('id');

        if ($media->count() !== count($orderedMediaIds)) {
            throw new InvalidArgumentException('Ordered media IDs must include every item in the collection.');
        }

        foreach ($orderedMediaIds as $index => $mediaId) {
            /** @var Media|null $item */
            $item = $media->get($mediaId);

            if ($item === null) {
                throw new NotFoundHttpException('Media not found in collection.');
            }

            $item->order_column = $index + 1;
            $item->save();
        }
    }

    /**
     * Paginate media owned by a model.
     *
     * @param  array{search?: string|null, collection?: string|null, mime_type?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Media>
     */
    public function listForOwner(HasMedia $owner, array $params = []): LengthAwarePaginator
    {
        return $this->ownerQuery($owner, $params)
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * Media options as label/value pairs for the owner's library.
     *
     * @param  array{search?: string|null, collection?: string|null, mime_type?: string|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function optionsForOwner(HasMedia $owner, array $params = []): Collection
    {
        return $this->ownerQuery($owner, $params)
            ->orderBy('name')
            ->get(['id', 'name', 'file_name'])
            ->map(fn (Media $media): array => [
                'label' => $media->name !== '' ? $media->name : $media->file_name,
                'value' => $media->id,
            ])
            ->values();
    }

    /**
     * Find a media item owned by the model.
     *
     * @throws NotFoundHttpException
     */
    public function findOwned(HasMedia $owner, Media $media): Media
    {
        $this->assertBelongsTo($owner, $media);

        return $media;
    }

    /**
     * Update display name / custom properties for owned media.
     *
     * @param  array{name?: string|null, custom_properties?: array<string, mixed>|null}  $data
     *
     * @throws NotFoundHttpException
     */
    public function updateOwned(HasMedia $owner, Media $media, array $data): Media
    {
        $this->assertBelongsTo($owner, $media);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $media->name = (string) $data['name'];
        }

        if (array_key_exists('custom_properties', $data) && is_array($data['custom_properties'])) {
            $media->custom_properties = array_merge($media->custom_properties ?? [], $data['custom_properties']);
        }

        $media->save();

        return $media->fresh() ?? $media;
    }

    /**
     * @param  array{search?: string|null, collection?: string|null, mime_type?: string|null}  $params
     * @return Builder<Media>
     */
    protected function ownerQuery(HasMedia $owner, array $params = []): Builder
    {
        $defaultCollection = MediaCollection::Library->value;

        return Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->when(
                filled($params['collection'] ?? null),
                fn (Builder $query) => $query->where('collection_name', $params['collection']),
                fn (Builder $query) => $query->where('collection_name', $defaultCollection),
            )
            ->when($params['mime_type'] ?? null, fn (Builder $query, string $mime) => $query->where('mime_type', 'like', $mime.'%'))
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('file_name', 'like', $like);
                });
            });
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }

    /**
     * Ensure media belongs to the model (ownership / tenant isolation helper).
     *
     * @throws NotFoundHttpException
     */
    public function assertBelongsTo(HasMedia $model, Media $media): void
    {
        if ($media->model_type !== $model->getMorphClass() || (string) $media->model_id !== (string) $model->getKey()) {
            throw new NotFoundHttpException('Media not found.');
        }
    }

    protected function collectionName(MediaCollection|string $collection): string
    {
        return $collection instanceof MediaCollection ? $collection->value : $collection;
    }
}
