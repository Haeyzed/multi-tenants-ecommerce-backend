<?php

declare(strict_types=1);

namespace App\Services\Landlord\TenantProfile;

use App\Enums\Media\MediaCollection;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tenant profile CRUD scoped to a tenant, including logo/cover media.
 */
class TenantProfileService
{
    /**
     * Create a new class instance.
     *
     * @param  MediaService  $mediaService
     */
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Show the profile for a tenant.
     *
     * @param  Tenant  $tenant
     * @return TenantProfile
     *
     * @throws NotFoundHttpException
     */
    public function showForTenant(Tenant $tenant): TenantProfile
    {
        $profile = $tenant->profile;

        if ($profile === null) {
            throw new NotFoundHttpException('Tenant profile not found.');
        }

        return $profile->load(['country', 'state', 'city', 'currency', 'language']);
    }

    /**
     * Create a profile for a tenant that does not yet have one.
     *
     * @param  Tenant  $tenant
     * @param  array<string, mixed>  $data
     * @param  ?UploadedFile  $logo
     * @param  ?UploadedFile  $cover
     * @return TenantProfile
     */
    public function store(
        Tenant $tenant,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $cover = null,
    ): TenantProfile {
        if ($tenant->profile()->exists()) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Tenant profile already exists.'],
            ]);
        }

        /** @var TenantProfile $profile */
        $profile = TenantProfile::query()->create([
            ...$data,
            'tenant_id' => $tenant->getTenantKey(),
            'is_public' => $data['is_public'] ?? false,
        ]);

        $this->syncMedia($profile, $logo, $cover);

        return $profile->fresh(['country', 'state', 'city', 'currency', 'language']) ?? $profile;
    }

    /**
     * Update a tenant's profile.
     *
     * @param  Tenant  $tenant
     * @param  array<string, mixed>  $data
     * @param  ?UploadedFile  $logo
     * @param  ?UploadedFile  $cover
     * @return TenantProfile
     *
     * @throws NotFoundHttpException
     */
    public function update(
        Tenant $tenant,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $cover = null,
    ): TenantProfile {
        $profile = $this->showForTenant($tenant);

        $profile->fill($data);
        $profile->save();

        $this->syncMedia($profile, $logo, $cover);

        return $profile->fresh(['country', 'state', 'city', 'currency', 'language']) ?? $profile;
    }

    /**
     * Replace the tenant profile logo.
     *
     * @param  Tenant  $tenant
     * @param  UploadedFile  $logo
     * @return Media
     *
     * @throws NotFoundHttpException
     */
    public function replaceLogo(Tenant $tenant, UploadedFile $logo): Media
    {
        $profile = $this->showForTenant($tenant);

        return $this->mediaService->replace($profile, $logo, MediaCollection::Logo);
    }

    /**
     * Remove the tenant profile logo.
     *
     * @param  Tenant  $tenant
     * @return void
     *
     * @throws NotFoundHttpException
     */
    public function removeLogo(Tenant $tenant): void
    {
        $profile = $this->showForTenant($tenant);

        $this->mediaService->removeCollection($profile, MediaCollection::Logo);
    }

    /**
     * Replace the tenant profile cover image.
     *
     * @param  Tenant  $tenant
     * @param  UploadedFile  $cover
     * @return Media
     *
     * @throws NotFoundHttpException
     */
    public function replaceCover(Tenant $tenant, UploadedFile $cover): Media
    {
        $profile = $this->showForTenant($tenant);

        return $this->mediaService->replace($profile, $cover, MediaCollection::Cover);
    }

    /**
     * Remove the tenant profile cover image.
     *
     * @param  Tenant  $tenant
     * @return void
     *
     * @throws NotFoundHttpException
     */
    public function removeCover(Tenant $tenant): void
    {
        $profile = $this->showForTenant($tenant);

        $this->mediaService->removeCollection($profile, MediaCollection::Cover);
    }

    /**
     * Delete a tenant's profile and its media.
     *
     * @param  Tenant  $tenant
     * @return void
     *
     * @throws NotFoundHttpException
     */
    public function destroy(Tenant $tenant): void
    {
        $profile = $this->showForTenant($tenant);

        $this->mediaService->removeCollection($profile, MediaCollection::Logo);
        $this->mediaService->removeCollection($profile, MediaCollection::Cover);
        $profile->delete();
    }

    /**
     * Resolve a publicly visible profile by slug.
     *
     * @param  string  $slug
     * @return TenantProfile
     *
     * @throws NotFoundHttpException
     */
    public function showPublicBySlug(string $slug): TenantProfile
    {
        /** @var TenantProfile|null $profile */
        $profile = TenantProfile::query()
            ->where('slug', $slug)
            ->where('is_public', true)
            ->first();

        if ($profile === null) {
            throw new NotFoundHttpException('Public tenant profile not found.');
        }

        return $profile->load(['country', 'state', 'city', 'currency', 'language']);
    }

    /**
     * Attach logo and/or cover media when provided.
     *
     * @param  TenantProfile  $profile
     * @param  ?UploadedFile  $logo
     * @param  ?UploadedFile  $cover
     * @return void
     */
    protected function syncMedia(
        TenantProfile $profile,
        ?UploadedFile $logo = null,
        ?UploadedFile $cover = null,
    ): void {
        if ($logo !== null) {
            $this->mediaService->replace($profile, $logo, MediaCollection::Logo);
        }

        if ($cover !== null) {
            $this->mediaService->replace($profile, $cover, MediaCollection::Cover);
        }
    }
}
