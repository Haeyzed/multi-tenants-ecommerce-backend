<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact person for a supplier.
 *
 * @property int $id
 * @property int $supplier_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $role
 * @property bool $is_primary
 */
class SupplierContact extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'name',
        'email',
        'phone',
        'role',
        'is_primary',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_id' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
