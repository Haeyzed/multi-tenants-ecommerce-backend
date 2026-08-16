<?php

declare(strict_types=1);

namespace App\Services\Tenant\Integration;

use App\Models\Tenant\User;
use App\Services\Landlord\Feature\FeatureGate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mint and revoke long-lived Sanctum tokens for programmatic integrations.
 *
 * Product SPA login tokens remain ungated. Only integration tokens require the
 * plan `api-access` feature.
 */
class IntegrationTokenService
{
    public const string TOKEN_NAME_PREFIX = 'integration:';

    public function __construct(private readonly FeatureGate $featureGate) {}

    /**
     * @param  array{per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, PersonalAccessToken>
     */
    public function list(User $user, array $params = []): LengthAwarePaginator
    {
        $this->assertApiAccess();

        return $user->tokens()
            ->where('name', 'like', self::TOKEN_NAME_PREFIX.'%')
            ->latest('id')
            ->paginate($this->perPage($params));
    }

    /**
     * @param  array{name: string, abilities?: list<string>|null}  $data
     * @return array{token: PersonalAccessToken, plain_text_token: string}
     */
    public function create(User $user, array $data): array
    {
        $this->assertApiAccess();

        $label = trim($data['name']);
        $name = self::TOKEN_NAME_PREFIX.$label;

        /** @var list<string> $abilities */
        $abilities = $data['abilities'] ?? ['*'];

        if ($abilities === []) {
            $abilities = ['*'];
        }

        $newAccessToken = $user->createToken($name, $abilities);

        return [
            'token' => $newAccessToken->accessToken,
            'plain_text_token' => $newAccessToken->plainTextToken,
        ];
    }

    public function destroy(User $user, int $tokenId): void
    {
        $this->assertApiAccess();

        /** @var PersonalAccessToken|null $token */
        $token = $user->tokens()
            ->whereKey($tokenId)
            ->where('name', 'like', self::TOKEN_NAME_PREFIX.'%')
            ->first();

        if ($token === null) {
            throw new NotFoundHttpException('Integration token not found.');
        }

        $token->delete();
    }

    protected function assertApiAccess(): void
    {
        $tenant = tenant();

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'feature' => 'Tenant context is required for integration tokens.',
            ]);
        }

        // Match DomainService: only enforce when a subscription exists so local
        // provisioning without a plan is not blocked, while subscribed Free/Starter
        // plans without api-access are denied.
        if ($tenant->activeSubscription() !== null) {
            $this->featureGate->assert('api-access', $tenant);
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
