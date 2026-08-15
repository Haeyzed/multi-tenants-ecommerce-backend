<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
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
     * The guard name used by Spatie Permission for tenant users.
     */
    protected string $guard_name = 'tenant';

    /**
     * Get the attributes that should be cast.
     *
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
     * Register the avatar media collection.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    /**
     * Resolve the public avatar URL when present.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        /** @var Media|null $media */
        $media = $this->getFirstMedia('avatar');

        return $media?->getUrl();
    }

    /**
     * Apply search and filter constraints to the user query.
     *
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
