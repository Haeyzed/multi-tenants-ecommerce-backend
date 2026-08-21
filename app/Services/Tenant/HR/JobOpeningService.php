<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Models\HR\JobOpening;
use App\Services\Landlord\Feature\UsageLimiter;
use App\Services\Media\MediaService;
use App\Services\Tenant\Catalog\SeoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Recruitment job openings / listings.
 */
class JobOpeningService
{
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     * @param  SeoService  $seo
     * @param  MediaService  $media
     * @param  UsageLimiter  $usageLimiter
     * @param  RecruitmentActivityService  $activities
     * @param  WorkLocationService  $workLocations
     */
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly SeoService $seo,
        private readonly MediaService $media,
        private readonly UsageLimiter $usageLimiter,
        private readonly RecruitmentActivityService $activities,
        private readonly WorkLocationService $workLocations,
    ) {}

    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, status?: string|null, department_id?: int|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobOpening>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return JobOpening::query()
            ->with($this->openingRelations())
            ->withCount('applications')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a paginated public list of resources.
     *
     * @param  array{search?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, JobOpening>
     */
    public function listPublic(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertPublicJobListingsEnabled();

        return JobOpening::query()
            ->publiclyListed()
            ->with($this->openingRelations())
            ->filter(['search' => $params['search'] ?? null])
            ->applySort($params['sort'] ?? '-published_at')
            ->paginate($this->perPage($params));
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return JobOpening
     */
    public function store(array $data): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        $this->assertListingLimit($data['status'] ?? JobOpeningStatus::Draft);

        $opening = JobOpening::query()->create($this->payload($data, true));
        $this->syncLifecycleTimestamps($opening);

        if (is_array($seo)) {
            $this->seo->upsert($opening, $seo);
        }

        $this->activities->record($opening, 'created');

        return $opening->load($this->openingRelations());
    }

    /**
     * Retrieve a single resource.
     *
     * @param  JobOpening  $opening
     * @return JobOpening
     */
    public function show(JobOpening $opening): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $opening->load($this->openingRelations())->loadCount('applications');
    }

    /**
     * Retrieve a published resource by slug.
     *
     * @param  string  $slug
     * @return JobOpening
     */
    public function showPublicBySlug(string $slug): JobOpening
    {
        $this->hrSettings->assertPublicJobListingsEnabled();

        return JobOpening::query()
            ->publiclyListed()
            ->with($this->openingRelations())
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Update a resource.
     *
     * @param  JobOpening  $opening
     * @param  array<string, mixed>  $data
     * @return JobOpening
     */
    public function update(JobOpening $opening, array $data): JobOpening
    {
        $this->hrSettings->assertRecruitmentEnabled();

        $seo = $data['seo'] ?? null;
        unset($data['seo']);

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->nullableCode($data['code']);
        }

        $nextStatus = array_key_exists('status', $data)
            ? JobOpeningStatus::fromInput($data['status'])
            : $opening->status;

        if (! $opening->status->isActiveListing() && $nextStatus->isActiveListing()) {
            $this->assertListingLimit($nextStatus);
        }

        $opening->fill($this->payload($data, false));
        $this->syncLifecycleTimestamps($opening);
        $opening->save();

        if (is_array($seo)) {
            $this->seo->upsert($opening, $seo);
        }

        $this->activities->record($opening, 'updated', null, [
            'status' => $opening->status->value,
        ]);

        return $opening->fresh($this->openingRelations()) ?? $opening;
    }

    /**
     * Publish.
     *
     * @param  JobOpening  $opening
     * @return JobOpening
     */
    public function publish(JobOpening $opening): JobOpening
    {
        return $this->update($opening, [
            'status' => JobOpeningStatus::Published,
            'published_at' => $opening->published_at ?? now(),
            'closed_at' => null,
        ]);
    }

    /**
     * Pause.
     *
     * @param  JobOpening  $opening
     * @return JobOpening
     */
    public function pause(JobOpening $opening): JobOpening
    {
        return $this->update($opening, ['status' => JobOpeningStatus::Paused]);
    }

    /**
     * Close.
     *
     * @param  JobOpening  $opening
     * @return JobOpening
     */
    public function close(JobOpening $opening): JobOpening
    {
        return $this->update($opening, [
            'status' => JobOpeningStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /**
     * Cancel.
     *
     * @param  JobOpening  $opening
     * @return JobOpening
     */
    public function cancel(JobOpening $opening): JobOpening
    {
        return $this->update($opening, [
            'status' => JobOpeningStatus::Cancelled,
            'closed_at' => now(),
        ]);
    }

    /**
     * Add image.
     *
     * @param  JobOpening  $opening
     * @param  UploadedFile  $file
     * @return Media
     */
    public function addImage(JobOpening $opening, UploadedFile $file): Media
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->media->add($opening, $file, MediaCollection::FeaturedImage);
    }

    /**
     * Delete a resource.
     *
     * @param  JobOpening  $opening
     * @return void
     *
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
        $this->activities->record($opening, 'deleted');
        $opening->delete();
    }

    /**
     * Payload.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $creating
     * @return array<string, mixed>
     */
    protected function payload(array $data, bool $creating): array
    {
        $payload = [];

        $keys = [
            'title', 'slug', 'department_id', 'designation_id', 'work_location_id', 'employment_type', 'work_location',
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
            $payload['status'] = JobOpeningStatus::fromInput($data['status'] ?? JobOpeningStatus::Draft);
            $payload['openings_count'] = $data['openings_count'] ?? 1;
            $payload['salary_currency'] = strtoupper((string) ($data['salary_currency'] ?? $this->hrSettings->payrollCurrency()));
        } elseif (array_key_exists('code', $data)) {
            $payload['code'] = $this->nullableCode($data['code']);
        }

        if (isset($payload['salary_currency'])) {
            $payload['salary_currency'] = strtoupper((string) $payload['salary_currency']);
        }

        if (array_key_exists('status', $payload) && $payload['status'] !== null) {
            $payload['status'] = JobOpeningStatus::fromInput($payload['status']);
        }

        $payload = $this->workLocations->applySnapshot($payload);

        if (array_key_exists('work_location_id', $payload) && ! Schema::hasColumn('job_openings', 'work_location_id')) {
            unset($payload['work_location_id']);
        }

        return $payload;
    }

    /**
     * Sync lifecycle timestamps.
     *
     * @param  JobOpening  $opening
     * @return void
     */
    protected function syncLifecycleTimestamps(JobOpening $opening): void
    {
        if ($opening->status->isPubliclyListable() && $opening->published_at === null) {
            $opening->published_at = now();
        }

        if (in_array($opening->status, [JobOpeningStatus::Closed, JobOpeningStatus::Cancelled], true) && $opening->closed_at === null) {
            $opening->closed_at = now();
        }
    }

    /**
     * Assert listing limit.
     *
     * @param  JobOpeningStatus|string  $status
     * @return void
     */
    protected function assertListingLimit(JobOpeningStatus|string $status): void
    {
        $normalized = JobOpeningStatus::fromInput($status);

        if (! $normalized->isActiveListing()) {
            return;
        }

        $this->usageLimiter->assertLimitIfPresent('active_job_listings');
    }

    /**
     * Opening relations.
     *
     * @return list<string>
     */
    protected function openingRelations(): array
    {
        $relations = ['department', 'designation'];

        if (Schema::hasTable('seo_meta')) {
            $relations[] = 'seo';
        }

        if (Schema::hasColumn('job_openings', 'work_location_id')) {
            $relations[] = 'workLocation';
        }

        return $relations;
    }

    /**
     * Nullable code.
     *
     * @param  mixed  $code
     * @return ?string
     */
    protected function nullableCode(mixed $code): ?string
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';

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
