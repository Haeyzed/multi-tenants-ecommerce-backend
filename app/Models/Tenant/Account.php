<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Accounting\AccountType;
use Database\Factories\Tenant\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chart of accounts ledger account.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property bool $is_system
 * @property bool $is_active
 * @property string|null $description
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_system',
        'is_active',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
