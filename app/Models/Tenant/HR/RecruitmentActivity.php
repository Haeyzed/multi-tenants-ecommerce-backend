<?php

declare(strict_types=1);

namespace App\Models\Tenant\HR;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Non-sensitive recruitment audit trail. Does not store salary, notes, or resume contents.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $action
 * @property int|null $actor_id
 * @property array<string, mixed>|null $meta
 */
class RecruitmentActivity extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_type',
        'subject_id',
        'action',
        'actor_id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'actor_id' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
