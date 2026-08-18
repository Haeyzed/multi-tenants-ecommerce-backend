<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Models\Tenant\RecruitmentStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-configurable application pipeline stages.
 */
class RecruitmentStageService
{
    /**
     * @var list<array{name: string, slug: string, kind: JobApplicationStatus, sort_order: int, is_default: bool, is_terminal: bool}>
     */
    public const DEFAULTS = [
        ['name' => 'Applied', 'slug' => 'applied', 'kind' => JobApplicationStatus::Received, 'sort_order' => 10, 'is_default' => true, 'is_terminal' => false],
        ['name' => 'Screening', 'slug' => 'screening', 'kind' => JobApplicationStatus::Screening, 'sort_order' => 20, 'is_default' => false, 'is_terminal' => false],
        ['name' => 'Shortlisted', 'slug' => 'shortlisted', 'kind' => JobApplicationStatus::Shortlisted, 'sort_order' => 30, 'is_default' => false, 'is_terminal' => false],
        ['name' => 'Interview', 'slug' => 'interview', 'kind' => JobApplicationStatus::Interview, 'sort_order' => 40, 'is_default' => false, 'is_terminal' => false],
        ['name' => 'Offer', 'slug' => 'offer', 'kind' => JobApplicationStatus::Offered, 'sort_order' => 50, 'is_default' => false, 'is_terminal' => false],
        ['name' => 'Hired', 'slug' => 'hired', 'kind' => JobApplicationStatus::Hired, 'sort_order' => 60, 'is_default' => false, 'is_terminal' => true],
        ['name' => 'Rejected', 'slug' => 'rejected', 'kind' => JobApplicationStatus::Rejected, 'sort_order' => 70, 'is_default' => false, 'is_terminal' => true],
        ['name' => 'Withdrawn', 'slug' => 'withdrawn', 'kind' => JobApplicationStatus::Withdrawn, 'sort_order' => 80, 'is_default' => false, 'is_terminal' => true],
    ];

    public function __construct(private readonly HrSettingsService $hrSettings) {}

    /**
     * @return Collection<int, RecruitmentStage>
     */
    public function list(): Collection
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->ensureDefaults();

        return RecruitmentStage::query()->applySort()->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): RecruitmentStage
    {
        $this->hrSettings->assertRecruitmentEnabled();
        $this->ensureDefaults();

        $stage = RecruitmentStage::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
            'kind' => $data['kind'] ?? JobApplicationStatus::Screening,
            'sort_order' => $data['sort_order'] ?? ((int) RecruitmentStage::query()->max('sort_order') + 10),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_terminal' => (bool) ($data['is_terminal'] ?? false),
        ]);

        if ($stage->is_default) {
            $this->clearOtherDefaults($stage);
        }

        return $stage;
    }

    public function show(RecruitmentStage $stage): RecruitmentStage
    {
        $this->hrSettings->assertRecruitmentEnabled();

        return $stage;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentStage $stage, array $data): RecruitmentStage
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $data['name'] ?? $stage->name, $stage->id);
        }

        $stage->fill($data);
        $stage->save();

        if ($stage->is_default) {
            $this->clearOtherDefaults($stage);
        }

        return $stage->fresh() ?? $stage;
    }

    /**
     * @throws ValidationException
     */
    public function destroy(RecruitmentStage $stage): void
    {
        $this->hrSettings->assertRecruitmentEnabled();

        if ($stage->applications()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This stage has applications and cannot be deleted.'],
            ]);
        }

        if ($stage->is_default) {
            throw ValidationException::withMessages([
                'id' => ['The default application stage cannot be deleted.'],
            ]);
        }

        $stage->delete();
    }

    public function defaultStage(): RecruitmentStage
    {
        $this->ensureDefaults();

        $stage = RecruitmentStage::query()->where('is_default', true)->orderBy('sort_order')->first();

        return $stage ?? RecruitmentStage::query()->orderBy('sort_order')->firstOrFail();
    }

    public function stageForKind(JobApplicationStatus $kind): RecruitmentStage
    {
        $this->ensureDefaults();

        $stage = RecruitmentStage::query()->where('kind', $kind)->orderBy('sort_order')->first();

        return $stage ?? $this->defaultStage();
    }

    public function ensureDefaults(): void
    {
        if (RecruitmentStage::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $row) {
            RecruitmentStage::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'kind' => $row['kind'],
                'sort_order' => $row['sort_order'],
                'is_default' => $row['is_default'],
                'is_terminal' => $row['is_terminal'],
            ]);
        }
    }

    protected function clearOtherDefaults(RecruitmentStage $stage): void
    {
        RecruitmentStage::query()->whereKeyNot($stage->id)->update(['is_default' => false]);
    }

    protected function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug((string) ($slug ?: $name));
        $base = $base !== '' ? $base : 'stage';
        $candidate = $base;
        $i = 1;

        while (RecruitmentStage::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
