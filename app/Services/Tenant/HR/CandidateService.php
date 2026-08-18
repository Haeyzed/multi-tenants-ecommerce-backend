<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\HR\CandidateStatus;
use App\Models\Tenant\Candidate;
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
    public function __construct(
        private readonly HrSettingsService $hrSettings,
        private readonly MediaService $media,
    ) {}

    /**
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
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Candidate
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->findOrCreate($data);
    }

    public function show(Candidate $candidate): Candidate
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $candidate->load(['employee.user', 'applications.jobOpening'])->loadCount('applications');
    }

    /**
     * @param  array<string, mixed>  $data
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

        return $candidate->fresh(['employee.user']) ?? $candidate;
    }

    /**
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
        $candidate->delete();
    }

    public function addResume(Candidate $candidate, UploadedFile $file): Media
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $this->media->add($candidate, $file, MediaCollection::Resume);
    }

    /**
     * Reuse the same candidate for the same email or phone.
     *
     * @param  array<string, mixed>  $data
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

        return Candidate::query()->create([
            ...$payload,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? CandidateStatus::Active,
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
