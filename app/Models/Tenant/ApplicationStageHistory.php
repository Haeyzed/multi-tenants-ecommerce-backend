<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\JobApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable application stage/status change.
 *
 * @property int $id
 * @property int $job_application_id
 * @property int|null $from_stage_id
 * @property int|null $to_stage_id
 * @property JobApplicationStatus|null $from_status
 * @property JobApplicationStatus $to_status
 * @property int|null $changed_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
class ApplicationStageHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'from_stage_id',
        'to_stage_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_application_id' => 'integer',
            'from_stage_id' => 'integer',
            'to_stage_id' => 'integer',
            'changed_by' => 'integer',
            'from_status' => JobApplicationStatus::class,
            'to_status' => JobApplicationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return BelongsTo<RecruitmentStage, $this>
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'from_stage_id');
    }

    /**
     * @return BelongsTo<RecruitmentStage, $this>
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'to_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
