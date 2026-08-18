<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\ApplicationSource;
use App\Enums\Tenant\HR\JobApplicationStatus;
use Database\Factories\Tenant\JobApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Application of a candidate against a job opening.
 *
 * @property int $id
 * @property int $job_opening_id
 * @property int|null $candidate_id
 * @property int|null $recruitment_stage_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property ApplicationSource|null $source
 * @property Carbon|null $applied_at
 * @property JobApplicationStatus $status
 * @property string|null $cover_letter
 * @property string|null $notes
 * @property int|null $hired_employee_id
 */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_opening_id',
        'candidate_id',
        'recruitment_stage_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'source',
        'applied_at',
        'status',
        'cover_letter',
        'notes',
        'hired_employee_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'received',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_opening_id' => 'integer',
            'candidate_id' => 'integer',
            'recruitment_stage_id' => 'integer',
            'hired_employee_id' => 'integer',
            'source' => ApplicationSource::class,
            'applied_at' => 'datetime',
            'status' => JobApplicationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<JobOpening, $this>
     */
    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<RecruitmentStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'recruitment_stage_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }

    /**
     * @return HasMany<ApplicationStageHistory, $this>
     */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(ApplicationStageHistory::class)->latest('id');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * @return HasMany<JobOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class);
    }

    /**
     * @param  Builder<JobApplication>  $query
     * @param  array{search?: string|null, status?: string|null, job_opening_id?: int|null, candidate_id?: int|null, recruitment_stage_id?: int|null}  $params
     * @return Builder<JobApplication>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('candidate', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['job_opening_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('job_opening_id', $id);
            })
            ->when($params['candidate_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('candidate_id', $id);
            })
            ->when($params['recruitment_stage_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('recruitment_stage_id', $id);
            });
    }

    /**
     * @param  Builder<JobApplication>  $query
     * @return Builder<JobApplication>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['status', 'email', 'applied_at', 'created_at', 'id'];
        $sort = $sort ?: '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
