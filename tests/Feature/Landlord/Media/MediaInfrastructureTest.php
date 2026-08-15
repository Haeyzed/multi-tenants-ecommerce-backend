<?php

declare(strict_types=1);

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantProfile;
use App\Models\Landlord\User;
use App\Services\Media\MediaService;
use Database\Seeders\Landlord\PermissionSeeder;
use Database\Seeders\Landlord\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    Storage::fake('public');
    config([
        'media-library.disk_name' => 'public',
        'media-library.queue_conversions_by_default' => false,
    ]);
});

/**
 * @param  list<string>  $roles
 */
function mediaInfraLandlord(array $roles = ['admin']): User
{
    $user = User::factory()->create();
    $user->syncRoles($roles);

    return $user;
}

function mediaInfraTenant(): Tenant
{
    return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Media Store',
        'slug' => 'media-store-'.uniqid(),
        'email' => 'media-store@example.com',
        'status' => 'active',
        'is_active' => true,
    ]));
}

test('landlord can upload replace and delete avatar', function (): void {
    $user = mediaInfraLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $upload = $this->post('/api/auth/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
    ], ['Accept' => 'application/json']);

    $upload->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.collection', MediaCollection::Avatar->value)
        ->assertJsonStructure(['data' => ['id', 'url', 'mime_type', 'size', 'thumbnail_url']]);

    expect($user->fresh()->getMedia(MediaCollection::Avatar->value))->toHaveCount(1);

    $this->post('/api/auth/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar-2.png', 180, 180),
    ], ['Accept' => 'application/json'])
        ->assertOk();

    expect($user->fresh()->getMedia(MediaCollection::Avatar->value))->toHaveCount(1);

    $this->deleteJson('/api/auth/avatar')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->getMedia(MediaCollection::Avatar->value))->toHaveCount(0);
});

test('avatar upload rejects invalid and oversized files', function (): void {
    $user = mediaInfraLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $this->post('/api/auth/avatar', [
        'avatar' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    config(['media.upload_limits.image' => 10]);

    $this->post('/api/auth/avatar', [
        'avatar' => UploadedFile::fake()->image('huge.jpg')->size(50),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

test('landlord can upload replace and delete tenant profile logo', function (): void {
    $user = mediaInfraLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $tenant = mediaInfraTenant();
    TenantProfile::query()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'display_name' => 'Media Store',
        'slug' => 'media-store-profile-'.uniqid(),
        'is_public' => true,
    ]);

    $upload = $this->post("/api/tenants/{$tenant->id}/profile/logo", [
        'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
    ], ['Accept' => 'application/json']);

    $upload->assertOk()
        ->assertJsonPath('data.collection', MediaCollection::Logo->value);

    $profile = $tenant->fresh()->profile;
    expect($profile->getMedia(MediaCollection::Logo->value))->toHaveCount(1);

    $this->post("/api/tenants/{$tenant->id}/profile/logo", [
        'logo' => UploadedFile::fake()->image('logo-2.jpg', 320, 320),
    ], ['Accept' => 'application/json'])
        ->assertOk();

    expect($profile->fresh()->getMedia(MediaCollection::Logo->value))->toHaveCount(1);

    $this->deleteJson("/api/tenants/{$tenant->id}/profile/logo")
        ->assertOk();

    expect($profile->fresh()->getMedia(MediaCollection::Logo->value))->toHaveCount(0);
});

test('media service assertBelongsTo blocks cross-owner deletion', function (): void {
    $owner = mediaInfraLandlord();
    $other = mediaInfraLandlord();

    $media = app(MediaService::class)->add(
        $owner,
        UploadedFile::fake()->image('private.jpg'),
        MediaCollection::Avatar,
    );

    expect(fn () => app(MediaService::class)->remove($other, $media))
        ->toThrow(NotFoundHttpException::class);
});

test('single file collection replaces existing media', function (): void {
    $user = mediaInfraLandlord();
    $service = app(MediaService::class);

    $first = $service->replace($user, UploadedFile::fake()->image('one.jpg'), MediaCollection::Avatar);
    $second = $service->replace($user, UploadedFile::fake()->image('two.jpg'), MediaCollection::Avatar);

    expect($user->fresh()->getMedia(MediaCollection::Avatar->value))->toHaveCount(1)
        ->and(Media::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and(Media::query()->whereKey($second->id)->exists())->toBeTrue();
});

test('media service addMany attaches multiple files to multi-file collections', function (): void {
    $profile = TenantProfile::query()->create([
        'tenant_id' => mediaInfraTenant()->getTenantKey(),
        'display_name' => 'Gallery Profile',
        'slug' => 'gallery-profile-'.uniqid(),
        'is_public' => false,
    ]);

    // Attach via generic gallery collection without model registration — use MediaCollection value on HasMedia.
    // TenantProfile only registers logo/cover; addMany still works for any collection name on Spatie.
    $media = app(MediaService::class)->addMany(
        $profile,
        [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
        MediaCollection::Gallery,
    );

    expect($media)->toHaveCount(2)
        ->and($profile->getMedia(MediaCollection::Gallery->value))->toHaveCount(2);
});

test('avatar conversions are generated for images', function (): void {
    $user = mediaInfraLandlord();

    $media = app(MediaService::class)->replace(
        $user,
        UploadedFile::fake()->image('avatar.jpg', 400, 400),
        MediaCollection::Avatar,
    );

    expect($media->hasGeneratedConversion(MediaConversion::Thumb->value))->toBeTrue();
});

test('deleting user cleans avatar media', function (): void {
    $user = mediaInfraLandlord();
    $media = app(MediaService::class)->replace(
        $user,
        UploadedFile::fake()->image('avatar.jpg'),
        MediaCollection::Avatar,
    );

    $mediaId = $media->id;
    $user->delete();

    expect(Media::query()->whereKey($mediaId)->exists())->toBeFalse();
});

test('unauthenticated users cannot upload avatar', function (): void {
    $this->post('/api/auth/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

test('landlord can manage own media library', function (): void {
    $user = mediaInfraLandlord();
    Sanctum::actingAs($user, ['*'], 'landlord');

    $upload = $this->post('/api/media', [
        'file' => UploadedFile::fake()->image('library.jpg', 400, 400),
        'name' => 'Library Shot',
    ], ['Accept' => 'application/json']);

    $upload->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.collection', MediaCollection::Library->value)
        ->assertJsonPath('data.name', 'Library Shot');

    $mediaId = $upload->json('data.id');

    $this->getJson('/api/media')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $mediaId);

    $this->getJson('/api/media/options')
        ->assertOk()
        ->assertJsonPath('data.0.value', $mediaId)
        ->assertJsonPath('data.0.label', 'Library Shot');

    $this->getJson("/api/media/{$mediaId}")
        ->assertOk()
        ->assertJsonPath('data.id', $mediaId);

    $this->putJson("/api/media/{$mediaId}", [
        'name' => 'Renamed Shot',
        'custom_properties' => ['alt' => 'storefront'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Shot');

    $this->deleteJson("/api/media/{$mediaId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Media::query()->whereKey($mediaId)->exists())->toBeFalse();
});

test('landlord cannot access another users media', function (): void {
    $owner = mediaInfraLandlord();
    $other = mediaInfraLandlord();

    $media = app(MediaService::class)->add(
        $owner,
        UploadedFile::fake()->image('private-lib.jpg'),
        MediaCollection::Library,
    );

    Sanctum::actingAs($other, ['*'], 'landlord');

    $this->getJson("/api/media/{$media->id}")->assertNotFound();
    $this->putJson("/api/media/{$media->id}", ['name' => 'Stolen'])->assertNotFound();
    $this->deleteJson("/api/media/{$media->id}")->assertNotFound();
});

test('unauthenticated users cannot access media library', function (): void {
    $this->getJson('/api/media')->assertUnauthorized();
    $this->post('/api/media', [
        'file' => UploadedFile::fake()->image('library.jpg'),
    ], ['Accept' => 'application/json'])->assertUnauthorized();
});
