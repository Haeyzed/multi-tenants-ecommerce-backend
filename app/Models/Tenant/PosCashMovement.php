<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Pos\PosCashMovementType;
use Database\Factories\Tenant\PosCashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash drawer movement within a POS session.
 *
 * @property int $id
 * @property int $pos_session_id
 * @property PosCashMovementType $type
 * @property string $amount
 * @property string|null $reason
 * @property int $user_id
 */
class PosCashMovement extends Model
{
    /** @use HasFactory<PosCashMovementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pos_session_id',
        'type',
        'amount',
        'reason',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pos_session_id' => 'integer',
            'user_id' => 'integer',
            'type' => PosCashMovementType::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
