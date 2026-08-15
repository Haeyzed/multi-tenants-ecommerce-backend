<?php

declare(strict_types=1);

namespace App\Services\Landlord\TenantProfile;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tenant profile CRUD scoped to a tenant, including logo/banner media.
 */
class TenantProfileService
{
    /**
     * Show the profile for a tenant.
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
     * @param  array{
     *     display_name: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     website?: string|null,
     *     address?: string|null,
     *     country_id?: int|null,
     *     state_id?: int|null,
     *     city_id?: int|null,
     *     currency_id?: int|null,
     *     language_id?: int|null,
     *     timezone?: string|null,
     *     is_public?: bool
     * }  $data
     */
    public function store(
        Tenant $tenant,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $banner = null,
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

        $this->syncMedia($profile, $logo, $banner);

        return $profile->fresh(['country', 'state', 'city', 'currency', 'language']) ?? $profile;
    }

    /**
     * Update a tenant's profile.
     *
     * @param  array{
     *     display_name?: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     website?: string|null,
     *     address?: string|null,
     *     country_id?: int|null,
     *     state_id?: int|null,
     *     city_id?: int|null,
     *     currency_id?: int|null,
     *     language_id?: int|null,
     *     timezone?: string|null,
     *     is_public?: bool
     * }  $data
     *
     * @throws NotFoundHttpException
     */
    public function update(
        Tenant $tenant,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $banner = null,
    ): TenantProfile {
        $profile = $this->showForTenant($tenant);

        $profile->fill($data);
        $profile->save();

        $this->syncMedia($profile, $logo, $banner);

        return $profile->fresh(['country', 'state', 'city', 'currency', 'language']) ?? $profile;
    }

    /**
     * Delete a tenant's profile and its media.
     *
     * @throws NotFoundHttpException
     */
    public function destroy(Tenant $tenant): void
    {
        $profile = $this->showForTenant($tenant);

        $profile->clearMediaCollection('logo');
        $profile->clearMediaCollection('banner');
        $profile->delete();
    }

    /**
     * Resolve a publicly visible profile by slug.
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
     * Attach logo and/or banner media when provided.
     */
    protected function syncMedia(
        TenantProfile $profile,
        ?UploadedFile $logo = null,
        ?UploadedFile $banner = null,
    ): void {
        if ($logo !== null) {
            $profile->clearMediaCollection('logo');
            $profile->addMedia($logo)->toMediaCollection('logo');
        }

        if ($banner !== null) {
            $profile->clearMediaCollection('banner');
            $profile->addMedia($banner)->toMediaCollection('banner');
        }
    }
}
