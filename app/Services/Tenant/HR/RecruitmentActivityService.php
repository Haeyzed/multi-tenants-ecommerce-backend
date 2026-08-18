<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\Candidate;
use App\Models\Tenant\Interview;
use App\Models\Tenant\JobApplication;
use App\Models\Tenant\JobOffer;
use App\Models\Tenant\RecruitmentActivity;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight recruitment audit log. Omits salary, notes, resumes, and feedback text.
 */
class RecruitmentActivityService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(Model $subject, string $action, ?User $actor = null, array $meta = []): void
    {
        if (! Schema::hasTable('recruitment_activities')) {
            return;
        }

        RecruitmentActivity::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'meta' => $this->sanitize($meta),
        ]);
    }

    /**
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, RecruitmentActivity>
     */
    public function listForCandidate(Candidate $candidate, array $params = []): LengthAwarePaginator
    {
        $applicationIds = $candidate->applications()->pluck('id')->all();

        return $this->paginate($this->groupsFor(
            $candidate,
            $applicationIds,
        ), $params);
    }

    /**
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, RecruitmentActivity>
     */
    public function listForApplication(JobApplication $application, array $params = []): LengthAwarePaginator
    {
        return $this->paginate($this->groupsFor($application, [$application->id]), $params);
    }

    /**
     * @param  list<int>  $applicationIds
     * @param  array{sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, RecruitmentActivity>
     */
    protected function paginate(array $groups, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['per_page'] ?? 15), 100));

        if (! Schema::hasTable('recruitment_activities')) {
            return new Paginator([], 0, $perPage);
        }

        $query = RecruitmentActivity::query()
            ->with('actor:id,first_name,last_name,email')
            ->where(function (Builder $query) use ($groups): void {
                foreach ($groups as [$type, $ids]) {
                    if ($ids === []) {
                        continue;
                    }

                    $query->orWhere(function (Builder $query) use ($type, $ids): void {
                        $query->where('subject_type', $type)->whereIn('subject_id', $ids);
                    });
                }
            });

        $sort = $params['sort'] ?? '-created_at';
        $direction = str_starts_with((string) $sort, '-') ? 'desc' : 'asc';
        $column = ltrim((string) $sort, '-');

        if (! in_array($column, ['created_at', 'id', 'action'], true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id')->paginate($perPage);
    }

    /**
     * @param  list<int>  $applicationIds
     * @return list<array{0: string, 1: list<int>}>
     */
    protected function groupsFor(Model $root, array $applicationIds): array
    {
        $interviewIds = $applicationIds === []
            ? []
            : Interview::query()->whereIn('job_application_id', $applicationIds)->pluck('id')->all();
        $offerIds = $applicationIds === []
            ? []
            : JobOffer::query()->whereIn('job_application_id', $applicationIds)->pluck('id')->all();

        return [
            [$root->getMorphClass(), [(int) $root->getKey()]],
            [(new JobApplication)->getMorphClass(), array_map('intval', $applicationIds)],
            [(new Interview)->getMorphClass(), array_map('intval', $interviewIds)],
            [(new JobOffer)->getMorphClass(), array_map('intval', $offerIds)],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function sanitize(array $meta): array
    {
        unset(
            $meta['salary'],
            $meta['notes'],
            $meta['cover_letter'],
            $meta['comments'],
            $meta['strengths'],
            $meta['weaknesses'],
            $meta['resume'],
            $meta['password'],
            $meta['credentials'],
            $meta['host_url'],
            $meta['client_secret'],
            $meta['access_token'],
            $meta['refresh_token'],
        );

        return $meta;
    }
}
