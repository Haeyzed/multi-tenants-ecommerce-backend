<?php

declare(strict_types=1);

namespace App\Services\Tenant\Customer;

use App\Enums\Media\MediaCollection;
use App\Enums\Notification\NotificationChannel;
use App\Enums\Tenant\Customer\CustomerStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Services\Media\MediaService;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Admin and self-service customer management.
 */
class CustomerService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MediaService $mediaService,
    ) {}

    /**
     * Paginate customers with search, filters, and sorts.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Customer>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        return Customer::query()
            ->withTrashed()
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Retrieve a customer with addresses.
     */
    public function show(Customer $customer): Customer
    {
        return $customer->load(['addresses', 'customerGroup']);
    }

    /**
     * Update a customer (admin — no password changes).
     *
     * @param  array{
     *     first_name?: string,
     *     last_name?: string,
     *     email?: string,
     *     phone?: string|null,
     *     status?: CustomerStatus|string,
     *     customer_group_id?: int|null
     * }  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill($data);
        $customer->save();

        return $customer->fresh(['addresses', 'customerGroup']) ?? $customer->load(['addresses', 'customerGroup']);
    }

    /**
     * Update a customer's status.
     */
    public function updateStatus(Customer $customer, CustomerStatus $status): Customer
    {
        $customer->forceFill(['status' => $status])->save();

        if ($status !== CustomerStatus::Active) {
            $customer->tokens()->delete();
        }

        return $customer->fresh(['addresses']) ?? $customer->load('addresses');
    }

    /**
     * Update the authenticated customer's profile and optional avatar.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string|null}  $data
     */
    public function updateProfile(Customer $customer, array $data, ?UploadedFile $avatar = null): Customer
    {
        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $customer->email;

        $customer->fill($data);

        if ($emailChanged) {
            $customer->forceFill(['email_verified_at' => null]);
        }

        $customer->save();

        if ($emailChanged) {
            $customer->sendEmailVerificationNotification();
        }

        if ($avatar !== null) {
            $this->mediaService->replace($customer, $avatar, MediaCollection::Avatar);
        }

        return $customer->fresh() ?? $customer;
    }

    /**
     * Replace the authenticated customer's avatar.
     */
    public function replaceAvatar(Customer $customer, UploadedFile $avatar): Media
    {
        return $this->mediaService->replace($customer, $avatar, MediaCollection::Avatar);
    }

    /**
     * Remove the authenticated customer's avatar.
     */
    public function removeAvatar(Customer $customer): void
    {
        $this->mediaService->removeCollection($customer, MediaCollection::Avatar);
    }

    /**
     * Deactivate a customer account (self-service).
     */
    public function deactivateAccount(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $customer->forceFill(['status' => CustomerStatus::Inactive])->save();
            $customer->tokens()->delete();
            $customer->delete();

            $this->notifications->send(
                $customer,
                'customer.account_deactivated',
                [
                    'user_name' => $customer->full_name,
                    'email' => $customer->email,
                ],
                [
                    NotificationChannel::Email->value,
                    NotificationChannel::Database->value,
                ],
            );
        });
    }

    /**
     * List addresses for a customer.
     *
     * @return Collection<int, CustomerAddress>
     */
    public function listAddresses(Customer $customer): Collection
    {
        return $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get();
    }

    /**
     * Store a new address for a customer.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     phone?: string|null,
     *     address_line_1: string,
     *     address_line_2?: string|null,
     *     country_id?: int|null,
     *     state_id?: int|null,
     *     city_id?: int|null,
     *     postal_code?: string|null,
     *     landmark?: string|null,
     *     is_default?: bool
     * }  $data
     */
    public function storeAddress(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault || ! $customer->addresses()->exists()) {
                $this->unsetDefaultAddresses($customer);
                $data['is_default'] = true;
            }

            /** @var CustomerAddress $address */
            $address = $customer->addresses()->create($data);

            return $address;
        });
    }

    /**
     * Update an address belonging to a customer.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateAddress(Customer $customer, CustomerAddress $address, array $data): CustomerAddress
    {
        $this->ensureAddressBelongsToCustomer($customer, $address);

        return DB::transaction(function () use ($customer, $address, $data): CustomerAddress {
            if (($data['is_default'] ?? false) === true) {
                $this->unsetDefaultAddresses($customer, $address->id);
            }

            $address->fill($data);
            $address->save();

            return $address->fresh() ?? $address;
        });
    }

    /**
     * Delete an address belonging to a customer.
     *
     * @throws ValidationException
     */
    public function destroyAddress(Customer $customer, CustomerAddress $address): void
    {
        $this->ensureAddressBelongsToCustomer($customer, $address);

        DB::transaction(function () use ($customer, $address): void {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = $customer->addresses()->orderBy('id')->first();

                if ($next !== null) {
                    $next->forceFill(['is_default' => true])->save();
                }
            }
        });
    }

    /**
     * Mark an address as the default for a customer.
     *
     * @throws ValidationException
     */
    public function makeDefault(Customer $customer, CustomerAddress $address): CustomerAddress
    {
        $this->ensureAddressBelongsToCustomer($customer, $address);

        return DB::transaction(function () use ($customer, $address): CustomerAddress {
            $this->unsetDefaultAddresses($customer, $address->id);

            $address->forceFill(['is_default' => true])->save();

            return $address->fresh() ?? $address;
        });
    }

    /**
     * @throws ValidationException
     */
    protected function ensureAddressBelongsToCustomer(Customer $customer, CustomerAddress $address): void
    {
        if ($address->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'address' => ['This address does not belong to the authenticated customer.'],
            ]);
        }
    }

    protected function unsetDefaultAddresses(Customer $customer, ?int $exceptId = null): void
    {
        $query = $customer->addresses()->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
