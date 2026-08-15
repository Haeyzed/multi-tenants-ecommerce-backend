<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform-managed notification template stored on the central connection.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property list<string> $channels
 * @property list<string>|null $variables
 * @property string|null $title
 * @property string|null $body
 * @property string|null $email_subject
 * @property string|null $email_body
 * @property string|null $push_title
 * @property string|null $push_body
 * @property string|null $sms_body
 * @property bool $is_mandatory
 * @property bool $is_active
 */
class NotificationTemplate extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'channels',
        'variables',
        'title',
        'body',
        'email_subject',
        'email_body',
        'push_title',
        'push_body',
        'sms_body',
        'is_mandatory',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_mandatory' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'variables' => 'array',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<NotificationTemplate>  $query
     * @param  array{search?: string|null, is_active?: bool|null}  $params
     * @return Builder<NotificationTemplate>
     */
    public function scopeFilter(Builder $query, array $params): Builder
    {
        if (! empty($params['search'])) {
            $search = $params['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('key', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $params) && $params['is_active'] !== null) {
            $query->where('is_active', (bool) $params['is_active']);
        }

        return $query;
    }
}
