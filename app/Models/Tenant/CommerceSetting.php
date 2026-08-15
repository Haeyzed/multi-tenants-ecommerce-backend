<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value commerce configuration (tax rate, account maps, etc.).
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
class CommerceSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];
}
