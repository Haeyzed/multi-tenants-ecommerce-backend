<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use Database\Factories\Landlord\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    /**
     * The guard name used by Spatie Permission for landlord users.
     */
    protected string $guard_name = 'landlord';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Register media collections for the landlord user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Avatar->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));

        $this->addMediaCollection(MediaCollection::Library->value)
            ->acceptsMimeTypes([
                ...config('media.mimes.image', []),
                ...config('media.mimes.document', []),
                ...config('media.mimes.video', []),
                ...config('media.mimes.audio', []),
            ]);
    }

    /**
     * Register image conversions for avatar and library images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = config('media.conversions.thumb');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Avatar->value, MediaCollection::Library->value);
    }

    /**
     * Resolve the public avatar URL when present.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(MediaCollection::Avatar->value) ?: null;
    }

    /**
     * @param  Builder<User>  $query
     * @param  array{search?: string|null}  $params
     * @return Builder<User>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        $search = $params['search'] ?? null;

        return $query->when($search, function (Builder $query, string $search): void {
            $like = '%'.$search.'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        });
    }
}
