<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Accounting\JournalEntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Double-entry journal header.
 *
 * @property int $id
 * @property string $reference
 * @property string|null $description
 * @property Carbon $entry_date
 * @property JournalEntryStatus $status
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $entry_type
 * @property Carbon|null $posted_at
 */
class JournalEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'description',
        'entry_date',
        'status',
        'source_type',
        'source_id',
        'entry_type',
        'posted_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalEntryStatus::class,
            'source_id' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * Optional morph source (order, goods receipt, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
