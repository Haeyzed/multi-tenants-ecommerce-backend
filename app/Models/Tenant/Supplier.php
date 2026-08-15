<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Procurement\SupplierStatus;
use Database\Factories\Tenant\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vendor that supplies inventory via purchase orders.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $tax_number
 * @property SupplierStatus $status
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $city_id
 * @property string|null $postal_code
 * @property string|null $notes
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'website',
        'tax_number',
        'status',
        'address_line_1',
        'address_line_2',
        'country_id',
        'state_id',
        'city_id',
        'postal_code',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'country_id' => 'integer',
            'state_id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<SupplierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
