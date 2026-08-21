<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use App\Models\Landlord\World\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Application service for landlord Language world data.
 */
class LanguageService
{
    /**
     * Retrieve a paginated list of languages.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Language>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Language::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a single language by identifier.
     *
     * @param  int  $id
     * @return Language
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): Language
    {
        return Language::query()->findOrFail($id);
    }

    /**
     * Retrieve language options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Language::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Language $language): array => [
                'label' => $language->name,
                'value' => $language->id,
            ])
            ->values();
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
