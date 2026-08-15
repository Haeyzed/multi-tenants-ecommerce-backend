<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Notification;

use App\Models\Landlord\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationTemplate
 */
class NotificationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var NotificationTemplate $template */
        $template = $this->resource;

        return [
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'channels' => $template->channels,
            'variables' => $template->variables,
            'title' => $template->title,
            'body' => $template->body,
            'email_subject' => $template->email_subject,
            'email_body' => $template->email_body,
            'push_title' => $template->push_title,
            'push_body' => $template->push_body,
            'sms_body' => $template->sms_body,
            'is_mandatory' => (bool) $template->is_mandatory,
            'is_active' => (bool) $template->is_active,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }
}
