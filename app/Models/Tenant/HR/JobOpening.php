<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Enums\Tenant\HR\EmploymentType;
use App\Enums\Tenant\HR\JobOpeningStatus;
use App\Enums\Tenant\HR\JobRemoteType;
use App\Models\Concerns\HasSeo;
use Database\Factories\HR\JobOpeningFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Recruitment vacancy / job listing.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $code
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property int|null $work_location_id
 * @property EmploymentType|null $employment_type
 * @property string|null $work_location
 * @property JobRemoteType|null $remote_type
 * @property string|null $experience_level
 * @property JobOpeningStatus $status
 * @property int $openings_count
 * @property string|null $salary_min
 * @property string|null $salary_max
 * @property string $salary_currency
 * @property string|null $description
 * @property string|null $short_description
 * @property string|null $requirements
 * @property string|null $responsibilities
 * @property string|null $qualifications
 * @property list<string>|null $skills
 * @property string|null $benefits
 * @property Carbon|null $closes_at
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 */
class JobOpening extends Model implements HasMedia
{
    /** @use HasFactory<JobOpeningFactory> */
    use HasFactory, HasSeo, HasSlug, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'code',
        'department_id',
        'designation_id',
        'work_location_id',
        'employment_type',
        'work_location',
        'remote_type',
        'experience_level',
        'status',
        'openings_count',
        'salary_min',
        'salary_max',
        'salary_currency',
        'description',
        'short_description',
        'requirements',
        'responsibilities',
        'qualifications',
        'skills',
        'benefits',
        'closes_at',
        'published_at',
        'closed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'openings_count' => 1,
        'salary_currency' => 'NGN',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'designation_id' => 'integer',
            'work_location_id' => 'integer',
            'employment_type' => EmploymentType::class,
            'remote_type' => JobRemoteType::class,
            'status' => JobOpeningStatus::class,
            'openings_count' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'skills' => 'array',
            'closes_at' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->skipGenerateWhen(fn (): bool => filled($this->slug));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::FeaturedImage->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::FeaturedImage->value);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<WorkLocation, $this>
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function isAcceptingApplications(): bool
    {
        if (! $this->status->acceptsApplications()) {
            return false;
        }

        if ($this->closes_at !== null && $this->closes_at->endOfDay()->lt(now())) {
            return false;
        }

        return true;
    }

    /**
     * @param  Builder<JobOpening>  $query
     * @return Builder<JobOpening>
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [JobOpeningStatus::Published, JobOpeningStatus::Open])
            ->where(function (Builder $query): void {
                $query->whereNull('closes_at')
                    ->orWhereDate('closes_at', '>=', now()->toDateString());
            });
    }

    /**
     * @param  Builder<JobOpening>  $query
     * @param  array{search?: string|null, status?: string|null, department_id?: int|null}  $params
     * @return Builder<JobOpening>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
            })
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['department_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('department_id', $id);
            });
    }

    /**
     * @param  Builder<JobOpening>  $query
     * @return Builder<JobOpening>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['title', 'status', 'closes_at', 'published_at', 'created_at', 'id'];
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
