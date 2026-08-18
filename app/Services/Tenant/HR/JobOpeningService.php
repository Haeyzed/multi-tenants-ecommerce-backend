<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Models\Tenant\JobOpening;
use App\Services\Media\MediaService;
use App\Services\Tenant\Catalog\SeoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Recruitment job openings / listings.
 */
class JobOpeningService
{
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly SeoService $seo,
        private readonly MediaService $media,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, department_id?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobOpening>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return JobOpening::query()
            ->with(['department', 'designation', 'seo'])
            ->withCount('applications')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{search?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobOpening>
     */
    public function listPublic(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertPublicJobListingsEnabled();

        return JobOpening::query()
            ->publiclyListed()
            ->with(['department', 'designation', 'seo'])
            ->filter(['search' => $params['search'] ?? null])
            ->applySort($params['sort'] ?? '-published_at')
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $opening = JobOpening::query()->create($this->payload($data, true));
        $this->syncLifecycleTimestamps($opening);

        if (is_array($seo)) {
            $this->seo->upsert($opening, $seo);
        }

        return $opening->load(['department', 'designation']);
    }

    public function show(JobOpening $opening): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $opening->load(['department', 'designation', 'seo'])->loadCount('applications');
    }

    public function showPublicBySlug(string $slug): JobOpening
    {
        $this->hrSettings->assertPublicJobListingsEnabled();

        return JobOpening::query()
            ->publiclyListed()
            ->with(['department', 'designation', 'seo'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobOpening $opening, array $data): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->nullableCode($data['code']);
        }

        $opening->fill($this->payload($data, false));
        $this->syncLifecycleTimestamps($opening);
        $opening->save();

        if (is_array($seo)) {
            $this->seo->upsert($opening, $seo);
        }

        return $opening->fresh(['department', 'designation']) ?? $opening;
    }

    public function publish(JobOpening $opening): JobOpening
    {
        return $this->update($opening, [
            'status' => JobOpeningStatus::Open,
            'published_at' => $opening->published_at ?? now(),
            'closed_at' => null,
        ]);
    }

    public function pause(JobOpening $opening): JobOpening
    {
        return $this->update($opening, ['status' => JobOpeningStatus::Paused]);
    }

    public function close(JobOpening $opening): JobOpening
    {
        return $this->update($opening, [
            'status' => JobOpeningStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function cancel(JobOpening $opening): JobOpening
    {
        return $this->update($opening, [
            'status' => JobOpeningStatus::Cancelled,
            'closed_at' => now(),
        ]);
    }

    public function addImage(JobOpening $opening, UploadedFile $file): Media
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->media->add($opening, $file, MediaCollection::FeaturedImage);
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

        $opening->clearMediaCollection(MediaCollection::FeaturedImage->value);
        $opening->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function payload(array $data, bool $creating): array
    {
        $payload = [];

        $keys = [
            'title', 'slug', 'department_id', 'designation_id', 'employment_type', 'work_location',
            'remote_type', 'experience_level', 'status', 'openings_count', 'salary_min', 'salary_max',
            'salary_currency', 'description', 'short_description', 'requirements', 'responsibilities',
            'qualifications', 'skills', 'benefits', 'closes_at', 'published_at', 'closed_at',
        ];

        foreach ($keys as $key) {
            if ($creating || array_key_exists($key, $data)) {
                if (array_key_exists($key, $data)) {
                    $payload[$key] = $data[$key];
                }
            }
        }

        if ($creating) {
            $payload['title'] = $data['title'];
            $payload['code'] = $this->nullableCode($data['code'] ?? null);
            $payload['status'] = $data['status'] ?? JobOpeningStatus::Draft;
            $payload['openings_count'] = $data['openings_count'] ?? 1;
            $payload['salary_currency'] = strtoupper((string) ($data['salary_currency'] ?? $this->hrSettings->payrollCurrency()));
        } elseif (array_key_exists('code', $data)) {
            $payload['code'] = $this->nullableCode($data['code']);
        }

        if (isset($payload['salary_currency'])) {
            $payload['salary_currency'] = strtoupper((string) $payload['salary_currency']);
        }

        return $payload;
    }

    protected function syncLifecycleTimestamps(JobOpening $opening): void
    {
        if ($opening->status === JobOpeningStatus::Open && $opening->published_at === null) {
            $opening->published_at = now();
        }

        if (in_array($opening->status, [JobOpeningStatus::Closed, JobOpeningStatus::Cancelled], true) && $opening->closed_at === null) {
            $opening->closed_at = now();
        }
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
