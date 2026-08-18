<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Models\Tenant\JobOpening;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Recruitment job openings.
 */
class JobOpeningService
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @param  array{search?: string|null, status?: string|null, department_id?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobOpening>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return JobOpening::query()
            ->with(['department', 'designation'])
            ->withCount('applications')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return JobOpening::query()->create([
            'title' => $data['title'],
            'code' => $this->nullableCode($data['code'] ?? null),
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'status' => $data['status'] ?? JobOpeningStatus::Draft,
            'openings_count' => $data['openings_count'] ?? 1,
            'description' => $data['description'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
        ])->load(['department', 'designation']);
    }

    public function show(JobOpening $opening): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $opening->load(['department', 'designation'])->loadCount('applications');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobOpening $opening, array $data): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->nullableCode($data['code']);
        }

        $opening->fill($data);
        $opening->save();

        return $opening->fresh(['department', 'designation']) ?? $opening;
    }

    /**
     * @throws ValidationException
     */
    public function destroy(JobOpening $opening): void
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if ($opening->applications()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This job opening has applications and cannot be deleted.'],
            ]);
        }

        $opening->delete();
    }

    protected function nullableCode(mixed $code): ?string
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';

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
