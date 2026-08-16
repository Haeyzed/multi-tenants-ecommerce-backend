<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Key/value platform (landlord) configuration.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
class PlatformSetting extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];
}
