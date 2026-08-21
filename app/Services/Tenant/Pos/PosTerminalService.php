<?php

declare(strict_types=1);

namespace App\Services\Tenant\Pos;

use App\Enums\Tenant\Pos\PosTerminalStatus;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\PosTerminal;
use App\Services\Landlord\Feature\UsageLimiter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * POS terminal CRUD and select options.
 */
class PosTerminalService
{
    /**
     * Create a new class instance.
     *
     * @param  UsageLimiter  $usageLimiter
     */
    public function __construct(private readonly UsageLimiter $usageLimiter) {}

    /**
     * search?: string|null, status?: string|null, sort?: string|null, per_page?: int|null }  $params
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, PosTerminal>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return PosTerminal::query()
            ->with(['warehouse'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Return options for select inputs.
     *
     * @param  array{search?: string|null, status?: string|null}  $params
     * @return list<array{value: int, label: string}>
     */
    public function options(array $params = []): array
    {
        return PosTerminal::query()
            ->filter($params)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (PosTerminal $terminal): array => [
                'value' => $terminal->id,
                'label' => $terminal->name.' ('.$terminal->code.')',
            ])
            ->values()
            ->all();
    }

    /**
     * name: string, code: string, status?: PosTerminalStatus|string|null, warehouse_id?: int|null, location_label?: string|null }  $data
     *
     * @param  array{
     *     name: string,
     *     code: string,
     *     status?: PosTerminalStatus|string|null,
     *     warehouse_id?: int|null,
     *     location_label?: string|null
     * }  $data
     * @return PosTerminal
     */
    public function store(array $data): PosTerminal
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant && $tenant->activeSubscription() !== null) {
            $this->usageLimiter->assertCanCreate('pos_terminals', $tenant);
        }

        return PosTerminal::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'status' => $data['status'] ?? PosTerminalStatus::Active,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'location_label' => $data['location_label'] ?? null,
        ]);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  PosTerminal  $terminal
     * @return PosTerminal
     */
    public function show(PosTerminal $terminal): PosTerminal
    {
        return $terminal->loadMissing(['warehouse', 'openSession']);
    }

    /**
     * name?: string, code?: string, status?: PosTerminalStatus|string, warehouse_id?: int|null, location_label?: string|null }  $data
     *
     * @param  PosTerminal  $terminal
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     status?: PosTerminalStatus|string,
     *     warehouse_id?: int|null,
     *     location_label?: string|null
     * }  $data
     * @return PosTerminal
     */
    public function update(PosTerminal $terminal, array $data): PosTerminal
    {
        $terminal->fill($data);
        $terminal->save();

        return $terminal->fresh(['warehouse']) ?? $terminal;
    }

    /**
     * Delete a resource.
     *
     * @param  PosTerminal  $terminal
     * @return void
     */
    public function destroy(PosTerminal $terminal): void
    {
        if ($terminal->sessions()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages([
                'terminal' => 'Cannot delete a terminal with an open session.',
            ]);
        }

        $terminal->delete();
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
