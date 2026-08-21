<?php

declare(strict_types=1);

namespace App\Services\Tenant\Procurement;

use App\Enums\Tenant\Procurement\PurchaseOrderStatus;
use App\Enums\Tenant\Procurement\SupplierStatus;
use App\Models\Tenant\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for suppliers (soft deletes).
 */
class SupplierService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Supplier::query()->latest('id');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Return options for select inputs.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    public function options(): Collection
    {
        return Supplier::query()
            ->where('status', SupplierStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Supplier $supplier): array => [
                'label' => $supplier->name,
                'value' => $supplier->id,
            ])
            ->values();
    }

    /**
     * Create a resource.
     *
     * @param  array<string, mixed>  $data
     * @return Supplier
     */
    public function store(array $data): Supplier
    {
        return Supplier::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'status' => $data['status'] ?? SupplierStatus::Active->value,
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Supplier  $supplier
     * @return Supplier
     */
    public function show(Supplier $supplier): Supplier
    {
        return $supplier->load('contacts');
    }

    /**
     * Update a resource.
     *
     * @param  Supplier  $supplier
     * @param  array<string, mixed>  $data
     * @return Supplier
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->fill($data);
        $supplier->save();

        return $supplier->fresh('contacts') ?? $supplier;
    }

    /**
     * Soft-delete a supplier when no open purchase orders remain.
     *
     * @param  Supplier  $supplier
     * @return void
     *
     * @throws ValidationException
     */
    public function destroy(Supplier $supplier): void
    {
        $hasOpenPurchaseOrders = $supplier->purchaseOrders()
            ->whereNotIn('status', [
                PurchaseOrderStatus::Cancelled->value,
                PurchaseOrderStatus::Received->value,
                PurchaseOrderStatus::Closed->value,
            ])
            ->exists();

        if ($hasOpenPurchaseOrders) {
            throw ValidationException::withMessages([
                'supplier' => 'Cannot delete a supplier with open purchase orders.',
            ]);
        }

        $supplier->delete();
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
