<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\HR\CandidateStatus;
use App\Models\Tenant\HR\Candidate;
use App\Models\Tenant\HR\RecruitmentActivity;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Candidate CRM. One person, many applications. Not a User or Employee.
 */
class CandidateService
{
    /**
     * Create a new class instance.
     *
     * @param  HrSettingsService  $hrSettings
     * @param  MediaService  $media
     * @param  RecruitmentActivityService  $activities
     */
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly MediaService $media,
        private readonly RecruitmentActivityService $activities,
    ) {}

    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, status?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Candidate>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return Candidate::query()
            ->withCount('applications')
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return Candidate
     */
    public function store(array $data): Candidate
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->findOrCreate($data);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Candidate  $candidate
     * @return Candidate
     */
    public function show(Candidate $candidate): Candidate
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $candidate->load(['employee.user', 'applications.jobOpening'])->loadCount('applications');
    }

    /**
     * List activities.
     *
     * @param  Candidate  $candidate
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, RecruitmentActivity>
     */
    public function listActivities(Candidate $candidate, array $params = []): LengthAwarePaginator
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->activities->listForCandidate($candidate, $params);
    }

    /**
     * Update a resource.
     *
     * @param  Candidate  $candidate
     * @param  array<string, mixed>  $data
     * @return Candidate
     */
    public function update(Candidate $candidate, array $data): Candidate
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim((string) $data['email']));
        }

        unset($data['employee_id']);

        $candidate->fill($data);
        $candidate->save();

        $this->activities->record($candidate, 'updated');

        return $candidate->fresh(['employee.user']) ?? $candidate;
    }

    /**
     * Delete a resource.
     *
     * @param  Candidate  $candidate
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(Candidate $candidate): void
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if ($candidate->applications()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This candidate has applications and cannot be deleted.'],
            ]);
        }

        $candidate->clearMediaCollection(MediaCollection::Resume->value);
        $this->activities->record($candidate, 'deleted');
        $candidate->delete();
    }

    /**
     * Add resume.
     *
     * @param  Candidate  $candidate
     * @param  UploadedFile  $file
     * @return Media
     */
    public function addResume(Candidate $candidate, UploadedFile $file): Media
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->media->add($candidate, $file, MediaCollection::Resume);
    }

    /**
     * Reuse the same candidate for the same email or phone.
     *
     * @param  array<string, mixed>  $data
     * @return Candidate
     */
    public function findOrCreate(array $data): Candidate
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
        $phone = $phone === '' ? null : $phone;

        $candidate = $email !== ''
            ? Candidate::query()->where('email', $email)->first()
            : null;

        if ($candidate === null && $phone !== null) {
            $candidate = Candidate::query()->where('phone', $phone)->first();
        }

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email !== '' ? $email : ($candidate?->email ?? $email),
            'phone' => $phone ?? $candidate?->phone,
            'address' => $data['address'] ?? $candidate?->address,
            'portfolio_url' => $data['portfolio_url'] ?? $candidate?->portfolio_url,
            'linkedin_url' => $data['linkedin_url'] ?? $candidate?->linkedin_url,
        ];

        if ($candidate !== null) {
            $candidate->fill(array_filter($payload, fn (mixed $value): bool => $value !== null && $value !== ''));
            $candidate->save();

            return $candidate->fresh() ?? $candidate;
        }

        $created = Candidate::query()->create([
            ...$payload,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? CandidateStatus::Active,
        ]);

        $this->activities->record($created, 'created');

        return $created;
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
