<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use App\Models\Landlord\World\State;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Application service for landlord State world data.
 */
class StateService
{
    /**
     * Retrieve a paginated list of states.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, State>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return State::query()
            ->filter($params)
            ->orderBy('name')
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a single state by identifier.
     *
     * @param  int  $id
     * @return State
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): State
    {
        return State::query()->findOrFail($id);
    }

    /**
     * Retrieve state options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, filters?: array<string, mixed>|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return State::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (State $state): array => [
                'label' => $state->name,
                'value' => $state->id,
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
