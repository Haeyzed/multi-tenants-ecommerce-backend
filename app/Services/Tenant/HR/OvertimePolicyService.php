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
     * @param  array<string, mixed>  $data
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

    public function show(OvertimePolicy $policy): OvertimePolicy
    {
        return $policy;
    }

    /**
     * @param  array<string, mixed>  $data
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

    protected function ensureSingleDefault(OvertimePolicy $policy): void
    {
        if (! $policy->is_default) {
            return;
        }

        OvertimePolicy::query()->whereKeyNot($policy->id)->update(['is_default' => false]);
    }

    protected function nullableCode(mixed $code): ?string
    {
        $code = is_string($code) ? strtolower(trim($code)) : '';

        return $code === '' ? null : $code;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
