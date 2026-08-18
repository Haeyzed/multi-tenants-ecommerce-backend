<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\HR\CandidateStatus;
use Database\Factories\Tenant\CandidateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Recruitment candidate. Not a User, Employee, seller, or customer.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $portfolio_url
 * @property string|null $linkedin_url
 * @property string|null $notes
 * @property CandidateStatus $status
 * @property int|null $employee_id
 */
class Candidate extends Model implements HasMedia
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory, InteractsWithMedia, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'portfolio_url',
        'linkedin_url',
        'notes',
        'status',
        'employee_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'status' => CandidateStatus::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Resume->value)
            ->singleFile()
            ->useDisk('local')
            ->acceptsMimeTypes(config('media.mimes.document', []));
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @param  Builder<Candidate>  $query
     * @param  array{search?: string|null, status?: string|null}  $params
     * @return Builder<Candidate>
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
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            });
    }

    /**
     * @param  Builder<Candidate>  $query
     * @return Builder<Candidate>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['first_name', 'email', 'status', 'created_at', 'id'];
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
