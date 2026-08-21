<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Enums\Tenant\HR\LeaveType as LeaveTypeCode;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-configurable leave categories with seeded defaults.
 */
class LeaveTypeService
{
    /**
     * search?: string|null, is_active?: bool|null, is_paid?: bool|null, sort?: string|null, per_page?: int|null }  $params
     *
     * @param  array{
     *     search?: string|null,
     *     is_active?: bool|null,
     *     is_paid?: bool|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, LeaveType>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $this->ensureDefaults();

        return LeaveType::query()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Return options for select inputs.
     *
     * @return Collection<int, array{label: string, value: string, id: int}>
     */
    public function options(): Collection
    {
        $this->ensureDefaults();

        return LeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (LeaveType $type): array => [
                'label' => $type->name,
                'value' => $type->code,
                'id' => $type->id,
            ])
            ->values();
    }

    /**
     * name: string, code: string, is_paid?: bool, is_active?: bool, default_days?: int, allow_carry_over?: bool, description?: string|null }  $data
     *
     * @param  array{
     *     name: string,
     *     code: string,
     *     is_paid?: bool,
     *     is_active?: bool,
     *     default_days?: int,
     *     allow_carry_over?: bool,
     *     description?: string|null
     * }  $data
     * @return LeaveType
     */
    public function store(array $data): LeaveType
    {
        $this->ensureDefaults();

        return LeaveType::query()->create([
            'name' => $data['name'],
            'code' => strtolower($data['code']),
            'is_paid' => $data['is_paid'] ?? true,
            'is_active' => $data['is_active'] ?? true,
            'default_days' => $data['default_days'] ?? 0,
            'allow_carry_over' => $data['allow_carry_over'] ?? false,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  LeaveType  $leaveType
     * @return LeaveType
     */
    public function show(LeaveType $leaveType): LeaveType
    {
        return $leaveType;
    }

    /**
     * name?: string, code?: string, is_paid?: bool, is_active?: bool, default_days?: int, allow_carry_over?: bool, description?: string|null }  $data
     *
     * @param  LeaveType  $leaveType
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     is_paid?: bool,
     *     is_active?: bool,
     *     default_days?: int,
     *     allow_carry_over?: bool,
     *     description?: string|null
     * }  $data
     * @return LeaveType
     */
    public function update(LeaveType $leaveType, array $data): LeaveType
    {
        if (array_key_exists('code', $data) && is_string($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }

        $leaveType->fill($data);
        $leaveType->save();

        return $leaveType->fresh() ?? $leaveType;
    }

    /**
     * Delete a resource.
     *
     * @param  LeaveType  $leaveType
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(LeaveType $leaveType): void
    {
        if (LeaveRequest::query()->where('leave_type_id', $leaveType->id)->exists()) {
            throw ValidationException::withMessages([
                'leave_type' => ['This leave type is in use and cannot be deleted. Deactivate it instead.'],
            ]);
        }

        $leaveType->delete();
    }

    /**
     * Resolve an active leave type by code, seeding defaults if needed.
     *
     * @param  string  $code
     * @return LeaveType
     *
     * @throws ValidationException
     */
    public function findActiveByCode(string $code): LeaveType
    {
        $this->ensureDefaults();

        $type = LeaveType::query()->where('code', strtolower($code))->first();

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => ['The selected leave type is invalid.'],
            ]);
        }

        if (! $type->is_active) {
            throw ValidationException::withMessages([
                'type' => ['The selected leave type is inactive.'],
            ]);
        }

        return $type;
    }

    /**
     * Resolve an active leave type by primary key, seeding defaults if needed.
     *
     * @param  int  $id
     * @return LeaveType
     *
     * @throws ValidationException
     */
    public function findActiveById(int $id): LeaveType
    {
        $this->ensureDefaults();

        $type = LeaveType::query()->find($id);

        if ($type === null) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['The selected leave type is invalid.'],
            ]);
        }

        if (! $type->is_active) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['The selected leave type is inactive.'],
            ]);
        }

        return $type;
    }

    /**
     * Seed Annual, Sick, Unpaid, and Other when the tenant has no leave types.
     *
     * @return void
     */
    public function ensureDefaults(): void
    {
        if (LeaveType::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Annual', 'code' => LeaveTypeCode::Annual->value, 'is_paid' => true, 'default_days' => 21, 'allow_carry_over' => true],
            ['name' => 'Sick', 'code' => LeaveTypeCode::Sick->value, 'is_paid' => true, 'default_days' => 10, 'allow_carry_over' => false],
            ['name' => 'Unpaid', 'code' => LeaveTypeCode::Unpaid->value, 'is_paid' => false, 'default_days' => 0, 'allow_carry_over' => false],
            ['name' => 'Other', 'code' => LeaveTypeCode::Other->value, 'is_paid' => true, 'default_days' => 0, 'allow_carry_over' => false],
        ];

        foreach ($defaults as $default) {
            LeaveType::query()->create($default);
        }
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
