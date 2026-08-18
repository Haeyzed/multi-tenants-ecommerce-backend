<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\JobOfferStatus;
use Database\Factories\Tenant\JobOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Compensation offer against an application. Not an employee salary record.
 *
 * @property int $id
 * @property int $job_application_id
 * @property string|null $position
 * @property string $salary
 * @property string $currency
 * @property Carbon|null $start_date
 * @property Carbon|null $expires_at
 * @property JobOfferStatus $status
 * @property string|null $notes
 * @property int|null $approved_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $decided_at
 */
class JobOffer extends Model
{
    /** @use HasFactory<JobOfferFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'position',
        'salary',
        'currency',
        'start_date',
        'expires_at',
        'status',
        'notes',
        'approved_by',
        'sent_at',
        'decided_at',
        'response_token_hash',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'response_token_hash',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'NGN',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_application_id' => 'integer',
            'salary' => 'decimal:2',
            'start_date' => 'date',
            'expires_at' => 'date',
            'status' => JobOfferStatus::class,
            'approved_by' => 'integer',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->endOfDay()->lt(now())
            && ! $this->status->isTerminal();
    }
}
