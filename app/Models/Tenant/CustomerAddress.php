<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer shipping or billing address.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $phone
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $city_id
 * @property string|null $postal_code
 * @property string|null $landmark
 * @property bool $is_default
 */
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'country_id',
        'state_id',
        'city_id',
        'postal_code',
        'landmark',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'country_id' => 'integer',
            'state_id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
