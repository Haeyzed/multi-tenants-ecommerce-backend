<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\HR\OvertimePolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Overtime policy CRUD.
 */
class OvertimePolicyService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, is_active?: bool|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, OvertimePolicy>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return OvertimePolicy::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Return options for select inputs.
     *
     * @return Collection<int, array{label: string, value: int, id: int}>
     */
    public function options(): Collection
    {
        return OvertimePolicy::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (OvertimePolicy $policy): array => [
                'label' => $policy->name,
                'value' => $policy->id,
                'id' => $policy->id,
            ])
            ->values();
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return OvertimePolicy
     */
    public function store(array $data): OvertimePolicy
    {
        $policy = OvertimePolicy::query()->create([
            'name' => $data['name'],
            'code' => $this->nullableCode($data['code'] ?? null),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => $data['is_active'] ?? true,
            'weekday_rate_percent' => $data['weekday_rate_percent'] ?? 150,
            'weekend_rate_percent' => $data['weekend_rate_percent'] ?? 200,
            'holiday_rate_percent' => $data['holiday_rate_percent'] ?? 200,
            'daily_threshold_minutes' => $data['daily_threshold_minutes'] ?? 0,
            'max_daily_minutes' => $data['max_daily_minutes'] ?? 0,
            'weekly_threshold_minutes' => $data['weekly_threshold_minutes'] ?? 0,
            'weekly_rate_percent' => $data['weekly_rate_percent'] ?? 150,
            'round_to_minutes' => $data['round_to_minutes'] ?? 1,
        ]);

        $this->ensureSingleDefault($policy);

        return $policy;
    }

    /**
     * Retrieve a single resource.
     *
     * @param  OvertimePolicy  $policy
     * @return OvertimePolicy
     */
    public function show(OvertimePolicy $policy): OvertimePolicy
    {
        return $policy;
    }

    /**
     * Update a resource.
     *
     * @param  OvertimePolicy  $policy
     * @param  array<string, mixed>  $data
     * @return OvertimePolicy
     */
    public function update(OvertimePolicy $policy, array $data): OvertimePolicy
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = $this->nullableCode($data['code']);
        }

        $policy->fill($data);
        $policy->save();
        $this->ensureSingleDefault($policy);

        return $policy;
    }

    /**
     * Delete a resource.
     *
     * @param  OvertimePolicy  $policy
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(OvertimePolicy $policy): void
    {
        if ($policy->workSchedules()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This overtime policy is assigned to a work schedule and cannot be deleted.'],
            ]);
        }

        $policy->delete();
    }

    /**
     * Ensure single default.
     *
     * @param  OvertimePolicy  $policy
     * @return void
     */
    protected function ensureSingleDefault(OvertimePolicy $policy): void
    {
        if (! $policy->is_default) {
            return;
        }

        OvertimePolicy::query()->whereKeyNot($policy->id)->update(['is_default' => false]);
    }

    /**
     * Nullable code.
     *
     * @param  mixed  $code
     * @return ?string
     */
    protected function nullableCode(mixed $code): ?string
    {
        $code = is_string($code) ? strtolower(trim($code)) : '';

        return $code === '' ? null : $code;
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
