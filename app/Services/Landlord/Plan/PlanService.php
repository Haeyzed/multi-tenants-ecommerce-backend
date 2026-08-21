<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plan;

use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Landlord plan CRUD and feature syncing.
 */
class PlanService
{
    /**
     * Retrieve a paginated list of plans.
     *
     * @param  array{search?: string|null, is_active?: bool|null, is_public?: bool|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Plan>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Plan::query()
            ->with('features')
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($params));
    }

    /**
     * List publicly available plans for the pricing page.
     *
     * @return Collection<int, Plan>
     */
    public function listPublic(): Collection
    {
        return Plan::query()
            ->publiclyAvailable()
            ->with('features')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Retrieve plan options as label/value pairs for select inputs.
     *
     * @param  array{search?: string|null, is_active?: bool|null, is_public?: bool|null}  $params
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(array $params = []): Collection
    {
        return Plan::query()
            ->filter($params)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'price', 'currency'])
            ->map(fn (Plan $plan): array => [
                'label' => $plan->name,
                'value' => $plan->id,
            ])
            ->values();
    }

    /**
     * Create a plan.
     *
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     price?: string|float|int,
     *     currency?: string,
     *     currency_id?: int|null,
     *     billing_interval?: string,
     *     billing_interval_count?: int,
     *     trial_days?: int,
     *     is_active?: bool,
     *     is_public?: bool,
     *     sort_order?: int,
     *     features?: list<array{slug: string, is_enabled?: bool, limit?: int|null}>
     * }  $data
     * @return Plan
     */
    public function store(array $data): Plan
    {
        $features = $data['features'] ?? null;
        unset($data['features']);

        return DB::connection((string) config('tenancy.database.central_connection'))
            ->transaction(function () use ($data, $features): Plan {
                /** @var Plan $plan */
                $plan = Plan::query()->create([
                    ...$data,
                    'price' => $data['price'] ?? 0,
                    'currency' => $data['currency'] ?? 'NGN',
                    'billing_interval' => $data['billing_interval'] ?? 'monthly',
                    'billing_interval_count' => $data['billing_interval_count'] ?? 1,
                    'trial_days' => $data['trial_days'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                    'is_public' => $data['is_public'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                ]);

                if (is_array($features)) {
                    $this->syncFeatures($plan, $features);
                }

                return $plan->load('features');
            });
    }

    /**
     * Show a plan.
     *
     * @param  Plan  $plan
     * @return Plan
     */
    public function show(Plan $plan): Plan
    {
        return $plan->load('features');
    }

    /**
     * Show a publicly available plan.
     *
     * @param  Plan  $plan
     * @return Plan
     */
    public function showPublic(Plan $plan): Plan
    {
        if (! $plan->is_public || ! $plan->is_active) {
            abort(404, 'Plan not found.');
        }

        return $plan->load('features');
    }

    /**
     * Update a plan.
     *
     * @param  Plan  $plan
     * @param  array{
     *     name?: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     price?: string|float|int,
     *     currency?: string,
     *     currency_id?: int|null,
     *     billing_interval?: string,
     *     billing_interval_count?: int,
     *     trial_days?: int,
     *     is_active?: bool,
     *     is_public?: bool,
     *     sort_order?: int,
     *     features?: list<array{slug: string, is_enabled?: bool, limit?: int|null}>
     * }  $data
     * @return Plan
     */
    public function update(Plan $plan, array $data): Plan
    {
        $features = $data['features'] ?? null;
        unset($data['features']);

        return DB::connection((string) config('tenancy.database.central_connection'))
            ->transaction(function () use ($plan, $data, $features): Plan {
                $plan->fill($data);
                $plan->save();

                if (is_array($features)) {
                    $this->syncFeatures($plan, $features);
                }

                return $plan->fresh('features') ?? $plan->load('features');
            });
    }

    /**
     * Delete a plan.
     *
     * @param  Plan  $plan
     * @return void
     */
    public function destroy(Plan $plan): void
    {
        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'plan' => ['Cannot delete a plan that has subscriptions.'],
            ]);
        }

        $plan->features()->detach();
        $plan->delete();
    }

    /**
     * Sync plan features by feature slug.
     *
     * @param  Plan  $plan
     * @param  list<array{feature?: string, slug?: string, enabled?: bool, is_enabled?: bool, limit?: int|null}>  $features
     * @return Plan
     *
     * @throws ValidationException
     */
    public function syncFeatures(Plan $plan, array $features): Plan
    {
        $sync = [];

        foreach ($features as $item) {
            $slug = $item['feature'] ?? $item['slug'] ?? null;

            if (! is_string($slug) || $slug === '') {
                throw ValidationException::withMessages([
                    'features' => ['Each feature must include a feature slug.'],
                ]);
            }

            /** @var Feature|null $feature */
            $feature = Feature::query()->where('slug', $slug)->first();

            if ($feature === null) {
                throw ValidationException::withMessages([
                    'features' => ["Feature [{$slug}] was not found."],
                ]);
            }

            $enabled = $item['enabled'] ?? $item['is_enabled'] ?? true;

            $sync[$feature->id] = [
                'is_enabled' => (bool) $enabled,
                'limit' => array_key_exists('limit', $item) ? $item['limit'] : null,
            ];
        }

        $plan->features()->sync($sync);

        return $plan->fresh('features') ?? $plan->load('features');
    }

    /**
     * Resolve the page size for paginated listings.
     *
     * @param  array{per_page?: int|null}  $params
     * @return int
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
